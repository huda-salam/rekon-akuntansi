<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\MasterReference;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationSourceTest extends TestCase
{
    use RefreshDatabase;

    private function activeYear(): AccountingYear
    {
        return AccountingYear::create(['year' => 2026, 'is_active' => true]);
    }

    public function test_skpd_user_can_view_only_own_sources(): void
    {
        $year = $this->activeYear();
        $own = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $other = Skpd::create(['code' => 'SKPD-B', 'name' => 'SKPD B', 'is_active' => true]);
        $user = User::create(['name' => 'User SKPD A', 'email' => 'a@example.test', 'password' => 'password', 'role' => 'skpd', 'skpd_id' => $own->id]);

        \App\Models\AuthorizationRecord::create(['accounting_year_id' => $year->id, 'skpd_id' => $other->id, 'type' => 'pendapatan', 'total_amount' => 100]);

        $this->actingAs($user, 'sanctum')->getJson('/api/authorizations')->assertOk()->assertJsonPath('data', []);
    }

    public function test_skpd_user_cannot_input_authorization_source(): void
    {
        $year = $this->activeYear();
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $user = User::create(['name' => 'User SKPD A', 'email' => 'a@example.test', 'password' => 'password', 'role' => 'skpd', 'skpd_id' => $skpd->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/authorizations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'details' => [['amount' => 100000]],
        ])->assertForbidden();
    }

    public function test_admin_source_total_is_derived_from_detail_rows(): void
    {
        $year = $this->activeYear();
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/authorizations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'total_amount' => 1,
            'details' => [['amount' => 40000], ['amount' => 60000]],
        ])->assertCreated();

        $response->assertJsonPath('total_amount', '100000.00');
    }

    public function test_admin_rejects_account_not_in_year_master(): void
    {
        $year = $this->activeYear();
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/authorizations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'details' => [['account_code' => '4.99.99.99', 'account_name' => 'Palsu', 'amount' => 100000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('details.0.account_code');
    }

    public function test_admin_canonicalizes_account_name_from_year_master(): void
    {
        $year = $this->activeYear();
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null]);
        MasterReference::create([
            'year' => $year->year,
            'code' => '4.1.01',
            'description' => 'Pendapatan Pajak Daerah',
            'type' => 'rekening_pendapatan',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/authorizations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'details' => [[
                'account_code' => '4.1.01',
                'account_name' => 'Nama yang dikirim client',
                'amount' => 100000,
            ]],
        ])->assertCreated();

        $response->assertJsonPath('details.0.account_code', '4.1.01')
            ->assertJsonPath('details.0.account_name', 'Pendapatan Pajak Daerah');
    }
}
