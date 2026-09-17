<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\MasterReference;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationAccountValidationTest extends TestCase
{
    use RefreshDatabase;

    private function activeYear(): AccountingYear
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => false]);
        DB::table('accounting_years')->whereKey($year->id)->update(['is_active' => 1]);

        return $year->fresh();
    }

    public function test_account_code_must_exist_in_year_master_and_type(): void
    {
        $year = $this->activeYear();
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'account-validation@example.test', 'password' => 'password', 'role' => 'admin']);

        MasterReference::create([
            'year' => 2026,
            'type' => 'rekening_pendapatan',
            'code' => '4.01.01.01',
            'description' => 'Pendapatan Pajak',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/authorizations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'details' => [[
                'account_code' => '4.99.99.99',
                'account_name' => 'Dipalsukan',
                'amount' => 100000,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('details.0.account_code');
    }

    public function test_account_name_is_canonicalized_from_year_master(): void
    {
        $year = $this->activeYear();
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'account-canonical@example.test', 'password' => 'password', 'role' => 'admin']);

        MasterReference::create([
            'year' => 2026,
            'type' => 'rekening_pendapatan',
            'code' => '4.01.01.01',
            'description' => 'Pendapatan Pajak',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/authorizations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'details' => [[
                'account_code' => '4.01.01.01',
                'account_name' => 'Nama dari client tidak dipercaya',
                'amount' => 100000,
            ]],
        ])->assertCreated();

        $response->assertJsonPath('details.0.account_code', '4.01.01.01');
        $response->assertJsonPath('details.0.account_name', 'Pendapatan Pajak');
    }
}
