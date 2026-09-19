<?php

namespace App\Services;

use App\Models\FinancialFact;
use Illuminate\Support\Collection;

class FinancialFactCalculator
{
    /**
     * Calculate a derived metric from facts belonging to the same reconciliation grain.
     *
     * @return array{value:float,inputs:array<int,array<string,mixed>>,lineage:array<int,int>}
     */
    public function calculate(Collection $facts, array $inputMetrics, string $expression): array
    {
        $values = [];
        $inputs = [];
        $lineage = [];

        foreach ($inputMetrics as $metric) {
            $fact = $facts->firstWhere('metric', $metric);

            if (! $fact) {
                throw new \InvalidArgumentException("Input metric [{$metric}] tidak ditemukan.");
            }

            $values[$metric] = (float) $fact->value;
            $inputs[] = [
                'metric' => $metric,
                'value' => (float) $fact->value,
                'financial_fact_id' => $fact->id,
            ];
            $lineage[] = $fact->id;
        }

        $value = $this->evaluate($values, $expression);

        return [
            'value' => $value,
            'inputs' => $inputs,
            'lineage' => $lineage,
        ];
    }

    private function evaluate(array $values, string $expression): float
    {
        $expression = trim($expression);

        if ($expression === 'SUM') {
            return array_sum($values);
        }

        if (preg_match('/^([a-zA-Z0-9_]+)\s*([+\-])\s*([a-zA-Z0-9_]+)$/', $expression, $matches)) {
            $left = $values[$matches[1]] ?? throw new \InvalidArgumentException('Metric expression kiri tidak ditemukan.');
            $right = $values[$matches[3]] ?? throw new \InvalidArgumentException('Metric expression kanan tidak ditemukan.');

            return $matches[2] === '+' ? $left + $right : $left - $right;
        }

        throw new \InvalidArgumentException("Expression calculation tidak didukung: {$expression}");
    }
}
