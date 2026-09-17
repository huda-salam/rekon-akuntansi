<?php

namespace App\Http\Controllers;

use App\Models\Official;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficialAdminController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(request()->user()?->isAdmin(), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = Official::query()
            ->with('skpd:id,code,name')
            ->orderBy('name');

        if ($request->filled('skpd_id')) {
            $query->where('skpd_id', $request->integer('skpd_id'));
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'skpd_id' => ['required', 'integer', 'exists:skpds,id'],
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'position' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(Official::create($data)->load('skpd:id,code,name'), 201);
    }

    public function update(Request $request, Official $official): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'skpd_id' => ['sometimes', 'required', 'integer', 'exists:skpds,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nip' => ['sometimes', 'nullable', 'string', 'max:30'],
            'position' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $official->update($data);

        return response()->json($official->fresh()->load('skpd:id,code,name'));
    }
}
