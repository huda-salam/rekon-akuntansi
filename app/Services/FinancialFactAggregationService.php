<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use Illuminate\Support\Collection;

class FinancialFactAggregationService
{
    /**
     * Build reconciliation grains without mutating source facts.
     *
     * scope=document   = one source record (legacy/current behaviour)
     * scope=skpd_month = all facts for one SKPD + month
     * scope=skpd       = all facts for one SKPD in the run
     * scope=year       = all facts in the run
     *
     * Each aggregate metric keeps the IDs of every source fact contributing
     * to the amount, so downstream results remain traceable.
     */
    public function grains(Collection $facts, ReconciliationRule $rule): Collection
    {
        $scope = $rule->scope ?: 'document';

        $groups = match ($scope) {
            'skpd_month' => $facts->groupBy(fn ($fact) => implode(':', [
                $fact->skpd_id ?? 'none',
                $fact->month ?? 'none',
            ])),
            'skpd' => $facts->groupBy(fn ($fact) => (string) ($fact->skpd_id ?? 'none')),
            'year' => collect(['year' => $facts]),
            default => $facts->groupBy('source_record_id'),
        };

        return $groups->map(function (Collection $group) {
            $aggregated = $group
                ->groupBy('metric')
                ->map(function (Collection $metricFacts, string $metric) {
                    $first = $metricFacts->first();
                    $lineageIds = $metricFacts->pluck('id')->filter()->values()->all();

                    $fact = (object) [
                        'metric' => $metric,
                        'value' => round((float) $metricFacts->sum(fn ($item) => (float) $item->value), 2),
                        'id' => $first?->id,
                        'lineage_ids' => $lineageIds,
                    ];

                    return $fact;
                });

            return [
                'facts' => $aggregated->values(),
                'source_document_id' => $this->singleOrNull($group->pluck('source_document_id')),
                'skpd_id' => $this->singleOrNull($group->pluck('skpd_id')),
                'month' => $this->singleOrNull($group->pluck('month')),
                'financial_fact_ids' => $group->pluck('id')->filter()->values()->all(),
            ];
        })->values();
    }

    private function singleOrNull(Collection $values): mixed
    {
        $values = $values->filter(fn ($value) => $value !== null)->unique()->values();

        return $values->count() === 1 ? $values->first() : null;
    }
}
