<?php

namespace App\Http\Controllers;

use App\Models\AccountingYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingYearController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AccountingYear::orderByDesc('year')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required','integer','min:2000','max:2100','unique:accounting_years,year'],
            'active' => ['sometimes','boolean'],
        ]);

        $year = DB::transaction(function () use ($data) {
            if (!empty($data['active'])) {
                AccountingYear::query()->update(['active' => false]);
            }
            return AccountingYear::create($data);
        });

        return response()->json($year, 201);
    }

    public function activate(AccountingYear $accountingYear): JsonResponse
    {
        DB::transaction(function () use ($accountingYear) {
            AccountingYear::query()->update(['active' => false]);
            $accountingYear->update(['active' => true]);
        });
        return response()->json($accountingYear->fresh());
    }
}
