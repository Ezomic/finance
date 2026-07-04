# Bank import formats

`/import` accepts one or more files against a single account and format. All parsers normalize to the same intermediate shape — `['date', 'amount', 'description']` per row — before dedup and insert (`ImportController::store()`).

| Format | Value | Parser |
|---|---|---|
| ING Bank XLS export | `ing_xls` | `parseIngXls()` |
| Generic CSV | `csv_generic` | `parseCsvGeneric()` |
| MT940 (SWIFT) | `mt940` | `parseMt940()` |
| CAMT.053 (ISO 20022 XML) | `camt053` | `parseCamt053()` |

## ING XLS

Expects columns (after the header row): Rekeningnummer, Muntsoort, Transactiedatum, Rentedatum, Beginsaldo, Eindsaldo, Transactiebedrag, Omschrijving. The date column sometimes arrives as a float (`20260601.0`) rather than a string — cast through `(int)` before parsing as `Ymd`. Amount uses comma as decimal separator.

## Generic CSV

Delimiter is sniffed from the header line (comma, semicolon, or tab — whichever appears most) rather than assumed, since bank exports vary. Column names are matched case-insensitively against common variants in English and Dutch: `date`/`datum`/`transactiedatum`, `amount`/`bedrag`/`transactiebedrag`, `description`/`omschrijving`/`memo`/`name`/`payee`.

## MT940

Parses SWIFT field `:61:` (`YYMMDD[MMDD]<D|C|RD|RC>[currency]amount,decimals`) for date/sign/amount, and the following `:86:` field for the description. Continuation lines (ones that don't start a new `:tag:`) are folded into whichever tag they belong to, since MT940 wraps long fields across lines. Two-digit years: `>= 70` → 1900s, otherwise 2000s.

## CAMT.053

Standard ISO 20022 bank-to-customer statement XML. Reads each `Ntry` (entry): amount + `CdtDbtInd` (credit/debit) for the amount and sign, `BookgDt` (falling back to `ValDt`) for the date, and description from `RmtInf/Ustrd`, falling back to `AddtlNtryInf`, falling back to the counterparty name.

## Shared description cleanup

ING XLS and MT940 share `cleanIngDescription()`, which handles two common raw formats:
- SEPA-style: `/TRTP/iDEAL/Wero/IBAN/.../NAME/Vitens NV/REMI/...` → extracts `NAME` (+ first 60 chars of `REMI` if present)
- POS/ATM-style: `BEA, Google Pay   Kruidvat 3662,...` → extracts the merchant name between the payment method and the trailing comma

## Deduplication and categorization on import

- Existing transactions for the target account are pulled once (not one query per row) and keyed by `date|amount|description` to skip re-importing the same statement twice
- Rows are also deduped against each other within the same file/batch
- Each imported row gets a best-effort category guess via `CategoryGuesser` (keyword rules against the household's existing categories) — anything unmatched is left uncategorized and picked up by the [categorize workflow](features.md#categorize-workflow), scoped to that import's batch UUID

Large multi-year exports can be slow to parse — `store()` bumps `memory_limit` to 512M and the time limit to 120s for the request.
