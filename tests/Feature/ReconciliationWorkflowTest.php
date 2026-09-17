<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\AuthorizationRecord;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_skpd_user_can_only_view_own_reconciliation(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $own = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $other = Skpd::create(['code' => 'SKPD-B', 'name' => 'SKPD B', 'is_active' => true]);
        $user = User::create([
            'name' => 'User SKPD A', 'email' => 'a@example.test', 'password' => 'password',
            'role' => 'skpd', 'skpd_id' => $own->id,
        ]);
        $otherRecon = \App\Models\Reconciliation::create([
            'accounting_year_id' => $year->id,
            'skpd_id' => $other->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/reconciliations')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/reconciliations/{$otherRecon->id}")
            ->assertForbidden();
    }

    public function test_skpd_user_cannot_create_reconciliation(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $user = User::create([
            'name' => 'User SKPD A', 'email' => 'a@example.test', 'password' => 'password',
            'role' => 'skpd', 'skpd_id' => $skpd->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reconciliations', [
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpd->id,
            ])
            ->assertForbidden();
    }

    public function test_skpkd_can_process_reconciliation(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $skpkd = User::create([
            'name' => 'SKPKD', 'email' => 'skpkd@example.test', 'password' => 'password',
            'role' => 'skpkd', 'skpd_id' => null,
        ]);
        $source = $this->createAuthorization($year, $skpd, 100000);

        $this->actingAs($skpkd, 'sanctum')
            ->postJson('/api/reconciliations', [
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpd->id,
                'details' => [[
                    'source_type' => 'authorization',
                    'source_id' => $source->id,
                    'match_status' => 'unmatched',
                    'matched_amount' => 0,
                ]],
            ])
            ->assertCreated();
    }

    public function test_reconciliation_rejects_source_from_another_skpd(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpdA = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $skpdB = Skpd::create(['code' => 'SKPD-B', 'name' => 'SKPD B', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'skpd_id' => null,
        ]);
        $source = $this->createAuthorization($year, $skpdB, 100000);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reconciliations', [
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpdA->id,
                'details' => [[
                    'source_type' => 'authorization',
                    'source_id' => $source->id,
                    'matched_amount' => 100000,
                    'match_status' => 'matched',
                ]],
            ])
            ->assertUnprocessable();
    }

    public function test_reconciliation_recomputes_source_and_difference_server_side(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'skpd_id' => null,
        ]);
        $source = $this->createAuthorization($year, $skpd, 100000);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reconciliations', [
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpd->id,
                'details' => [[
                    'source_type' => 'authorization',
                    'source_id' => $source->id,
                    'match_status' => 'partial',
                    'source_amount' => 1,
                    'matched_amount' => 40000,
                    'difference_amount' => -999999,
                ]],
            ])
            ->assertCreated();

        $response->assertJsonPath('details.0.source_amount', '100000.00')
            ->assertJsonPath('details.0.matched_amount', '40000.00')
            ->assertJsonPath('details.0.difference_amount', '60000.00')
            ->assertJsonPath('status', 'draft');
    }

    public function test_matching_update_changes_status_to_in_review_and_validates_amounts(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'skpd_id' => null,
        ]);
        $source = $this->createAuthorization($year, $skpd, 100000);

        $recon = $this->actingAs($admin, 'sanctum')->postJson('/api/reconciliations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'details' => [[
                'source_type' => 'authorization',
                'source_id' => $source->id,
            ]],
        ])->assertCreated()->json();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/reconciliations/{$recon['id']}", [
                'details' => [[
                    'source_type' => 'authorization',
                    'source_id' => $source->id,
                    'match_status' => 'matched',
                    'matched_amount' => 100000,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'in_review')
            ->assertJsonPath('details.0.difference_amount', '0.00');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/reconciliations/{$recon['id']}", [
                'details' => [[
                    'source_type' => 'authorization',
                    'source_id' => $source->id,
                    'match_status' => 'matched',
                    'matched_amount' => 50000,
                ]],
            ])
            ->assertUnprocessable();
    }

    public function test_new_reconciliation_requires_active_accounting_year(): void
    {
        $year = AccountingYear::create(['year' => 2025, 'is_active' => false]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'skpd_id' => null,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reconciliations', [
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpd->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accounting_year_id');
    }

    public function test_admin_can_finalize_and_finalized_reconciliation_is_immutable(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'skpd_id' => null,
        ]);
        $source = $this->createAuthorization($year, $skpd, 100000);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/reconciliations', [
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'notes' => 'Snapshot test',
            'details' => [[
                'source_type' => 'authorization',
                'source_id' => $source->id,
                'match_status' => 'matched',
                'matched_amount' => 100000,
            ]],
        ])->assertCreated();

        $id = $create->json('id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/reconciliations/{$id}", [
            'details' => [[
                'source_type' => 'authorization',
                'source_id' => $source->id,
                'match_status' => 'matched',
                'matched_amount' => 100000,
            ]],
        ])->assertOk();

        $this->actingAs($admin, 'sanctum')->postJson("/api/reconciliations/{$id}/finalize", [
            'number' => 'BA/001/2026',
            'date' => '2026-01-31',
            'signatory_official_name' => 'Pejabat A',
            'signatory_official_position' => 'Kepala SKPD',
        ])->assertCreated();

        $snapshot = \App\Models\ReconciliationSnapshot::where('reconciliation_id', $id)->firstOrFail();
        $hash = $snapshot->snapshot_hash;

        $this->actingAs($admin, 'sanctum')->putJson("/api/reconciliations/{$id}", [
            'notes' => 'Tidak boleh berubah',
        ])->assertForbidden();

        $this->assertDatabaseHas('reconciliations', ['id' => $id, 'status' => 'finalized']);
        $this->assertDatabaseHas('reconciliation_snapshots', ['reconciliation_id' => $id, 'snapshot_hash' => $hash]);
        $this->assertDatabaseHas('berita_acaras', ['reconciliation_id' => $id, 'number' => 'BA/001/2026']);
    }

    private function createAuthorization(AccountingYear $year, Skpd $skpd, int $amount): AuthorizationRecord
    {
        $record = AuthorizationRecord::create([
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'type' => 'pendapatan',
            'authorization_number' => 'PENG-' . $skpd->id . '-' . $amount,
            'total_amount' => $amount,
        ]);

        $record->details()->create([
            'amount' => $amount,
            'account_code' => '4.1.01',
            'account_name' => 'Pendapatan',
        ]);

        return $record->fresh();
    }
}
