<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The four tiered roles from §9.1 and the permissions they carry.
 *
 * Structural data — safe to run in production (§7.4).
 */
class RolesSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        User::ROLE_SUPER_ADMIN => ['*'],

        // Content only.
        User::ROLE_EDITOR => [
            'pages.view', 'pages.create', 'pages.update', 'pages.delete', 'pages.publish',
            'sections.manage',
            'solutions.manage', 'sectors.manage', 'products.manage',
            'impact.manage', 'stories.manage', 'reports.manage',
            'training.manage', 'partners.manage',
            'media.manage', 'navigation.manage',
        ],

        // Campaigns and landing pages.
        User::ROLE_CAMPAIGN_MANAGER => [
            'campaigns.view', 'campaigns.create', 'campaigns.update',
            'campaigns.delete', 'campaigns.publish',
            'sections.manage', 'media.manage',
            'leads.view', 'leads.export',
        ],

        // Leads only: read, export, change status (§9.1).
        User::ROLE_SALES => [
            'leads.view', 'leads.export', 'leads.update_status',
        ],
    ];

    private const EXTRA_PERMISSIONS = [
        'settings.manage', 'users.manage', 'redirects.manage', 'activity.view',
    ];

    public function run(): void
    {
        // Spatie caches the permission table. Without this the roles below
        // cannot see permissions created moments earlier in the same run.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->reject(fn (string $p): bool => $p === '*')
            ->merge(self::EXTRA_PERMISSIONS)
            ->unique();

        foreach ($all as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            // super-admin is granted everything via a Gate::before rule in
            // AuthServiceProvider, so it needs no explicit permission rows.
            if ($permissions === ['*']) {
                continue;
            }

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
