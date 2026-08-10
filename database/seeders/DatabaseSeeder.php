<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Structural data only — safe to run in production (§7.4).
 *
 * There is deliberately no demo user here: the admin account is created with
 * `php artisan amad:create-admin`, so no known-password account can reach a
 * live environment by accident.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            StructureSeeder::class,
            LeadFieldsSeeder::class,
            NavigationSeeder::class,
        ]);
    }
}
