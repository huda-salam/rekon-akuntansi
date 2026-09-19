<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\ReconciliationResult;

class ReconciliationLineageService
{
    public function resolve(ReconciliationResult $result): array
    {
        $ids = collect($result->lineage['financial_fact_ids'] ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $facts = FinancialFact::query()
            ->whereIn('id', $ids)
            ->with([
                'document:id,original_filename,document_type,source_category',
                'record:id,source_document_id,source_row,sheet_name,record_type,payload',
                'skpd:id,code,name',
            ])
            ->get()
            ->keyBy('id');

        return $ids->map(function (int $id) use ($facts) {
            $fact = $facts->get($id);

            if (! $fact) {
                return [
                    'financial_fact_id' => $id,
                    'missing' => true,
                ];
            }

            return [
                'financial_fact_id' => $fact->id,
                'metric' => $fact->metric,
                'value' => (string) $fact->value,
                'unit' => $fact->unit,
                'period' => $fact->period,
                'month' => $fact->month,
                'source_type' => $fact->source_type,
                'transaction_type' => $fact->transaction_type,
                'dimensions' => $fact->dimensions,
                'lineage' => $fact->lineage,
                'source' => [
                    'document_id' => $fact->source_document_id,
                    'filename' => $fact->document?->original_filename,
                    'document_type' => $fact->document?->document_type,
                    'source_category' => $fact->document?->source_category,
                    'record_id' => $fact->source_record_id,
                    'sheet_name' => $fact->record?->sheet_name,
                    'source_row' => $fact->record?->source_row,
                    'record_type' => $fact->record?->record_type,
                    'payload' => $fact->record?->payload,
                ],
            ];
        })->all();
    }
}
