<?php

namespace App\Services;

use App\Models\BeritaAcara;
use App\Models\Reconciliation;
use App\Models\ReconciliationSnapshot;
use App\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeReconciliationService
{
    public function execute(Reconciliation $reconciliation, array $ba): BeritaAcara
    {
        return DB::transaction(function () use ($reconciliation, $ba) {
            $reconciliation->loadMissing(['accountingYear', 'skpd', 'details']);

            if ($reconciliation->status === 'finalized' || $reconciliation->snapshot()->exists()) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Rekonsiliasi sudah difinalisasi dan tidak dapat diubah.',
                ]);
            }

            if ($reconciliation->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Rekonsiliasi harus berada pada status in_review sebelum difinalisasi.',
                ]);
            }

            if ($reconciliation->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Rekonsiliasi tidak dapat difinalisasi tanpa detail pencocokan.',
                ]);
            }

            $finalizedAt = now();
            $run = null;
            $runResults = [];

            if (! empty($ba['reconciliation_run_id'])) {
                $run = ReconciliationRun::query()
                    ->with(['results.rule', 'results.skpd'])
                    ->findOrFail($ba['reconciliation_run_id']);

                if ((int) $run->accounting_year_id !== (int) $reconciliation->accounting_year_id
                    || ($run->month !== null && (int) $run->month !== (int) $reconciliation->month)) {
                    throw ValidationException::withMessages([
                        'reconciliation_run_id' => 'Run rekonsiliasi tidak sesuai tahun/periode BA.',
                    ]);
                }

                if ($run->status !== 'completed') {
                    throw ValidationException::withMessages([
                        'reconciliation_run_id' => 'Run rekonsiliasi harus berstatus completed sebelum menjadi bagian dari BA snapshot.',
                    ]);
                }

                $runResultsForSkpd = $run->results
                    ->filter(fn ($result) => $result->skpd_id === null || (int) $result->skpd_id === (int) $reconciliation->skpd_id);

                if ($runResultsForSkpd->isEmpty()) {
                    throw ValidationException::withMessages([
                        'reconciliation_run_id' => 'Run rekonsiliasi tidak memiliki hasil untuk SKPD pada BA ini.',
                    ]);
                }

                $unresolved = $runResultsForSkpd->filter(function ($result) {
                    if ($result->status === 'PASS') {
                        return false;
                    }

                    return ! $result->reviews()
                        ->whereIn('status', ['RESOLVED', 'ACCEPTED'])
                        ->exists();
                });

                if ($unresolved->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'reconciliation_run_id' => 'Masih terdapat hasil VARIANCE/INCOMPLETE/ERROR yang belum memiliki review RESOLVED atau ACCEPTED.',
                    ]);
                }

                $runResults = $runResultsForSkpd
                    ->map(fn ($result) => [
                    'id' => $result->id,
                    'rule_id' => $result->reconciliation_rule_id,
                    'rule_code' => $result->rule?->code,
                    'rule_name' => $result->rule?->name,
                    'category' => $result->rule?->category,
                    'skpd_id' => $result->skpd_id,
                    'month' => $result->month,
                    'period' => $result->period,
                    'status' => $result->status,
                    'expected_value' => $result->expected_value !== null ? (string) $result->expected_value : null,
                    'actual_value' => $result->actual_value !== null ? (string) $result->actual_value : null,
                    'variance' => $result->variance !== null ? (string) $result->variance : null,
                    'inputs' => $result->inputs,
                    'lineage' => $result->lineage,
                    'explanation' => $result->explanation,
                ])->values()->all();
            }

            $snapshotData = [
                'reconciliation_id' => $reconciliation->id,
                'reconciliation_run_id' => $run?->id,
                'accounting_year' => $reconciliation->accountingYear->year,
                'skpd_code' => $reconciliation->skpd->code,
                'skpd_name' => $reconciliation->skpd->name,
                'month' => $reconciliation->month,
                'reconciliation_type' => $reconciliation->reconciliation_type,
                'sequence' => $reconciliation->sequence,
                'period_start' => $reconciliation->period_start?->toDateString(),
                'period_end' => $reconciliation->period_end?->toDateString(),
                'notes' => $reconciliation->notes,
                'finalized_at' => $finalizedAt->toIso8601String(),
                'ba_number' => $ba['number'],
                'ba_date' => $ba['date'],
                'signatory' => [
                    'name' => $ba['signatory_official_name'],
                    'nip' => $ba['signatory_official_nip'] ?? null,
                    'position' => $ba['signatory_official_position'],
                ],
                'run_summary' => $run?->summary,
                'run_results' => $runResults,
                'details' => $reconciliation->details->map(function ($detail) {
                    return [
                        'source_type' => $detail->source_type,
                        'source_id' => $detail->source_id,
                        'match_status' => $detail->match_status,
                        'source_amount' => (string) $detail->source_amount,
                        'matched_amount' => (string) $detail->matched_amount,
                        'difference_amount' => (string) $detail->difference_amount,
                        'notes' => $detail->notes,
                        'match_payload' => $detail->match_payload,
                    ];
                })->values()->all(),
            ];

            $json = json_encode($snapshotData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $snapshot = ReconciliationSnapshot::create([
                'reconciliation_id' => $reconciliation->id,
                'reconciliation_run_id' => $run?->id,
                'accounting_year' => $reconciliation->accountingYear->year,
                'skpd_code' => $reconciliation->skpd->code,
                'skpd_name' => $reconciliation->skpd->name,
                'period_start' => $reconciliation->period_start,
                'period_end' => $reconciliation->period_end,
                'month' => $reconciliation->month,
                'reconciliation_type' => $reconciliation->reconciliation_type,
                'sequence' => $reconciliation->sequence,
                'notes' => $reconciliation->notes,
                'finalized_at' => $finalizedAt,
                'snapshot_hash' => hash('sha256', $json),
                'snapshot_payload' => $snapshotData,
            ]);

            foreach ($snapshotData['details'] as $detail) {
                $snapshot->details()->create([
                    'source_type' => $detail['source_type'],
                    'source_reference' => isset($detail['source_id']) ? (string) $detail['source_id'] : null,
                    'match_status' => $detail['match_status'],
                    'source_amount' => $detail['source_amount'],
                    'matched_amount' => $detail['matched_amount'],
                    'difference_amount' => $detail['difference_amount'],
                    'notes' => $detail['notes'],
                    'detail_payload' => $detail,
                ]);
            }

            $reconciliation->update([
                'status' => 'finalized',
                'finalized_at' => $finalizedAt,
            ]);

            return BeritaAcara::create([
                'reconciliation_id' => $reconciliation->id,
                'snapshot_id' => $snapshot->id,
                'number' => $ba['number'],
                'date' => $ba['date'],
                'signatory_official_name' => $ba['signatory_official_name'],
                'signatory_official_nip' => $ba['signatory_official_nip'] ?? null,
                'signatory_official_position' => $ba['signatory_official_position'],
                'document_path' => $ba['document_path'] ?? null,
            ]);
        });
    }
}
