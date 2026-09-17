<?php

namespace App\Providers;

use App\Models\Reconciliation;
use App\Policies\ReconciliationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Reconciliation::class, ReconciliationPolicy::class);
    }
}
