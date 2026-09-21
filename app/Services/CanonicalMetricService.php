<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\SourceDocument;
use Illuminate\Support\Collection;

class CanonicalMetricService
{
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

            if ($metric === null) return null;

            return [
                'canonical_metric' => $metric,
                'value' => (float) $fact->value,
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

        if ($statement === 'LRA') {
            if ($this->containsAny($description, ['pendapatan daerah', 'pendapatan'])) return 'lra_revenue';
            if ($this->containsAny($description, ['belanja modal'])) return 'lra_capital_expenditure';
            if ($this->containsAny($description, ['belanja'])) return 'lra_expenditure';
            if ($this->containsAny($description, ['surplus / (defisit)', 'surplus/(defisit)', 'surplus defisit'])) return 'lra_surplus_deficit';
        }

        if ($statement === 'LO') {
            if ($this->containsAny($description, ['pendapatan daerah- lo', 'pendapatan daerah-lo', 'pendapatan'])) return 'lo_revenue';
            if ($this->containsAny($description, ['beban'])) return 'lo_expense';
            if ($this->containsAny($description, ['surplus / (defisit) - lo', 'surplus/(defisit) - lo'])) return 'lo_surplus_deficit';
        }

        if ($statement === 'NERACA') {
            if ($this->containsAny($description, ['kas', 'setara kas'])) return 'balance_cash';
            if ($this->containsAny($description, ['piutang'])) return 'balance_receivable';
            if ($this->containsAny($description, ['persediaan'])) return 'balance_inventory';
            if ($this->containsAny($description, ['aset tetap'])) return 'balance_fixed_assets';
            if ($this->containsAny($description, ['ekuitas'])) return 'balance_equity';
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
