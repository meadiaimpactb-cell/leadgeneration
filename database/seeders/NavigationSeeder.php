<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Navigation;
use App\Support\NavigationBuilder;
use Illuminate\Database\Seeder;

/**
 * Creates the three empty menus (§8.4).
 *
 * Their items are added by the client in the admin panel — the header order
 * shown in §11.1 is a wireframe, not a fixed menu, and hard-coding it here
 * would put content in the seeder.
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Navigation::HEADER, Navigation::FOOTER_MAIN, Navigation::FOOTER_LEGAL] as $key) {
            Navigation::query()->firstOrCreate(['key' => $key], ['is_active' => true]);
        }

        NavigationBuilder::flush();
    }
}
