<?php

use App\Services\SourceWorkbookInspectionService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('rekon:about', function () {
    $this->info('Rekon Akuntansi - aplikasi rekonsiliasi akuntansi pemerintah daerah.');
});


Artisan::command('rekon:dry-run-sources
    {path : Directory containing source .xls/.xlsx files}
    {year : Accounting year}
    {--user-id= : Existing user id used only inside the rolled-back dry-run transaction}
    {--json= : Optional output JSON file path}', function (string $path, int $year, ?string $userId = null, ?string $json = null) {
    if (! is_dir($path)) {
        $this->error("Directory tidak ditemukan: {$path}");
        return 1;
    }

    $resolvedUserId = $userId !== null ? (int) $userId : 0;

    $files = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['xlsx', 'xls'], true)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $this->info("Dry-run parser validation: {$year}");
    $this->line('Directory: ' . realpath($path));
    $this->line('Files: ' . count($files));
    $this->newLine();

    $results = [];
    $failed = [];

    foreach ($files as $index => $file) {
        $this->line(sprintf('[%d/%d] %s', $index + 1, count($files), basename($file)));

        try {
            $result = app(\App\Services\SourceWorkbookImportService::class)
                ->dryRun($file, $year, $resolvedUserId);

            $results[] = $result;
            $qualityStatus = $result['quality']['valid'] ? 'PASS' : 'FAIL';

            $this->line(sprintf(
                '  %s type=%s records=%d facts=%d rollback=%s',
                $qualityStatus,
                $result['document_type'],
                $result['source_records'],
                $result['financial_facts'],
                $result['rolled_back'] ? 'YES' : 'NO'
            ));

            foreach ($result['quality']['errors'] ?? [] as $error) {
                $this->error('    ERROR: ' . $error);
            }

            foreach ($result['quality']['warnings'] ?? [] as $warning) {
                $this->warn('    WARNING: ' . $warning);
            }

            if ($result['document_type'] === 'ledger' && ($result['quality']['stats']['ledger_facts_without_account'] ?? 0) > 0) {
                $this->warn('    Source columns: ' . implode(' | ', $result['source_columns'] ?? []));
            }

            foreach (array_slice($result['sample_facts'], 0, 3) as $fact) {
                $this->line(sprintf(
                    '    %s=%s | value=%s | account=%s | date=%s | period=%s',
                    $fact['metric'],
                    $fact['transaction_type'],
                    $fact['value'],
                    $fact['account_code'] ?? '-',
                    $fact['fact_date'] ?? '-',
                    $fact['period'] ?? '-'
                ));
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $context = '';

            if (preg_match('/sheet "([^"]+)",? baris (\d+)/iu', $message, $matches) === 1) {
                $context = sprintf(' [sheet=%s row=%d]', $matches[1], (int) $matches[2]);
            } elseif (preg_match('/(?:row|baris)\s*[:=]?\s*(\d+)/iu', $message, $matches) === 1) {
                $context = sprintf(' [row=%d]', (int) $matches[1]);
            }

            $failed[] = [
                'filename' => basename($file),
                'message' => $message,
                'exception' => get_class($e),
                'context' => $context,
            ];

            $this->error('  ERROR' . $context . ': ' . $message);
        }
    }

    $this->newLine();
    $this->table(
        ['Metric', 'Value'],
        [
            ['Files', count($files)],
            ['Parsed OK', count($results)],
            ['Failed', count($failed)],
            ['Transactions rolled back', collect($results)->every(fn (array $item) => $item['rolled_back'] === true) ? 'YES' : 'NO'],
            ['Source records', (int) collect($results)->sum('source_records')],
            ['Financial facts', (int) collect($results)->sum('financial_facts')],
            ['Quality PASS', collect($results)->where('quality.valid', true)->count()],
            ['Quality FAIL', collect($results)->where('quality.valid', false)->count()],
        ]
    );

    if ($failed !== []) {
        $this->newLine();
        $this->warn('Dry-run failures');

        foreach ($failed as $item) {
            $context = $item['context'] ?? '';
            $this->line("  {$item['filename']}{$context}: {$item['message']}");
        }
    }

    if ($json !== null) {
        $directory = dirname($json);
        if ($directory !== '.' && ! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $json,
            json_encode([
                'year' => $year,
                'directory' => realpath($path),
                'results' => $results,
                'failed' => $failed,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info("JSON report: {$json}");
    }

    $qualityFailed = collect($results)->where('quality.valid', false)->count();

    return ($failed === [] && $qualityFailed === 0) ? 0 : 1;
})->purpose('Run source parsers in a database transaction and roll back all changes.');

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


    $needsReview = array_values(array_filter(
        $report['files'],
        fn (array $file) => in_array($file['readiness'] ?? null, ['REVIEW', 'BLOCKED'], true)
    ));

    if ($needsReview !== []) {
        $this->newLine();
        $this->warn('Structure review candidates');

        foreach ($needsReview as $file) {
            $this->line(sprintf(
                '  %s [%s] %s',
                $file['filename'],
                $file['readiness'],
                implode('; ', $file['evidence'] ?? [])
            ));

            foreach ($file['structure_profile'] ?? [] as $sheet) {
                $labels = array_slice($sheet['labels'] ?? [], 0, 12);
                if ($labels === []) {
                    continue;
                }

                $this->line('    Sheet: ' . $sheet['name']);
                $this->line('      Labels: ' . implode(' | ', $labels));
            }
        }
    }

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
