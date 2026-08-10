<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * §9.1: the `editor` role owns content. Publishing is separated from editing
 * so drafting and going live can be different permissions if the client later
 * wants an approval step.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pages.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('pages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pages.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('pages.update');
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->can('pages.publish');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('pages.delete');
    }
}
