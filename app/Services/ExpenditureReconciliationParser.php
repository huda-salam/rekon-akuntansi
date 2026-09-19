<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExpenditureReconciliationParser
{
    private const METRICS = [
        'SP2D LS' => 'sp2d_ls',
        'SP2D UP/GU' => 'sp2d_up_gu',
        'SP2D TU' => 'sp2d_tu',
        'SP2D KKPD' => 'sp2d_kkpd',
        'TOTAL SP2D' => 'total_sp2d_reported',
        'SPJ LS' => 'spj_ls',
        'SPJ UP/GU' => 'spj_up_gu',
        'SPJ TU' => 'spj_tu',
        'SPJ KKPD' => 'spj_kkpd',
        'TOTAL SPJ' => 'total_spj_reported',
        'STS UP/GU' => 'sts_up_gu',
        'STS TU' => 'sts_tu',
        'CP LS' => 'cp_ls',
        'CP UP/GU' => 'cp_up_gu',
        'CP TU' => 'cp_tu',
        'TOTAL STS' => 'total_sts_reported',
        'KAS SIPD' => 'kas_sipd',
        'KAS BANK' => 'kas_bank',
        'KAS TUNAI' => 'kas_tunai',
        'SELISIH' => 'selisih_reported',
    ];

    public function supports(Collection $rows): bool
    {
        $header = $rows->first(fn ($row) => mb_strtolower(trim((string) ($row[0] ?? ''))) === 'no');
        if (! $header) {
            return false;
        }

        $labels = collect($header)->map(fn ($v) => mb_strtolower(trim((string) $v)));
        return $labels->contains('kode')
            && $labels->contains('skpd')
            && $labels->contains('sp2d ls')
            && $labels->contains('spj ls')
            && $labels->contains('selisih');
    }

    public function parse(Collection $rows, SourceDocument $document, int $year): int
    {
        $headerIndex = $rows->search(fn ($row) => mb_strtolower(trim((string) ($row[0] ?? ''))) === 'no');
        if ($headerIndex === false) {
            throw new \InvalidArgumentException('Header rekonsiliasi pengeluaran tidak ditemukan.');
        }

        $header = $rows->get($headerIndex);
        $columns = [];
        foreach ($header as $index => $label) {
            $normalized = mb_strtolower(trim((string) $label));
            if ($normalized !== '') {
                $columns[$normalized] = $index;
            }
        }

        $count = 0;
        foreach ($rows->slice($headerIndex + 1) as $offset => $row) {
            $code = trim((string) ($row[$columns['kode']] ?? ''));
            $name = trim((string) ($row[$columns['skpd']] ?? ''));
            if ($code === '' || mb_strtolower($code) === 'grand total') {
                continue;
            }

            $skpd = Skpd::query()->where('code', $code)->first();
            $record = SourceRecord::create([
                'source_document_id' => $document->id,
                'source_row' => $headerIndex + $offset + 2,
                'sheet_name' => 'Worksheet',
                'record_type' => 'expenditure_reconciliation_row',
                'payload' => $this->payload($row, $columns),
            ]);

            foreach (self::METRICS as $label => $metric) {
                $index = $columns[mb_strtolower($label)] ?? null;
                if ($index === null) {
                    continue;
                }

                $value = $this->number($row[$index] ?? null);
                if ($value === null) {
                    continue;
                }

                FinancialFact::create([
                    'source_document_id' => $document->id,
                    'source_record_id' => $record->id,
                    'accounting_year_id' => AccountingYear::query()->where('year', $year)->value('id'),
                    'skpd_id' => $skpd?->id,
                    'period' => 'UNKNOWN',
                    'source_type' => 'expenditure_reconciliation',
                    'transaction_type' => $this->transactionType($label),
                    'account_code' => null,
                    'metric' => $metric,
                    'value' => $value,
                    'unit' => 'IDR',
                    'dimensions' => ['skpd_code'=>$code, 'skpd_name'=>$name, 'origin'=>'reported'],
                ]);
            }

            $this->derivedFact($document, $record, $year, $skpd, $code, $name, 'total_sp2d_derived',
                $this->sum($row, $columns, ['sp2d ls','sp2d up/gu','sp2d tu','sp2d kkpd']));
            $this->derivedFact($document, $record, $year, $skpd, $code, $name, 'total_spj_derived',
                $this->sum($row, $columns, ['spj ls','spj up/gu','spj tu','spj kkpd']));
            $this->derivedFact($document, $record, $year, $skpd, $code, $name, 'total_sts_derived',
                $this->sum($row, $columns, ['sts up/gu','sts tu','cp ls','cp up/gu','cp tu']));
            $this->derivedFact($document, $record, $year, $skpd, $code, $name, 'kas_balance_derived',
                $this->sum($row, $columns, ['kas sipd','kas bank','kas tunai']));
            $count++;
        }

        return $count;
    }

    private function derivedFact(SourceDocument $document, SourceRecord $record, int $year, ?Skpd $skpd, string $code, string $name, string $metric, float $value): void
    {
        FinancialFact::create([
            'source_document_id'=>$document->id,
            'source_record_id'=>$record->id,
            'accounting_year_id'=>AccountingYear::query()->where('year',$year)->value('id'),
            'skpd_id'=>$skpd?->id,
            'period'=>'UNKNOWN',
            'source_type'=>'expenditure_reconciliation',
            'transaction_type'=>'calculation',
            'metric'=>$metric,
            'value'=>$value,
            'unit'=>'IDR',
            'dimensions'=>['skpd_code'=>$code,'skpd_name'=>$name,'origin'=>'derived'],
        ]);
    }

    private function transactionType(string $label): string
    {
        return str_starts_with($label, 'SP2D') ? 'SP2D'
            : (str_starts_with($label, 'SPJ') ? 'SPJ'
            : (str_starts_with($label, 'STS') || str_starts_with($label, 'CP') ? 'STS_CP'
            : (str_starts_with($label, 'KAS') ? 'KAS' : 'OTHER')));
    }

    private function sum(array|Collection $row, array $columns, array $labels): float
    {
        $total = 0.0;
        foreach ($labels as $label) {
            $index = $columns[$label] ?? null;
            if ($index !== null) {
                $total += $this->number($row[$index] ?? null) ?? 0.0;
            }
        }
        return $total;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        if (is_numeric($value)) return (float) $value;
        $normalized = str_replace(['.', ','], ['', '.'], trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function payload(array|Collection $row, array $columns): array
    {
        $payload = [];
        foreach ($columns as $label => $index) {
            $payload[$label] = $row[$index] ?? null;
        }
        return $payload;
    }
}