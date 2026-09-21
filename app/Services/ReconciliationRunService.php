<?php

namespace App\Services;

use App\Models\FinancialFact;
use App\Models\ReconciliationRule;
use App\Models\ReconciliationRun;
use App\Models\SourceDocument;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class ReconciliationRunService
{
    public function execute(
        int $yearId,
        int $startedBy,
        array $sourceDocumentIds = [],
        array $parameters = [],
        ?int $month = null,
        string $category = 'expenditure',
    ): ReconciliationRun {
        $documentTypes = match ($category) {
            'revenue' => ['revenue_reconciliation', 'ledger', 'financial_statement', 'non_rkud_transfer'],
            'expenditure' => ['expenditure_reconciliation', 'ledger', 'financial_statement'],
            'accounting' => ['ledger', 'financial_statement'],
            default => throw new RuntimeException("Reconciliation category [{$category}] is not supported."),
        };

        $documents = SourceDocument::query()
            ->where('accounting_year_id', $yearId)
            ->when(
                $sourceDocumentIds !== [],
                fn ($query) => $query->whereIn('id', $sourceDocumentIds),
            )
            ->whereIn('document_type', $documentTypes)
            ->get();

        if ($documents->isEmpty()) {
            throw new RuntimeException('No reconciliation source document is available for the selected accounting year and category.');
        }

        $documentIds = $documents->pluck('id')->values()->all();

        $rules = ReconciliationRule::query()
            ->where('status', 'active')
            ->where('category', $category)
            ->where(function ($query) use ($yearId) {
                $query->whereNull('accounting_year_id')
                    ->orWhere('accounting_year_id', $yearId);
            })
            ->orderBy('code')
            ->get();

        if ($rules->isEmpty()) {
            throw new RuntimeException('No active reconciliation rules are configured for the selected category.');
        }

        $run = ReconciliationRun::create([
            'accounting_year_id' => $yearId,
            'month' => $month,
            'started_by' => $startedBy,
            'status' => 'running',
            'started_at' => now(),
            'source_document_ids' => $documentIds,
            'parameters' => $parameters + ['month' => $month, 'category' => $category],
        ]);

        try {
            $facts = FinancialFact::query()
                ->where('accounting_year_id', $yearId)
                ->when($month !== null, function ($query) use ($month) {
                    // Keep annual snapshot facts (month IS NULL) available for
                    // accounting rules while still restricting monthly facts.
                    $query->where(function ($period) use ($month) {
                        $period->where('month', $month)->orWhereNull('month');
                    });
                })
                ->whereIn('source_document_id', $documentIds)
                ->whereIn('source_type', $documentTypes)
                ->get();

            if ($facts->isEmpty()) {
                throw new RuntimeException('The selected source document has no imported financial facts for the requested period.');
            }

            app(ReconciliationEngine::class)->run($run, $rules, $facts);

            return $run->fresh(['year', 'starter', 'results.rule', 'results.skpd']);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function listForUser($user, int $yearId, ?int $month = null, ?string $category = null): Collection
    {
        $query = ReconciliationRun::query()
            ->where('accounting_year_id', $yearId)
            ->when($month !== null, fn ($query) => $query->where('month', $month))
            ->withCount([
                'results',
                'results as pass_results_count' => fn ($query) => $query->where('status', 'PASS'),
                'results as variance_results_count' => fn ($query) => $query->where('status', 'VARIANCE'),
                'results as incomplete_results_count' => fn ($query) => $query->where('status', 'INCOMPLETE'),
            ])
            ->latest('id');

        if ($category !== null) {
            $query->whereJson('parameters->category', $category);
        }

        if (! $user->isAdmin()) {
            $query->whereHas('results', fn ($q) => $q->where('skpd_id', $user->skpd_id));
        }

        return $query->get();
    }
}
