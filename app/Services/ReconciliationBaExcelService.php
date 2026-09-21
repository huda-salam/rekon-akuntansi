<?php

namespace App\Services;

use App\Models\BeritaAcara;
use App\Models\ReconciliationSnapshot;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReconciliationBaExcelService
{
    public function export(BeritaAcara $beritaAcara): string
    {
        $snapshot = $beritaAcara->snapshot()->firstOrFail();
        $document = app(ReconciliationBaDocumentService::class)->build($snapshot);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BA Rekonsiliasi');

        $row = 1;
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", $document['title']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        $identity = $document['identity'];
        $metadata = [
            ['Nomor', $beritaAcara->number],
            ['Tanggal', $beritaAcara->date?->toDateString()],
            ['Tahun Anggaran', $identity['year']],
            ['SKPD', $identity['skpd_name']],
            ['Kode SKPD', $identity['skpd_code']],
            ['Bulan', $identity['month']],
            ['Jenis Rekonsiliasi', $identity['reconciliation_type']],
            ['Sequence', $identity['sequence']],
            ['Penandatangan', $beritaAcara->signatory_official_name],
            ['Jabatan', $beritaAcara->signatory_official_position],
            ['NIP', $beritaAcara->signatory_official_nip],
        ];

        foreach ($metadata as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;
        $sheet->setCellValue("A{$row}", 'RINGKASAN REKONSILIASI');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        foreach ($document['summary'] as $key => $value) {
            $sheet->setCellValue("A{$row}", strtoupper(str_replace('_', ' ', $key)));
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        foreach ($document['sections'] as $section) {
            $row++;
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", $section['title']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $headers = ['Kode', 'Kontrol', 'Periode', 'Status', 'Expected', 'Actual', 'Variance', 'Review'];
            foreach ($headers as $index => $header) {
                $column = chr(ord('A') + $index);
                $sheet->setCellValue("{$column}{$row}", $header);
            }
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:H{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;

            foreach ($section['controls'] as $control) {
                $review = $control['review']['status'] ?? '';
                $values = [
                    $control['rule_code'],
                    $control['rule_name'],
                    $control['period'],
                    $control['status'],
                    $control['expected_value'],
                    $control['actual_value'],
                    $control['variance'],
                    $review,
                ];
                foreach ($values as $index => $value) {
                    $column = chr(ord('A') + $index);
                    $sheet->setCellValue("{$column}{$row}", $value);
                }
                $row++;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getStyle("A1:H{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A1:H{$row}")->getAlignment()->setWrapText(true);
        $sheet->freezePane('A4');

        $filename = 'ba-rekonsiliasi-'.$snapshot->accounting_year.'-'.($snapshot->skpd_code ?: 'skpd').'-'.($snapshot->month ?: 'annual').'-'.$snapshot->id.'.xlsx';
        $path = 'ba-rekonsiliasi/'.$filename;
        Storage::disk('local')->makeDirectory('ba-rekonsiliasi');

        $temporaryPath = storage_path('app/'.$path);
        (new Xlsx($spreadsheet))->save($temporaryPath);

        return $path;
    }
}
