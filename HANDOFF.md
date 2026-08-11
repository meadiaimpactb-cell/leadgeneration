# HANDOFF

Working state for the admin-panel restructure. Updated after **every**
sub-feature that goes green — not at the end of a session.

A new session should be able to read this file and continue without the client
re-explaining anything. Read [PROJECT_BRIEF.md](PROJECT_BRIEF.md) and
[CLAUDE.md](CLAUDE.md) first; this file only covers work in flight.

**Last updated:** after **2.2 CRM** went green.
**Suite at that point:** 267 passing, 902 assertions. Pint clean.

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

### Phase 2.1 — Languages ✔

`/admin/languages` replaces its placeholder. The switch and the coverage
table; bilingual editing already existed through `BilingualFields` on the
shared content editor.

| What | Test |
|---|---|
| Screen reports the live state | `LanguagesScreenTest::the_screen_reports_the_current_state_of_the_english_site` |
| Drives `site.english_enabled`, does not invent a second flag | `LanguagesScreenTest::the_switch_drives_the_one_setting_the_site_already_reads` |
| Switching off closes `sitemap-en.xml` | `LanguagesScreenTest::turning_english_off_from_this_screen_closes_the_english_sitemap` |
| Coverage counted from translations, not stored | `LanguagesScreenTest::coverage_is_counted_from_the_translations_that_exist` |
| An empty content type reports no percentage | `LanguagesScreenTest::an_empty_content_type_reports_no_percentage_at_all` |

### Phase 2.2 — CRM connection ✔

`/admin/integrations/crm` replaces its placeholder. Configures the existing
driver abstraction; never talks to a provider directly (§22.8).

| What | Test |
|---|---|
| The screen offers exactly two providers — zid, odoo | `CrmConnectionScreenTest::the_screen_offers_exactly_two_providers` |
| Panel credentials override `.env` | `CrmConnectionScreenTest::credentials_save_to_settings_and_override_the_environment` |
| Saving an unchanged secret does not overwrite it with bullets | `CrmConnectionScreenTest::saving_an_unchanged_secret_leaves_it_alone` |
| A secret never reaches the browser | `CrmConnectionScreenTest::a_secret_is_never_sent_back_to_the_browser` |
| The bulk resend dispatches a job, does not loop in the request | `CrmConnectionScreenTest::the_bulk_resend_dispatches_a_job_rather_than_looping_in_the_request` |
| The job re-queues every stuck lead | `CrmConnectionScreenTest::the_job_requeues_every_stuck_lead` |
| Status counts what is actually stuck | `CrmConnectionScreenTest::the_status_counts_what_is_actually_stuck` |
| An editor cannot open or change it | `CrmConnectionScreenTest::an_editor_cannot_open_or_change_the_connection` |

**Run against the real backlog:** 135 stuck (75 pending + 60 failed) → 0.
`ResyncFailedLeads` logged `{"leads":135}`; the dashboard's "لم تصل إلى CRM"
tile now reads 0. Note the local driver is `null`, so this proved the
re-queue path, not a real provider round trip.

---

## 2. In progress right now

**Phase 2.3 — Notifications.** Not started; the tree is clean and committed
at the end of 2.2.

What already exists and must be reused, not rebuilt:

- `App\Notifications\NewLeadReceived` and `App\Notifications\CrmSyncFailed`.
  Both exist; what is missing is who receives them and an editable template.
- `App\Jobs\ResyncFailedLeads` currently writes its result to the log with a
  comment saying 2.3 turns that into a notification. That is a one-line
  change — find the `Log::info('CRM backlog re-queued.'` call.

Next step, exactly:

1. Build `/admin/integrations/notifications`, replacing the `notifications`
   entry in `UpcomingScreenController::SCREENS`, its slug in the
   `integrations/{screen}` route's `whereIn`, and the `soon: true` flag in
   `resources/js/admin/navigation.js`. 2.2 did exactly this for `crm` — copy
   that shape.
2. Recipients: name + email, plus a WhatsApp number column left unused for
   now (the brief asks for the structure, not the sending).
3. Per-recipient event toggles: new lead / CRM push failed / daily summary.
4. Editable subject and body with `{contact}` `{message}` `{source}` `{date}`.
5. Everything on the queue — the visitor's form must never wait on mail.

---

## 3. Not started

Phase 2: **2.3** Notifications · **2.4** Lead export · **2.5** Spam
protection.

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

---

## Design track (parallel to the phases)

**D0 — design system extracted.** `DESIGN_SYSTEM.md` + two additions to the
shared layer: `.cut` / `.cut--deep` (the octagon chamfer) and `.steps` (the
process timeline). Commit `f70be63`.

**D1 — `/solutions/government`** — the template all four segments share.
`SegmentHero` (running index, two actions, octagon photo over a Sadu edge),
`ProcessSteps` replacing the "how we work" paragraph, and a siblings row
before the form. Commit `f70be63`.

**D2a — `/solutions/companies`.** Five fixes plus two sections:

- Segment renamed to "شركات القطاع الخاص" everywhere — hero, `<title>`,
  breadcrumb, menu.
- The opening statement section was dropped from every segment: it was seeded
  from the sector summary, which is *also* the hero subtitle, so the same
  sentence appeared twice within one screen.
- Every emoji icon replaced with a line glyph from `NavIcon` — one was
  rendering as an Instagram-style gradient, a second brand's palette inside a
  card built from these tokens. `CardsGrid` now renders `NavIcon`, on a
  lavender disc at 12%, which is the one place §10.2 allows that colour.
- "متى تطلب الشركات منّا" — six occasions, private-sector only. The section is
  conditional on the segment, which is the point of four separate pages.
- FAQ for this segment is seven questions, including the invoice one. The
  answer is a sentence and nothing more: §2.2 forbids a commercial function,
  and the site must not grow one by answering a question about it.

**Still open on the design track:** D2 for `/solutions/partners`,
`/solutions/artisans`, `/impact`, `/training`, `/about`, `/contact`,
`/products/*`, `/lp/*`, and the 404 page. Each inherits this template; the
work per page is content angle plus whatever section that segment alone needs.

**Not done and not claimable:** screenshots at 360/768/1440 and Lighthouse —
there is no browser in this environment. Verification here is the
server-rendered HTML in both locales. A `hex-outside-tokens` lint was asked
for and has not been written.

**D2b — `/solutions/partners`.** The segment with the most different logic:
the reader is not the end customer, they are an intermediary serving *our*
customer, and their first question is "if I introduce you to my client, do you
take them from me". The page is built to answer that.

- **تعهّدنا لشركائنا** — three promises, partners only. This is the section
  the page exists for; nothing else on the site answers that question.
- **نماذج التعاون** — events, exhibitions, referral.
- Process steps are now **per segment**. A government body is buying a
  procedure it can check; a partner has bought before and is racing a tender
  deadline, so theirs starts at "send the brief" and ends at "delivered where
  you need it, packaged to suit how you appear".
- Six partner FAQs replacing the three copied ones — direct contact,
  white-label, speed of an indicative price, bringing their client to the
  showroom, concurrent events, and the referral model. **The referral answer
  carries no figure**: a percentage on a public page is a price list (§2.2).

Guarded by `SegmentPagesAreDistinctTest` (6): no segment repeats its hero line
in the body, the process steps differ between segments, each segment owns a
section the others do not, and the partner page quotes no rate.

Note on that last test: reducing a page to "what a visitor reads" needs the
Inertia `data-page` attribute, `<script>` bodies AND `<style>` bodies stripped
before the tags. Stripping tags alone leaves what is *between* them, and the
map embed's URL-encoded `%3A` reads as a percentage.
