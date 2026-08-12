<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Navigation;
use App\Support\NavigationBuilder;
use Database\Seeders\Concerns\SeedsRows;
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
    use SeedsRows;

    public function run(): void
    {
        foreach ([Navigation::HEADER, Navigation::FOOTER_MAIN, Navigation::FOOTER_LEGAL] as $key) {
            // Enforced, not set-on-create. NavigationBuilder skips any menu
            // whose `is_active` is false, and the panel edits menu ITEMS —
            // there is no switch anywhere for the menu row itself. So an
            // inactive one is not a decision anybody made; it is a row born
            // wrong, and its whole menu disappears from every page with no
            // error to explain it.
            $this->seedRow(
                Navigation::query(),
                identity: ['key' => $key],
                structure: ['is_active' => true],
            );
        }

        NavigationBuilder::flush();
    }
}
