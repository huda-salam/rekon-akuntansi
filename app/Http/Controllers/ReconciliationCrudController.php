<?php

namespace App\Http\Controllers;

use App\Models\AccountingYear;
use App\Models\Reconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReconciliationCrudController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accounting_year_id' => ['required', 'exists:accounting_years,id'],
            'skpd_id' => ['required', 'exists:skpds,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
            'details' => ['sometimes', 'array'],
            'details.*.source_type' => ['required_with:details', 'string', 'max:40'],
            'details.*.source_id' => ['nullable', 'integer'],
            'details.*.match_status' => ['sometimes', 'string', 'max:30'],
            'details.*.source_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.matched_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.difference_amount' => ['sometimes', 'numeric'],
            'details.*.notes' => ['nullable', 'string'],
            'details.*.match_payload' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        if (!$user->isAdmin() && (int) $data['skpd_id'] !== (int) $user->skpd_id) {
            abort(403, 'SKPD di luar kewenangan pengguna.');
        }

        $year = AccountingYear::findOrFail($data['accounting_year_id']);

        $reconciliation = DB::transaction(function () use ($data, $year) {
            $reconciliation = Reconciliation::create([
                'accounting_year_id' => $year->id,
                'skpd_id' => $data['skpd_id'],
                'status' => 'draft',
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['details'] ?? [] as $detail) {
                $reconciliation->details()->create([
                    'source_type' => $detail['source_type'],
                    'source_id' => $detail['source_id'] ?? null,
                    'match_status' => $detail['match_status'] ?? 'unmatched',
                    'source_amount' => $detail['source_amount'] ?? 0,
                    'matched_amount' => $detail['matched_amount'] ?? 0,
                    'difference_amount' => $detail['difference_amount'] ?? 0,
                    'notes' => $detail['notes'] ?? null,
                    'match_payload' => $detail['match_payload'] ?? null,
                ]);
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
            'details.*.id' => ['nullable', 'integer'],
            'details.*.source_type' => ['required_with:details', 'string', 'max:40'],
            'details.*.source_id' => ['nullable', 'integer'],
            'details.*.match_status' => ['sometimes', 'string', 'max:30'],
            'details.*.source_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.matched_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.difference_amount' => ['sometimes', 'numeric'],
            'details.*.notes' => ['nullable', 'string'],
            'details.*.match_payload' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($reconciliation, $data) {
            $reconciliation->update([
                'period_start' => $data['period_start'] ?? $reconciliation->period_start,
                'period_end' => $data['period_end'] ?? $reconciliation->period_end,
                'notes' => $data['notes'] ?? $reconciliation->notes,
            ]);

            if (array_key_exists('details', $data)) {
                $reconciliation->details()->delete();
                foreach ($data['details'] as $detail) {
                    $reconciliation->details()->create([
                        'source_type' => $detail['source_type'],
                        'source_id' => $detail['source_id'] ?? null,
                        'match_status' => $detail['match_status'] ?? 'unmatched',
                        'source_amount' => $detail['source_amount'] ?? 0,
                        'matched_amount' => $detail['matched_amount'] ?? 0,
                        'difference_amount' => $detail['difference_amount'] ?? 0,
                        'notes' => $detail['notes'] ?? null,
                        'match_payload' => $detail['match_payload'] ?? null,
                    ]);
                }
            }
        });

        return response()->json($reconciliation->fresh()->load(['accountingYear', 'skpd', 'details']));
    }
}
