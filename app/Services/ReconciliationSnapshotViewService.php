<?php

namespace App\Services;

use App\Models\ReconciliationSnapshot;
use Illuminate\Support\Collection;

class ReconciliationSnapshotViewService
{
    public function build(ReconciliationSnapshot $snapshot): array
    {
        $results = $snapshot->results()->orderBy('id')->get();

        return [
            'snapshot' => [
                'id' => $snapshot->id,
                'reconciliation_id' => $snapshot->reconciliation_id,
                'reconciliation_run_id' => $snapshot->reconciliation_run_id,
                'accounting_year' => $snapshot->accounting_year,
                'skpd_code' => $snapshot->skpd_code,
                'skpd_name' => $snapshot->skpd_name,
                'month' => $snapshot->month,
                'reconciliation_type' => $snapshot->reconciliation_type,
                'sequence' => $snapshot->sequence,
                'period_start' => $snapshot->period_start?->toDateString(),
                'period_end' => $snapshot->period_end?->toDateString(),
                'notes' => $snapshot->notes,
                'finalized_at' => $snapshot->finalized_at?->toIso8601String(),
                'snapshot_hash' => $snapshot->snapshot_hash,
            ],
            'summary' => $this->summary($results),
            'results' => $results->map(fn ($result) => [
                'id' => $result->id,
                'source_result_id' => $result->source_result_id,
                'rule_code' => $result->rule_code,
                'rule_name' => $result->rule_name,
                'category' => $result->category,
                'period' => $result->period,
                'status' => $result->status,
                'expected_value' => $result->expected_value,
                'actual_value' => $result->actual_value,
                'variance' => $result->variance,
                'explanation' => $result->explanation,
                'inputs' => $result->inputs,
                'lineage' => $result->lineage,
                'review' => $result->review,
            ])->values()->all(),
        ];
    }

    private function summary(Collection $results): array
    {
        $exceptions = $results->whereIn('status', ['VARIANCE', 'INCOMPLETE', 'ERROR']);
        $reviews = $exceptions->map(fn ($result) => $result->review['status'] ?? null);

        return [
            'total_controls' => $results->count(),
            'pass' => $results->where('status', 'PASS')->count(),
            'variance' => $results->where('status', 'VARIANCE')->count(),
            'incomplete' => $results->where('status', 'INCOMPLETE')->count(),
            'error' => $results->where('status', 'ERROR')->count(),
            'resolved_or_accepted' => $reviews->filter(fn ($status) => in_array($status, ['RESOLVED', 'ACCEPTED'], true))->count(),
            'unresolved' => $exceptions->filter(fn ($result) => ! in_array($result->review['status'] ?? null, ['RESOLVED', 'ACCEPTED'], true))->count(),
            'total_absolute_variance' => number_format($exceptions->sum(fn ($result) => abs((float) ($result->variance ?? 0))), 2, '.', ''),
        ];
    }
}
