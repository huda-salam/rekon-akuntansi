<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use Illuminate\Support\Collection;

class GenericWorkbookParser
{
    public function parse(
        Collection $sheets,
        SourceDocument $document,
        int $year,
        ?int $month = null,
    ): int {
        $yearId = AccountingYear::query()->where('year', $year)->value('id');

        if (! $yearId) {
            throw new \InvalidArgumentException("Tahun {$year} tidak ditemukan.");
        }

        $records = 0;

        foreach ($sheets as $sheetName => $rows) {
            $headerIndex = $this->headerIndex($rows);
            $headers = $headerIndex !== null ? $this->headers($rows->get($headerIndex)) : [];

            foreach ($rows as $rowIndex => $row) {
                if ($headerIndex !== null && $rowIndex <= $headerIndex) {
                    continue;
                }

                $payload = $this->payload($row, $headers);

                if ($this->isEmpty($payload)) {
                    continue;
                }

                $record = SourceRecord::create([
                    'source_document_id' => $document->id,
                    'source_row' => $rowIndex + 1,
                    'sheet_name' => (string) $sheetName,
                    'record_type' => $document->document_type . '_row',
                    'payload' => $payload,
                ]);

                $this->facts($row, $headers, $record, $document, $year, $yearId, $month);

                $records++;
            }
        }

        return $records;
    }

    private function facts(array|Collection $row, array $headers, SourceRecord $record, SourceDocument $document, int $year, int $yearId, ?int $month): void
    {
        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;

            if (! $this->isNumber($value)) {
                continue;
            }

            $metric = $this->metric($header);

            if ($metric === '') {
                continue;
            }

            FinancialFact::create([
                'source_document_id' => $document->id,
                'source_record_id' => $record->id,
                'accounting_year_id' => $yearId,
                'skpd_id' => $this->resolveSkpd($row, $headers)?->id,
                'period' => $month ? sprintf('%04d-%02d', $year, $month) : 'UNKNOWN',
                'month' => $month,
                'source_type' => $document->document_type,
                'transaction_type' => 'OTHER',
                'account_code' => $this->accountCode($row, $headers),
                'metric' => $metric,
                'value' => $this->number($value),
                'unit' => 'IDR',
                'dimensions' => [
                    'sheet_name' => (string) $record->sheet_name,
                    'source_row' => $record->source_row,
                    'column' => $header,
                    'origin' => 'reported',
                ],
                'lineage' => [
                    'origin' => 'reported',
                    'source_document_id' => $document->id,
                    'source_record_id' => $record->id,
                    'sheet_name' => $record->sheet_name,
                    'source_row' => $record->source_row,
                    'column' => $header,
                ],
            ]);
        }
    }

    private function headerIndex(Collection $rows): ?int
    {
        $best = null;
        $score = 0;

        foreach ($rows->take(30) as $index => $row) {
            $nonEmpty = collect($row)->filter(fn ($v) => trim((string) ($v ?? '')) !== '')->count();

            if ($nonEmpty >= 2 && $nonEmpty > $score) {
                $score = $nonEmpty;
                $best = $index;
            }
        }

        return $best;
    }

    private function headers(array|Collection $row): array
    {
        $headers = [];

        foreach ($row as $index => $value) {
            $label = trim((string) ($value ?? ''));
            if ($label !== '') {
                $headers[$index] = $label;
            }
        }

        return $headers;
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
        $label = preg_replace('/\\s+/', ' ', $label) ?? $label;
        $label = preg_replace('/[^a-z0-9]+/i', '_', $label) ?? $label;

        return trim($label, '_');
    }

    private function isEmpty(array $payload): bool
    {
        return collect($payload)->every(fn ($value) => trim((string) ($value ?? '')) === '');
    }

    private function isNumber(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '' && is_numeric($value);
    }

    private function number(mixed $value): float
    {
        return (float) $value;
    }

    private function resolveSkpd(array|Collection $row, array $headers): ?Skpd
    {
        foreach (['SKPD', 'Skpd', 'NAMA SKPD', 'NAMA SKPD/UNIT KERJA'] as $label) {
            $index = array_search($label, $headers, true);

            if ($index !== false && trim((string) ($row[$index] ?? '')) !== '') {
                $name = trim((string) $row[$index]);
                return Skpd::query()->where('name', $name)->first();
            }
        }

        return null;
    }

    private function accountCode(array|Collection $row, array $headers): ?string
    {
        foreach (['KODE REKENING', 'Kode Rekening', 'KODE'] as $label) {
            $index = array_search($label, $headers, true);

            if ($index !== false) {
                $value = trim((string) ($row[$index] ?? ''));
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
