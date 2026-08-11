# HANDOFF

Working state for the admin-panel restructure. Updated after **every**
sub-feature that goes green — not at the end of a session.

A new session should be able to read this file and continue without the client
re-explaining anything. Read [PROJECT_BRIEF.md](PROJECT_BRIEF.md) and
[CLAUDE.md](CLAUDE.md) first; this file only covers work in flight.

**Last updated:** after closing Phase 1 (4/4).
**Suite at that point:** 254 passing, 865 assertions. Pint clean.

---

## 1. Done and tested

### Public site — navigation restructure
Header cut to five entries + language switch + one button. Solutions carries a
dropdown of the four audience segments.

| What | Test |
|---|---|
| Header is exactly five entries; products/sectors absent | `NavigationReachabilityTest::the_header_carries_exactly_five_entries` |
| Four segments reachable from a menu, both locales | `NavigationReachabilityTest::every_browsable_page_is_reachable_from_some_menu` |
| Showcase reachable from the home page (its only route now) | `NavigationReachabilityTest::the_{arabic,english}_home_page_reaches_the_products_showcase` |
| `/products` carries no price, cart or purchase control | `ShowcaseIsNotAStoreTest::the_showcase_carries_no_price_and_no_purchase_control` |
| Showcase ends at the one lead form | `ShowcaseIsNotAStoreTest::the_showcase_ends_at_the_one_contact_field` |
| Category URLs stay in the sitemap | `ShowcaseIsNotAStoreTest::the_category_urls_stay_in_the_sitemap` |
| The premises block opens a map, not `/products` | `ShowcaseIsNotAStoreTest::the_visit_block_points_at_the_map_not_the_product_grid` |

Old paths 301 via the `redirects` table — see `RedirectsSeeder`.

### Admin — Phase 1 (closed 4/4)

| What | Test |
|---|---|
| Sidebar defined in one module, not in the layout | `AdminSidebarStructureTest::the_panel_map_lives_in_exactly_one_module` |
| Seven groups in the approved order | `AdminSidebarStructureTest::the_groups_appear_in_the_approved_order` |
| Old "القطاعات" label gone | `AdminSidebarStructureTest::the_old_sectors_label_is_gone_from_the_panel` |
| Segment editor nested under Solutions and reachable | `AdminSidebarStructureTest::the_segment_editor_is_reachable_from_the_menu` |
| Every sidebar link opens | `AdminSidebarStructureTest::every_screen_the_sidebar_names_actually_opens` |
| Every placeholder states its purpose | `AdminSidebarStructureTest::every_upcoming_screen_states_what_it_will_do` |
| Both admin languages carry the same keys | `AdminSidebarStructureTest::both_admin_languages_carry_the_same_keys` |
| Segment picker: options, save, reload, clear | `SolutionSegmentTaxonomyTest` (4 tests) |

---

## 2. In progress right now

**Phase 2.1 — Languages.** Nothing written yet; starting from a clean tree
apart from the Phase 1 work listed above.

Next step, exactly:

1. Decide and record the storage choice for the English-site switch (see
   Decisions below — likely a `settings` key, not a new table).
2. Build `/admin/languages` for real, replacing its placeholder in
   `UpcomingScreenController::SCREENS` (remove the `languages` entry there and
   its route in `routes/admin.php`, and drop `soon: true` from
   `resources/js/admin/navigation.js`).
3. Switch semantics: when English is off, `LangSwitch` must not render, `/en`
   must not be indexed, and `sitemap-en.xml` must 404. There is already a test
   for the last two — `SitemapAndRobotsTest::turning_english_off_removes_it_
   from_the_index_and_404s_its_sitemap` — so wire the new screen to the same
   setting that test drives rather than inventing a second flag.
4. Translation completeness per content type, read from the existing
   `translatedLocales()` on each model.

Files that will be touched: `app/Http/Controllers/Admin/`, `routes/admin.php`,
`resources/js/admin/navigation.js`, `resources/js/Pages/Admin/`,
`resources/lang/{ar,en}/admin.php`.

---

## 3. Not started

Phase 2: **2.2** CRM · **2.3** Notifications · **2.4** Lead export ·
**2.5** Spam protection.

Phase 3: media library · drafts and preview · CTA registry · landing-page
builder · confirmation messages.

Phase 4: computed dashboard metrics · activity log · backups · sitemap screen ·
graduated permissions.

Placeholders currently standing in, with the phase that replaces each:
`UpcomingScreenController::SCREENS`.

---

## 4. Architectural decisions already taken

Do not reopen these in a new session without the client.

**Translations stay in per-entity translation tables.** Every content model
already uses `HasTranslations` with a `*_translations` table and
`translatedLocales()`. Phase 2.1 adds a language *switch*, not a storage
change. A move to duplicated columns or a single polymorphic table would touch
every model, resource, sitemap query and the "missing translations do not fall
back" rule (§12) — all to solve nothing that is currently broken.

**Sectors were merged into Solutions in the menu, not in the data.** The four
public pages at `/solutions/{government,companies,partners,artisans}` *are*
Sector records with their own hero, sections, metrics and clients. They keep
their own editor, listed as "شرائح الحلول" under Solutions, and a solution
additionally carries them as a taxonomy (`solution_sector` pivot). Flattening
a segment into a label would have orphaned four public pages.

**The pivot is named explicitly.** `belongsToMany(Sector::class, 'solution_sector')`
— Eloquent's convention would look for `sector_solution` and a wrong guess is
a 500, not an empty list.

**`/products` stays live and out of every menu.** §2 permits showing work
informationally; what is forbidden is the commercial catalogue. 332 pieces
across 16 category URLs are the site's largest SEO asset. Its only route is
the home page's showroom section, which is why that link has its own test.

**The header CTA scrolls, it does not open a modal.** §6.1 allows one form;
sending the visitor to the form already on the page keeps one visible form per
page. Falls back to `/contact` when the page has none.

**Placeholders are real routes, not dead links.** A 404 in the sidebar teaches
an operator to distrust the menu.

**Two SSR renders inside one test return the first render's markup.** One
render per test. This has now bitten twice; if a test asserts the wrong
locale's content, this is why.

---

## 5. Known debts, not blocking

- `storage/app/public` is ~5.1 GB / 9,943 files — repeated seeding and test
  runs leak media. A `amad:prune-media` command was offered and not built;
  it deletes, so it needs the client's word.
- `public/documents/amad-craft-profile.pdf` does not exist; the hero's
  "حمّل ملف الشركة" button 404s until Amad Craft supplies it.
- Opening hours in `DemoContentSeeder` are seeded as **draft, unverified**.
- The hero GIF is 20.7 MB and is the LCP. ffmpeg is not installed here; a
  225 KB poster paints first as a mitigation.
- Sector cards still use emoji icons (📄 📅 📊 …); the design brief forbids
  emoji and wants line icons, as already done for the four solutions.
- Per-segment impact figures and testimonials are empty by design — they need
  Amad Craft's own data, and the global figures must not be attributed to one
  segment.
