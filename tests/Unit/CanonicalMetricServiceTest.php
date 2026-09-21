<?php

namespace Tests\Unit;

use App\Models\FinancialFact;
use App\Services\CanonicalMetricService;
use Tests\TestCase;

class CanonicalMetricServiceTest extends TestCase
{
    public function test_lra_revenue_is_normalized_from_statement_description(): void
    {
        $fact = new FinancialFact([
            'source_type' => 'financial_statement',
            'transaction_type' => 'LRA',
            'metric' => 'realisasi_2026',
            'value' => 123,
            'dimensions' => [
                'statement' => 'LRA',
                'description' => '4 PENDAPATAN DAERAH',
            ],
        ]);

        $result = app(CanonicalMetricService::class)->normalize(collect([$fact]));

        $this->assertSame('lra_revenue', $result->first()['canonical_metric']);
        $this->assertSame(123.0, $result->first()['value']);
    }

    public function test_budget_column_is_not_used_as_lra_actual(): void
    {
        $fact = new FinancialFact([
            'source_type' => 'financial_statement',
            'transaction_type' => 'LRA',
            'metric' => 'anggaran',
            'value' => 999,
            'dimensions' => [
                'statement' => 'LRA',
                'description' => '4 PENDAPATAN DAERAH',
                'column' => 'ANGGARAN',
            ],
        ]);

        $this->assertCount(0, app(CanonicalMetricService::class)->normalize(collect([$fact])));
    }

    public function test_realization_column_is_used_for_lra_actual(): void
    {
        $fact = new FinancialFact([
            'source_type' => 'financial_statement',
            'transaction_type' => 'LRA',
            'metric' => 'realisasi',
            'value' => 500,
            'dimensions' => [
                'statement' => 'LRA',
                'description' => '4 PENDAPATAN DAERAH',
                'column' => 'REALISASI',
            ],
        ]);

        $result = app(CanonicalMetricService::class)->normalize(collect([$fact]));

        $this->assertSame('lra_revenue', $result->first()['canonical_metric']);
        $this->assertSame(500.0, $result->first()['value']);
    }

    public function test_unmapped_financial_statement_row_is_not_invented_as_a_canonical_metric(): void
    {
        $fact = new FinancialFact([
            'source_type' => 'financial_statement',
            'transaction_type' => 'LRA',
            'metric' => 'realisasi_2026',
            'value' => 123,
            'dimensions' => [
                'statement' => 'LRA',
                'description' => 'Catatan Administratif',
            ],
        ]);

        $this->assertCount(0, app(CanonicalMetricService::class)->normalize(collect([$fact])));
    }
}
