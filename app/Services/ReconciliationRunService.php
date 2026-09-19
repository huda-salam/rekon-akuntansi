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
    ): ReconciliationRun {
        $documents = SourceDocument::query()
            ->where('accounting_year_id', $yearId)
            ->when(
                $sourceDocumentIds !== [],
                fn ($query) => $query->whereIn('id', $sourceDocumentIds),
            )
            ->where('document_type', 'expenditure_reconciliation')
            ->get();

        if ($documents->isEmpty()) {
            throw new RuntimeException('No expenditure reconciliation source document is available for the selected accounting year.');
        }

        $documentIds = $documents->pluck('id')->values()->all();

        $rules = ReconciliationRule::query()
            ->where('status', 'active')
            ->where('category', 'expenditure')
            ->where(function ($query) use ($yearId) {
                $query->whereNull('accounting_year_id')
                    ->orWhere('accounting_year_id', $yearId);
            })
            ->orderBy('code')
            ->get();

        if ($rules->isEmpty()) {
            throw new RuntimeException('No active expenditure reconciliation rules are configured.');
        }

        $run = ReconciliationRun::create([
            'accounting_year_id' => $yearId,
            'month' => $month,
            'started_by' => $startedBy,
            'status' => 'running',
            'started_at' => now(),
            'source_document_ids' => $documentIds,
            'parameters' => $parameters + ['month' => $month],
        ]);

        try {
            $facts = FinancialFact::query()
                ->where('accounting_year_id', $yearId)
                ->when($month !== null, fn ($query) => $query->where('month', $month))
                ->whereIn('source_document_id', $documentIds)
                ->whereIn('source_type', ['expenditure_reconciliation', 'rekonsiliasi_pengeluaran'])
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

    public function listForUser($user, int $yearId, ?int $month = null): Collection
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

        if (! $user->isAdmin()) {
            $query->whereHas('results', fn ($q) => $q->where('skpd_id', $user->skpd_id));
        }

        return $query->get();
    }
}
