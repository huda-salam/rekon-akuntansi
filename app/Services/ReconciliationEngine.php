<?php

namespace App\Services;

use App\Models\ReconciliationResult;
use App\Models\ReconciliationRule;
use App\Models\ReconciliationRun;
use App\Models\FinancialFact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconciliationEngine
{
    public function run(
        ReconciliationRun $run,
        Collection $rules,
        Collection $facts,
    ): Collection {
        $results = collect();

        foreach ($facts->groupBy('source_record_id') as $recordFacts) {
            foreach ($rules as $rule) {
                if (! $this->ruleApplies($rule, $recordFacts)) {
                    continue;
                }

                $calculation = app(FinancialFactCalculator::class)->calculate(
                    $recordFacts,
                    $rule->input_metrics ?? [],
                    $rule->expression,
                );

                $expected = 0.0;
                $actual = $calculation['value'];
                $variance = $actual - $expected;
                $status = abs($variance) <= (float) $rule->tolerance ? 'PASS' : 'VARIANCE';

                $result = ReconciliationResult::create([
                    'reconciliation_run_id' => $run->id,
                    'reconciliation_rule_id' => $rule->id,
                    'source_document_id' => $recordFacts->first()->source_document_id,
                    'skpd_id' => $recordFacts->first()->skpd_id,
                    'period' => $recordFacts->first()->period,
                    'status' => $status,
                    'expected_value' => $expected,
                    'actual_value' => $actual,
                    'variance' => $variance,
                    'inputs' => $calculation['inputs'],
                    'lineage' => [
                        'financial_fact_ids' => $calculation['lineage'],
                    ],
                ]);

                $results->push($result);
            }
        }

        $summary = $results->groupBy('status')->map->count()->all();

        $run->update([
            'status' => 'completed',
            'completed_at' => now(),
            'summary' => $summary,
        ]);

        return $results;
    }

    private function ruleApplies(ReconciliationRule $rule, Collection $facts): bool
    {
        $required = collect($rule->input_metrics ?? []);

        return $required->every(
            fn ($metric) => $facts->contains('metric', $metric)
        );
    }
}
