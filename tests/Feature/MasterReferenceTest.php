<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\MasterReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_master_references_by_year_type_and_parent(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null,
        ]);

        MasterReference::create(['year' => 2026, 'type' => 'rekening_belanja', 'code' => '5.1.01', 'description' => 'Belanja Pegawai', 'parent_code' => '5.1', 'is_active' => true]);
        MasterReference::create(['year' => 2026, 'type' => 'rekening_belanja', 'code' => '5.1.02', 'description' => 'Belanja Barang', 'parent_code' => '5.1', 'is_active' => true]);
        MasterReference::create(['year' => 2025, 'type' => 'rekening_belanja', 'code' => '5.1.01', 'description' => 'Belanja Lama', 'parent_code' => '5.1', 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/master-references?year=2026&type=rekening_belanja&parent_code=5.1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', '5.1.01')
            ->assertJsonPath('data.1.code', '5.1.02');
    }

    public function test_master_reference_search_matches_code_or_description(): void
    {
        AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null,
        ]);
        MasterReference::create(['year' => 2026, 'type' => 'rekening_pendapatan', 'code' => '4.1.01', 'description' => 'Pajak Daerah', 'is_active' => true]);
        MasterReference::create(['year' => 2026, 'type' => 'rekening_pendapatan', 'code' => '4.1.02', 'description' => 'Retribusi Daerah', 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/master-references?type=rekening_pendapatan&q=Pajak')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', '4.1.01');
    }

    public function test_default_year_is_active_year(): void
    {
        AccountingYear::create(['year' => 2025, 'is_active' => false]);
        AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null,
        ]);
        MasterReference::create(['year' => 2025, 'type' => 'rekening_belanja', 'code' => '5.1.01', 'description' => 'Lama', 'is_active' => true]);
        MasterReference::create(['year' => 2026, 'type' => 'rekening_belanja', 'code' => '5.1.01', 'description' => 'Aktif', 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/master-references?type=rekening_belanja')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Aktif');
    }

    public function test_skpd_user_cannot_query_master_references(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = \App\Models\Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $user = User::create([
            'name' => 'SKPD', 'email' => 'skpd@test.local', 'password' => 'password', 'role' => 'skpd', 'skpd_id' => $skpd->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/master-references?year=2026&type=rekening_belanja')
            ->assertForbidden();
    }
}
