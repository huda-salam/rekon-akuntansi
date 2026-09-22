<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\SourceDocument;

class SourceFactQualityGate
{
    /**
     * @return array{
     *   valid:bool,
     *   errors:array<int,string>,
     *   warnings:array<int,string>,
     *   stats:array<string,int>
     * }
     */
    public function evaluate(SourceDocument $document): array
    {
        $facts = FinancialFact::query()
            ->where('source_document_id', $document->id)
            ->get([
                'id', 'source_record_id', 'source_type', 'transaction_type',
                'account_code', 'metric', 'value', 'fact_date', 'lineage',
            ]);

        $errors = [];
        $warnings = [];

        if ($facts->isEmpty()) {
            $errors[] = 'Parser tidak menghasilkan FinancialFact.';
        }

        $forbiddenMetricNames = [
            'no', 'nomor', 'tanggal', 'tgl', 'tahun', 'bulan',
            'nomor_dokumen', 'nomor_sp2b', 'nomor_sp2d_bun', 'nomor_sp2bdd',
            'nomor_sp3bp', 'nomor_sp2bp', 'nomor_spb', 'nomor_sp2t',
        ];

        $metadataFacts = $facts->filter(
            fn (FinancialFact $fact) => in_array(mb_strtolower(trim($fact->metric)), $forbiddenMetricNames, true)
        );

        if ($metadataFacts->isNotEmpty()) {
            $errors[] = sprintf(
                '%d FinancialFact berasal dari field metadata/non-keuangan.',
                $metadataFacts->count()
            );
        }

        $duplicateMetrics = $facts
            ->groupBy(fn (FinancialFact $fact) => ($fact->source_record_id ?? 'null') . '|' . $fact->metric)
            ->filter(fn ($group) => $group->count() > 1);

        if ($duplicateMetrics->isNotEmpty()) {
            $errors[] = sprintf(
                '%d kombinasi source_record + metric terduplikasi.',
                $duplicateMetrics->count()
            );
        }

        $derivedWithoutLineage = $facts->filter(
            fn (FinancialFact $fact) =>
                strtolower((string) $fact->transaction_type) === 'calculation'
                && ! $this->hasLineageInputs($fact->lineage)
        );

        if ($derivedWithoutLineage->isNotEmpty()) {
            $errors[] = sprintf(
                '%d derived fact tidak memiliki lineage input.',
                $derivedWithoutLineage->count()
            );
        }

        if ($document->document_type === 'ledger') {
            $ledgerFacts = $facts->whereIn('metric', ['debit', 'credit', 'balance']);

            if ($ledgerFacts->isEmpty()) {
                $errors[] = 'Ledger tidak menghasilkan fact debit/kredit/saldo.';
            } elseif ($ledgerFacts->whereNotNull('account_code')->isEmpty()) {
                $errors[] = 'Ledger menghasilkan fact tetapi tidak memiliki account_code.';
            }

            $missingDates = $ledgerFacts->whereNull('fact_date')->count();
            if ($missingDates > 0) {
                $warnings[] = sprintf('%d fact ledger tidak memiliki tanggal transaksi.', $missingDates);
            }
        }

        if (in_array($document->document_type, ['blud', 'non_rkud_transfer'], true)) {
            if ($facts->whereIn('metric', [
                'saldo_awal', 'saldo_akhir', 'pendapatan', 'belanja',
                'penerimaan', 'pengeluaran', 'pembiayaan',
            ])->isEmpty()) {
                $warnings[] = 'Transfer workbook tidak menghasilkan metric keuangan utama yang umum.';
            }
        }

        $zeroFacts = $facts->filter(fn (FinancialFact $fact) => (float) $fact->value === 0.0)->count();
        if ($facts->count() > 0 && $zeroFacts === $facts->count()) {
            $warnings[] = 'Seluruh FinancialFact bernilai nol.';
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => [
                'financial_facts' => $facts->count(),
                'zero_value_facts' => $zeroFacts,
                'derived_facts' => $facts->where('transaction_type', 'calculation')->count(),
                'ledger_facts_without_account' => $document->document_type === 'ledger'
                    ? $facts->whereIn('metric', ['debit', 'credit', 'balance'])->whereNull('account_code')->count()
                    : 0,
            ],
        ];
    }

    private function hasLineageInputs(mixed $lineage): bool
    {
        if (! is_array($lineage)) {
            return false;
        }

        return ! empty($lineage['input_fact_ids'])
            || ! empty($lineage['input_metrics'])
            || ! empty($lineage['source_record_id']);
    }
}
