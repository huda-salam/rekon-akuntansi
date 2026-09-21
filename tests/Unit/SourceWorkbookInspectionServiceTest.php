<?php

namespace Tests\Unit;

use App\Services\SourceWorkbookInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceWorkbookInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspection_classifies_fixture_workbook_without_persisting_data(): void
    {
        $directory = storage_path('framework/testing/source-inspection');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . '/fixture.xlsx';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');
        $sheet->fromArray([
            ['REKAPITULASI REALISASI (SP2D, SPJ, STS)'],
            ['No', 'Kode', 'SKPD', 'SP2D LS', 'SP2D UP/GU', 'SP2D TU', 'SP2D KKPD', 'TOTAL SP2D', 'SPJ LS', 'SPJ UP/GU', 'SPJ TU', 'SPJ KKPD', 'TOTAL SPJ', 'STS UP/GU', 'STS TU', 'CP LS', 'CP UP/GU', 'CP TU', 'TOTAL STS', 'KAS SIPD', 'KAS BANK', 'KAS TUNAI', 'SELISIH', 'STATUS'],
            [1, '001', 'Test SKPD', 100, 20, 0, 0, 120, 100, 20, 0, 0, 120, 0, 0, 0, 0, 0, 0, 100, 100, 0, 0, 'SUDAH'],
        ]);

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        try {
            $report = app(SourceWorkbookInspectionService::class)->inspect($path);

            $this->assertSame('fixture.xlsx', $report['filename']);
            $this->assertSame('expenditure_reconciliation', $report['document_type']);
            $this->assertSame('RKUD', $report['source_category']);
            $this->assertSame(1.0, $report['confidence']);
            $this->assertSame(1, $report['sheets']);
            $this->assertGreaterThanOrEqual(3, $report['source_rows']);
            $this->assertGreaterThan(0, $report['numeric_cells']);

            $this->assertDatabaseCount('source_documents', 0);
            $this->assertDatabaseCount('source_records', 0);
            $this->assertDatabaseCount('financial_facts', 0);
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }
}
