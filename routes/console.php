<?php

use App\Services\SourceWorkbookInspectionService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('rekon:about', function () {
    $this->info('Rekon Akuntansi - aplikasi rekonsiliasi akuntansi pemerintah daerah.');
});

Artisan::command('rekon:inspect-sources
    {path : Directory containing source .xls/.xlsx files}
    {--json= : Optional output JSON file path}', function (string $path, ?string $json = null) {
    $this->info('Scanning source workbooks...');
    $this->line('Directory: ' . realpath($path));

    $report = app(SourceWorkbookInspectionService::class)->inspectDirectory(
        $path,
        fn (string $file) => $this->line('  Inspecting: ' . basename($file))
    );

    $this->newLine();
    $this->info('Source Workbook Inspection');
    $this->line('Directory: ' . realpath($path));
    $this->newLine();

    $summary = $report['summary'];

    $this->table(
        ['Metric', 'Value'],
        [
            ['Total files', $summary['total_files']],
            ['Total sheets', $summary['total_sheets']],
            ['Source rows', $summary['total_source_rows']],
            ['Numeric cells', $summary['total_numeric_cells']],
            ['Unknown / error', $summary['unknown_or_error']],
            ['Low confidence', $summary['low_confidence']],
        ]
    );

    $this->newLine();

    $rows = array_map(
        fn (array $file) => [
            $file['filename'],
            $file['document_type'],
            $file['source_category'] ?? '-',
            number_format((float) $file['confidence'], 2),
            $file['sheets'],
            $file['source_rows'],
            $file['numeric_cells'],
            $file['readiness'],
            $file['parser'] ?? '-',
        ],
        $report['files']
    );

    $this->table(
        ['File', 'Type', 'Category', 'Confidence', 'Sheets', 'Rows', 'Numeric', 'Readiness', 'Parser'],
        $rows
    );

    if ($json !== null) {
        $directory = dirname($json);

        if ($directory !== '.' && ! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $json,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info("JSON report: {$json}");
    }
})->purpose('Inspect and classify source workbooks without importing them.');
