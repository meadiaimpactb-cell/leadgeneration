<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Middleware\ApplyRedirects;
use App\Models\Redirect;
use Database\Seeders\Concerns\SeedsRows;
use Illuminate\Database\Seeder;

/**
 * Collapses the multi-page site into the one landing page (§22.7).
 *
 * ────────────────────────────────────────────────────────────────────────
 *  NOT WIRED INTO DatabaseSeeder OR TestSeeder. RUN IT DELIBERATELY:
 *      php artisan db:seed --class=LandingRedirectsSeeder
 * ────────────────────────────────────────────────────────────────────────
 *
 * WHY IT IS NOT AUTOMATIC
 *
 * `ApplyRedirects` is a global `prepend` middleware, so it answers BEFORE the
 * router. The moment a row here exists, the page it names stops being
 * reachable — the route still exists and will never be reached. Running this
 * is therefore the same act as retiring those eleven pages, and that is a
 * decision for Amad Craft rather than a side effect of a deployment.
 *
 * WHAT IT COSTS AND WHAT IT DOES NOT
 *
 * Eleven indexed URLs become one. Google consolidates their signals into the
 * target over the following weeks; deep links from anywhere keep working, at
 * a 301, which is what §22.7 requires and what §16 calls the hard condition.
 *
 * The usual objection — that collapsing indexed pages into one throws away
 * accumulated ranking — is much weaker here than it looks, and the reason is
 * measurable: server-side rendering is currently DOWN in production, so those
 * eleven pages have been serving Google an empty `<div id="app">` with no
 * title. There is very little indexed signal to lose. That is an argument for
 * doing this now rather than after SSR is fixed and the pages have earned
 * standing worth preserving.
 *
 * WHERE EACH ONE POINTS
 *
 * To the anchor whose content actually replaced it, never to the bare page.
 * A visitor who followed a link about artisans should arrive at the artisan
 * section, not at the top of a very long document with no idea why they moved.
 *
 * Sections have no page of their own any more, so `/impact`, `/training` and
 * `/products` have no successor block on the landing page. They point at the
 * root: honest, and better than an anchor that does not answer their question.
 */
class LandingRedirectsSeeder extends Seeder
{
    use SeedsRows;

    public function run(): void
    {
        /** Old path (locale-relative) => fragment on the landing page. */
        $moves = [
            'about' => '',
            'solutions' => '#home',
            'solutions/government' => '#government',
            'solutions/companies' => '#government',
            'solutions/partners' => '#partners',
            'solutions/artisans' => '#artisans',
            'contact' => '#contact',

            // No successor block: the landing page does not carry an impact,
            // training or product section. The root is the honest answer.
            'impact' => '',
            'impact/stories' => '',
            'training' => '',
            'products' => '',
            'partners' => '#partners',
        ];

        /*
         * Pages Amad Craft asked to remove (7 September 2026), beyond the
         * eleven the landing page replaced.
         *
         * `our-service` was published and in the sitemap, so deleting it
         * without a rule here would turn an indexed URL into a 404 — the one
         * thing §22.7 forbids outright. `verify-primary-live` was a page left
         * over from testing: unlisted, but live, so it is covered too rather
         * than left to guess.
         *
         * The `home` page needs no rule: `/{locale}/home` has never resolved,
         * because PageController reserves the slug (Page::ROOT_SLUGS).
         */
        $removed = ['our-service', 'verify-primary-live'];

        foreach (array_keys(config('site.locales')) as $locale) {
            foreach ($moves as $old => $fragment) {
                $this->add("/{$locale}/{$old}", "/{$locale}{$fragment}");
            }

            foreach ($removed as $old) {
                $this->add("/{$locale}/{$old}", "/{$locale}");
            }
        }

        $this->collapseChains();

        /*
         * The middleware caches the active set for an hour, so without this
         * the rows are right and the site keeps serving the old ones. The
         * redirects screen already flushes on save; a seeder has to as well.
         */
        ApplyRedirects::flush();
    }

    /**
     * No redirect points at another redirect (§13).
     *
     * The earlier restructure left rows carrying `/{locale}/sectors/{slug}` to
     * `/{locale}/solutions/{slug}`. Retiring the solutions pages turned every
     * one of those into the first hop of a two-hop chain — a crawler follows
     * two 301s and a share of the signal is lost at each, which is exactly
     * what §13's "no redirect chains" is about.
     *
     * Retargeted rather than left alone, because the old destination no
     * longer resolves: this is repairing a row that has become wrong, not
     * overruling a decision an administrator made.
     */
    private function collapseChains(): void
    {
        $targets = Redirect::query()
            ->where('is_active', true)
            ->pluck('to_path', 'from_path');

        foreach (Redirect::query()->where('is_active', true)->get() as $rule) {
            $to = $rule->to_path;
            $seen = [];

            // Follow the chain to its end, guarding against a cycle a client
            // could create in the panel.
            while (isset($targets[$to]) && ! isset($seen[$to])) {
                $seen[$to] = true;
                $to = $targets[$to];
            }

            if ($to !== $rule->to_path) {
                $rule->forceFill(['to_path' => $to])->save();
            }
        }
    }

    /**
     * Idempotent, and never overrides a row the client has since edited —
     * the same rule RedirectsSeeder follows, for the same reason: a redirect
     * an administrator retargeted in the panel is a decision, not drift.
     */
    private function add(string $from, string $to): void
    {
        $this->seedRow(
            Redirect::query(),
            identity: ['from_path' => $from],
            // No `$structure`: every column here is the client's to change in
            // the redirects screen, and re-imposing this file's target on
            // each run would silently undo a live SEO decision (§22.7).
            owned: ['to_path' => $to, 'status_code' => 301, 'is_active' => true],
        );
    }
}
