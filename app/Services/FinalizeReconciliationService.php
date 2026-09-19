<?php

namespace App\Services;

use App\Models\BeritaAcara;
use App\Models\Reconciliation;
use App\Models\ReconciliationSnapshot;
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
            $snapshotData = [
                'reconciliation_id' => $reconciliation->id,
                'accounting_year' => $reconciliation->accountingYear->year,
                'skpd_code' => $reconciliation->skpd->code,
                'skpd_name' => $reconciliation->skpd->name,
                'month' => $reconciliation->month,
                'reconciliation_type' => $reconciliation->reconciliation_type,
                'sequence' => $reconciliation->sequence,
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
                'accounting_year' => $reconciliation->accountingYear->year,
                'skpd_code' => $reconciliation->skpd->code,
                'skpd_name' => $reconciliation->skpd->name,
                'period_start' => $reconciliation->period_start,
                'period_end' => $reconciliation->period_end,
                'month' => $reconciliation->month,
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
