<?php

namespace App\Http\Controllers;

use App\Models\Reconciliation;
use App\Services\FinalizeReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Reconciliation::query()->with(['accountingYear', 'skpd']);

        if (! $user->isAdmin()) {
            $query->where('skpd_id', $user->skpd_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show(Request $request, Reconciliation $reconciliation): JsonResponse
    {
        $this->authorize('view', $reconciliation);

        return response()->json(
            $reconciliation->load(['accountingYear', 'skpd', 'details'])
        );
    }

    public function finalize(
        Request $request,
        Reconciliation $reconciliation,
        FinalizeReconciliationService $service,
    ): JsonResponse {
        $this->authorize('finalize', $reconciliation);

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'signatory_official_name' => ['required', 'string', 'max:255'],
            'signatory_official_nip' => ['nullable', 'string', 'max:30'],
            'signatory_official_position' => ['required', 'string', 'max:255'],
            'document_path' => ['nullable', 'string', 'max:500'],
        ]);

        $ba = $service->execute($reconciliation, $validated);

        return response()->json($ba->load('snapshot'), 201);
    }
}
