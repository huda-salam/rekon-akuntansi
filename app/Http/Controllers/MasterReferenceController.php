<?php

namespace App\Http\Controllers;

use App\Models\MasterReference;
use App\Models\AccountingYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'type' => ['nullable', Rule::in([
                'urusan', 'bidang', 'program', 'sub_kegiatan', 'skpd',
                'rekening_belanja', 'rekening_pendapatan', 'rekening_pembiayaan',
            ])],
            'parent_code' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $year = $data['year'] ?? AccountingYear::query()->where('is_active', true)->value('year');

        $query = MasterReference::query()
            ->where('is_active', true)
            ->when($year !== null, fn ($q) => $q->where('year', $year))
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->when(isset($data['parent_code']), fn ($q) => $q->where('parent_code', $data['parent_code']))
            ->when(isset($data['q']), function ($q) use ($data) {
                $term = trim($data['q']);
                $q->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderBy('code');

        return response()->json($query->paginate($data['per_page'] ?? 100));
    }
}
