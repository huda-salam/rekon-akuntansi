<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use Illuminate\Support\Collection;

class CrossSourceReconciliationService
{
    public function compare(
        Collection $facts,
        ReconciliationRule $rule,
        ?int $skpdId = null,
        ?int $month = null,
    ): ?array {
        $metadata = $rule->metadata ?? [];
        $left = $metadata['left'] ?? null;
        $right = $metadata['right'] ?? null;

        if (! is_array($left) || ! is_array($right)) {
            return null;
        }

        $leftValue = $this->aggregateSide($facts, $left, $skpdId, $month);
        $rightValue = $this->aggregateSide($facts, $right, $skpdId, $month);

        if ($leftValue === null || $rightValue === null) {
            return null;
        }

        $variance = round($leftValue - $rightValue, 2);

        return [
            'left' => $leftValue,
            'right' => $rightValue,
            'variance' => $variance,
            'status' => abs($variance) <= (float) $rule->tolerance ? 'PASS' : 'VARIANCE',
        ];
    }

    private function aggregateSide(
        Collection $facts,
        array $side,
        ?int $skpdId,
        ?int $month,
    ): ?float {
        $query = $facts;

        if ($skpdId !== null) {
            $query = $query->filter(fn ($fact) => (int) $fact->skpd_id === $skpdId);
        }

        if ($month !== null) {
            $query = $query->filter(fn ($fact) => (int) $fact->month === $month);
        }

        if (isset($side['source_type'])) {
            $query = $query->filter(fn ($fact) => $fact->source_type === $side['source_type']);
        }

        if (isset($side['document_ids'])) {
            $ids = collect($side['document_ids'])->map(fn ($id) => (int) $id);
            $query = $query->filter(fn ($fact) => $ids->contains((int) $fact->source_document_id));
        }

        if (isset($side['canonical_metrics'])) {
            $canonical = app(CanonicalMetricService::class)->normalize($query);
            $metrics = collect($side['canonical_metrics']);
            $value = $canonical->filter(fn ($item) => $metrics->contains($item['canonical_metric']))->sum(fn ($item) => (float) $item['value']);
            return $canonical->filter(fn ($item) => $metrics->contains($item['canonical_metric']))->isEmpty() ? null : round((float) $value, 2);
        }

        if (isset($side['metrics'])) {
            $metrics = collect($side['metrics']);
            $query = $query->filter(fn ($fact) => $metrics->contains($fact->metric));
        }

        if (isset($side['account_prefix'])) {
            $prefix = (string) $side['account_prefix'];
            $query = $query->filter(fn ($fact) => str_starts_with((string) $fact->account_code, $prefix));
        }

        if ($query->isEmpty()) {
            return null;
        }

        return round((float) $query->sum(fn ($fact) => (float) $fact->value), 2);
    }
}
