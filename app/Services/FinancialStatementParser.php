<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use Illuminate\Support\Collection;

class FinancialStatementParser implements SourceWorkbookParser
{
    public function supports(string $type): bool
    {
        return $type === 'financial_statement';
    }

    public function parse(Collection $sheets, SourceDocument $document, int $year, ?int $month = null): int
    {
        $yearId = AccountingYear::query()->where('year', $year)->value('id');

        if (! $yearId) {
            throw new \InvalidArgumentException("Tahun {$year} tidak ditemukan.");
        }

        $records = 0;

        foreach ($sheets as $sheetName => $rows) {
            $header = $this->findHeader($rows);
            if ($header === null) continue;

            $headers = $this->headers($rows->get($header));

            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex <= $header || $this->empty($row)) continue;

                $accountCode = $this->accountCode($row, $headers);
                $description = $this->description($row, $headers);

                // Report rows without an account code are retained only when they
                // contain a meaningful financial value. This preserves subtotal
                // and total evidence without pretending it is an account posting.
                $numeric = $this->numericColumns($row, $headers);
                if ($numeric === []) continue;

                $record = SourceRecord::create([
                    'source_document_id' => $document->id,
                    'source_row' => $rowIndex + 1,
                    'sheet_name' => (string) $sheetName,
                    'record_type' => $document->document_type . '_account_row',
                    'payload' => $this->payload($row, $headers),
                ]);

                $skpd = $this->resolveSkpd($row, $headers);

                foreach ($numeric as $item) {
                    FinancialFact::create([
                        'source_document_id' => $document->id,
                        'source_record_id' => $record->id,
                        'accounting_year_id' => $yearId,
                        'skpd_id' => $skpd?->id,
                        'period' => $month ? sprintf('%04d-%02d', $year, $month) : (string) $year,
                        'month' => $month,
                        'source_type' => 'financial_statement',
                        'transaction_type' => strtoupper($this->statementName($document->original_filename)),
                        'account_code' => $accountCode,
                        'metric' => $item['metric'],
                        'value' => $item['value'],
                        'unit' => 'IDR',
                        'dimensions' => [
                            'sheet_name' => $sheetName,
                            'source_row' => $rowIndex + 1,
                            'description' => $description,
                            'column' => $item['header'],
                            'statement' => $this->statementName($document->original_filename),
                        ],
                        'lineage' => [
                            'origin' => 'reported',
                            'source_document_id' => $document->id,
                            'source_record_id' => $record->id,
                            'sheet_name' => $sheetName,
                            'source_row' => $rowIndex + 1,
                            'column' => $item['header'],
                            'account_code' => $accountCode,
                            'account_level' => $this->accountLevel($accountCode),
                            'summary_row' => $this->isSummaryRow($accountCode, $this->statementName($document->original_filename)),
                        ],
                    ]);
                }

                $records++;
            }
        }

        return $records;
    }

    private function findHeader(Collection $rows): ?int
    {
        $best = null;
        $scoreBest = 0;

        foreach ($rows->take(30) as $index => $row) {
            $labels = collect($row)
                ->map(fn ($v) => mb_strtolower(trim((string) ($v ?? ''))))
                ->filter()
                ->values();

            $score = $labels->filter(fn ($v) => in_array($v, [
                'kode rekening', 'uraian', 'anggaran', 'realisasi', '2026', '2025',
                'perubahan', '%', 'saldo', 'konsolidasi', 'debit', 'kredit',
            ], true))->count();

            if ($score > $scoreBest && $labels->count() >= 2) {
                $best = $index;
                $scoreBest = $score;
            }
        }

        return $best;
    }

    private function headers(array|Collection $row): array
    {
        $headers = [];
        foreach ($row as $i => $value) {
            $label = trim((string) ($value ?? ''));
            $headers[$i] = $label !== '' ? $label : "column_{$i}";
        }

        return $headers;
    }

    private function numericColumns(array|Collection $row, array $headers): array
    {
        $items = [];

        foreach ($headers as $index => $header) {
            $label = mb_strtolower(trim($header));
            if ($label === '' || $this->isNonFinancialColumn($label)) continue;

            $value = $this->number($row[$index] ?? null);
            if ($value === null) continue;

            $items[] = [
                'header' => $header,
                'metric' => $this->metric($header),
                'value' => $value,
            ];
        }

        return $items;
    }

    private function isNonFinancialColumn(string $label): bool
    {
        return str_contains($label, '%')
            || str_contains($label, 'persentase')
            || $label === 'no'
            || $label === 'nomor';
    }

    private function accountCode(array|Collection $row, array $headers): ?string
    {
        foreach ($headers as $index => $header) {
            if (in_array(mb_strtolower(trim($header)), ['kode rekening', 'kode akun', 'kode'], true)) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') return $value;
            }
        }

        $first = trim((string) ($row[0] ?? ''));
        return preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $first) ? $first : null;
    }

    private function accountLevel(?string $code): int
    {
        if ($code === null || $code === '') return 0;
        return count(explode('.', $code));
    }

    private function isSummaryRow(?string $code, string $statement): bool
    {
        if ($code === null) return false;

        return match ($statement) {
            'LRA' => in_array($code, ['4', '5'], true),
            'LO' => in_array($code, ['7', '8'], true),
            'NERACA' => in_array($code, ['1', '2', '3'], true),
            default => false,
        };
    }

    private function description(array|Collection $row, array $headers): ?string
    {
        foreach ($headers as $index => $header) {
            if (in_array(mb_strtolower(trim($header)), ['uraian', 'nama rekening', 'rekening'], true)) {
                $value = trim((string) ($row[$index] ?? ''));
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private function resolveSkpd(array|Collection $row, array $headers): ?Skpd
    {
        foreach ($headers as $index => $header) {
            if (! in_array(mb_strtolower(trim($header)), ['skpd', 'nama skpd', 'unit kerja'], true)) continue;
            $name = trim((string) ($row[$index] ?? ''));
            if ($name !== '') {
                return Skpd::query()->where('name', $name)->first();
            }
        }

        return null;
    }

    private function payload(array|Collection $row, array $headers): array
    {
        $payload = [];
        foreach ($headers as $index => $header) {
            $payload[$header] = $row[$index] ?? null;
        }

        return $payload;
    }

    private function metric(string $header): string
    {
        $label = mb_strtolower(trim($header));
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        $label = preg_replace('/[^a-z0-9]+/i', '_', $label) ?? $label;

        return trim($label, '_');
    }

    private function statementName(string $filename): string
    {
        $name = mb_strtolower($filename);

        return str_contains($name, 'neraca') ? 'NERACA'
            : (str_contains($name, 'lpe') ? 'LPE'
            : (str_contains($name, 'operasional') || str_contains($name, 'lo') ? 'LO' : 'LRA'));
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
            $text = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0] . '.' . $parts[1]
                : str_replace(',', '', $text);
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
