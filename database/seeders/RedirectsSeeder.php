<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Redirect;
use Database\Seeders\Concerns\SeedsRows;
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
    use SeedsRows;

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
     * Nothing here is enforced, and that is the decision — not an oversight.
     *
     * Every column below is editable in the redirects screen: the target, the
     * status code and the active switch. If the client has already pointed
     * `/ar/sectors/artisans` somewhere else, or turned the rule off because
     * it was sending traffic to the wrong page, that outranks this file.
     * Re-imposing 301 → /solutions/artisans on every seeder run would undo a
     * live SEO decision silently, which is the failure §22.7 is about.
     */
    private function add(string $from, string $to): void
    {
        $this->seedRow(
            Redirect::query(),
            identity: ['from_path' => $from],
            owned: ['to_path' => $to, 'status_code' => 301, 'is_active' => true],
        );
    }
}
