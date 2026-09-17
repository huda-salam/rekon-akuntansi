<?php

namespace App\Http\Controllers;

use App\Services\ImportMasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataImportController extends Controller
{
    public function store(Request $request, ImportMasterDataService $service): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'source_year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $result = $service->execute($validated['file'], $validated['source_year']);

        return response()->json([
            'message' => 'Master data berhasil diimpor.',
            ...$result,
        ]);
    }
}
