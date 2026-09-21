<?php

namespace App\Services;

use App\Models\ReconciliationSnapshot;

class ReconciliationBaDocumentService
{
    public function build(ReconciliationSnapshot $snapshot): array
    {
        $snapshot->loadMissing(['results', 'details', 'reconciliationRun']);

        $results = $snapshot->results->sortBy(['category', 'rule_code'])->values();

        $sections = [];
        foreach ($results->groupBy('category') as $category => $items) {
            $sections[] = [
                'category' => $category,
                'title' => $this->categoryTitle($category),
                'controls' => $items->map(fn ($item) => [
                    'rule_code' => $item->rule_code,
                    'rule_name' => $item->rule_name,
                    'period' => $item->period,
                    'status' => $item->status,
                    'expected_value' => $item->expected_value,
                    'actual_value' => $item->actual_value,
                    'variance' => $item->variance,
                    'explanation' => $item->explanation,
                    'review' => $item->review,
                    'lineage' => $item->lineage,
                ])->all(),
            ];
        }

        return [
            'title' => 'BERITA ACARA REKONSILIASI',
            'identity' => [
                'number' => data_get($snapshot->snapshot_payload, 'ba_number'),
                'date' => data_get($snapshot->snapshot_payload, 'ba_date'),
                'year' => $snapshot->accounting_year,
                'skpd_code' => $snapshot->skpd_code,
                'skpd_name' => $snapshot->skpd_name,
                'month' => $snapshot->month,
                'reconciliation_type' => $snapshot->reconciliation_type,
                'sequence' => $snapshot->sequence,
                'period_start' => $snapshot->period_start?->toDateString(),
                'period_end' => $snapshot->period_end?->toDateString(),
            ],
            'signatory' => data_get($snapshot->snapshot_payload, 'signatory', []),
            'summary' => $this->summary($results),
            'sections' => $sections,
            'details' => $snapshot->details->map(fn ($detail) => [
                'source_type' => $detail->source_type,
                'source_reference' => $detail->source_reference,
                'match_status' => $detail->match_status,
                'source_amount' => $detail->source_amount,
                'matched_amount' => $detail->matched_amount,
                'difference_amount' => $detail->difference_amount,
                'notes' => $detail->notes,
                'payload' => $detail->detail_payload,
            ])->all(),
            'snapshot' => [
                'id' => $snapshot->id,
                'hash' => $snapshot->snapshot_hash,
                'finalized_at' => $snapshot->finalized_at?->toIso8601String(),
            ],
        ];
    }

    private function summary($results): array
    {
        $exceptions = $results->whereIn('status', ['VARIANCE', 'INCOMPLETE', 'ERROR']);

        return [
            'total_controls' => $results->count(),
            'pass' => $results->where('status', 'PASS')->count(),
            'variance' => $results->where('status', 'VARIANCE')->count(),
            'incomplete' => $results->where('status', 'INCOMPLETE')->count(),
            'error' => $results->where('status', 'ERROR')->count(),
            'exceptions' => $exceptions->count(),
            'accepted_or_resolved' => $exceptions->filter(
                fn ($result) => in_array($result->review['status'] ?? null, ['RESOLVED', 'ACCEPTED'], true)
            )->count(),
        ];
    }

    private function categoryTitle(?string $category): string
    {
        return match ($category) {
            'revenue' => 'REKONSILIASI PENDAPATAN',
            'expenditure' => 'REKONSILIASI PENGELUARAN',
            'accounting' => 'REKONSILIASI AKUNTANSI',
            default => strtoupper((string) $category),
        };
    }
}
