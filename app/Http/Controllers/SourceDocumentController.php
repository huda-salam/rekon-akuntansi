<?php

namespace App\Http\Controllers;

use App\Models\SourceDocument;
use App\Services\SourceWorkbookImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SourceDocument::query()
            ->with(['year:id,year', 'uploader:id,name', 'importBatch:id,status,file_count,imported_count,failed_count'])
            ->latest('id');

        if (! $request->user()->isAdmin()) {
            $query->where('uploaded_by', $request->user()->id);
        }

        return response()->json($query->paginate(30));
    }

    public function batch(Request $request, SourceWorkbookImportService $service): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $result = $service->executeBatch(
            $data['files'],
            $data['year'],
            $request->user()->id,
            $data['month'] ?? null,
        );

        return response()->json([
            'batch' => $result['batch'],
            'imported' => collect($result['imported'])->map(fn ($document) => $document->load(['year:id,year']))->values(),
            'failed' => $result['failed'],
        ], empty($result['imported']) ? 422 : 201);
    }

    public function store(Request $request, SourceWorkbookImportService $service): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $document = $service->execute($data['file'], $data['year'], $request->user()->id);

        return response()->json(
            $document->load(['year:id,year', 'uploader:id,name']),
            201
        );
    }
}
