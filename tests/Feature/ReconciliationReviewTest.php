<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\ReconciliationResult;
use App\Models\ReconciliationRule;
use App\Models\ReconciliationRun;
use App\Models\SourceDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_exception_and_review_is_a_separate_audit_record(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $user = User::factory()->create(['role' => 'admin']);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'fixture.xlsx',
            'document_type' => 'expenditure_reconciliation',
            'source_category' => 'RKUD',
            'checksum_sha256' => hash('sha256', uniqid('', true)),
            'status' => 'IMPORTED',
        ]);

        $rule = ReconciliationRule::create([
            'accounting_year_id' => null,
            'code' => 'EXP-001',
            'name' => 'Test',
            'category' => 'expenditure',
            'scope' => 'source_record',
            'version' => '1',
            'status' => 'active',
            'expression' => 'a - b',
            'tolerance' => 0,
            'input_metrics' => ['a', 'b'],
        ]);

        $run = ReconciliationRun::create([
            'accounting_year_id' => $year->id,
            'started_by' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = ReconciliationResult::create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_rule_id' => $rule->id,
            'source_document_id' => $document->id,
            'period' => 'UNKNOWN',
            'status' => 'VARIANCE',
            'expected_value' => 0,
            'actual_value' => 100,
            'variance' => 100,
            'inputs' => ['a' => 100, 'b' => 0],
            'lineage' => ['financial_fact_ids' => []],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/reconciliation-results/{$result->id}/review", [
                'status' => 'INVESTIGATING',
                'note' => 'Perlu ditelusuri ke dokumen sumber.',
                'evidence' => ['reference' => 'fixture.xlsx'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'INVESTIGATING');

        $this->assertDatabaseHas('reconciliation_reviews', [
            'reconciliation_result_id' => $result->id,
            'reviewed_by' => $user->id,
            'status' => 'INVESTIGATING',
        ]);

        $this->assertSame('VARIANCE', $result->fresh()->status);
    }
}
