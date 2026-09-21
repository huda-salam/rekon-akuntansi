<?php

namespace App\Services;

use App\Models\ReconciliationResult;
use App\Models\ReconciliationRule;
use App\Models\ReconciliationRun;
use Illuminate\Support\Collection;
use Throwable;

class ReconciliationEngine
{
    public function run(
        ReconciliationRun $run,
        Collection $rules,
        Collection $facts,
    ): Collection {
        $results = collect();
        $calculator = app(FinancialFactCalculator::class);
        $aggregator = app(FinancialFactAggregationService::class);
        $crossSource = app(CrossSourceReconciliationService::class);

        foreach ($rules as $rule) {
            $metadata = $rule->metadata ?? [];

            if (isset($metadata['left'], $metadata['right'])) {
                // Cross-source rules represent one comparison per reconciliation
                // grain. Aggregator grains are only used to discover SKPDs; the
                // actual period semantics are defined by each rule side.
                $grains = $aggregator->grains($facts, $rule);
                $comparisonGrains = $grains
                    ->groupBy(fn ($grain) => (string) ($grain['skpd_id'] ?? 'null'))
                    ->map(fn ($group) => $group->first())
                    ->values();

                if ($comparisonGrains->isEmpty()) {
                    $comparisonGrains = collect([[
                        'skpd_id' => null,
                        'month' => $run->month,
                        'source_document_id' => null,
                        'financial_fact_ids' => [],
                    ]]);
                }

                foreach ($comparisonGrains as $grain) {
                    $comparisonMonth = $run->month ?? $grain['month'];
                    $comparison = $crossSource->compare(
                        $facts,
                        $rule,
                        $grain['skpd_id'],
                        $comparisonMonth,
                    );

                    $base = [
                        'reconciliation_run_id' => $run->id,
                        'reconciliation_rule_id' => $rule->id,
                        'source_document_id' => $grain['source_document_id'],
                        'skpd_id' => $grain['skpd_id'],
                        'month' => $comparisonMonth,
                        'period' => $this->period($run->year?->year, $comparisonMonth),
                    ];

                    $result = $comparison === null
                        ? ReconciliationResult::create($base + [
                            'status' => 'INCOMPLETE',
                            'expected_value' => 0,
                            'actual_value' => null,
                            'variance' => null,
                            'inputs' => [],
                            'lineage' => ['financial_fact_ids' => $grain['financial_fact_ids'], 'cross_source' => true],
                            'explanation' => 'Cross-source inputs are incomplete for this reconciliation grain.',
                        ])
                        : ReconciliationResult::create($base + [
                            'status' => $comparison['status'],
                            'expected_value' => 0,
                            'actual_value' => $comparison['variance'],
                            'variance' => $comparison['variance'],
                            'inputs' => [
                                ['side' => 'left', 'value' => $comparison['left']],
                                ['side' => 'right', 'value' => $comparison['right']],
                            ],
                            'lineage' => [
                                'financial_fact_ids' => $grain['financial_fact_ids'],
                                'cross_source' => true,
                            ],
                        ]);

                    $results->push($result);
                }

                continue;
            }

            foreach ($aggregator->grains($facts, $rule) as $grain) {
                $recordFacts = collect($grain['facts']);
                $base = [
                    'reconciliation_run_id' => $run->id,
                    'reconciliation_rule_id' => $rule->id,
                    'source_document_id' => $grain['source_document_id'],
                    'skpd_id' => $grain['skpd_id'],
                    'month' => $run->month ?? $grain['month'],
                    'period' => $this->period($run->year?->year, $run->month ?? $grain['month']),
                ];

                if (! $this->ruleApplies($rule, $recordFacts)) {
                    $result = ReconciliationResult::create($base + [
                        'status' => 'INCOMPLETE',
                        'expected_value' => 0,
                        'actual_value' => null,
                        'variance' => null,
                        'inputs' => [],
                        'lineage' => [
                            'financial_fact_ids' => $grain['financial_fact_ids'],
                            'missing_metrics' => collect($rule->input_metrics ?? [])
                                ->reject(fn ($metric) => $recordFacts->contains('metric', $metric))
                                ->values()
                                ->all(),
                        ],
                        'explanation' => 'Required financial facts are missing for this reconciliation rule.',
                    ]);

                    $results->push($result);
                    continue;
                }

                try {
                    $calculation = $calculator->calculate(
                        $recordFacts,
                        $rule->input_metrics ?? [],
                        $rule->expression,
                    );

                    $expected = 0.0;
                    $actual = $calculation['value'];
                    $variance = $actual - $expected;
                    $status = abs($variance) <= (float) $rule->tolerance ? 'PASS' : 'VARIANCE';

                    $result = ReconciliationResult::create($base + [
                        'status' => $status,
                        'expected_value' => $expected,
                        'actual_value' => $actual,
                        'variance' => $variance,
                        'inputs' => $calculation['inputs'],
                        'lineage' => [
                            'financial_fact_ids' => $calculation['lineage'],
                            'rule_expression' => $rule->expression,
                        ],
                    ]);
                } catch (Throwable $exception) {
                    $result = ReconciliationResult::create($base + [
                        'status' => 'ERROR',
                        'expected_value' => 0,
                        'actual_value' => null,
                        'variance' => null,
                        'inputs' => [],
                        'lineage' => [],
                        'explanation' => $exception->getMessage(),
                    ]);
                }

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
        return collect($rule->input_metrics ?? [])->every(
            fn ($metric) => $facts->contains('metric', $metric)
        );
    }

    private function period(?int $year, ?int $month): string
    {
        if ($year === null) {
            return 'UNKNOWN';
        }

        return $month !== null
            ? sprintf('%04d-%02d', $year, $month)
            : sprintf('%04d', $year);
    }
}
