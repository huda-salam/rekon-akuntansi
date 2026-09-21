<?php

namespace Tests\Unit;

use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use App\Services\FinancialFactAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialFactAggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_skpd_month_scope_aggregates_each_metric_and_preserves_lineage(): void
    {
        $rule = new ReconciliationRule([
            'scope' => 'skpd_month',
            'input_metrics' => ['debit', 'credit'],
        ]);

        $facts = collect([
            new FinancialFact(['id' => 11, 'skpd_id' => 7, 'month' => 9, 'metric' => 'debit', 'value' => 100]),
            new FinancialFact(['id' => 12, 'skpd_id' => 7, 'month' => 9, 'metric' => 'debit', 'value' => 50]),
            new FinancialFact(['id' => 13, 'skpd_id' => 7, 'month' => 9, 'metric' => 'credit', 'value' => 25]),
            new FinancialFact(['id' => 14, 'skpd_id' => 8, 'month' => 9, 'metric' => 'debit', 'value' => 999]),
        ]);

        $grains = app(FinancialFactAggregationService::class)->grains($facts, $rule);

        $this->assertCount(2, $grains);
        $first = $grains->first(fn ($grain) => $grain['skpd_id'] === 7);
        $this->assertNotNull($first);

        $this->assertSame(150.0, $first['facts']->firstWhere('metric', 'debit')->value);
        $this->assertSame(25.0, $first['facts']->firstWhere('metric', 'credit')->value);
        $this->assertSame([11, 12], $first['facts']->firstWhere('metric', 'debit')->lineage_ids);
        $this->assertSame([11, 12, 13], $first['financial_fact_ids']);
    }
}
