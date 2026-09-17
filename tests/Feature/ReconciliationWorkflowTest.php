<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_skpd_user_can_create_only_own_reconciliation(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $own = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $other = Skpd::create(['code' => 'SKPD-B', 'name' => 'SKPD B', 'is_active' => true]);
        $user = User::create([
            'name' => 'User SKPD A', 'email' => 'a@example.test', 'password' => 'password',
            'role' => 'skpd', 'skpd_id' => $own->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reconciliations', [
                'accounting_year_id' => $year->id,
                'skpd_id' => $other->id,
                'period_start' => '2026-01-01',
                'period_end' => '2026-01-31',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_and_finalize_immutable_reconciliation(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'skpd_id' => null,
        ]);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/reconciliations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'notes' => 'Snapshot test',
            'details' => [[
                'source_type' => 'authorization',
                'source_id' => 10,
                'match_status' => 'matched',
                'source_amount' => 100000,
                'matched_amount' => 100000,
                'difference_amount' => 0,
            ]],
        ])->assertCreated();

        $id = $create->json('id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/reconciliations/{$id}/finalize", [
            'number' => 'BA/001/2026',
            'date' => '2026-01-31',
            'signatory_official_name' => 'Pejabat A',
            'signatory_official_position' => 'Kepala SKPD',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->putJson("/api/reconciliations/{$id}", [
            'notes' => 'Tidak boleh berubah',
        ])->assertForbidden();

        $this->assertDatabaseHas('reconciliations', ['id' => $id, 'status' => 'finalized']);
        $this->assertDatabaseHas('reconciliation_snapshots', ['reconciliation_id' => $id]);
        $this->assertDatabaseHas('berita_acaras', ['reconciliation_id' => $id, 'number' => 'BA/001/2026']);
    }
}
