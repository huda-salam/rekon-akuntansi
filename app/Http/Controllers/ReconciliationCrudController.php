<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationRecord;
use App\Models\Reconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReconciliationCrudController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Reconciliation::class);

        $data = $request->validate([
            'accounting_year_id' => ['required', 'exists:accounting_years,id'],
            'skpd_id' => ['required', 'exists:skpds,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'reconciliation_type' => ['nullable', Rule::in(['REGULAR', 'ADDENDUM', 'STAGED'])],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'reconciliation_type' => [Rule::in(['REGULAR', 'ADDENDUM', 'STAGED'])],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
            'details' => ['sometimes', 'array'],
            'details.*.source_type' => ['required_with:details', 'in:authorization'],
            'details.*.source_id' => ['required', 'integer', 'exists:authorizations,id'],
            'details.*.match_status' => ['sometimes', 'in:unmatched,matched,partial,exception'],
            'details.*.matched_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
            'details.*.match_payload' => ['nullable', 'array'],
        ]);

        $this->assertActiveYearAndSkpd($data['accounting_year_id'], $data['skpd_id']);
        $details = $this->normalizeDetails($data['details'] ?? [], $data['accounting_year_id'], $data['skpd_id']);

        $reconciliation = DB::transaction(function () use ($data, $details) {
            $reconciliation = Reconciliation::create([
                'accounting_year_id' => $data['accounting_year_id'],
                'skpd_id' => $data['skpd_id'],
                'month' => $data['month'] ?? null,
                'reconciliation_type' => $data['reconciliation_type'] ?? 'REGULAR',
                'sequence' => $data['sequence'] ?? null,
                'status' => 'draft',
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($details !== []) {
                $reconciliation->details()->createMany($details);
            }

            return $reconciliation;
        });

        return response()->json($reconciliation->load(['accountingYear', 'skpd', 'details']), 201);
    }

    public function update(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        $this->authorize('update', $reconciliation);

        $data = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
            'details' => ['sometimes', 'array'],
            'details.*.source_type' => ['required_with:details', 'in:authorization'],
            'details.*.source_id' => ['required', 'integer', 'exists:authorizations,id'],
            'details.*.match_status' => ['sometimes', 'in:unmatched,matched,partial,exception'],
            'details.*.matched_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
            'details.*.match_payload' => ['nullable', 'array'],
        ]);

        $details = null;
        if (array_key_exists('details', $data)) {
            $details = $this->normalizeDetails(
                $data['details'],
                $reconciliation->accounting_year_id,
                $reconciliation->skpd_id,
            );
        }

        DB::transaction(function () use ($reconciliation, $data, $details) {
            $reconciliation->update([
                'month' => $data['month'] ?? $reconciliation->month,
                'reconciliation_type' => $data['reconciliation_type'] ?? $reconciliation->reconciliation_type,
                'sequence' => $data['sequence'] ?? $reconciliation->sequence,
                'period_start' => $data['period_start'] ?? $reconciliation->period_start,
                'period_end' => $data['period_end'] ?? $reconciliation->period_end,
                'notes' => $data['notes'] ?? $reconciliation->notes,
                'status' => $details !== null && $details !== [] ? 'in_review' : $reconciliation->status,
            ]);

            if ($details !== null) {
                $reconciliation->details()->delete();
                if ($details !== []) {
                    $reconciliation->details()->createMany($details);
                }
            }
        });

        return response()->json($reconciliation->fresh()->load(['accountingYear', 'skpd', 'details']));
    }

    private function assertActiveYearAndSkpd(int $yearId, int $skpdId): void
    {
        $yearIsActive = DB::table('accounting_years')
            ->where('id', $yearId)
            ->where('is_active', true)
            ->exists();

        if (! $yearIsActive) {
            throw ValidationException::withMessages([
                'accounting_year_id' => 'Rekonsiliasi baru hanya dapat dibuat untuk tahun anggaran aktif.',
            ]);
        }

        $skpdIsActive = DB::table('skpds')
            ->where('id', $skpdId)
            ->where('is_active', true)
            ->exists();

        if (! $skpdIsActive) {
            throw ValidationException::withMessages([
                'skpd_id' => 'SKPD tidak aktif atau tidak ditemukan.',
            ]);
        }
    }

    private function normalizeDetails(array $details, int $yearId, int $skpdId): array
    {
        if ($details === []) {
            return [];
        }

        $sourceIds = array_map(static fn (array $detail): int => (int) $detail['source_id'], $details);
        if (count($sourceIds) !== count(array_unique($sourceIds))) {
            throw ValidationException::withMessages([
                'details' => 'Sumber pengesahan yang sama tidak boleh dicantumkan lebih dari satu kali.',
            ]);
        }

        $sources = AuthorizationRecord::query()
            ->whereIn('id', $sourceIds)
            ->where('accounting_year_id', $yearId)
            ->where('skpd_id', $skpdId)
            ->with('details:id,authorization_id,amount')
            ->get()
            ->keyBy('id');

        if ($sources->count() !== count($sourceIds)) {
            throw ValidationException::withMessages([
                'details' => 'Seluruh sumber pengesahan harus berasal dari tahun dan SKPD rekonsiliasi.',
            ]);
        }

        return array_map(function (array $detail) use ($sources): array {
            $source = $sources->get((int) $detail['source_id']);
            $sourceAmount = (float) $source->details->sum(fn ($item) => (float) $item->amount);
            $matchedAmount = (float) ($detail['matched_amount'] ?? 0);
            $status = $detail['match_status'] ?? 'unmatched';

            if ($matchedAmount > $sourceAmount) {
                throw ValidationException::withMessages([
                    'details' => "Nilai cocok untuk sumber #{$source->id} tidak boleh melebihi nilai sumber.",
                ]);
            }

            $this->assertMatchConsistency($status, $sourceAmount, $matchedAmount);

            return [
                'source_type' => 'authorization',
                'source_id' => $source->id,
                'match_status' => $status,
                'source_amount' => $sourceAmount,
                'matched_amount' => $matchedAmount,
                'difference_amount' => $sourceAmount - $matchedAmount,
                'notes' => $detail['notes'] ?? null,
                'match_payload' => $detail['match_payload'] ?? null,
            ];
        }, $details);
    }

    private function assertMatchConsistency(string $status, float $sourceAmount, float $matchedAmount): void
    {
        $epsilon = 0.005;

        if ($status === 'unmatched' && abs($matchedAmount) > $epsilon) {
            throw ValidationException::withMessages([
                'details' => 'Status belum cocok harus memiliki nilai cocok sebesar 0.',
            ]);
        }

        if ($status === 'matched' && abs($sourceAmount - $matchedAmount) > $epsilon) {
            throw ValidationException::withMessages([
                'details' => 'Status cocok harus memiliki nilai cocok sama dengan nilai sumber.',
            ]);
        }

        if ($status === 'partial' && ($matchedAmount <= $epsilon || $matchedAmount >= $sourceAmount - $epsilon)) {
            throw ValidationException::withMessages([
                'details' => 'Status sebagian harus memiliki nilai cocok lebih dari 0 dan kurang dari nilai sumber.',
            ]);
        }
    }
}
