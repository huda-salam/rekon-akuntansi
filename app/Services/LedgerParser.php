<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use Illuminate\Support\Collection;

class LedgerParser implements SourceWorkbookParser
{
    public function supports(string $type): bool
    {
        return $type === 'ledger';
    }

    public function parse(Collection $sheets, SourceDocument $document, int $year, ?int $month = null): int
    {
        $yearId = AccountingYear::query()->where('year', $year)->value('id');
        if (! $yearId) throw new \InvalidArgumentException("Tahun {$year} tidak ditemukan.");

        $records = 0;

        foreach ($sheets as $sheetName => $rows) {
            $headerIndex = $this->findHeader($rows);
            if ($headerIndex === null) continue;

            $headers = $this->headers($rows->get($headerIndex));

            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex <= $headerIndex || $this->empty($row)) continue;

                $accountCode = $this->field($row, $headers, ['kode rekening', 'kode akun', 'kode']);
                $date = $this->field($row, $headers, ['tanggal', 'tgl', 'date']);
                $documentNumber = $this->field($row, $headers, ['nomor', 'no', 'nomor bukti', 'referensi', 'no jurnal']);

                $record = SourceRecord::create([
                    'source_document_id' => $document->id,
                    'source_row' => $rowIndex + 1,
                    'sheet_name' => (string) $sheetName,
                    'record_type' => 'ledger_row',
                    'payload' => $this->payload($row, $headers),
                ]);

                foreach ($this->amountFields($row, $headers) as $item) {
                    $factMonth = $month ?? $this->monthFromDate($date);
                    FinancialFact::create([
                        'source_document_id' => $document->id,
                        'source_record_id' => $record->id,
                        'accounting_year_id' => $yearId,
                        'skpd_id' => $this->resolveSkpd($document)?->id,
                        'fact_date' => $this->date($date),
                        'period' => $factMonth ? sprintf('%04d-%02d', $year, $factMonth) : (string) $year,
                        'month' => $factMonth,
                        'source_type' => 'ledger',
                        'transaction_type' => $item['metric'] === 'debit' ? 'DEBIT' : ($item['metric'] === 'credit' ? 'CREDIT' : 'BALANCE'),
                        'document_number' => $documentNumber,
                        'account_code' => $accountCode,
                        'metric' => $item['metric'],
                        'value' => $item['value'],
                        'unit' => 'IDR',
                        'dimensions' => ['sheet_name' => $sheetName, 'source_row' => $rowIndex + 1, 'column' => $item['header']],
                        'lineage' => ['origin' => 'reported', 'source_document_id' => $document->id, 'source_record_id' => $record->id, 'sheet_name' => $sheetName, 'source_row' => $rowIndex + 1, 'column' => $item['header']],
                    ]);
                }

                $records++;
            }
        }

        return $records;
    }

    private function resolveSkpd(SourceDocument $document): ?Skpd
    {
        $filename = mb_strtolower($document->original_filename);

        foreach (Skpd::query()->get() as $skpd) {
            $name = mb_strtolower(trim($skpd->name));
            if ($name !== '' && str_contains($filename, $name)) {
                return $skpd;
            }
        }

        return null;
    }

    private function findHeader(Collection $rows): ?int
    {
        $best = null; $scoreBest = 0;

        foreach ($rows->take(40) as $index => $row) {
            $labels = collect($row)->map(fn ($v) => mb_strtolower(trim((string) ($v ?? ''))))->filter()->values();
            $score = $labels->filter(fn ($v) => in_array($v, [
                'tanggal', 'tgl', 'kode rekening', 'nama rekening', 'uraian', 'debit', 'kredit', 'saldo', 'referensi', 'nomor',
            ], true))->count();

            if ($score > $scoreBest && $labels->count() >= 3) {
                $scoreBest = $score; $best = $index;
            }
        }

        return $best;
    }

    private function amountFields(array|Collection $row, array $headers): array
    {
        $result = [];

        foreach ($headers as $index => $header) {
            $label = mb_strtolower(trim($header));
            $metric = match (true) {
                str_contains($label, 'debit') => 'debit',
                str_contains($label, 'kredit'), str_contains($label, 'credit') => 'credit',
                str_contains($label, 'saldo'), str_contains($label, 'balance') => 'balance',
                default => null,
            };

            if ($metric === null) continue;
            $value = $this->number($row[$index] ?? null);
            if ($value !== null) $result[] = ['metric' => $metric, 'header' => $header, 'value' => $value];
        }

        return $result;
    }

    private function field(array|Collection $row, array $headers, array $names): ?string
    {
        foreach ($headers as $index => $header) {
            if (in_array(mb_strtolower(trim($header)), $names, true)) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') return $value;
            }
        }
        return null;
    }

    private function payload(array|Collection $row, array $headers): array
    {
        $payload = [];
        foreach ($headers as $index => $header) $payload[$header] = $row[$index] ?? null;
        return $payload;
    }

    private function headers(array|Collection $row): array
    {
        $headers = [];
        foreach ($row as $index => $value) $headers[$index] = trim((string) ($value ?? '')) ?: "column_{$index}";
        return $headers;
    }

    private function date(?string $value): ?string
    {
        if (! $value) return null;
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed && $parsed->format($format) === $value) return $parsed->format('Y-m-d');
        }
        return null;
    }

    private function monthFromDate(?string $value): ?int
    {
        $date = $this->date($value);
        return $date ? (int) substr($date, 5, 2) : null;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $text = trim((string) $value);
        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        $text = trim($text, "() \t\n\r");
        if (! preg_match('/^-?[0-9][0-9.,]*$/', $text)) return null;

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = strrpos($text, ',') > strrpos($text, '.')
                ? str_replace(',', '.', str_replace('.', '', $text))
                : str_replace(',', '', $text);
        } elseif (str_contains($text, ',')) {
            $parts = explode(',', $text);
            $text = count($parts) === 2 && strlen($parts[1]) <= 2 ? $parts[0] . '.' . $parts[1] : str_replace(',', '', $text);
        } elseif (substr_count($text, '.') > 1) {
            $text = str_replace('.', '', $text);
        }

        if (! is_numeric($text)) return null;
        $number = (float) $text;
        return $negative ? -abs($number) : $number;
    }

    private function empty(array|Collection $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) ($value ?? '')) === '');
    }
}
