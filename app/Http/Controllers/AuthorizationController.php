<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuthorizationRecord::query()->with('details')->latest();
        if (!$request->user()->isAdmin()) {
            $query->where('skpd_id', $request->user()->skpd_id);
        }
        return response()->json($query->paginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skpd_id' => ['required','exists:skpds,id'],
            'accounting_year_id' => ['required','exists:accounting_years,id'],
            'number' => ['required','string','max:150'],
            'date' => ['required','date'],
            'type' => ['required','in:pendapatan,belanja'],
            'source_payload' => ['nullable','array'],
            'details' => ['required','array','min:1'],
            'details.*.account_code' => ['required','string','max:100'],
            'details.*.account_name' => ['nullable','string','max:255'],
            'details.*.amount' => ['required','numeric','min:0'],
            'details.*.source_payload' => ['nullable','array'],
        ]);

        $user = $request->user();
        if (!$user->isAdmin() && (int)$data['skpd_id'] !== (int)$user->skpd_id) {
            abort(403, 'SKPD di luar kewenangan pengguna.');
        }

        $record = AuthorizationRecord::create($data);
        $record->details()->createMany($data['details']);
        return response()->json($record->load('details'), 201);
    }
}
