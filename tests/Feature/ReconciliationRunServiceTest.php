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
use App\Services\ReconciliationRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationRunServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_service_evaluates_all_rules_for_all_source_records(): void
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

        foreach ([3, 4] as $row) {
            $record = SourceRecord::create([
                'source_document_id' => $document->id,
                'source_row' => $row,
                'sheet_name' => 'Worksheet',
                'record_type' => 'expenditure_reconciliation_row',
                'payload' => [],
            ]);

            FinancialFact::createMany([
                ['source_document_id' => $document->id, 'source_record_id' => $record->id, 'accounting_year_id' => $year->id, 'skpd_id' => $skpd->id, 'metric' => 'total_sp2d_reported', 'value' => 120, 'unit' => 'IDR', 'dimensions' => []],
                ['source_document_id' => $document->id, 'source_record_id' => $record->id, 'accounting_year_id' => $year->id, 'skpd_id' => $skpd->id, 'metric' => 'total_sp2d_derived', 'value' => 120, 'unit' => 'IDR', 'dimensions' => []],
                ['source_document_id' => $document->id, 'source_record_id' => $record->id, 'accounting_year_id' => $year->id, 'skpd_id' => $skpd->id, 'metric' => 'total_spj_reported', 'value' => 110, 'unit' => 'IDR', 'dimensions' => []],
                ['source_document_id' => $document->id, 'source_record_id' => $record->id, 'accounting_year_id' => $year->id, 'skpd_id' => $skpd->id, 'metric' => 'total_spj_derived', 'value' => 110, 'unit' => 'IDR', 'dimensions' => []],
            ]);
        }

        foreach ([
            ['code' => 'EXP-001', 'expression' => 'total_sp2d_reported - total_sp2d_derived', 'input_metrics' => ['total_sp2d_reported', 'total_sp2d_derived']],
            ['code' => 'EXP-002', 'expression' => 'total_spj_reported - total_spj_derived', 'input_metrics' => ['total_spj_reported', 'total_spj_derived']],
        ] as $definition) {
            ReconciliationRule::create([
                'accounting_year_id' => null,
                'code' => $definition['code'],
                'name' => $definition['code'],
                'category' => 'expenditure',
                'scope' => 'source_record',
                'version' => '1',
                'status' => 'active',
                'expression' => $definition['expression'],
                'tolerance' => 0,
                'input_metrics' => $definition['input_metrics'],
            ]);
        }

        $run = app(ReconciliationRunService::class)->execute(
            $year->id,
            $user->id,
            [$document->id],
        );

        $this->assertSame('completed', $run->status);
        $this->assertSame(['PASS' => 4], $run->summary);
        $this->assertCount(4, $run->results);
    }
}
