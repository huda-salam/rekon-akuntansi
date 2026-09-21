<?php

namespace App\Http\Controllers;

use App\Models\ReconciliationResult;
use App\Models\ReconciliationReview;
use App\Services\ReconciliationLineageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReconciliationReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->skpd_id !== null, 403);

        $query = ReconciliationResult::query()
            ->with([
                'rule:id,code,name,category,tolerance',
                'skpd:id,code,name',
                'sourceDocument:id,original_filename,document_type',
                'reviews.reviewer:id,name,email',
            ])
            ->whereIn('status', ['VARIANCE', 'INCOMPLETE', 'ERROR'])
            ->latest('id');

        if (! $request->user()->isAdmin()) {
            $query->where('skpd_id', $request->user()->skpd_id);
        }

        if ($request->filled('status')) {
            $query->whereHas('reviews', fn ($review) => $review->where('status', $request->string('status')));
        }

        if ($request->filled('skpd_id') && $request->user()->isAdmin()) {
            $query->where('skpd_id', $request->integer('skpd_id'));
        }

        if ($request->filled('month')) {
            $query->where('month', $request->integer('month'));
        }

        return response()->json($query->paginate(50));
    }

    public function show(
        Request $request,
        ReconciliationResult $reconciliationResult,
        ReconciliationLineageService $lineageService,
    ): JsonResponse {
        $this->assertCanAccess($request, $reconciliationResult);

        return response()->json([
            'result' => $reconciliationResult->load([
                'run.year:id,year',
                'rule:id,code,name,category,tolerance,expression,input_metrics',
                'skpd:id,code,name',
                'sourceDocument:id,original_filename,document_type,source_category',
                'reviews.reviewer:id,name,email',
            ]),
            'lineage' => $lineageService->resolve($reconciliationResult),
        ]);
    }

    public function review(Request $request, ReconciliationResult $reconciliationResult): JsonResponse
    {
        $user = $request->user();

        $this->assertCanAccess($request, $reconciliationResult);

        abort_if($reconciliationResult->run->status !== 'completed', 422, 'Reconciliation run is not completed.');

        $data = $request->validate([
            'status' => ['required', Rule::in(['OPEN', 'INVESTIGATING', 'RESOLVED', 'ACCEPTED'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array'],
        ]);

        $latest = $reconciliationResult->reviews()->latest('id')->first();
        $allowed = [
            null => ['OPEN', 'INVESTIGATING'],
            'OPEN' => ['OPEN', 'INVESTIGATING'],
            'INVESTIGATING' => ['INVESTIGATING', 'RESOLVED'],
            'RESOLVED' => ['RESOLVED', 'ACCEPTED'],
            'ACCEPTED' => ['ACCEPTED'],
        ];

        $currentStatus = $latest?->status;
        abort_unless(
            in_array($data['status'], $allowed[$currentStatus] ?? [], true),
            422,
            'Transisi status review tidak valid.'
        );

        if (in_array($data['status'], ['RESOLVED', 'ACCEPTED'], true)) {
            abort_if(blank($data['note'] ?? null), 422, 'Catatan wajib diisi untuk status RESOLVED atau ACCEPTED.');
            abort_if(empty($data['evidence'] ?? null), 422, 'Evidence wajib diisi untuk status RESOLVED atau ACCEPTED.');
        }

        $review = ReconciliationReview::create([
            'reconciliation_result_id' => $reconciliationResult->id,
            'reviewed_by' => $user->id,
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'evidence' => $data['evidence'] ?? null,
            'reviewed_at' => now(),
        ]);

        return response()->json($review->load('reviewer:id,name,email'), 201);
    }

    private function assertCanAccess(Request $request, ReconciliationResult $result): void
    {
        abort_unless(
            $request->user()->isAdmin() || $request->user()->skpd_id === $result->skpd_id,
            403
        );
    }
}
