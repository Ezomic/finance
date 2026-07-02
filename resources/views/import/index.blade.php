@extends('layouts.app')
@section('title', 'Import transactions')
@section('subtitle', 'Upload a bank export file to bulk-import transactions')
@section('content')

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 card p-6">
        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="label">Bank / format</label>
                <select name="format" required class="input">
                    <option value="ing_xls">ING Bank — XLS / XLSX export (Excel)</option>
                    <option value="csv_generic">Generic CSV / TXT (Delimited)</option>
                    <option value="mt940">MT940 (.940 / .sta / .txt) (SWIFT)</option>
                    <option value="camt053">CAMT.053 (.xml) (XML)</option>
                </select>
            </div>

            <div>
                <label class="label">Import into account</label>
                <select name="account_id" required class="input">
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->typeLabel() }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">File(s)</label>
                <input type="file" name="files[]" accept=".xls,.xlsx,.csv,.txt,.xml,.940,.sta" multiple required class="input py-2">
                <p class="text-xs text-gray-500 mt-1">Accepted: .xls, .xlsx, .csv, .txt, .xml, .940, .sta — max 10 MB each. Select multiple files (e.g. several months of statements) to import them together — duplicates across files are skipped too.</p>
            </div>

            <button type="submit" class="btn-primary w-full">Import transactions</button>
        </form>
    </div>

    <div class="card p-6 h-fit space-y-4 text-sm text-gray-600">
        <h2 class="font-semibold text-gray-900">How it works</h2>
        <p>Choose the format that matches your bank's export, pick which account the transactions belong to, and upload one or more files.</p>
        <ul class="space-y-2 list-disc list-inside">
            <li>Duplicate transactions are automatically skipped (same date, amount, and description).</li>
            <li>Categories are guessed from the description where possible — you can edit any transaction afterwards.</li>
            <li>Income is any positive amount; expenses are negative.</li>
        </ul>
        <p class="font-medium text-gray-700">ING export instructions:</p>
        <ol class="space-y-1 list-decimal list-inside">
            <li>Log in to Mijn ING</li>
            <li>Go to your account → Download transactions</li>
            <li>Choose XLS, MT940, or CAMT.053 format and download</li>
            <li>Upload that file here, matching the format above</li>
        </ol>
    </div>
</div>
@endsection
