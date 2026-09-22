<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use SplFileInfo;
use Throwable;

class SourceWorkbookInspectionService
{
    public function __construct(
        private readonly SourceDocumentDetector $detector,
        private readonly SourceWorkbookStructureValidator $structureValidator,
    ) {}

    /**
     * Inspect workbook structure without persisting anything.
     *
     * @return array<string,mixed>
     */
    public function inspect(string $path): array
    {
        $file = new SplFileInfo($path);

        if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException("File workbook tidak valid: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        // Inspection/classification only needs the header area. Loading an
        // entire legacy XLS can consume hundreds of MB and may terminate PHP
        // before our exception handler can report anything. Limit the reader
        // to the first 20 rows on every sheet.
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell($column, $row, $worksheetName = ''): bool
            {
                return $row <= 20;
            }
        });

        $spreadsheet = $reader->load($path);

        $sheets = new Collection();
        $sheetStats = [];
        $sourceRows = 0;
        $numericCells = 0;

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = collect();
            $sheetNumericCells = 0;
            $nonEmptyRows = 0;
            $rowCount = 0;

            foreach ($worksheet->getRowIterator() as $row) {
                $rowCount++;
                $values = [];

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);

                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    $values[] = $value;

                    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
                        $sheetNumericCells++;
                    }
                }

                $hasValue = collect($values)->contains(
                    fn ($value) => trim((string) ($value ?? '')) !== ''
                );

                if ($hasValue) {
                    $nonEmptyRows++;
                }

                $rows->push($values);
            }

            $sheets->put($worksheet->getTitle(), $rows);
            $sheetStats[] = [
                'name' => $worksheet->getTitle(),
                'rows' => $rowCount,
                'non_empty_rows' => $nonEmptyRows,
                'numeric_cells' => $sheetNumericCells,
            ];

            $sourceRows += $nonEmptyRows;
            $numericCells += $sheetNumericCells;
        }

        $detection = $this->detector->detect($sheets, $file->getFilename());
        $validation = $this->validation($detection['type'], (float) $detection['confidence'], $sheets);
        $profile = $this->buildStructureProfile($sheets);

        return [
            'filename' => $file->getFilename(),
            'path' => $file->getPathname(),
            'size_bytes' => $file->getSize(),
            'sheets' => count($sheetStats),
            'source_rows' => $sourceRows,
            'numeric_cells' => $numericCells,
            'document_type' => $detection['type'],
            'source_category' => $detection['category'],
            'confidence' => $detection['confidence'],
            'evidence' => $detection['evidence'],
            'parser' => $validation['parser'],
            'readiness' => $validation['readiness'],
            'warnings' => $validation['warnings'],
            'structure_profile' => $profile,
            'sheet_stats' => $sheetStats,
        ];
    }

    /**
     * Build a compact structural profile from the header area already loaded
     * during inspection. This is diagnostic metadata only; it is never persisted.
     *
     * @param Collection<string, Collection<int, array<int, mixed>>> $sheets
     * @return array<int,array{name:string,non_empty_rows:int,labels:array<int,string>}>
     */
    private function buildStructureProfile(Collection $sheets): array
    {
        return $sheets->map(function (Collection $rows, string $sheetName): array {
            $labels = $rows
                ->take(20)
                ->flatten()
                ->map(fn ($value) => trim((string) ($value ?? '')))
                ->filter(fn (string $value) => $value !== '')
                ->map(fn (string $value) => mb_strtolower($value))
                ->unique()
                ->values();

            return [
                'name' => $sheetName,
                'non_empty_rows' => $rows->filter(
                    fn (array $row) => collect($row)->contains(
                        fn ($value) => trim((string) ($value ?? '')) !== ''
                    )
                )->count(),
                'labels' => $labels->take(40)->all(),
            ];
        })->values()->all();
    }

    /**
     * @param Collection<string, Collection<int, array<int, mixed>>> $sheets
     * @return array{parser:?string,readiness:string,warnings:array<int,string>}
     */
    public function validation(string $documentType, float $confidence, ?Collection $sheets = null): array
    {
        $parsers = [
            'expenditure_reconciliation' => ExpenditureReconciliationParser::class,
            'revenue_reconciliation' => RevenueReconciliationParser::class,
            'ledger' => LedgerParser::class,
            'financial_statement' => FinancialStatementParser::class,
            'blud' => NormalizedWorkbookParser::class,
            'non_rkud_transfer' => NormalizedWorkbookParser::class,
        ];

        $parser = $parsers[$documentType] ?? null;
        $warnings = [];

        if ($documentType === 'unknown') {
            return [
                'parser' => null,
                'readiness' => 'BLOCKED',
                'warnings' => ['Document type tidak dikenali; workbook tidak boleh diimpor otomatis.'],
            ];
        }

        if ($confidence < 0.8) {
            $warnings[] = 'Confidence detector di bawah 0.80; validasi struktur sumber diperlukan sebelum import.';
        }

        if ($parser === null) {
            $warnings[] = 'Belum ada parser eksplisit untuk document type ini; import belum siap.';
        }

        if ($sheets !== null) {
            $structure = $this->structureValidator->validate($documentType, $sheets);

            if (! $structure['valid']) {
                $warnings[] = 'Struktur minimum sumber belum terpenuhi: ' . implode(', ', $structure['missing']) . '.';
            }
        } else {
            $structure = ['valid' => true, 'missing' => [], 'evidence' => []];
        }

        return [
            'parser' => $parser,
            'readiness' => $parser !== null
                && $confidence >= 0.8
                && $structure['valid']
                ? 'READY'
                : 'REVIEW',
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  callable(string):void|null  $onFile
     * @return array{files:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public function inspectDirectory(string $directory, ?callable $onFile = null): array
    {
        if (! is_dir($directory)) {
            throw new \InvalidArgumentException("Directory tidak ditemukan: {$directory}");
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['xlsx', 'xls'], true)) {
                continue;
            }

            if ($onFile !== null) {
                $onFile($file->getPathname());
            }

            try {
                $files[] = $this->inspect($file->getPathname());
            } catch (Throwable $e) {
                $files[] = [
                    'filename' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size_bytes' => $file->getSize(),
                    'sheets' => 0,
                    'source_rows' => 0,
                    'numeric_cells' => 0,
                    'document_type' => 'ERROR',
                    'source_category' => null,
                    'confidence' => 0.0,
                    'evidence' => [$e->getMessage()],
                    'parser' => null,
                    'readiness' => 'BLOCKED',
                    'warnings' => ['Workbook gagal diinspeksi: ' . $e->getMessage()],
                    'sheet_stats' => [],
                ];
            }
        }

        usort($files, fn (array $a, array $b) => strnatcasecmp($a['path'], $b['path']));

        $summary = [
            'total_files' => count($files),
            'by_document_type' => collect($files)->countBy('document_type')->all(),
            'by_source_category' => collect($files)->countBy('source_category')->all(),
            'unknown_or_error' => collect($files)->whereIn('document_type', ['unknown', 'ERROR'])->count(),
            'low_confidence' => collect($files)->where('confidence', '<', 0.8)->where('confidence', '>', 0)->count(),
            'total_sheets' => (int) collect($files)->sum('sheets'),
            'total_source_rows' => (int) collect($files)->sum('source_rows'),
            'total_numeric_cells' => (int) collect($files)->sum('numeric_cells'),
        ];

        return compact('files', 'summary');
    }
}
