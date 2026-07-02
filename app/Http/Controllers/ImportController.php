<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\CategoryGuesser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function index()
    {
        $accounts = $this->household()
            ->accounts()
            ->where("is_archived", false)
            ->orderBy("name")
            ->get();

        return view("import.index", compact("accounts"));
    }

    public function store(Request $request)
    {
        $request->validate([
            "files" => ["required", "array", "min:1"],
            "files.*" => [
                "file",
                "extensions:xls,xlsx,csv,txt,xml,940,sta",
                "max:10240",
            ],
            "account_id" => ["required", "exists:accounts,id"],
            "format" => [
                "required",
                "in:ing_xls,csv_generic,mt940,camt053",
            ],
        ]);

        // Large workbooks (multi-year exports) can be memory- and time-hungry
        // to parse; the defaults are sized for small requests.
        ini_set("memory_limit", "512M");
        set_time_limit(120);

        $household = $this->household();
        $account = Account::findOrFail($request->integer("account_id"));
        $this->abortUnlessOwned($account);

        $format = $request->string("format")->toString();
        $rows = [];

        foreach ($request->file("files") as $file) {
            $path = $file->storeAs(
                "imports",
                uniqid() . "_" . $file->getClientOriginalName(),
                "local",
            );
            $fullPath = storage_path("app/private/" . $path);

            $rows = array_merge(
                $rows,
                match ($format) {
                    "ing_xls" => $this->parseIngXls($fullPath),
                    "mt940" => $this->parseMt940($fullPath),
                    "camt053" => $this->parseCamt053($fullPath),
                    default => $this->parseCsvGeneric($fullPath),
                },
            );
        }

        $categories = $household->categories()->pluck("id", "name");
        $userId = $request->user()->id;

        // Pull existing transactions for this account once, instead of
        // running one "exists" query per imported row.
        $seenKeys = $account
            ->transactions()
            ->get(["date", "amount", "description"])
            ->map(
                fn(Transaction $t) => $this->duplicateKey(
                    $t->date->format("Y-m-d"),
                    (float) $t->amount,
                    $t->description ?? "",
                ),
            )
            ->flip();

        $imported = 0;
        $skipped = 0;
        $newRows = [];
        $now = now();
        $batch = (string) Str::uuid();

        foreach ($rows as $row) {
            $key = $this->duplicateKey(
                $row["date"],
                abs($row["amount"]),
                $row["description"],
            );

            if ($seenKeys->has($key)) {
                $skipped++;
                continue;
            }
            $seenKeys->put($key, true); // dedupe repeats within the same file too

            $type = $row["amount"] >= 0 ? "income" : "expense";
            $category = $this->guessCategory(
                $row["description"],
                $categories,
                $type,
            );

            $newRows[] = [
                "household_id" => $household->id,
                "account_id" => $account->id,
                "category_id" => $category,
                "user_id" => $userId,
                "type" => $type,
                "amount" => number_format(abs($row["amount"]), 2, ".", ""),
                "description" => $row["description"],
                "date" => $row["date"],
                "import_batch" => $batch,
                "created_at" => $now,
                "updated_at" => $now,
            ];
            $imported++;
        }

        if (!empty($newRows)) {
            DB::transaction(function () use ($newRows) {
                foreach (array_chunk($newRows, 500) as $chunk) {
                    Transaction::insert($chunk);
                }
            });
        }

        return redirect()
            ->route("transactions.index", $imported > 0 ? ["import_batch" => $batch] : [])
            ->with(
                "status",
                "Import complete: {$imported} transactions added, {$skipped} duplicates skipped.",
            );
    }

    private function duplicateKey(
        string $date,
        float $amount,
        string $description,
    ): string {
        return $date . "|" . number_format($amount, 2, ".", "") . "|" . $description;
    }

    // -------------------------------------------------------------------------
    // Parsers
    // -------------------------------------------------------------------------

    private function parseIngXls(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $lines = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        array_shift($lines); // skip header row

        $rows = [];

        foreach ($lines as $line) {
            if (count($line) < 7) {
                continue;
            }

            // Columns: Rekeningnummer, Muntsoort, Transactiedatum, Rentedatum,
            //          Beginsaldo, Eindsaldo, Transactiebedrag, Omschrijving
            $dateRaw = trim((string) $line[2]); // YYYYMMDD — may come in as float like 20260601.0
            $dateStr = (string) (int) $dateRaw; // strip any decimal part

            // Skip rows with a missing/invalid date (e.g. blank or summary rows)
            if (strlen($dateStr) !== 8) {
                continue;
            }

            $amount = (float) str_replace(",", ".", (string) $line[6]);
            $description = $this->cleanIngDescription((string) ($line[7] ?? ""));

            $rows[] = [
                "date" => Carbon::createFromFormat("Ymd", $dateStr)->format(
                    "Y-m-d",
                ),
                "amount" => $amount,
                "description" => $description,
            ];
        }

        return $rows;
    }

    private function parseCsvGeneric(string $path): array
    {
        $rows = [];
        $handle = fopen($path, "r");
        $delimiter = $this->detectDelimiter($handle);
        $header = array_map("strtolower", fgetcsv($handle, 0, $delimiter));

        // Map common column name variants
        $dateCol = $this->findCol($header, [
            "date",
            "datum",
            "transactiedatum",
            "transaction date",
        ]);
        $amountCol = $this->findCol($header, [
            "amount",
            "bedrag",
            "transactiebedrag",
            "value",
        ]);
        $descCol = $this->findCol($header, [
            "description",
            "omschrijving",
            "memo",
            "name",
            "payee",
        ]);

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!isset($line[$dateCol], $line[$amountCol])) {
                continue;
            }

            $rows[] = [
                "date" => Carbon::parse($line[$dateCol])->format("Y-m-d"),
                "amount" => (float) str_replace(",", ".", $line[$amountCol]),
                "description" => $line[$descCol] ?? "",
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Excel/bank "text" exports vary between comma, semicolon, and
     * tab-delimited — sniff the header line rather than assuming comma.
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = fgets($handle);
        rewind($handle);

        $best = ",";
        $bestCount = 0;

        foreach ([",", ";", "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function parseMt940(string $path): array
    {
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path));

        // Fold continuation lines (ones that don't start a new SWIFT tag)
        // into whichever tag they belong to.
        $tags = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^:(\d{2}[A-Z]?):(.*)$/', $line, $m)) {
                $tags[] = ["tag" => $m[1], "value" => $m[2]];
                $current = count($tags) - 1;
            } elseif ($current !== null && trim($line) !== "") {
                $tags[$current]["value"] .= $line;
            }
        }

        $rows = [];

        foreach ($tags as $i => $tag) {
            if ($tag["tag"] !== "61") {
                continue;
            }

            // Field 61: YYMMDD[MMDD]<D|C|RD|RC>[currency]amount,decimals...
            if (
                !preg_match(
                    '/^(\d{6})(?:\d{4})?(R?[DC])[A-Z]?(\d+,\d{0,2})/',
                    $tag["value"],
                    $m,
                )
            ) {
                continue;
            }

            [, $dateRaw, $sign, $amountRaw] = $m;

            $amount = (float) str_replace(",", ".", $amountRaw);
            $negative = str_ends_with($sign, "D");
            if (str_starts_with($sign, "R")) {
                $negative = !$negative;
            }

            $description =
                isset($tags[$i + 1]) && $tags[$i + 1]["tag"] === "86"
                    ? $this->cleanIngDescription($tags[$i + 1]["value"])
                    : "";

            $rows[] = [
                "date" => $this->mt940Date($dateRaw),
                "amount" => $negative ? -$amount : $amount,
                "description" => $description,
            ];
        }

        return $rows;
    }

    private function mt940Date(string $yyMMdd): string
    {
        $yy = (int) substr($yyMMdd, 0, 2);
        $year = $yy >= 70 ? 1900 + $yy : 2000 + $yy;

        return sprintf(
            "%04d-%s-%s",
            $year,
            substr($yyMMdd, 2, 2),
            substr($yyMMdd, 4, 2),
        );
    }

    private function parseCamt053(string $path): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML(file_get_contents($path));
        $xpath = new \DOMXPath($dom);

        $rows = [];

        foreach ($xpath->query('//*[local-name()="Ntry"]') as $entry) {
            $amountNode = $xpath
                ->query('.//*[local-name()="Amt"]', $entry)
                ->item(0);
            $indNode = $xpath
                ->query('.//*[local-name()="CdtDbtInd"]', $entry)
                ->item(0);
            $dateNode =
                $xpath
                    ->query(
                        './/*[local-name()="BookgDt"]/*[local-name()="Dt"] | .//*[local-name()="BookgDt"]/*[local-name()="DtTm"]',
                        $entry,
                    )
                    ->item(0) ??
                $xpath
                    ->query(
                        './/*[local-name()="ValDt"]/*[local-name()="Dt"] | .//*[local-name()="ValDt"]/*[local-name()="DtTm"]',
                        $entry,
                    )
                    ->item(0);

            if (!$amountNode || !$indNode || !$dateNode) {
                continue;
            }

            $amount = (float) $amountNode->textContent;
            $negative = strtoupper(trim($indNode->textContent)) === "DBIT";

            $descNode =
                $xpath
                    ->query(
                        './/*[local-name()="RmtInf"]/*[local-name()="Ustrd"]',
                        $entry,
                    )
                    ->item(0) ??
                $xpath
                    ->query('.//*[local-name()="AddtlNtryInf"]', $entry)
                    ->item(0) ??
                $xpath
                    ->query(
                        './/*[local-name()="RltdPties"]//*[local-name()="Nm"]',
                        $entry,
                    )
                    ->item(0);

            $rows[] = [
                "date" => substr($dateNode->textContent, 0, 10),
                "amount" => $negative ? -$amount : $amount,
                "description" => trim($descNode?->textContent ?? ""),
            ];
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cleanIngDescription(string $raw): string
    {
        // ING descriptions are packed SEPA strings like:
        // /TRTP/iDEAL/Wero/IBAN/.../NAME/Vitens NV/REMI/...
        // or plain POS strings like:
        // BEA, Google Pay   Kruidvat 3662,...
        if (str_contains($raw, "/NAME/")) {
            preg_match("/\/NAME\/([^\/]+)/", $raw, $m);
            $name = trim($m[1] ?? "");
            preg_match("/\/REMI\/([^\/]+)/", $raw, $r);
            $remi = trim($r[1] ?? "");
            return $name . ($remi ? " – " . substr($remi, 0, 60) : "");
        }

        // POS / ATM format: "BEA, Google Pay   Merchant name,..."
        if (preg_match("/^(?:BEA|GEA)[,\s]+[^\s]+\s+(.+?),/", $raw, $m)) {
            return trim($m[1]);
        }

        return trim($raw);
    }

    private function findCol(array $header, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $header);
            if ($idx !== false) {
                return $idx;
            }
        }

        return 0;
    }

    /**
     * Returns null if no match — user can recategorise later.
     */
    private function guessCategory(
        string $description,
        $categories,
        string $type,
    ): ?int {
        $categoryName = CategoryGuesser::guess($description);

        return $categoryName ? $categories[$categoryName] ?? null : null;
    }
}
