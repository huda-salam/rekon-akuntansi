<?php

namespace App\Services;

use App\Models\BeritaAcara;
use App\Models\ReconciliationSnapshot;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReconciliationBaExcelService
{
    public function export(BeritaAcara $beritaAcara): string
    {
        $snapshot = $beritaAcara->snapshot()->firstOrFail();
        $document = app(ReconciliationBaDocumentService::class)->build($snapshot);

        $spreadsheet = new Spreadsheet();
        $ba = $spreadsheet->getActiveSheet();
        $ba->setTitle('BA Rekonsiliasi');

        $this->buildBaSheet($ba, $beritaAcara, $document);

        $controls = $spreadsheet->createSheet();
        $controls->setTitle('Kontrol');
        $this->buildControlsSheet($controls, $document);

        $lineage = $spreadsheet->createSheet();
        $lineage->setTitle('Lineage');
        $this->buildLineageSheet($lineage, $document);

        $review = $spreadsheet->createSheet();
        $review->setTitle('Review');
        $this->buildReviewSheet($review, $document);

        $filename = $this->filename($snapshot);
        $path = 'ba-rekonsiliasi/'.$filename;
        Storage::disk('local')->makeDirectory('ba-rekonsiliasi');

        $temporaryPath = storage_path('app/'.$path);
        (new Xlsx($spreadsheet))->save($temporaryPath);

        return $path;
    }

    private function buildBaSheet($sheet, BeritaAcara $ba, array $document): void
    {
        $row = 1;
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', $document['title']);
        $this->titleStyle($sheet, 'A1:H1');
        $row = 3;

        foreach ([
            ['Nomor', $ba->number],
            ['Tanggal', $ba->date?->format('d-m-Y')],
            ['Tahun Anggaran', $document['identity']['year']],
            ['SKPD', $document['identity']['skpd_name']],
            ['Kode SKPD', $document['identity']['skpd_code']],
            ['Bulan', $document['identity']['month']],
            ['Jenis Rekonsiliasi', $document['identity']['reconciliation_type']],
            ['Sequence', $document['identity']['sequence']],
            ['Penandatangan', $document['signatory']['name'] ?? $ba->signatory_official_name],
            ['Jabatan', $document['signatory']['position'] ?? $ba->signatory_official_position],
            ['NIP', $document['signatory']['nip'] ?? $ba->signatory_official_nip],
        ] as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'RINGKASAN REKONSILIASI');
        $this->sectionStyle($sheet, "A{$row}:H{$row}");
        $row++;

        $summaryLabels = [
            'total_controls' => 'Jumlah Kontrol',
            'pass' => 'PASS',
            'variance' => 'VARIANCE',
            'incomplete' => 'INCOMPLETE',
            'error' => 'ERROR',
            'exceptions' => 'EXCEPTION',
            'accepted_or_resolved' => 'RESOLVED / ACCEPTED',
        ];
        foreach ($summaryLabels as $key => $label) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $document['summary'][$key] ?? 0);
            $row++;
        }

        foreach ($document['sections'] as $section) {
            $row += 1;
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", $section['title']);
            $this->sectionStyle($sheet, "A{$row}:H{$row}");
            $row++;

            $headers = ['Kode', 'Kontrol', 'Periode', 'Status', 'Expected', 'Actual', 'Variance', 'Review'];
            $this->headerRow($sheet, $row, $headers);
            $row++;

            foreach ($section['controls'] as $control) {
                $values = [
                    $control['rule_code'],
                    $control['rule_name'],
                    $control['period'],
                    $control['status'],
                    $control['expected_value'],
                    $control['actual_value'],
                    $control['variance'],
                    $control['review']['status'] ?? '',
                ];
                $this->writeRow($sheet, $row, $values);
                $row++;
            }
        }

        $this->standardSheet($sheet, $row, [18, 46, 16, 14, 18, 18, 18, 18]);
    }

    private function buildControlsSheet($sheet, array $document): void
    {
        $headers = ['Kategori', 'Kode', 'Kontrol', 'Periode', 'Status', 'Expected', 'Actual', 'Variance', 'Penjelasan'];
        $this->headerRow($sheet, 1, $headers);
        $row = 2;

        foreach ($document['sections'] as $section) {
            foreach ($section['controls'] as $control) {
                $this->writeRow($sheet, $row, [
                    $section['category'],
                    $control['rule_code'],
                    $control['rule_name'],
                    $control['period'],
                    $control['status'],
                    $control['expected_value'],
                    $control['actual_value'],
                    $control['variance'],
                    $control['explanation'],
                ]);
                $row++;
            }
        }

        $this->standardSheet($sheet, $row, [18, 16, 46, 18, 14, 18, 18, 18, 60]);
        $sheet->freezePane('A2');
        $sheet->autoFilter->setRange("A1:I".max(1, $row - 1));
    }

    private function buildLineageSheet($sheet, array $document): void
    {
        $headers = ['Kode', 'Kategori', 'Status', 'Source Document', 'Sheet', 'Row', 'Column', 'Source Record', 'Financial Fact', 'Detail'];
        $this->headerRow($sheet, 1, $headers);
        $row = 2;

        foreach ($document['sections'] as $section) {
            foreach ($section['controls'] as $control) {
                $lineage = $control['lineage'] ?? [];
                $entries = $this->flattenLineage($lineage);

                if ($entries === []) {
                    $this->writeRow($sheet, $row, [$control['rule_code'], $section['category'], $control['status'], '', '', '', '', '', '', 'Tidak ada lineage terstruktur.']);
                    $row++;
                    continue;
                }

                foreach ($entries as $entry) {
                    $this->writeRow($sheet, $row, [
                        $control['rule_code'],
                        $section['category'],
                        $control['status'],
                        $entry['source_document'] ?? '',
                        $entry['sheet'] ?? '',
                        $entry['row'] ?? '',
                        $entry['column'] ?? '',
                        $entry['source_record_id'] ?? '',
                        $entry['financial_fact_id'] ?? '',
                        $entry['detail'] ?? '',
                    ]);
                    $row++;
                }
            }
        }

        $this->standardSheet($sheet, $row, [16, 18, 14, 48, 28, 10, 10, 16, 16, 60]);
        $sheet->freezePane('A2');
        $sheet->autoFilter->setRange("A1:J".max(1, $row - 1));
    }

    private function buildReviewSheet($sheet, array $document): void
    {
        $headers = ['Kode', 'Kategori', 'Status', 'Review Status', 'Catatan', 'Evidence', 'Reviewed By', 'Reviewed At'];
        $this->headerRow($sheet, 1, $headers);
        $row = 2;

        foreach ($document['sections'] as $section) {
            foreach ($section['controls'] as $control) {
                $review = $control['review'] ?? [];
                $this->writeRow($sheet, $row, [
                    $control['rule_code'],
                    $section['category'],
                    $control['status'],
                    $review['status'] ?? '',
                    $review['note'] ?? '',
                    is_array($review['evidence'] ?? null) ? json_encode($review['evidence'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($review['evidence'] ?? ''),
                    $review['reviewed_by'] ?? '',
                    $review['reviewed_at'] ?? '',
                ]);
                $row++;
            }
        }

        $this->standardSheet($sheet, $row, [16, 18, 14, 18, 60, 60, 16, 24]);
        $sheet->freezePane('A2');
        $sheet->autoFilter->setRange("A1:H".max(1, $row - 1));
    }

    private function flattenLineage(array $lineage): array
    {
        $entries = [];

        foreach ($lineage as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $entries[] = [
                            'source_document' => $item['source_document'] ?? $item['filename'] ?? null,
                            'sheet' => $item['sheet_name'] ?? $item['sheet'] ?? null,
                            'row' => $item['source_row'] ?? $item['row'] ?? null,
                            'column' => $item['source_column'] ?? $item['column'] ?? null,
                            'source_record_id' => $item['source_record_id'] ?? null,
                            'financial_fact_id' => $item['financial_fact_id'] ?? null,
                            'detail' => $key,
                        ];
                    }
                }
            } elseif (is_array($value)) {
                foreach ($this->flattenLineage($value) as $item) {
                    $item['detail'] = trim(($item['detail'] ?? '').' '.$key);
                    $entries[] = $item;
                }
            }
        }

        return $entries;
    }

    private function headerRow($sheet, int $row, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, $row, $header);
        }
        $this->headerStyle($sheet, "A{$row}:".chr(64 + count($headers)).$row);
    }

    private function writeRow($sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            $sheet->setCellValueByColumnAndRow($index + 1, $row, $this->excelValue($value));
        }
        $sheet->getStyle("A{$row}:".chr(64 + count($values)).$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function excelValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    private function titleStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function sectionStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function headerStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($range)->getAlignment()->setWrapText(true);
    }

    private function standardSheet($sheet, int $lastRow, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $sheet->getStyle("A1:".chr(64 + count($widths)).max(1, $lastRow))
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        $sheet->freezePane('A2');
    }

    private function filename(ReconciliationSnapshot $snapshot): string
    {
        $safeSkpd = preg_replace('/[^A-Za-z0-9_-]+/', '-', $snapshot->skpd_code ?: 'skpd');

        return 'BA-Rekonsiliasi-'.$snapshot->accounting_year.'-'.$safeSkpd.'-'.($snapshot->month ?: 'annual').'-'.$snapshot->id.'.xlsx';
    }
}
