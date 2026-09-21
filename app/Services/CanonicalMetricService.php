<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\SourceDocument;
use Illuminate\Support\Collection;

class CanonicalMetricService
{
    /** Explicit semantic mapping; unmapped rows remain available as raw facts. */
    public const METRICS = [
        'lra_revenue' => 'LRA Pendapatan',
        'lra_expenditure' => 'LRA Belanja',
        'lra_capital_expenditure' => 'LRA Belanja Modal',
        'lra_surplus_deficit' => 'LRA Surplus/(Defisit)',
        'lo_revenue' => 'LO Pendapatan',
        'lo_expense' => 'LO Beban',
        'lo_surplus_deficit' => 'LO Surplus/(Defisit)',
        'balance_cash' => 'Neraca Kas',
        'balance_receivable' => 'Neraca Piutang',
        'balance_inventory' => 'Neraca Persediaan',
        'balance_fixed_assets' => 'Neraca Aset Tetap',
        'balance_equity' => 'Neraca Ekuitas',
        'lpe_beginning_equity' => 'LPE Ekuitas Awal',
        'lpe_surplus_deficit' => 'LPE Surplus/(Defisit)',
        'lpe_ending_equity' => 'LPE Ekuitas Akhir',
    ];

    public function normalize(Collection $facts): Collection
    {
        return $facts->map(function (FinancialFact $fact) {
            $metric = $this->canonicalMetric($fact);

            if ($metric === null || ! $this->isComparableColumn($fact, $metric)) return null;

            return [
                'canonical_metric' => $metric,
                'value' => $this->signedValue($fact, $metric),
                'financial_fact_id' => $fact->id,
                'source_document_id' => $fact->source_document_id,
                'source_type' => $fact->source_type,
                'skpd_id' => $fact->skpd_id,
                'month' => $fact->month,
                'account_code' => $fact->account_code,
                'dimensions' => $fact->dimensions ?? [],
            ];
        })->filter()->values();
    }

    private function signedValue(FinancialFact $fact, string $metric): float
    {
        $value = (float) $fact->value;

        if ($fact->source_type !== 'ledger') {
            return $value;
        }

        return match ($metric) {
            'lra_revenue' => strtoupper((string) $fact->transaction_type) === 'DEBIT' ? -$value : $value,
            'lra_expenditure', 'lra_capital_expenditure' => strtoupper((string) $fact->transaction_type) === 'CREDIT' ? -$value : $value,
            default => $value,
        };
    }

    private function isComparableColumn(FinancialFact $fact, string $metric): bool
    {
        if ($fact->source_type !== 'financial_statement') return true;

        $column = mb_strtolower((string) ($fact->dimensions['column'] ?? ''));

        // Never compare budget/target columns as if they were realized amounts.
        if ($this->containsAny($column, ['anggaran', 'perubahan', 'target', 'persentase', '%'])) {
            return false;
        }

        // The working papers contain multiple entity columns. Only the
        // consolidated/realized column is safe for a cross-source control.
        if ($this->containsAny($column, ['realisasi', 'konsolidasi', 'saldo'])) {
            return true;
        }

        // A standalone statement export may expose only one numeric column.
        // Keep it comparable when its column has no explicit budget semantics.
        return ! $this->containsAny($column, ['pagu', 'rencana']);
    }

    private function canonicalMetric(FinancialFact $fact): ?string
    {
        $statement = strtoupper((string) ($fact->dimensions['statement'] ?? $fact->transaction_type ?? ''));
        $description = mb_strtolower((string) ($fact->dimensions['description'] ?? ''));

        if ($fact->source_type === 'ledger') {
            $code = (string) $fact->account_code;
            if (str_starts_with($code, '4')) return 'lra_revenue';
            if (str_starts_with($code, '5.2')) return 'lra_capital_expenditure';
            if (str_starts_with($code, '5')) return 'lra_expenditure';
            return null;
        }

        if ($fact->source_type !== 'financial_statement') return null;

        $summary = (bool) ($fact->dimensions['summary_row'] ?? false);

        if ($statement === 'LRA') {
            if ($summary && (string) $fact->account_code === '4') return 'lra_revenue';
            if ($summary && (string) $fact->account_code === '5') return 'lra_expenditure';
            if ($this->containsAny($description, ['belanja modal']) && (string) $fact->account_code === '5.2') return 'lra_capital_expenditure';
            if ($this->containsAny($description, ['surplus / (defisit)', 'surplus/(defisit)', 'surplus defisit'])) return 'lra_surplus_deficit';
        }

        if ($statement === 'LO') {
            if ($summary && (string) $fact->account_code === '7') return 'lo_revenue';
            if ($summary && (string) $fact->account_code === '8') return 'lo_expense';
            if ($this->containsAny($description, ['surplus / (defisit) - lo', 'surplus/(defisit) - lo'])) return 'lo_surplus_deficit';
        }

        if ($statement === 'NERACA') {
            // Only classify high-confidence aggregate balance-sheet lines.
            // Detail rows must remain raw facts; otherwise cross-source
            // aggregation can double-count the balance.
            if ($summary && $this->containsAny($description, ['kas dan setara kas'])) return 'balance_cash';
            if ($summary && $this->containsAny($description, ['piutang'])) return 'balance_receivable';
            if ($summary && $this->containsAny($description, ['persediaan'])) return 'balance_inventory';
            if ($summary && $this->containsAny($description, ['aset tetap'])) return 'balance_fixed_assets';
            if ($summary && (string) $fact->account_code === '3') return 'balance_equity';
        }

        if ($statement === 'LPE') {
            if ($this->containsAny($description, ['ekuitas awal'])) return 'lpe_beginning_equity';
            if ($this->containsAny($description, ['surplus / (defisit) - lo', 'surplus/(defisit)'])) return 'lpe_surplus_deficit';
            if ($this->containsAny($description, ['ekuitas akhir'])) return 'lpe_ending_equity';
        }

        return null;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) return true;
        }

        return false;
    }
}
