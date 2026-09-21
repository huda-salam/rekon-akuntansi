<?php

namespace Tests\Unit;

use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use App\Services\CrossSourceReconciliationService;
use Tests\TestCase;

class CrossSourceReconciliationServiceTest extends TestCase
{
    public function test_annual_financial_statement_can_be_compared_to_monthly_ledger(): void
    {
        $rule = new ReconciliationRule([
            'tolerance' => 0,
            'metadata' => [
                'left' => [
                    'source_type' => 'ledger',
                    'canonical_metrics' => ['lra_revenue'],
                ],
                'right' => [
                    'source_type' => 'financial_statement',
                    'canonical_metrics' => ['lra_revenue'],
                    'period_mode' => 'any',
                    'skpd_mode' => 'exact',
                ],
            ],
        ]);

        $ledger = new FinancialFact([
            'source_document_id' => 1,
            'skpd_id' => 7,
            'month' => 9,
            'source_type' => 'ledger',
            'transaction_type' => 'CREDIT',
            'account_code' => '4.1',
            'value' => 1000,
        ]);

        $statement = new FinancialFact([
            'source_document_id' => 2,
            'skpd_id' => 7,
            'month' => null,
            'source_type' => 'financial_statement',
            'transaction_type' => 'LRA',
            'account_code' => '4',
            'value' => 1000,
            'dimensions' => [
                'statement' => 'LRA',
                'description' => 'PENDAPATAN DAERAH',
                'column' => 'REALISASI',
                'summary_row' => true,
            ],
        ]);

        $result = app(CrossSourceReconciliationService::class)->compare(
            collect([$ledger, $statement]),
            $rule,
            7,
            9,
        );

        $this->assertSame(1000.0, $result['left']);
        $this->assertSame(1000.0, $result['right']);
        $this->assertSame('PASS', $result['status']);
    }

    public function test_it_compares_two_source_families_without_merging_their_facts(): void
    {
        $rule = new ReconciliationRule([
            'tolerance' => 0,
            'metadata' => [
                'left' => [
                    'source_type' => 'ledger',
                    'metrics' => ['realisasi'],
                ],
                'right' => [
                    'source_type' => 'financial_statement',
                    'metrics' => ['realisasi_2026'],
                ],
            ],
        ]);

        $facts = collect([
            new FinancialFact([
                'source_document_id' => 1,
                'skpd_id' => 7,
                'month' => 9,
                'source_type' => 'ledger',
                'metric' => 'realisasi',
                'value' => 1000,
            ]),
            new FinancialFact([
                'source_document_id' => 2,
                'skpd_id' => 7,
                'month' => 9,
                'source_type' => 'financial_statement',
                'metric' => 'realisasi_2026',
                'value' => 1000,
            ]),
        ]);

        $result = app(CrossSourceReconciliationService::class)->compare($facts, $rule, 7, 9);

        $this->assertSame(1000.0, $result['left']);
        $this->assertSame(1000.0, $result['right']);
        $this->assertSame(0.0, $result['variance']);
        $this->assertSame('PASS', $result['status']);
    }
}
