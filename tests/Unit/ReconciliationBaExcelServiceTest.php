<?php

namespace Tests\Unit;

use App\Models\BeritaAcara;
use App\Models\ReconciliationSnapshot;
use App\Models\ReconciliationSnapshotResult;
use App\Services\ReconciliationBaExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconciliationBaExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_creates_a_snapshot_based_excel_workbook(): void
    {
        Storage::fake('local');

        $snapshot = ReconciliationSnapshot::create([
            'reconciliation_id' => 1,
            'accounting_year' => 2026,
            'skpd_code' => 'DINKES',
            'skpd_name' => 'Dinas Kesehatan',
            'month' => 8,
            'reconciliation_type' => 'REGULAR',
            'sequence' => 1,
            'finalized_at' => now(),
            'snapshot_hash' => hash('sha256', uniqid('', true)),
            'snapshot_payload' => [
                'ba_number' => 'BA/001/2026',
                'ba_date' => '2026-08-31',
                'signatory' => [
                    'name' => 'Pejabat',
                    'nip' => '123',
                    'position' => 'Kepala BKAD',
                ],
            ],
        ]);

        ReconciliationSnapshotResult::create([
            'snapshot_id' => $snapshot->id,
            'rule_code' => 'REV-001',
            'rule_name' => 'Kontrol pendapatan',
            'category' => 'revenue',
            'period' => '2026-08',
            'status' => 'PASS',
            'expected_value' => 0,
            'actual_value' => 0,
            'variance' => 0,
            'lineage' => [
                'financial_facts' => [[
                    'financial_fact_id' => 99,
                    'source_document' => 'rekonsiliasi pendapatan.xlsx',
                    'sheet_name' => 'DINKES',
                    'source_row' => 16,
                    'source_column' => 'H',
                ]],
            ],
        ]);

        $ba = BeritaAcara::create([
            'reconciliation_id' => 1,
            'snapshot_id' => $snapshot->id,
            'number' => 'BA/001/2026',
            'date' => '2026-08-31',
            'signatory_official_name' => 'Pejabat',
            'signatory_official_nip' => '123',
            'signatory_official_position' => 'Kepala BKAD',
        ]);

        $path = app(ReconciliationBaExcelService::class)->export($ba);

        Storage::disk('local')->assertExists($path);
        $this->assertStringEndsWith('.xlsx', $path);
    }
}
