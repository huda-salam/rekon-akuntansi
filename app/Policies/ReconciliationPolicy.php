<?php

namespace App\Policies;

use App\Models\Reconciliation;
use App\Models\User;

class ReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->skpd_id !== null;
    }

    public function view(User $user, Reconciliation $reconciliation): bool
    {
        return $user->isAdmin() || $user->skpd_id === $reconciliation->skpd_id;
    }

    public function update(User $user, Reconciliation $reconciliation): bool
    {
        return $reconciliation->status !== 'finalized'
            && ($user->isAdmin() || $user->skpd_id === $reconciliation->skpd_id);
    }

    public function finalize(User $user, Reconciliation $reconciliation): bool
    {
        return $reconciliation->status !== 'finalized' && $user->isAdmin();
    }
}
