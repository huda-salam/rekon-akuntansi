<?php

namespace App\Http\Controllers;

use App\Models\AccountingYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingYearAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(AccountingYear::orderByDesc('year')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:accounting_years,year'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $year = DB::transaction(function () use ($data) {
            $hasActive = AccountingYear::query()->where('is_active', true)->exists();
            $makeActive = ! $hasActive || ! empty($data['is_active']);

            if ($makeActive) {
                AccountingYear::query()->update(['is_active' => false]);
            }

            return AccountingYear::create([
                'year' => $data['year'],
                'is_active' => $makeActive,
            ]);
        });

        return response()->json($year, 201);
    }

    public function activate(Request $request, AccountingYear $accountingYear): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        DB::transaction(function () use ($accountingYear) {
            AccountingYear::query()->update(['is_active' => false]);
            $accountingYear->update(['is_active' => true]);
        });

        return response()->json($accountingYear->fresh());
    }
}
