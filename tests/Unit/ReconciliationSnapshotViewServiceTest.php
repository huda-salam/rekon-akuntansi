<?php

namespace Tests\Unit;

use App\Models\ReconciliationSnapshot;
use App\Models\ReconciliationSnapshotResult;
use App\Services\ReconciliationSnapshotViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationSnapshotViewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_view_builds_audit_summary_without_mutating_results(): void
    {
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
            'snapshot_payload' => [],
        ]);

        ReconciliationSnapshotResult::create([
            'snapshot_id' => $snapshot->id,
            'source_result_id' => 10,
            'rule_code' => 'REV-001',
            'rule_name' => 'Test PASS',
            'category' => 'revenue',
            'status' => 'PASS',
            'expected_value' => 0,
            'actual_value' => 0,
            'variance' => 0,
        ]);

        ReconciliationSnapshotResult::create([
            'snapshot_id' => $snapshot->id,
            'source_result_id' => 11,
            'rule_code' => 'REV-002',
            'rule_name' => 'Test VARIANCE',
            'category' => 'revenue',
            'status' => 'VARIANCE',
            'expected_value' => 0,
            'actual_value' => 100,
            'variance' => 100,
            'review' => [
                'status' => 'ACCEPTED',
                'note' => 'Ada bukti pendukung.',
                'evidence' => ['ref' => 'BA-001'],
            ],
        ]);

        $view = app(ReconciliationSnapshotViewService::class)->build($snapshot);

        $this->assertSame(2, $view['summary']['total_controls']);
        $this->assertSame(1, $view['summary']['pass']);
        $this->assertSame(1, $view['summary']['variance']);
        $this->assertSame(1, $view['summary']['resolved_or_accepted']);
        $this->assertSame(0, $view['summary']['unresolved']);
        $this->assertSame('100.00', $view['summary']['total_absolute_variance']);
        $this->assertSame('VARIANCE', $view['results'][1]['status']);
        $this->assertSame('ACCEPTED', $view['results'][1]['review']['status']);
    }
}
