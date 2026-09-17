<?php

namespace App\Http\Controllers;

use App\Models\Skpd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkpdAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Skpd::orderBy('code')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:skpds,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(Skpd::create($data), 201);
    }

    public function update(Request $request, Skpd $skpd): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:skpds,code,' . $skpd->id],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $skpd->update($data);

        return response()->json($skpd->fresh());
    }
}
