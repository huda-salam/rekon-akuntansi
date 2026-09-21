<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use App\Models\ReconciliationRun;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use App\Models\User;
use App\Services\ReconciliationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenditure_total_sp2d_rule_reconciles_reported_and_derived_values(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $user = User::factory()->create();
        $skpd = Skpd::create([
            'code' => '7.01.0.00.0.00.46.0000',
            'name' => 'Kecamatan Pare',
            'is_active' => true,
        ]);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'rekonsiliasi pengeluaran.xlsx',
            'document_type' => 'expenditure_reconciliation',
            'source_category' => 'RKUD',
            'checksum_sha256' => hash('sha256', uniqid('', true)),
            'status' => 'IMPORTED',
        ]);

        $record = SourceRecord::create([
            'source_document_id' => $document->id,
            'source_row' => 3,
            'sheet_name' => 'Worksheet',
            'record_type' => 'expenditure_reconciliation_row',
            'payload' => [],
        ]);

        FinancialFact::createMany([
            [
                'source_document_id' => $document->id,
                'source_record_id' => $record->id,
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpd->id,
                'metric' => 'total_sp2d_reported',
                'value' => 120,
                'unit' => 'IDR',
                'dimensions' => [],
            ],
            [
                'source_document_id' => $document->id,
                'source_record_id' => $record->id,
                'accounting_year_id' => $year->id,
                'skpd_id' => $skpd->id,
                'metric' => 'total_sp2d_derived',
                'value' => 120,
                'unit' => 'IDR',
                'dimensions' => [],
            ],
        ]);

        $rule = ReconciliationRule::create([
            'accounting_year_id' => $year->id,
            'code' => 'EXP-TOTAL-SP2D',
            'name' => 'TOTAL SP2D consistency',
            'category' => 'expenditure',
            'scope' => 'source_record',
            'expression' => 'total_sp2d_reported - total_sp2d_derived',
            'tolerance' => 0,
            'input_metrics' => ['total_sp2d_reported', 'total_sp2d_derived'],
        ]);

        $run = ReconciliationRun::create([
            'accounting_year_id' => $year->id,
            'started_by' => $user->id,
            'status' => 'running',
            'started_at' => now(),
            'source_document_ids' => [$document->id],
        ]);

        $results = app(ReconciliationEngine::class)->run(
            $run,
            collect([$rule]),
            FinancialFact::query()->where('source_record_id', $record->id)->get()
        );

        $this->assertCount(1, $results);
        $this->assertSame('PASS', $results->first()->status);
        $this->assertSame(0.0, (float) $results->first()->variance);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_rule_creates_variance_when_reported_total_is_inconsistent(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $user = User::factory()->create();

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'fixture.xlsx',
            'document_type' => 'expenditure_reconciliation',
            'source_category' => 'RKUD',
            'checksum_sha256' => hash('sha256', uniqid('', true)),
            'status' => 'IMPORTED',
        ]);

        $record = SourceRecord::create([
            'source_document_id' => $document->id,
            'source_row' => 3,
            'sheet_name' => 'Worksheet',
            'record_type' => 'expenditure_reconciliation_row',
            'payload' => [],
        ]);

        FinancialFact::createMany([
            [
                'source_document_id' => $document->id,
                'source_record_id' => $record->id,
                'accounting_year_id' => $year->id,
                'metric' => 'total_sp2d_reported',
                'value' => 125,
                'unit' => 'IDR',
                'dimensions' => [],
            ],
            [
                'source_document_id' => $document->id,
                'source_record_id' => $record->id,
                'accounting_year_id' => $year->id,
                'metric' => 'total_sp2d_derived',
                'value' => 120,
                'unit' => 'IDR',
                'dimensions' => [],
            ],
        ]);

        $rule = ReconciliationRule::create([
            'accounting_year_id' => $year->id,
            'code' => 'EXP-TOTAL-SP2D',
            'name' => 'TOTAL SP2D consistency',
            'category' => 'expenditure',
            'scope' => 'source_record',
            'expression' => 'total_sp2d_reported - total_sp2d_derived',
            'tolerance' => 0,
            'input_metrics' => ['total_sp2d_reported', 'total_sp2d_derived'],
        ]);

        $run = ReconciliationRun::create([
            'accounting_year_id' => $year->id,
            'started_by' => $user->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $results = app(ReconciliationEngine::class)->run(
            $run,
            collect([$rule]),
            FinancialFact::query()->where('source_record_id', $record->id)->get()
        );

        $this->assertSame('VARIANCE', $results->first()->status);
        $this->assertSame(5.0, (float) $results->first()->variance);
    }

    public function test_rule_is_marked_incomplete_when_required_fact_is_missing(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $user = User::factory()->create();

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'fixture.xlsx',
            'document_type' => 'expenditure_reconciliation',
            'source_category' => 'RKUD',
            'checksum_sha256' => hash('sha256', uniqid('', true)),
            'status' => 'IMPORTED',
        ]);

        $record = SourceRecord::create([
            'source_document_id' => $document->id,
            'source_row' => 3,
            'sheet_name' => 'Worksheet',
            'record_type' => 'expenditure_reconciliation_row',
            'payload' => [],
        ]);

        FinancialFact::create([
            'source_document_id' => $document->id,
            'source_record_id' => $record->id,
            'accounting_year_id' => $year->id,
            'metric' => 'total_sp2d_reported',
            'value' => 120,
            'unit' => 'IDR',
            'dimensions' => [],
        ]);

        $rule = ReconciliationRule::create([
            'accounting_year_id' => $year->id,
            'code' => 'EXP-TOTAL-SP2D',
            'name' => 'TOTAL SP2D consistency',
            'category' => 'expenditure',
            'scope' => 'source_record',
            'expression' => 'total_sp2d_reported - total_sp2d_derived',
            'tolerance' => 0,
            'input_metrics' => ['total_sp2d_reported', 'total_sp2d_derived'],
        ]);

        $run = ReconciliationRun::create([
            'accounting_year_id' => $year->id,
            'started_by' => $user->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $results = app(ReconciliationEngine::class)->run(
            $run,
            collect([$rule]),
            FinancialFact::query()->where('source_record_id', $record->id)->get()
        );

        $this->assertSame('INCOMPLETE', $results->first()->status);
        $this->assertContains('total_sp2d_derived', $results->first()->lineage['missing_metrics']);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_multi_term_expression_is_evaluated_without_arbitrary_code_execution(): void
    {
        $calculator = app(\App\Services\FinancialFactCalculator::class);

        $facts = collect([
            new FinancialFact(['metric'=>'a','value'=>100]),
            new FinancialFact(['metric'=>'b','value'=>20]),
            new FinancialFact(['metric'=>'c','value'=>30]),
        ]);

        $result = $calculator->calculate($facts, ['a','b','c'], 'a - b - c');

        $this->assertSame(50.0, $result['value']);
    }
}
