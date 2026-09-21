<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use App\Models\SourceDocument;
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

        if ($skpdId !== null && ($side['skpd_mode'] ?? 'exact') !== 'any') {
            $query = $query->filter(fn ($fact) => (int) $fact->skpd_id === $skpdId);
        }

        $query = $this->filterPeriod($query, $side['period_mode'] ?? 'monthly', $month);

        if (isset($side['source_type'])) {
            $query = $query->filter(fn ($fact) => $fact->source_type === $side['source_type']);
        }

        if (isset($side['report_scope'])) {
            $documentIds = $query->pluck('source_document_id')->filter()->unique()->values();
            if ($documentIds->isEmpty()) {
                return null;
            }

            $scopes = SourceDocument::query()
                ->whereIn('id', $documentIds)
                ->get(['id', 'metadata'])
                ->mapWithKeys(fn ($document) => [(int) $document->id => $document->metadata['report_scope'] ?? null]);

            $allowed = collect($side['report_scope']);
            $query = $query->filter(fn ($fact) => $allowed->contains($scopes->get((int) $fact->source_document_id)));
        }

        if (isset($side['document_ids'])) {
            $ids = collect($side['document_ids'])->map(fn ($id) => (int) $id);
            $query = $query->filter(fn ($fact) => $ids->contains((int) $fact->source_document_id));
        }

        if (isset($side['canonical_metrics'])) {
            $canonical = app(CanonicalMetricService::class)->normalize($query);
            $metrics = collect($side['canonical_metrics']);
            $selected = $canonical->filter(fn ($item) => $metrics->contains($item['canonical_metric']));

            return $selected->isEmpty()
                ? null
                : round((float) $selected->sum(fn ($item) => (float) $item['value']), 2);
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

    /**
     * Period semantics are explicit because a reconciliation period is not
     * necessarily the same thing as the source file's stored month.
     *
     * MONTHLY: source facts belong to the requested month.
     * YEAR_TO_DATE: monthly facts from January through the requested month.
     * ANNUAL_SNAPSHOT: annual/closing facts with no monthly period.
     * ANY: do not constrain the period.
     * PRIOR_YEAR: reserved for prior-year comparison; requires a caller-provided
     * prior-year fact set and therefore behaves as ANY at this layer.
     * OPENING_BALANCE: facts without a monthly period (opening snapshot).
     */
    private function filterPeriod(Collection $facts, string $mode, ?int $month): Collection
    {
        return match (strtoupper($mode)) {
            'ANY' => $facts,
            'ANNUAL_SNAPSHOT' => $facts->filter(fn ($fact) => $fact->month === null),
            'OPENING_BALANCE' => $facts->filter(fn ($fact) => $fact->month === null),
            'MONTHLY' => $month === null
                ? $facts
                : $facts->filter(fn ($fact) => (int) $fact->month === $month),
            'YEAR_TO_DATE' => $month === null
                ? $facts
                : $facts->filter(fn ($fact) => $fact->month !== null && (int) $fact->month <= $month),
            'PRIOR_YEAR' => $facts,
            default => throw new \InvalidArgumentException("Unsupported cross-source period mode [{$mode}]."),
        };
    }
}
