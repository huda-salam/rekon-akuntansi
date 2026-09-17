<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorizationSourceController extends Controller
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
            'skpd_id' => ['required', 'exists:skpds,id'],
            'accounting_year_id' => ['required', 'exists:accounting_years,id'],
            'authorization_number' => ['nullable', 'string', 'max:100'],
            'authorization_date' => ['nullable', 'date'],
            'type' => ['required', 'in:pendapatan,belanja'],
            'description' => ['nullable', 'string', 'max:255'],
            'source_payload' => ['nullable', 'array'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.account_code' => ['nullable', 'string', 'max:100'],
            'details.*.account_name' => ['nullable', 'string', 'max:255'],
            'details.*.description' => ['nullable', 'string', 'max:255'],
            'details.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'details.*.unit' => ['nullable', 'string', 'max:50'],
            'details.*.amount' => ['required', 'numeric', 'min:0'],
            'details.*.source_reference' => ['nullable', 'string', 'max:150'],
            'details.*.source_payload' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        if (!$user->isAdmin() && (int) $data['skpd_id'] !== (int) $user->skpd_id) {
            abort(403, 'SKPD di luar kewenangan pengguna.');
        }

        $record = DB::transaction(function () use ($data) {
            $details = $data['details'];
            unset($data['details']);

            $data['total_amount'] = collect($details)->sum(fn ($detail) => (float) $detail['amount']);

            $record = AuthorizationRecord::create($data);
            $record->details()->createMany($details);

            return $record;
        });

        return response()->json($record->load('details'), 201);
    }
}
