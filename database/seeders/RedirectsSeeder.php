<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Redirect;
use Illuminate\Database\Seeder;

/**
 * Legacy paths kept alive after the navigation was restructured (§22.7).
 *
 * Two moves are covered:
 *
 *  · The four audience segments left `/sectors/{slug}` for `/solutions/{slug}`,
 *    and two of them were also renamed. Every old address maps to the exact
 *    page that replaced it — not to a hub — because a 301 to a list is a
 *    dead end for anyone who followed a link to a specific segment.
 *
 *  · `/products` was taken out of the header. The page itself is untouched
 *    and the showroom section still links to it; only the menu entry went.
 *    No redirect is written for it, deliberately — see the note below.
 *
 * Structural, idempotent, and safe in production: it only ever adds rows the
 * client has not already edited.
 */
class RedirectsSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            'government-entities' => 'government',
            'private-sector' => 'companies',
            'partners' => 'partners',
            'artisans' => 'artisans',
        ];

        foreach (array_keys(config('site.locales')) as $locale) {
            foreach ($segments as $old => $new) {
                $this->add("/{$locale}/sectors/{$old}", "/{$locale}/solutions/{$new}");
            }
        }
    }

    /**
     * Never overwrites an existing row: if the client has already redirected
     * a path somewhere, that decision outranks this file.
     */
    private function add(string $from, string $to): void
    {
        Redirect::query()->firstOrCreate(
            ['from_path' => $from],
            ['to_path' => $to, 'status_code' => 301, 'is_active' => true],
        );
    }
}
