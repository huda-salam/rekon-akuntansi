<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\ReconciliationResult;
use App\Models\ReconciliationRule;
use App\Models\ReconciliationRun;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationLineageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_drill_down_to_source_document_sheet_row_and_fact(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $skpd = Skpd::create(['code' => 'SKPD-01', 'name' => 'Dinas Test']);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'rekonsiliasi.xlsx',
            'document_type' => 'expenditure_reconciliation',
            'source_category' => 'RKUD',
            'checksum_sha256' => hash('sha256', uniqid('', true)),
            'status' => 'IMPORTED',
        ]);

        $record = SourceRecord::create([
            'source_document_id' => $document->id,
            'source_row' => 17,
            'sheet_name' => 'Worksheet',
            'record_type' => 'expenditure_reconciliation_row',
            'payload' => ['skpd' => 'Dinas Test', 'sp2d ls' => 1000],
        ]);

        $fact = FinancialFact::create([
            'source_document_id' => $document->id,
            'source_record_id' => $record->id,
            'accounting_year_id' => $year->id,
            'skpd_id' => $skpd->id,
            'period' => '2026-09',
            'month' => 9,
            'source_type' => 'expenditure_reconciliation',
            'transaction_type' => 'SP2D',
            'metric' => 'sp2d_ls',
            'value' => 1000,
            'unit' => 'IDR',
            'dimensions' => ['origin' => 'reported'],
            'lineage' => [
                'origin' => 'reported',
                'source_record_id' => $record->id,
            ],
        ]);

        $rule = ReconciliationRule::create([
            'accounting_year_id' => null,
            'code' => 'EXP-TEST',
            'name' => 'Test lineage',
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
            'month' => 9,
            'started_by' => $user->id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $result = ReconciliationResult::create([
            'reconciliation_run_id' => $run->id,
            'reconciliation_rule_id' => $rule->id,
            'source_document_id' => $document->id,
            'skpd_id' => $skpd->id,
            'month' => 9,
            'period' => '2026-09',
            'status' => 'VARIANCE',
            'expected_value' => 0,
            'actual_value' => 1000,
            'variance' => 1000,
            'inputs' => ['a' => 1000, 'b' => 0],
            'lineage' => ['financial_fact_ids' => [$fact->id]],
        ]);

        $this->actingAs($user)
            ->getJson("/api/reconciliation-results/{$result->id}")
            ->assertOk()
            ->assertJsonPath('result.month', 9)
            ->assertJsonPath('result.period', '2026-09')
            ->assertJsonPath('lineage.0.financial_fact_id', $fact->id)
            ->assertJsonPath('lineage.0.metric', 'sp2d_ls')
            ->assertJsonPath('lineage.0.source.filename', 'rekonsiliasi.xlsx')
            ->assertJsonPath('lineage.0.source.sheet_name', 'Worksheet')
            ->assertJsonPath('lineage.0.source.source_row', 17);
    }
}
