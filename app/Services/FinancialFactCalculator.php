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

        if (preg_match('/^([a-zA-Z0-9_]+)(?:\s*([+\-])\s*([a-zA-Z0-9_]+))+$/', $expression)) {
            preg_match_all('/([+\-]?)\s*([a-zA-Z0-9_]+)/', $expression, $tokens, PREG_SET_ORDER);
            $result = null;

            foreach ($tokens as $token) {
                $operator = $token[1] ?? '';
                $metric = $token[2];
                if (! array_key_exists($metric, $values)) {
                    throw new \InvalidArgumentException("Metric expression [{$metric}] tidak ditemukan.");
                }

                $result = $result === null
                    ? $values[$metric]
                    : ($operator === '+' ? $result + $values[$metric] : $result - $values[$metric]);
            }

            return (float) $result;
        }

        throw new \InvalidArgumentException("Expression calculation tidak didukung: {$expression}");
    }
}
