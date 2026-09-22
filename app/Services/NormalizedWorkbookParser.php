<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use Illuminate\Support\Collection;

class NormalizedWorkbookParser implements SourceWorkbookParser
{
    public function supports(string $type): bool
    {
        return in_array($type, ['revenue_reconciliation', 'financial_statement', 'ledger', 'blud', 'non_rkud_transfer'], true);
    }

    public function parse(Collection $sheets, SourceDocument $document, int $year, ?int $month = null): int
    {
        $yearId = AccountingYear::query()->where('year', $year)->value('id');
        if (! $yearId) {
            throw new \InvalidArgumentException("Tahun {$year} tidak ditemukan.");
        }

        $records = 0;

        foreach ($sheets as $sheetName => $rows) {
            if (! $this->shouldParseSheet($sheetName, $rows, $document->document_type, $document->original_filename)) {
                continue;
            }

            $headerIndex = $this->headerIndex($rows, $document->document_type);
            $headers = $headerIndex !== null ? $this->headers($rows->get($headerIndex)) : [];

            if ($headerIndex === null || ! $this->hasFinancialColumns($headers)) {
                continue;
            }

            foreach ($rows as $rowIndex => $row) {
                if ($headerIndex !== null && $rowIndex <= $headerIndex) continue;
                if ($this->isEmptyRow($row)) continue;

                $record = SourceRecord::create([
                    'source_document_id' => $document->id,
                    'source_row' => $rowIndex + 1,
                    'sheet_name' => (string) $sheetName,
                    'record_type' => $document->document_type . '_row',
                    'payload' => $this->payload($row, $headers),
                ]);

                $this->facts($row, $headers, $record, $document, $yearId, $year, $month);
                $records++;
            }
        }

        return $records;
    }

    private function shouldParseSheet(string $sheetName, Collection $rows, string $type, string $filename): bool
    {
        $name = mb_strtolower(trim($sheetName));

        if (str_starts_with($name, 'cetak') || in_array($name, ['data bulan', 'worksheet'], true)) {
            return false;
        }

        $text = $this->sheetText($rows);

        if ($type === 'blud') {
            return str_contains($text, 'nomor sp3bp')
                && str_contains($text, 'nomor sp2bp');
        }

        if ($type === 'non_rkud_transfer') {
            if (str_contains(mb_strtolower($filename), 'dana desa')) {
                return $name === 'data dd';
            }

            if (str_contains(mb_strtolower($filename), 'bok')) {
                return $name === 'bok';
            }

            if (str_contains(mb_strtolower($filename), 'bosp')) {
                return $name === 'bosp';
            }

            if (in_array($name, ['blud', 'bok', 'bosp', 'data dd', 'data bulan'], true)) {
                return false;
            }

            return str_contains($text, 'nomor sp2b')
                || str_contains($text, 'nomor sp2d bun')
                || str_contains($text, 'nomor sp2bdd')
                || str_contains($text, 'nomor sp3bp')
                || $this->hasFinancialColumns($this->headersFromBestRow($rows, $type));
        }

        return true;
    }

    private function sheetText(Collection $rows): string
    {
        return $rows->take(20)
            ->flatten()
            ->map(fn ($value) => mb_strtolower(trim((string) ($value ?? ''))))
            ->filter()
            ->implode(' ');
    }

    private function headersFromBestRow(Collection $rows, string $type): array
    {
        $index = $this->headerIndex($rows, $type);
        return $index === null ? [] : $this->headers($rows->get($index));
    }

    private function hasFinancialColumns(array $headers): bool
    {
        $patterns = [
            'saldo', 'pendapatan', 'belanja', 'pembiayaan', 'anggaran',
            'realisasi', 'jumlah', 'nilai', 'nominal', 'penerimaan',
            'pengeluaran', 'transfer', 'beban',
        ];

        foreach ($headers as $header) {
            $label = mb_strtolower(trim($header));
            if (in_array($label, ['no', 'nomor', 'nomor dokumen', 'tahun', 'tanggal', 'tgl', 'bulan'], true)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (str_contains($label, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isFinancialColumn(string $label): bool
    {
        $label = mb_strtolower(trim($label));

        if ($label === '' || in_array($label, [
            'no', 'nomor', 'nomor dokumen', 'tanggal', 'tgl', 'bulan', 'tahun',
            'kode', 'kode skpd', 'skpd', 'nama skpd', 'unit kerja',
            'nama blud', 'nama blu', 'nama kuasa bud', 'kegiatan',
            'nomor sp2b', 'nomor sp2d bun', 'nomor sp2bdd', 'nomor sp3bp',
            'nomor sp2bp', 'nomor spb', 'nomor sp2t', 'referensi',
        ], true)) {
            return false;
        }

        foreach ([
            'saldo', 'pendapatan', 'belanja', 'pembiayaan', 'anggaran',
            'realisasi', 'jumlah', 'nilai', 'nominal', 'penerimaan',
            'pengeluaran', 'transfer', 'beban',
        ] as $pattern) {
            if (str_contains($label, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function headerIndex(Collection $rows, string $type): ?int
    {
        $best = null;
        $bestScore = -1;

        foreach ($rows->take(40) as $index => $row) {
            $labels = collect($row)->map(fn ($v) => mb_strtolower(trim((string) ($v ?? ''))))->filter();
            if ($labels->isEmpty()) continue;

            $targets = match ($type) {
                'revenue_reconciliation' => ['kode', 'kode rekening', 'rekening', 'uraian', 'realisasi', 'pendapatan', 'skpd', 'nama skpd'],
                'ledger' => ['tanggal', 'kode rekening', 'nama rekening', 'uraian', 'debit', 'kredit', 'saldo', 'referensi'],
                'financial_statement' => ['kode rekening', 'uraian', 'anggaran', 'realisasi', 'saldo', 'jumlah', 'tahun', 'konsolidasi'],
                default => ['kode rekening', 'uraian', 'tanggal', 'jumlah', 'nominal', 'nilai', 'pagu', 'realisasi'],
            };

            $score = $labels->intersect($targets)->count();
            if ($score > $bestScore && $score >= 2 && $labels->count() >= 2) {
                $bestScore = $score;
                $best = $index;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function headers(array|Collection $row): array
    {
        $headers = [];
        foreach ($row as $index => $value) {
            $label = trim((string) ($value ?? ''));
            $headers[$index] = $label !== '' ? $label : "column_{$index}";
        }
        return $headers;
    }

    private function payload(array|Collection $row, array $headers): array
    {
        $payload = [];
        foreach ($headers as $index => $header) $payload[$header] = $row[$index] ?? null;
        return $payload;
    }

    private function facts(array|Collection $row, array $headers, SourceRecord $record, SourceDocument $document, int $yearId, int $year, ?int $month): void
    {
        $skpd = $this->resolveSkpd($row, $headers);
        $accountCode = $this->accountCode($row, $headers);

        foreach ($headers as $index => $header) {
            if (! $this->isFinancialColumn($header)) continue;

            $value = $this->number($row[$index] ?? null);
            if ($value === null) continue;

            $metric = $this->metric($header);
            if ($metric === '') continue;

            FinancialFact::create([
                'source_document_id' => $document->id,
                'source_record_id' => $record->id,
                'accounting_year_id' => $yearId,
                'skpd_id' => $skpd?->id,
                'period' => $month ? sprintf('%04d-%02d', $year, $month) : (string) $year,
                'month' => $month,
                'source_type' => $document->document_type,
                'transaction_type' => $this->transactionType($document->document_type, $header),
                'account_code' => $accountCode,
                'metric' => $metric,
                'value' => $value,
                'unit' => 'IDR',
                'dimensions' => ['sheet_name' => $record->sheet_name, 'source_row' => $record->source_row, 'column' => $header, 'origin' => 'reported'],
                'lineage' => ['origin' => 'reported', 'source_document_id' => $document->id, 'source_record_id' => $record->id, 'sheet_name' => $record->sheet_name, 'source_row' => $record->source_row, 'column' => $header],
            ]);
        }
    }

    private function metric(string $header): string
    {
        $label = mb_strtolower(trim($header));
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        $label = preg_replace('/[^a-z0-9]+/i', '_', $label) ?? $label;
        return trim($label, '_');
    }

    private function transactionType(string $type, string $header): string
    {
        $label = mb_strtolower($header);
        if ($type === 'ledger') return str_contains($label, 'debit') ? 'DEBIT' : (str_contains($label, 'kredit') ? 'CREDIT' : 'JOURNAL');
        if ($type === 'financial_statement') return 'STATEMENT';
        if ($type === 'revenue_reconciliation') return 'REVENUE_RECON';
        if ($type === 'blud') return 'BLUD';
        return 'NON_RKUD_TRANSFER';
    }

    private function resolveSkpd(array|Collection $row, array $headers): ?Skpd
    {
        foreach ($headers as $index => $header) {
            if (! in_array(mb_strtolower(trim($header)), ['skpd', 'nama skpd', 'kode skpd', 'unit kerja', 'nama unit kerja'], true)) continue;
            $name = trim((string) ($row[$index] ?? ''));
            if ($name === '') continue;
            if ($skpd = Skpd::query()->where('name', $name)->first()) return $skpd;
        }
        return null;
    }

    private function accountCode(array|Collection $row, array $headers): ?string
    {
        foreach ($headers as $index => $header) {
            if (in_array(mb_strtolower(trim($header)), ['kode rekening', 'kode akun', 'account code', 'kode'], true)) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') return $value;
            }
        }
        return null;
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
            if (strrpos($text, ',') > strrpos($text, '.')) {
                $text = str_replace('.', '', $text);
                $text = str_replace(',', '.', $text);
            } else $text = str_replace(',', '', $text);
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

    private function isEmptyRow(array|Collection $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) ($value ?? '')) === '');
    }
}
