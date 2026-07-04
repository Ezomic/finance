<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function setUpHousehold(): array
    {
        $household = Household::create(['name' => 'Test Household', 'currency' => 'EUR']);
        $user = User::factory()->create(['current_household_id' => $household->id]);
        $household->users()->attach($user, ['role' => 'owner']);
        $account = Account::create([
            'household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Checking', 'type' => 'checking',
        ]);

        return [$household, $user, $account];
    }

    public function test_generic_csv_import_skips_existing_transactions_and_dedupes_within_the_file(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        // Already in the database.
        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 21, 'description' => 'Vitens NV', 'date' => '2026-06-01',
        ]);

        $csv = "date,amount,description\n"
            ."2026-06-01,-21,Vitens NV\n"          // duplicate of existing row -> skipped
            ."2026-06-02,-5.50,Albert Heijn\n"      // new
            ."2026-06-03,100,Salary\n"              // new
            ."2026-06-02,-5.50,Albert Heijn\n";     // duplicate of the row above, within the same file -> skipped

        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csv);

        $response = $this->actingAs($user)->post('/import', [
            'files' => [$file],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        $response->assertSessionHas('status', 'Import complete: 2 transactions added, 2 duplicates skipped.');
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(route('transactions.index'), $location);
        $this->assertStringContainsString('import_batch=', $location);

        $this->assertSame(3, $account->transactions()->count()); // 1 pre-existing + 2 newly imported
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'description' => 'Albert Heijn', 'type' => 'expense', 'amount' => 5.50]);
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'description' => 'Salary', 'type' => 'income', 'amount' => 100.00]);

        // Following the redirect shows only the newly imported rows, not the pre-existing one.
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $batchResponse = $this->get(route('transactions.index', ['import_batch' => $query['import_batch']]));
        $batchResponse->assertOk();
        $batchResponse->assertViewHas('transactions', fn ($transactions) => $transactions->total() === 2);
    }

    public function test_multiple_files_are_imported_together_in_one_batch_deduping_across_files(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $fileA = UploadedFile::fake()->createWithContent('jan.csv', "date,amount,description\n2026-01-01,-10,Rent\n2026-01-05,-5,Coffee\n");
        // Overlaps one row with fileA (same date/amount/description) and adds one new row.
        $fileB = UploadedFile::fake()->createWithContent('feb.csv', "date,amount,description\n2026-01-01,-10,Rent\n2026-02-01,-10,Rent\n");

        $response = $this->actingAs($user)->post('/import', [
            'files' => [$fileA, $fileB],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        $response->assertSessionHas('status', 'Import complete: 3 transactions added, 1 duplicates skipped.');
        $this->assertSame(3, $account->transactions()->count());

        // All rows from both files share a single import batch.
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(3, Transaction::where('import_batch', $query['import_batch'])->count());
    }

    public function test_reimporting_the_same_file_skips_everything(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $csv = "date,amount,description\n2026-06-01,-21,Vitens NV\n2026-06-02,-5.50,Albert Heijn\n";

        $this->actingAs($user)->post('/import', [
            'files' => [UploadedFile::fake()->createWithContent('transactions.csv', $csv)],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        $this->assertSame(2, $account->transactions()->count());

        $response = $this->actingAs($user)->post('/import', [
            'files' => [UploadedFile::fake()->createWithContent('transactions.csv', $csv)],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        $response->assertSessionHas('status', 'Import complete: 0 transactions added, 2 duplicates skipped.');
        $this->assertSame(2, $account->transactions()->count());
    }

    public function test_tab_delimited_txt_export_is_parsed_via_generic_csv_format(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $txt = "date\tamount\tdescription\n2026-06-01\t-21\tVitens NV\n2026-06-02\t100\tSalary\n";

        $response = $this->actingAs($user)->post('/import', [
            'files' => [UploadedFile::fake()->createWithContent('transactions.txt', $txt)],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        $response->assertSessionHas('status', 'Import complete: 2 transactions added, 0 duplicates skipped.');
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'description' => 'Vitens NV', 'type' => 'expense', 'amount' => 21.00]);
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'description' => 'Salary', 'type' => 'income', 'amount' => 100.00]);
    }

    public function test_mt940_import_parses_statement_lines_and_sepa_descriptions(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $mt940 = <<<'MT940'
        :20:REF001
        :25:NL00INGB0001234567
        :28C:1/1
        :60F:C260601EUR1000,00
        :61:2606010601D21,00NMSCNONREF
        :86:/TRTP/iDEAL/Wero/IBAN/NL94INGB0000869000/BIC/INGBNL2A/NAME/Vitens NV/REMI/425000423474/EREF/01-06-2026
        :61:2606020602C100,00NMSCNONREF
        :86:/TRTP/SEPA OVERBOEKING/NAME/Employer BV/REMI/Salary June/EREF/XYZ
        :62F:C260630EUR1079,00
        MT940;

        $response = $this->actingAs($user)->post('/import', [
            'files' => [UploadedFile::fake()->createWithContent('statement.940', $mt940)],
            'account_id' => $account->id,
            'format' => 'mt940',
        ]);

        $response->assertSessionHas('status', 'Import complete: 2 transactions added, 0 duplicates skipped.');
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id, 'date' => '2026-06-01', 'type' => 'expense', 'amount' => 21.00, 'description' => 'Vitens NV – 425000423474',
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id, 'date' => '2026-06-02', 'type' => 'income', 'amount' => 100.00, 'description' => 'Employer BV – Salary June',
        ]);
    }

    public function test_camt053_import_parses_entries_from_xml(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
          <BkToCstmrStmt>
            <Stmt>
              <Ntry>
                <Amt Ccy="EUR">21.00</Amt>
                <CdtDbtInd>DBIT</CdtDbtInd>
                <BookgDt><Dt>2026-06-01</Dt></BookgDt>
                <NtryDtls><TxDtls><RmtInf><Ustrd>Vitens NV invoice</Ustrd></RmtInf></TxDtls></NtryDtls>
              </Ntry>
              <Ntry>
                <Amt Ccy="EUR">100.00</Amt>
                <CdtDbtInd>CRDT</CdtDbtInd>
                <BookgDt><Dt>2026-06-02</Dt></BookgDt>
                <NtryDtls><TxDtls><RmtInf><Ustrd>Salary June</Ustrd></RmtInf></TxDtls></NtryDtls>
              </Ntry>
            </Stmt>
          </BkToCstmrStmt>
        </Document>
        XML;

        $response = $this->actingAs($user)->post('/import', [
            'files' => [UploadedFile::fake()->createWithContent('statement.xml', $xml)],
            'account_id' => $account->id,
            'format' => 'camt053',
        ]);

        $response->assertSessionHas('status', 'Import complete: 2 transactions added, 0 duplicates skipped.');
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id, 'date' => '2026-06-01', 'type' => 'expense', 'amount' => 21.00, 'description' => 'Vitens NV invoice',
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id, 'date' => '2026-06-02', 'type' => 'income', 'amount' => 100.00, 'description' => 'Salary June',
        ]);
    }

    public function test_categorize_page_can_be_scoped_to_just_the_imported_batch(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        // Pre-existing uncategorized transaction, unrelated to the import.
        Transaction::create([
            'household_id' => $household->id, 'account_id' => $account->id, 'user_id' => $user->id,
            'type' => 'expense', 'amount' => 9, 'description' => 'Old unrelated charge', 'date' => '2026-05-01',
        ]);

        $csv = "date,amount,description\n2026-06-01,-21,Vitens NV\n2026-06-02,100,Salary\n";

        $importResponse = $this->actingAs($user)->post('/import', [
            'files' => [UploadedFile::fake()->createWithContent('transactions.csv', $csv)],
            'account_id' => $account->id,
            'format' => 'csv_generic',
        ]);

        parse_str(parse_url($importResponse->headers->get('Location'), PHP_URL_QUERY), $query);
        $batch = $query['import_batch'];

        // The transactions list, scoped to the batch, points at Categorize with the same scope.
        $listResponse = $this->get(route('transactions.index', ['import_batch' => $batch]));
        $listResponse->assertOk();
        $listResponse->assertSee(route('categorize.index', ['import_batch' => $batch]), false);

        // Categorize, scoped to the batch, only shows the 2 freshly imported rows.
        $categorizeResponse = $this->get(route('categorize.index', ['import_batch' => $batch]));
        $categorizeResponse->assertOk();
        $categorizeResponse->assertViewHas('groups', function ($groups) {
            return $groups->sum(fn ($g) => $g['transactions']->count()) === 2;
        });

        // Categorizing a group from the scoped page redirects back into the same scope.
        $group = $categorizeResponse->viewData('groups')->first();
        $applyResponse = $this->post('/categorize', [
            'transaction_ids' => $group['transactions']->pluck('id')->all(),
            'category_choice' => 'new',
            'new_category_name' => 'Misc',
            'new_category_type' => $group['type'],
            'import_batch' => $batch,
        ]);
        $applyResponse->assertRedirect(route('categorize.index', ['import_batch' => $batch]));
    }

    public function test_ing_xls_import_parses_transactions_from_spreadsheet(): void
    {
        [$household, $user, $account] = $this->setUpHousehold();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // ING XLS column layout: Rekeningnummer, Muntsoort, Transactiedatum (YYYYMMDD),
        // Rentedatum, Beginsaldo, Eindsaldo, Transactiebedrag, Omschrijving
        $sheet->fromArray([
            ['Rekeningnummer', 'Muntsoort', 'Transactiedatum', 'Rentedatum', 'Beginsaldo', 'Eindsaldo', 'Transactiebedrag', 'Omschrijving'],
            ['NL00INGB0001234567', 'EUR', '20260601', '20260601', '1000', '979', '-21', '/NAME/Vitens NV/REMI/Invoice 42/'],
            ['NL00INGB0001234567', 'EUR', '20260602', '20260602', '979', '1079', '100', '/NAME/Employer BV/REMI/Salary June/'],
        ]);

        $tmpPath = tempnam(sys_get_temp_dir(), 'ing_xls_test_').'.xls';
        IOFactory::createWriter($spreadsheet, 'Xls')->save($tmpPath);

        $file = new UploadedFile($tmpPath, 'statement.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->actingAs($user)->post('/import', [
            'files' => [$file],
            'account_id' => $account->id,
            'format' => 'ing_xls',
        ]);

        $response->assertSessionHas('status', 'Import complete: 2 transactions added, 0 duplicates skipped.');
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'date' => '2026-06-01',
            'type' => 'expense',
            'amount' => '21.00',
            'description' => 'Vitens NV – Invoice 42',
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'date' => '2026-06-02',
            'type' => 'income',
            'amount' => '100.00',
            'description' => 'Employer BV – Salary June',
        ]);

        @unlink($tmpPath);
    }
}
