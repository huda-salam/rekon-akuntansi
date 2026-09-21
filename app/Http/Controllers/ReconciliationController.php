<?php

namespace App\Http\Controllers;

use App\Models\Reconciliation;
use App\Models\ReconciliationRun;
use App\Services\FinalizeReconciliationService;
use App\Services\ReconciliationRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Reconciliation::class);

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

    public function runs(Request $request, ReconciliationRunService $service): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'category' => ['nullable', Rule::in(['expenditure', 'revenue'])],
        ]);

        $year = \App\Models\AccountingYear::query()
            ->where('year', $data['year'])
            ->firstOrFail();

        abort_unless($request->user()->isAdmin() || $request->user()->skpd_id !== null, 403);

        return response()->json([
            'data' => $service->listForUser($request->user(), $year->id, $data['month'] ?? null, $data['category'] ?? null),
        ]);
    }

    public function run(
        Request $request,
        ReconciliationRunService $service,
    ): JsonResponse {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'category' => ['nullable', Rule::in(['expenditure', 'revenue'])],
            'source_document_ids' => ['sometimes', 'array'],
            'source_document_ids.*' => ['integer', 'distinct'],
        ]);

        $year = \App\Models\AccountingYear::query()
            ->where('year', $data['year'])
            ->firstOrFail();

        try {
            $run = $service->execute(
                $year->id,
                $request->user()->id,
                $data['source_document_ids'] ?? [],
                ['requested_year' => $data['year'], 'requested_month' => $data['month'] ?? null, 'category' => $data['category'] ?? 'expenditure'],
                $data['month'] ?? null,
                $data['category'] ?? 'expenditure',
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($run, 201);
    }

    public function runShow(Request $request, ReconciliationRun $reconciliationRun): JsonResponse
    {
        abort_unless(
            $request->user()->isAdmin()
            || $reconciliationRun->results()->where('skpd_id', $request->user()->skpd_id)->exists(),
            403
        );

        return response()->json(
            $reconciliationRun->load([
                'year:id,year',
                'starter:id,name,email',
                'results' => fn ($query) => $query->with([
                    'rule:id,code,name,category,tolerance',
                    'skpd:id,code,name',
                    'sourceDocument:id,original_filename,document_type',
                ])->orderBy('id'),
            ])
        );
    }

    public function finalize(
        Request $request,
        Reconciliation $reconciliation,
        FinalizeReconciliationService $service,
    ): JsonResponse {
        $this->authorize('finalize', $reconciliation);

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:150', Rule::unique('berita_acaras', 'number')],
            'date' => ['required', 'date'],
            'signatory_official_name' => ['required', 'string', 'max:255'],
            'signatory_official_nip' => ['nullable', 'string', 'max:30'],
            'signatory_official_position' => ['required', 'string', 'max:255'],
            'document_path' => ['nullable', 'string', 'max:500'],
            'reconciliation_run_id' => ['nullable', 'integer', 'exists:reconciliation_runs,id'],
        ]);

        $ba = $service->execute($reconciliation, $validated);

        return response()->json($ba->load([
            'snapshot',
            'reconciliation.accountingYear:id,year',
            'reconciliation.skpd:id,code,name',
        ]), 201);
    }
}
