<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SplFileInfo;
use Throwable;

class SourceWorkbookInspectionService
{
    public function __construct(
        private readonly SourceDocumentDetector $detector,
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
        $spreadsheet = $reader->load($path);

        $sheets = new Collection();
        $sheetStats = [];
        $sourceRows = 0;
        $numericCells = 0;

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = collect();
            $sheetNumericCells = 0;

            foreach ($worksheet->toArray(null, true, true, false) as $row) {
                $values = array_values($row);
                $rows->push($values);

                foreach ($values as $value) {
                    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
                        $sheetNumericCells++;
                    }
                }
            }

            $rowCount = $rows->count();
            $nonEmptyRows = $rows->filter(
                fn (array $row) => collect($row)->contains(
                    fn ($value) => trim((string) ($value ?? '')) !== ''
                )
            )->count();

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
            'sheet_stats' => $sheetStats,
        ];
    }

    /**
     * @return array{files:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public function inspectDirectory(string $directory): array
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
