<?php

namespace Tests\Unit;

use App\Services\SourceDocumentDetector;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SourceDocumentDetectorTest extends TestCase
{
    public function test_it_detects_expenditure_reconciliation_from_cell_content_not_sheet_name(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['REKAPITULASI REALISASI (SP2D, SPJ, STS)'],
                ['No', 'Kode', 'SKPD', 'SP2D LS', 'SP2D UP/GU', 'SP2D TU', 'SP2D KKPD', 'TOTAL SP2D', 'SPJ LS', 'SPJ UP/GU', 'SPJ TU', 'SPJ KKPD', 'TOTAL SPJ', 'STS UP/GU', 'STS TU', 'CP LS', 'CP UP/GU', 'CP TU', 'TOTAL STS', 'KAS SIPD', 'KAS BANK', 'KAS TUNAI', 'SELISIH', 'STATUS'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets);

        $this->assertSame('expenditure_reconciliation', $result['type']);
        $this->assertSame('RKUD', $result['category']);
        $this->assertSame(1.0, $result['confidence']);
        $this->assertNotEmpty($result['evidence']);
    }

    public function test_it_detects_financial_statement_from_content(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['PEMERINTAHAN KABUPATEN KEDIRI'],
                ['KECAMATAN PARE'],
                ['LAPORAN REALISASI ANGGARAN PENDAPATAN DAN BELANJA DAERAH'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets);

        $this->assertSame('financial_statement', $result['type']);
        $this->assertSame('ACCOUNTING', $result['category']);
    }

    public function test_unknown_workbook_is_not_claimed_as_supported(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['random content'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets);

        $this->assertSame('unknown', $result['type']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_it_detects_working_paper_financial_statements_from_filename(): void
    {
        $sheets = new Collection([
            'Kertas Kerja LRA - 2026' => new Collection([
                ['kode rekening', 'uraian', 'konsolidasi', 'dinas pendidikan'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets, 'kertas-kerja-lra.xlsx');

        $this->assertSame('financial_statement', $result['type']);
        $this->assertSame('ACCOUNTING', $result['category']);
        $this->assertSame(0.95, $result['confidence']);
    }

    public function test_it_detects_blud_from_internal_sp3bp_sp2bp_markers(): void
    {
        $sheets = new Collection([
            'BLUD-RSKK' => new Collection([
                ['no', 'nomor sp3bp', 'tanggal sp3bp', 'untuk bulan', 'nama blud', 'nomor sp2bp', 'tanggal sp2bp', 'saldo awal', 'belanja pegawai blud'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets, 'BLUD RSKK.xlsx');

        $this->assertSame('blud', $result['type']);
        $this->assertSame('NON_RKUD', $result['category']);
        $this->assertSame(0.98, $result['confidence']);
    }

    public function test_it_detects_dana_desa_from_data_dd_markers(): void
    {
        $sheets = new Collection([
            'Data DD' => new Collection([
                ['no', 'nomor sp2d bun', 'tanggal sp2d bun', 'bulan', 'nomor sp2bdd', 'tanggal sp2bdd', 'saldo awal', 'pendapatan', 'belanja', 'saldo akhir'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets, 'Dana Desa.xlsx');

        $this->assertSame('non_rkud_transfer', $result['type']);
        $this->assertSame('NON_RKUD', $result['category']);
        $this->assertSame(0.98, $result['confidence']);
    }

    public function test_dana_desa_is_not_misclassified_by_embedded_blud_template_sheet(): void
    {
        $sheets = new Collection([
            'BLUD' => new Collection([
                ['no', 'nomor sp3bp', 'tanggal sp3bp', 'skpd', 'nama blu', 'nama kuasa bud', 'nomor', 'nomor_lengkap', 'tanggal', 'tahun anggaran', 'saldo awal', 'pendapatan'],
            ]),
            'BOK' => new Collection([
                ['no', 'nomor sp2b', 'tanggal sp2b', 'skpd', 'nama blud', 'nama kuasa bud'],
            ]),
            'BOSP' => new Collection([
                ['no', 'nomor sp2b', 'tanggal sp2b', 'skpd', 'kegiatan', 'nama kuasa bud'],
            ]),
            'Data DD' => new Collection([
                ['no', 'nomor sp2d bun', 'tanggal sp2d bun', 'bulan', 'nomor sp2bdd', 'tanggal sp2bdd', 'saldo awal', 'pendapatan', 'belanja', 'saldo akhir'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets, 'Dana Desa.xlsx');

        $this->assertSame('non_rkud_transfer', $result['type']);
        $this->assertSame('NON_RKUD', $result['category']);
    }


}
