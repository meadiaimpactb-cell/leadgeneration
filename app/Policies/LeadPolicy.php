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

    /**
     * Archiving — the panel's substitute for the delete that will never exist.
     *
     * The need it answers is real and was not served at all: demo rows seeded
     * before launch, and obvious spam afterwards, sat in the list forever with
     * no way to get them out of the way. An editor who cannot tidy a list
     * stops trusting it, and a list nobody trusts stops being read.
     *
     * Granted to whoever may already move a lead to «lost», because that is
     * the same act of judgement about the same record — deciding it is not a
     * prospect. It removes the row from one list and nothing else: the row,
     * its attribution and its CRM history all stay exactly where they were,
     * and the dashboard keeps counting it.
     *
     * That is why this is a separate ability rather than a relaxation of
     * `delete()` above. `delete()` stays false for everyone, forever.
     */
    public function archive(User $user, Lead $lead): bool
    {
        return $user->can('leads.update_status');
    }
}
