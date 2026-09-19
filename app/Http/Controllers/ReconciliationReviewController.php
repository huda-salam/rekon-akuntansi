<?php

namespace App\Http\Controllers;

use App\Models\ReconciliationResult;
use App\Models\ReconciliationReview;
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
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('skpd_id') && $request->user()->isAdmin()) {
            $query->where('skpd_id', $request->integer('skpd_id'));
        }

        return response()->json($query->paginate(50));
    }

    public function review(Request $request, ReconciliationResult $reconciliationResult): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->skpd_id === $reconciliationResult->skpd_id,
            403
        );

        abort_if($reconciliationResult->run->status !== 'completed', 422, 'Reconciliation run is not completed.');

        $data = $request->validate([
            'status' => ['required', Rule::in(['OPEN', 'INVESTIGATING', 'RESOLVED', 'ACCEPTED'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array'],
        ]);

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
}
