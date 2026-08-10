<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

/**
 * §9.1: the `campaign-manager` role owns campaigns and landing pages.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.update');
    }

    public function publish(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.publish');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete');
    }
}
