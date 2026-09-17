<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationRecord;
use App\Models\MasterReference;
use App\Models\AccountingYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthorizationSourceController extends Controller
{
    private const ACCOUNT_TYPES = [
        'pendapatan' => 'rekening_pendapatan',
        'belanja' => 'rekening_belanja',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = AuthorizationRecord::query()
            ->with(['details', 'accountingYear', 'skpd'])
            ->latest();

        if (! $request->user()->isAdmin()) {
            $query->where('skpd_id', $request->user()->skpd_id);
        }

        return response()->json($query->paginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Input sumber pengesahan hanya dapat dilakukan oleh Admin/SKPKD.');
        }

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

        $this->assertActiveYearAndSkpd($data['accounting_year_id'], $data['skpd_id']);
        $data['details'] = $this->canonicalizeAccountDetails($data['details'], $data['accounting_year_id'], $data['type']);

        $record = DB::transaction(function () use ($data) {
            $details = $data['details'];
            unset($data['details']);

            $data['total_amount'] = collect($details)->sum(fn ($detail) => (float) $detail['amount']);

            $record = AuthorizationRecord::create($data);
            $record->details()->createMany($details);

            return $record;
        });

        return response()->json($record->load(['details', 'accountingYear', 'skpd']), 201);
    }

    private function assertActiveYearAndSkpd(int $yearId, int $skpdId): void
    {
        if (! DB::table('accounting_years')->whereKey($yearId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'accounting_year_id' => 'Sumber pengesahan baru hanya dapat dicatat pada tahun anggaran aktif.',
            ]);
        }

        if (! DB::table('skpds')->whereKey($skpdId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'skpd_id' => 'SKPD tidak aktif atau tidak ditemukan.',
            ]);
        }
    }

    private function canonicalizeAccountDetails(array $details, int $yearId, string $sourceType): array
    {
        $year = AccountingYear::query()->findOrFail($yearId)->year;
        $accountType = self::ACCOUNT_TYPES[$sourceType];

        foreach ($details as $index => &$detail) {
            $code = trim((string) ($detail['account_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $master = MasterReference::query()
                ->where('year', $year)
                ->where('type', $accountType)
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (! $master) {
                throw ValidationException::withMessages([
                    "details.{$index}.account_code" => "Rekening {$code} tidak ditemukan pada master {$accountType} tahun anggaran {$year}.",
                ]);
            }

            $detail['account_code'] = $master->code;
            $detail['account_name'] = $master->description;
        }

        return $details;
    }
}
