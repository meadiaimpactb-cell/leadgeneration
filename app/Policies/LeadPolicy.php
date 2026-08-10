<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * §9.1: the `sales` role sees leads only — read, export, change status.
 * It can never delete one: a lead is the site's only record of a real
 * prospect and its audit trail must survive a change of mind.
 *
 * super-admin bypasses all of this via the Gate::before rule in
 * AppServiceProvider.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can('leads.view');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('leads.update_status');
    }

    public function export(User $user): bool
    {
        return $user->can('leads.export');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return false;
    }
}
