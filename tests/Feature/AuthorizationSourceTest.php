<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationSourceTest extends TestCase
{
    use RefreshDatabase;

    private function activeYear(): AccountingYear
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => false]);
        DB::statement('UPDATE accounting_years SET is_active = 1 WHERE id = ?', [$year->id]);
        return $year->fresh();
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
}
