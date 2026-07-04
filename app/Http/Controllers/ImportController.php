<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Support\CategoryGuesser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    public function index(): View
    {
        $accounts = $this->household()
            ->accounts()
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        return view('import.index', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'file',
                'extensions:xls,xlsx,csv,txt,xml,940,sta',
                'max:10240',
            ],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('household_id', $this->household()->id)],
            'format' => [
                'required',
                'in:ing_xls,csv_generic,mt940,camt053',
            ],
        ]);

        // Large workbooks (multi-year exports) can be memory- and time-hungry
        // to parse; the defaults are sized for small requests.
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $household = $this->household();
        $account = Account::findOrFail($request->integer('account_id'));
        $this->abortUnlessOwned($account);

        $format = $request->string('format')->toString();
        $rows = [];
        $filenames = [];

        foreach ($request->file('files') as $file) {
            $filenames[] = $file->getClientOriginalName();
            $path = $file->storeAs(
                'imports',
                uniqid().'_'.$file->getClientOriginalName(),
                'local',
            );
            $fullPath = storage_path('app/private/'.$path);

            $rows = array_merge(
                $rows,
                match ($format) {
                    'ing_xls' => $this->parseIngXls($fullPath),
                    'mt940' => $this->parseMt940($fullPath),
                    'camt053' => $this->parseCamt053($fullPath),
                    default => $this->parseCsvGeneric($fullPath),
                },
            );
        }

        $categories = $household->categories()->pluck('id', 'name');
        $userId = $request->user()->id;

        // Pull existing transactions for this account once, instead of
        // running one "exists" query per imported row.
        /** @var array<string, true> $seenKeys */
        $seenKeys = $account
            ->transactions()
            ->get(['date', 'amount', 'description'])
            ->mapWithKeys(
                fn (Transaction $t) => [$this->duplicateKey(
                    $t->date->format('Y-m-d'),
                    (float) $t->amount,
                    $t->description ?? '',
                ) => true],
            )
            ->all();

        $imported = 0;
        $skipped = 0;
        $newRows = [];
        $now = now();
        $batch = (string) Str::uuid();

        foreach ($rows as $row) {
            $key = $this->duplicateKey(
                $row['date'],
                abs($row['amount']),
                $row['description'],
            );

            if (isset($seenKeys[$key])) {
                $skipped++;

                continue;
            }
            $seenKeys[$key] = true; // dedupe repeats within the same file too

            $type = $row['amount'] >= 0 ? 'income' : 'expense';
            $category = $this->guessCategory(
                $row['description'],
                $categories,
                $type,
            );

            $newRows[] = [
                'household_id' => $household->id,
                'account_id' => $account->id,
                'category_id' => $category,
                'user_id' => $userId,
                'type' => $type,
                'amount' => number_format(abs($row['amount']), 2, '.', ''),
                'description' => $row['description'],
                'date' => $row['date'],
                'import_batch' => $batch,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $imported++;
        }

        if (! empty($newRows)) {
            DB::transaction(function () use ($newRows) {
                foreach (array_chunk($newRows, 500) as $chunk) {
                    Transaction::insert($chunk);
                }
            });

            ActivityLog::create([
                'household_id' => $household->id,
                'user_id' => $userId,
                'subject_type' => Transaction::class,
                'subject_id' => null,
                'action' => 'imported',
                'summary' => "Imported {$imported} transaction(s) from ".implode(', ', $filenames)." into {$account->name}",
            ]);
        }

        return redirect()
            ->route('transactions.index', $imported > 0 ? ['import_batch' => $batch] : [])
            ->with(
                'status',
                "Import complete: {$imported} transactions added, {$skipped} duplicates skipped.",
            );
    }

    private function duplicateKey(
        string $date,
        float $amount,
        string $description,
    ): string {
        return $date.'|'.number_format($amount, 2, '.', '').'|'.$description;
    }

    // -------------------------------------------------------------------------
    // Parsers
    // -------------------------------------------------------------------------

    /** @return array<int, array{date: string, amount: float, description: string}> */
    private function parseIngXls(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
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

            $amount = (float) str_replace(',', '.', (string) $line[6]);
            $description = $this->cleanIngDescription((string) ($line[7] ?? ''));

            $rows[] = [
                'date' => Carbon::createFromFormat('Ymd', $dateStr)->format(
                    'Y-m-d',
                ),
                'amount' => $amount,
                'description' => $description,
            ];
        }

        return $rows;
    }

    /** @return array<int, array{date: string, amount: float, description: string}> */
    private function parseCsvGeneric(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $delimiter = $this->detectDelimiter($handle);
        $rawHeader = fgetcsv($handle, 0, $delimiter);
        $header = array_map(fn (?string $v): string => strtolower((string) $v), $rawHeader !== false ? $rawHeader : []);

        // Map common column name variants
        $dateCol = $this->findCol($header, [
            'date',
            'datum',
            'transactiedatum',
            'transaction date',
        ]);
        $amountCol = $this->findCol($header, [
            'amount',
            'bedrag',
            'transactiebedrag',
            'value',
        ]);
        $descCol = $this->findCol($header, [
            'description',
            'omschrijving',
            'memo',
            'name',
            'payee',
        ]);

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (! isset($line[$dateCol], $line[$amountCol])) {
                continue;
            }

            $rows[] = [
                'date' => Carbon::parse($line[$dateCol])->format('Y-m-d'),
                'amount' => (float) str_replace(',', '.', $line[$amountCol]),
                'description' => $line[$descCol] ?? '',
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Excel/bank "text" exports vary between comma, semicolon, and
     * tab-delimited — sniff the header line rather than assuming comma.
     *
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = fgets($handle);
        rewind($handle);

        if ($firstLine === false) {
            return ',';
        }

        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    /** @return array<int, array{date: string, amount: float, description: string}> */
    private function parseMt940(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }
        $lines = (array) preg_split('/\r\n|\r|\n/', $contents);

        // Fold continuation lines (ones that don't start a new SWIFT tag)
        // into whichever tag they belong to.
        $tags = [];
        $current = null;

        foreach ($lines as $lineRaw) {
            $line = (string) $lineRaw;
            if (preg_match('/^:(\d{2}[A-Z]?):(.*)$/', $line, $m)) {
                $tags[] = ['tag' => $m[1], 'value' => $m[2]];
                $current = count($tags) - 1;
            } elseif ($current !== null && trim($line) !== '') {
                $tags[$current]['value'] .= $line;
            }
        }

        $rows = [];

        foreach ($tags as $i => $tag) {
            if ($tag['tag'] !== '61') {
                continue;
            }

            // Field 61: YYMMDD[MMDD]<D|C|RD|RC>[currency]amount,decimals...
            if (
                ! preg_match(
                    '/^(\d{6})(?:\d{4})?(R?[DC])[A-Z]?(\d+,\d{0,2})/',
                    $tag['value'],
                    $m,
                )
            ) {
                continue;
            }

            [, $dateRaw, $sign, $amountRaw] = $m;

            $amount = (float) str_replace(',', '.', $amountRaw);
            $negative = str_ends_with($sign, 'D');
            if (str_starts_with($sign, 'R')) {
                $negative = ! $negative;
            }

            $description =
                isset($tags[$i + 1]) && $tags[$i + 1]['tag'] === '86'
                    ? $this->cleanIngDescription($tags[$i + 1]['value'])
                    : '';

            $rows[] = [
                'date' => $this->mt940Date($dateRaw),
                'amount' => $negative ? -$amount : $amount,
                'description' => $description,
            ];
        }

        return $rows;
    }

    private function mt940Date(string $yyMMdd): string
    {
        $yy = (int) substr($yyMMdd, 0, 2);
        $year = $yy >= 70 ? 1900 + $yy : 2000 + $yy;

        return sprintf(
            '%04d-%s-%s',
            $year,
            substr($yyMMdd, 2, 2),
            substr($yyMMdd, 4, 2),
        );
    }

    /** @return array<int, array{date: string, amount: float, description: string}> */
    private function parseCamt053(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $dom = new \DOMDocument;
        $dom->loadXML($contents);
        $xpath = new \DOMXPath($dom);

        $rows = [];

        $entries = $xpath->query('//*[local-name()="Ntry"]');
        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if (! $entry instanceof \DOMElement) {
                continue;
            }

            $amountNodeList = $xpath->query('.//*[local-name()="Amt"]', $entry);
            $indNodeList = $xpath->query('.//*[local-name()="CdtDbtInd"]', $entry);
            $dateNodeList1 = $xpath->query('.//*[local-name()="BookgDt"]/*[local-name()="Dt"] | .//*[local-name()="BookgDt"]/*[local-name()="DtTm"]', $entry);
            $dateNodeList2 = $xpath->query('.//*[local-name()="ValDt"]/*[local-name()="Dt"] | .//*[local-name()="ValDt"]/*[local-name()="DtTm"]', $entry);

            $amountNode = ($amountNodeList !== false) ? $amountNodeList->item(0) : null;
            $indNode = ($indNodeList !== false) ? $indNodeList->item(0) : null;
            $dateNode = ($dateNodeList1 !== false ? $dateNodeList1->item(0) : null)
                ?? ($dateNodeList2 !== false ? $dateNodeList2->item(0) : null);

            if (! $amountNode instanceof \DOMElement || ! $indNode instanceof \DOMElement || ! $dateNode instanceof \DOMElement) {
                continue;
            }

            $amount = (float) $amountNode->textContent;
            $negative = strtoupper(trim($indNode->textContent)) === 'DBIT';

            $descNodeList1 = $xpath->query('.//*[local-name()="RmtInf"]/*[local-name()="Ustrd"]', $entry);
            $descNodeList2 = $xpath->query('.//*[local-name()="AddtlNtryInf"]', $entry);
            $descNodeList3 = $xpath->query('.//*[local-name()="RltdPties"]//*[local-name()="Nm"]', $entry);

            $descNode = ($descNodeList1 !== false ? $descNodeList1->item(0) : null)
                ?? ($descNodeList2 !== false ? $descNodeList2->item(0) : null)
                ?? ($descNodeList3 !== false ? $descNodeList3->item(0) : null);

            $rows[] = [
                'date' => substr($dateNode->textContent, 0, 10),
                'amount' => $negative ? -$amount : $amount,
                'description' => trim($descNode instanceof \DOMElement ? $descNode->textContent : ''),
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
        if (str_contains($raw, '/NAME/')) {
            preg_match("/\/NAME\/([^\/]+)/", $raw, $m);
            $name = trim($m[1] ?? '');
            preg_match("/\/REMI\/([^\/]+)/", $raw, $r);
            $remi = trim($r[1] ?? '');

            return $name.($remi ? ' – '.substr($remi, 0, 60) : '');
        }

        // POS / ATM format: "BEA, Google Pay   Merchant name,..."
        if (preg_match("/^(?:BEA|GEA)[,\s]+[^\s]+\s+(.+?),/", $raw, $m)) {
            return trim($m[1]);
        }

        return trim($raw);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $candidates
     */
    private function findCol(array $header, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return 0;
    }

    /**
     * Returns null if no match — user can recategorise later.
     *
     * @param  Collection<string, int>  $categories
     */
    private function guessCategory(
        string $description,
        Collection $categories,
        string $type,
    ): ?int {
        $categoryName = CategoryGuesser::guess($description);

        return $categoryName ? $categories[$categoryName] ?? null : null;
    }
}
