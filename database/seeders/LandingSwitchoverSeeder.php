<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LeadField;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Redirect;
use App\Support\NavigationBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The site-wide half of the landing-page decision (7 September 2026).
 *
 * Two changes that reach beyond the new page: the header stops linking to
 * other pages and starts scrolling to sections, and the form drops to the two
 * controls the brief names.
 *
 * SPLIT OUT OF LandingPageSeeder ON PURPOSE. Seeding the page's copy is
 * additive and safe anywhere. These two are not — they switch off fields and
 * menu items that other parts of the site exercise, so they belong under their
 * own deliberate call rather than riding along with content.
 *
 * Run in production; called explicitly by the tests that assert the new shape.
 */
class LandingSwitchoverSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu();
        $this->footerMenus();
        $this->leadFields();

        NavigationBuilder::flush();
        LeadField::flushCache();
    }

    /**
     * The five header destinations, as rows in `navigations`.
     *
     * In the table rather than in the component so the client can rename or
     * reorder them without a developer (§9.1) — the same place every other
     * menu on the site lives. The URLs are fragments, which
     * NavigationItem::localised() passes through untouched, so one row serves
     * both languages.
     *
     * The existing header items are DEACTIVATED, not deleted: they point at
     * pages that still answer, and switching them back on in the panel is how
     * this is reversed if management changes its mind.
     */
    private function menu(): void
    {
        $navigation = Navigation::query()->firstOrCreate(['key' => 'header'], ['is_active' => true]);

        /*
         * Stored WITH the locale, not as a bare fragment.
         *
         * `#government` works only while the visitor is already on the landing
         * page. The same menu renders on the legal pages, where those links
         * pointed at sections that do not exist there and did nothing at all
         * when clicked. `/ar#government` is a real address: from the landing
         * page the header scrolls to it, and from anywhere else the browser
         * goes to the landing page and lands on the section.
         *
         * NavigationItem::localised() rewrites the locale segment and keeps
         * the fragment, so one row serves both languages.
         */
        $items = [
            ['/ar#home', 'الرئيسية', 'Home'],
            ['/ar#government', 'الجهات', 'Entities'],
            ['/ar#partners', 'الشركاء', 'Partners'],
            ['/ar#artisans', 'الحرفيون', 'Artisans'],
            ['/ar#contact', 'تواصل معنا', 'Contact us'],
        ];

        $urls = array_column($items, 0);

        NavigationItem::query()
            ->where('navigation_id', $navigation->id)
            ->where(fn ($q) => $q->whereNull('url')->orWhereNotIn('url', $urls))
            ->update(['is_active' => false]);

        $order = 0;

        foreach ($items as [$url, $ar, $en]) {
            $order += 10;

            $item = NavigationItem::query()->firstOrCreate(
                ['navigation_id' => $navigation->id, 'url' => $url],
                ['sort_order' => $order, 'is_active' => true],
            );

            $item->translations()->firstOrCreate(['locale' => 'ar'], ['label' => $ar]);
            $item->translations()->firstOrCreate(['locale' => 'en'], ['label' => $en]);
        }
    }

    /**
     * No menu links to something that redirects.
     *
     * The header was rebuilt on the five anchors, and the three footer menus
     * were left pointing at `/ar/solutions`, `/ar/impact`, `/ar/about` and
     * five more — every one of which now answers 301. A footer full of
     * redirects costs a hop on each click, spends crawl budget on nothing,
     * and tells a visitor the site has pages it does not have.
     *
     * The destination is READ FROM THE `redirects` TABLE rather than written
     * again here. That table is already the one place that knows where each
     * retired path went (§22.7), so a client who retargets a redirect in the
     * panel does not leave the footer behind.
     *
     * An item whose destination carries no anchor — `/ar/impact` goes to the
     * top of the landing page and nowhere more specific — is switched off
     * instead. A menu entry that scrolls to where the reader already is reads
     * as a broken link, not as a link.
     */
    private function footerMenus(): void
    {
        $redirects = Redirect::query()
            ->where('is_active', true)
            ->pluck('to_path', 'from_path');

        $items = NavigationItem::query()
            ->where('is_active', true)
            ->whereNotNull('url')
            ->whereHas('navigation', fn ($q) => $q->where('key', '!=', 'header'))
            ->get();

        foreach ($items as $item) {
            $to = $redirects->get('/'.trim((string) $item->url, '/'));

            if ($to === null) {
                continue;
            }

            // `/ar#partners` keeps the entry; a bare `/ar` does not.
            $anchor = str_contains($to, '#') ? '/ar#'.Str::after($to, '#') : null;

            $item->forceFill(
                $anchor === null
                    ? ['is_active' => false]
                    : ['url' => $anchor]
            )->save();
        }
    }

    /**
     * The two visible controls the approved form has: a name, and one way to
     * reach the person.
     *
     * THIS DEPARTS FROM §6.1 OF THE BRIEF, WHICH SAYS A NAME IS NEVER ASKED.
     * The management brief of 7 September 2026 requires it — «الحقول الظاهرة
     * للمستخدم — خانتان فقط لا غير: الاسم، وسيلة التواصل» — and that is the
     * later written decision, so it wins. The trade it makes is real and
     * should be watched: §6.1 kept the form at one field because every extra
     * control costs completions, and completions are the only thing this site
     * is measured on (§1).
     *
     * No migration is involved. `lead_fields` already drives what the form
     * renders and what StoreLeadRequest validates, and a non-reserved field's
     * answer lands in the leads table's `extra` JSON column.
     *
     * The optional message is switched OFF, because the brief fixes the
     * visible fields at two and names them. The row is kept so switching it
     * back on is a toggle in the panel.
     */
    private function leadFields(): void
    {
        /*
         * `name` already exists as a row — LeadFieldsSeeder ships it switched
         * OFF, with its labels already written, precisely so it could be
         * turned on the day someone decided the trade was worth making. That
         * day is today, so this enables the row rather than creating one.
         */
        LeadField::query()->where('key', 'name')->update([
            'is_enabled' => true,
            'is_required' => true,
            // Before the contact field: the brief names them in that order.
            'sort_order' => 1,
        ]);

        LeadField::query()->where('key', LeadField::KEY_CONTACT)->update(['sort_order' => 2]);

        /*
         * And the one field says it takes either.
         *
         * DemoContentSeeder relabels `contact` as «البريد الإلكتروني» with a
         * `name@company.sa` placeholder. That was right while the form also
         * carried a separate phone field: contact WAS the email then, and the
         * number had a box of its own.
         *
         * It is wrong now. The phone field is switched off below, so this is
         * the only way to reach the company — and §6.1 fixes it as one field
         * taking an email OR a mobile, auto-detected, with no type selector.
         * Wording that names only the email tells a buyer who would rather be
         * telephoned that this form is not for them, and the count of leads
         * is the one thing the site is measured on (§1).
         *
         * The strings are LeadFieldsSeeder's shipped wording, restored — not
         * copy written here (§22.1).
         */
        $contact = LeadField::query()->where('key', LeadField::KEY_CONTACT)->first();

        $contact?->translations()->updateOrCreate(['locale' => 'ar'], [
            'label' => 'وسيلة التواصل',
            'placeholder' => 'بريدك الإلكتروني أو رقم جوالك',
        ]);

        $contact?->translations()->updateOrCreate(['locale' => 'en'], [
            'label' => 'Contact',
            'placeholder' => 'Your email or mobile number',
        ]);

        /*
         * EVERY OTHER FIELD OFF — and this switches off fields that are
         * currently ON in production.
         *
         * At the time of writing the live form asks four things: an
         * organisation, a phone number, the contact value and a message. The
         * brief is unambiguous that the visitor sees two and only two —
         * «ممنوع زيادة عدد الحقول الظاهرة ... يبقى ما يراه المستخدم: الاسم +
         * وسيلة التواصل، لا أكثر» — so the other three are disabled here.
         *
         * Disabled, never deleted: each row keeps its labels and its history,
         * and an administrator can switch any of them back on from the panel
         * in one click if that instruction changes.
         */
        LeadField::query()
            ->whereNotIn('key', ['name', LeadField::KEY_CONTACT])
            ->update(['is_enabled' => false]);

        /*
         * The form's shape is cached per locale for an hour, so without this
         * the database says two fields and the site keeps rendering four
         * until the cache expires. Found exactly that way.
         */
        LeadField::flushCache();
    }
}
