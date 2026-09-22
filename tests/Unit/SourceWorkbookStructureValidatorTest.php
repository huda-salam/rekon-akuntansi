<?php

namespace Tests\Unit;

use App\Services\SourceWorkbookStructureValidator;
use App\Services\SourceWorkbookStructureValidator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SourceWorkbookStructureValidatorTest extends TestCase
{
    public function test_it_validates_financial_statement_structure(): void
    {
        $sheets = new Collection([
            'Kertas Kerja LRA - 2026' => new Collection([
                ['kode rekening', 'uraian', 'konsolidasi', 'dinas pendidikan'],
            ]),
        ]);

        $result = (new SourceWorkbookStructureValidator())->validate('financial_statement', $sheets);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['missing']);
    }

    public function test_it_validates_lpe_without_account_code_column(): void
    {
        $sheets = new Collection([
            'Kertas Kerja LPE - 2026' => new Collection([
                ['uraian', 'dinas pendidikan', 'dinas kesehatan'],
                ['ekuitas awal', 0, 0],
            ]),
        ]);

        $result = (new SourceWorkbookStructureValidator())->validate('financial_statement', $sheets);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['missing']);
    }

    public function test_it_validates_lra_program_with_generic_code_column(): void
    {
        $sheets = new Collection([
            'Sheet1' => new Collection([
                ['kode', 'uraian urusan, organisasi, program, kegiatan dan sub kegiatan', 'kelompok belanja', 'operasi', 'modal', 'anggaran'],
            ]),
        ]);

        $result = (new SourceWorkbookStructureValidator())->validate('financial_statement', $sheets);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['missing']);
    }

    public function test_it_validates_blud_structure(): void
    {
        $sheets = new Collection([
            'BLUD-RSKK' => new Collection([
                ['no', 'nomor sp3bp', 'tanggal sp3bp', 'nomor sp2bp', 'saldo awal', 'pendapatan', 'belanja pegawai blud'],
            ]),
        ]);

        $result = (new SourceWorkbookStructureValidator())->validate('blud', $sheets);

        $this->assertTrue($result['valid']);
    }

    public function test_it_rejects_ledger_without_debit_or_credit(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['tanggal', 'kode rekening', 'uraian'],
            ]),
        ]);

        $result = (new SourceWorkbookStructureValidator())->validate('ledger', $sheets);

        $this->assertFalse($result['valid']);
        $this->assertContains('ledger header', $result['missing']);
    }

    public function test_it_validates_dana_desa_transfer_structure(): void
    {
        $sheets = new Collection([
            'Data DD' => new Collection([
                ['no', 'nomor sp2d bun', 'tanggal sp2d bun', 'nomor sp2bdd', 'saldo awal', 'pendapatan', 'belanja', 'saldo akhir'],
            ]),
        ]);

        $result = (new SourceWorkbookStructureValidator())->validate('non_rkud_transfer', $sheets);

        $this->assertTrue($result['valid']);
    }
}
