<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveYear
{
    public function handle(Request $request, Closure $next): Response
    {
        $year = app(\App\Models\AccountingYear::class)->newQuery()->where('is_active', true)->first();

        if (! $year) {
            return response()->json(['message' => 'Belum ada tahun akuntansi aktif.'], 422);
        }

        $request->attributes->set('active_year', $year);

        return $next($request);
    }
}
