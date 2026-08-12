# HANDOFF

Working state for the admin-panel restructure. Updated after **every**
sub-feature that goes green — not at the end of a session.

A new session should be able to read this file and continue without the client
re-explaining anything. Read [PROJECT_BRIEF.md](PROJECT_BRIEF.md) and
[CLAUDE.md](CLAUDE.md) first; this file only covers work in flight.

**Last updated:** after **D2f — `/training`**, verified live in both locales.
**Suite:** 326 passing, 1,126 assertions on a quiet database. Pint clean.
**D2d, D2e and D2f are NOT committed** — see the warning immediately below.

---

## ⚠️ Two sessions are writing to this working tree

Discovered while closing D2d. Alongside this session's `/impact` work, another
is building `/training`, `/contact`, `/about`, `BridgeModel.vue` and their
tests, with file writes interleaved minute by minute — and both sessions run
`php artisan test` against the same `amadcraft_b2b_test`.

What it looks like from inside, so the next session does not spend an hour
misdiagnosing it as its own bug:

- Tests fail in files nobody touched (`AssetUrlTest`, `ContactDockTest`).
- `SQLSTATE[40001] Deadlock` and `1050 Table 'sessions' already exists` and
  `1824 Failed to open the referenced table` — one run's `RefreshDatabase`
  drops tables while the other is mid-transaction.
- **Every failing test passes on its own.** That is the signature. A real
  regression does not care whether it has company.

D2d was therefore **not committed**: `git status` shows both sessions' work
mixed together, and `git add -A` would sweep another session's half-finished
pages into a commit describing the impact page.

Before committing anything from here: confirm the other session has stopped,
run `php artisan migrate:fresh --env=testing --force` once, then the suite.

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

**Nothing is half-built.** The design track's four segment pages are closed and
committed; the admin phases are paused where the client stopped them.

Two candidates for the next session, in the client's own order of asking:

- **Design track D2** continues at `/impact` — see the Design track section.
- **Phase 2.3 — Notifications**, described immediately below, paused at the
  client's request after 2.2.

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

**Found while closing D2c, not fixed.** The partner and artisan heroes serve
their original `.jpeg` where the government and companies heroes serve `.avif`:

```
government  /storage/5/1.avif      partners  /storage/7/6.jpeg
companies   /storage/6/4.avif      artisans  /storage/8/8.jpeg
```

`SegmentHero` renders `image.webp ?? image.url`, so the fallback is doing its
job — the conversion is simply absent for media 7 and 8.
`media-library:regenerate --ids=7,8 --force` reports "All done!" and changes
nothing, which means the cause is upstream of regeneration and needs a look at
the conversion registration for that collection. Two pages ship a full-size
photograph until then. Small, real, and deliberately left open rather than
half-diagnosed at the end of a session.

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

**Still open on the design track:** D2 for `/impact`, `/training`, `/about`,
`/contact`, `/products/*`, `/lp/*`, and the 404 page. Each inherits this
template; the work per page is content angle plus whatever section that page
alone needs.

**D2d — `/impact`.** The proof page. Every other page points here, so the rule
is narrower than elsewhere: no assertion without something behind it.

The four defects named in the brief, and what each turned out to be:

- **Report covers were a stock photograph of a necklace on a model**, the same
  one on both reports, in the space a document cover belongs — so a report read
  as merchandise. New `ui/ReportCover.vue` draws a navy document face with the
  mark and the year, from tokens and markup, costing no bytes and never the
  wrong year. An uploaded cover still wins over it. *The brief asked for the
  PDF's first page as a thumbnail; that needs Imagick, which is not installed
  here. If it is added, a medialibrary conversion can populate `cover` and this
  component needs no change.*
- **A card headed 2025 wearing a 2023 badge.** The year was typed into the
  title as well as held in the `year` field. Two places for one fact disagree
  eventually; the year is out of the titles and the badge is the only statement
  of it.
- **«تحميل التقرير (612 B)».** The demo seeder attached a hand-built 612-byte
  PDF to every report so the link would be "testable". What it produced was a
  public button on the credibility page handing a public body an empty file.
  The seeder now attaches nothing and removes what it attached; `ReportsList`
  shows «التقرير قيد الإعداد». A test that needs a download attaches its own.
- **Stories illustrated with product shots.** `StoryCarousel` even carried
  `mix-blend-mode: multiply` to drop the white studio sweep — the compensation
  was the tell. **Amad Craft has since supplied three workshop photographs**
  (`public/images/image (2..4).jpg`): hands at the loom, palm fronds split at
  the bench. Matched to the craft each caption names, with alt text describing
  people at work rather than the shared "a craft piece" default.

  ⚠️ **`story-zari` is an approximate match.** Zari is gold thread worked onto
  fabric; the photograph is weaving. It is the closest of the three and it is
  still the wrong craft — swap it when a Zari photograph arrives. A picture
  that contradicts its own caption is the original defect wearing a new coat.

  Note the ordering in the seeder: `detachSeededMedia` runs **before**
  `attachImage`, because `attachImage` returns early when the collection is
  occupied — reversed, the stale catalogue shot keeps its place and the page
  looks finished while showing the wrong thing.

Built on top of it:

- Hero gained a second action; the first goes to `#reports`, not the form — a
  public body arriving for the document should not read the page to reach it.
- «آخر تحديث للأرقام», derived from when the figures were last edited rather
  than a second field to maintain.
- `/impact/stories` and `/impact/stories/{slug}`, with «اقرأوا القصة كاملة»
  keyed on the story having a body, and «كل القصص» appearing only once there
  are more than the three on show. Sitemap lists only stories with a body — a
  headline and a pull quote is a thin page.
- Report downloads push a `report_download` conversion to `dataLayer`, guarded
  so no consent means no throw.

**Seeded empty on purpose, and this is the honest part of the page:** «كيف
نقيس» and «ارتباطنا برؤية 2030». Both are methodology. The hero promises we
measure «استقرار الدخل» — whether that means three consecutive months of orders
or six is a fact only Amad Craft holds, and a placeholder definition on the page
whose entire job is to be checkable does more damage than an absent section.
The rows exist in the section builder; `CardsGrid` renders `v-if="items.length"`.
Filling `items` in the panel switches each on with no further work.

**Not built: «أثرنا على الخريطة».** It needs artisans-per-region figures that do
not exist in any record here, and drawing approximate regions on the one page
that must be checkable is the same failure as the placeholder PDF. It is the
optional-activation section in the brief; it should be built when the regional
data arrives.

Guarded by `ImpactPageProvesItsClaimsTest` (10), including the single-source
test the brief asked for: change a figure and both the home page and /impact
move together.

**D2e — `/contact`.** Mostly built in a parallel session; this session settled
the one point the client overruled.

The brief's fix for the duplicated location — «موقعنا» plus the footer's
showroom block, two headings, two maps, two addresses and two sets of opening
hours in a two-screen page — was to suppress the footer block on this page.
**The client reversed which of the two survives:** «لا تحذف الخريطه من الفوتر
لاني الموقع المفروض يكون موحد». The footer block is the site's one answer to
"where are you", and a visitor who learns to look for it at the foot of every
page must find it at the foot of this one.

So `Contact.vue` dropped `has-own-location` and its own `MapBlock` instead. The
appointment request was the only thing that section owned which the footer
block does not offer, and it moved into the direct-contact card beside the form
— which is where someone who has already decided to make contact is looking.
`the_contact_page_carries_exactly_one_location_section` now asserts the
arrangement in both directions: the footer heading present, «موقعنا» absent.

**The decision the brief asked for is already made: option A.** The optional
single-line message exists site-wide, collapsed until asked for, and the schema
carries `leads.message`. Three required fields plus one optional message, as
§7 specified.

⚠️ **A consequence worth a decision.** The contact page used to be the one page
that loaded no Google script before a click — its map was click-to-load, and
the brief praised the pattern. The footer block's map is a real (lazy) Google
iframe, so /contact now loads Google on arrival like every other page. Nothing
regressed site-wide; /contact simply joined the rest. Applying the click-to-load
treatment to the footer block would give uniformity *and* the privacy pattern
together, on every page — it is a change to every page's footer, so it is the
client's call, not a tidy-up to make unasked.

**D2f — `/about`.** Built in the parallel session; this session verified it
against the brief's acceptance criteria and wrote the guard tests they call for
(`AboutPageEarnsTrustTest`, 6).

Checked and holding: the bridge diagram is drawn and carries a text
alternative, its five stages come from the section's `settings`, the duplicated
«كيف نعمل» is gone, the figures are the shared `ImpactMetric` record (change one
and /about moves with the home page and /impact), the team grid is off until
switched on, and no photograph is shared with a segment page — the gallery is
showroom interiors, not the leather bag that appeared on five pages.

One test was mine and wrong before it was right: matching segment step titles
against the whole document reported «تواصلوا معنا» as a duplicated procedure
when it is a navigation label on every page. The header and footer are stripped
before the comparison now — a false positive there teaches the next person to
distrust the test rather than the page.

**«محطات» now carries real, sourced facts.** Amad Craft supplied «تقرير مركز
التدريب والإنتاج بالأحساء — سبتمبر 2025 إلى فبراير 2026», and the four invented
milestones were replaced with four the report documents:

| Station | Source in the report |
|---|---|
| 09·2025 first cohort, three named workshops | p.5 |
| 11·2025 Banan recognition, Heritage Commission | p.25 |
| 12·2025 second cohort, 300+ applications | p.5, p.7 |
| 02·2026 75 trainees, 44 production contracts | p.7 / p.14 / p.21, p.24 |

Two notes on the reading. The 75 is stated on p.7 **and** independently confirmed
by the two cohort rosters (37 + 38); the per-workshop «المقبولين» column sums to
88, which is acceptances, not completions — the rosters are the truth. And the
stations are stamped month · year rather than year alone because that is the
precision the report supports; nothing reaches back before September 2025,
because the report does not cover it.

**Still unused from that report, and worth using:**

- **The recognitions**, all documented with photographs: Banan / Heritage
  Commission, «الأحساء تستاهل» with UNESCO (honoured by the Governor of
  Al-Ahsa), the King Faisal University handicrafts forum, and معرض البشت
  الحساوي. A «تكريمات ومشاركات» band on /about or /impact is the obvious home.
- **The programme facts for `/training`**: 8 weeks, Sunday–Thursday 08:00–14:00,
  women 18 and over, and the six workshop names across two cohorts. The training
  pages currently carry none of this.
- **The workshop photographs** (pp.10–23) — trainees at the loom, palm fronds
  at the bench, gypsum carving, and the finished pieces. These are far better
  than anything currently on /impact, and they come with the report's own
  framing. **They are in the PDF, not in the repo** — someone needs to export
  them to `public/images/` before they can be used.
- **The magazine coverage** (مجلة الأحساء, issue 175) — usable as press, but
  note its «335 حرفياً وحرفية» and «150 حرفياً» figures are the magazine's, not
  the report's, and the two do not reconcile with the 75 above. Do not mix them
  into the impact counters without asking which measure each one is.

The team section stays off until Amad Craft decides to publish people.

**Rhythm rule — «لا كحلي فوق كحلي أبداً».** Now DESIGN_SYSTEM.md §7b and swept
across every built page by `NavyNeverTouchesNavyTest`.

**The sweep found exactly one offender: `/impact`.** The other eight pages —
home, /about, /contact, /training and the four segments — were already clean,
so no other page needed changing. The test walks the top-level blocks of
`<main>` and fails on two `on-dark` sections in a row; it ignores nesting, so
`CtaBand`'s navy card inside its light outer section correctly does not trip
it, and it excludes the footer, which the rule's own text exempts.

**Two `/impact` fixes shipped with it:**

- **The hero had lost its heading**, and the cause was mine. This page has a
  prop called `page`, and I added `const page = usePage()` for a locale check —
  in `<script setup>` a top-level binding of that name wins over the prop in
  the template, so `page?.title` resolved to the Inertia page object. Nothing
  threw; the `v-if` saw undefined and the hero rendered as two buttons on an
  empty navy field. Renamed to `inertia`, and
  `ImpactPageProvesItsClaimsTest::no_page_shows_a_hero_without_a_heading` now
  asserts a non-empty `<h1>` on every hero page, because the same collision is
  available to all of them and no framework error announces it.
- **The figures moved to the floating card** — `ImpactStats variant="overlap"`,
  the same component the home page calls, not a copy. Cream, octagon-cut,
  riding the hero's edge on a negative margin, 2×2 on phones with a 20px
  overlap. The heading «أثرنا بالأرقام» is gone: four large numbers do not need
  announcing.

  Note the shadow: `clip-path` cuts a `box-shadow` off with the corners, so the
  soft shadow is a `drop-shadow` filter on the section instead — filters follow
  the clipped outline. That is the only way to get the octagon and the shadow
  on one element.

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

**D2c — `/solutions/artisans`.** The one segment that is not buying anything.
Everything else on this site addresses someone with a budget; this page
addresses someone deciding whether to trust an intermediary with their work.

- **The buyer-facing services grid is gone from this page.** It was offering
  "الهدايا المؤسسية / مستلزمات الفعاليات / الإنتاج المخصص" to a craftsperson —
  services for someone who is not the reader. Removed at template level in D1,
  now guarded.
- **The process steps were the government page's, copied.** They now read as a
  joining path: تواصلوا معنا · جلسة تعارف · تأهيل وتدريب عند الحاجة · أول طلبية ·
  شراكة مستمرة.
- **من نستهدف** — named crafts rather than "any artisan". The specificity is the
  serious signal.
- **ماذا تكسبون معنا** — three cards, and **no figure in any of them**: no
  commission, no margin, no rate. §2.2, same rule that governs the partner page.
- **The form's first field is relabelled, not changed.** "اسم الشركة" is wrong
  for an individual, so `LeadField` grew a `firstFieldLabel` prop and this page
  passes "الاسم أو اسم المشروع الحرفي". Same three fields, same endpoint,
  same requirement — the label only.
- **من الحرفيين أنفسهم is seeded EMPTY, deliberately.** A quote attributed to a
  craftsperson who never said it is not placeholder copy, it is a fabricated
  endorsement (§22.1). The section row exists in the builder with its heading;
  `Testimonial.vue` renders `v-if="body"`, so the page shows nothing until Amad
  Craft pastes a real quote and a real name. **This is content the client owes
  us** — it is the single most persuasive element the page could carry.

Guarded by `SegmentPagesAreDistinctTest` (8). The two artisan tests cost three
attempts each and both failures were mine, worth recording because they will
recur:

- Counting fields by `class="lead-input"` verbatim finds two of three — Vue
  merges the bound modifier into `class="lead-input lead-input--mono"`. Match
  the class as a **token**, not the attribute.
- The buyer-service strings survived removal from the template because
  `SectorController` was still passing a `solutions` prop the page no longer
  rendered. Invisible on screen, fully present in the `data-page` JSON. **A prop
  that stopped being rendered is still shipped** — deleted at the controller.

**D2f — `/training`.** The one page with two readers: an artisan deciding
whether to join a track, and an institution deciding whether to fund one. It
showed tracks to the first and then ended at a form headed «ترغبون برعاية مسار
تدريبي؟» — so the artisan who read to the bottom found no door.

- **Two doors, one form.** The hero's first action is a fragment link down to
  the tracks; its second, and one button in each column of the new
  `AudienceSplit` section, call `choose(tag)` — which sets the interest, scrolls
  to the band and focuses the field. The three fields never change (§6.1).
  Only the heading, the message placeholder and a stored tag do, and all three
  come from `settings.variants` keyed by the tag itself, so the copy for an
  audience and the label it is filed under cannot drift apart.
- **`leads.interest`** — a new nullable column, set from which button was
  pressed and never asked of the visitor. It reaches the row and the side panel
  in the admin list, a filter built from the tags that actually exist, the CSV
  export, `LeadPayload` (so every CRM driver gets it), the Odoo description,
  the `NewLeadReceived` mail and the `lead_submitted` dataLayer event.
  Deliberately **not** an enum: the value comes from a section setting the
  client edits, and a whitelist would turn a typo in the panel into a rejected
  lead. A visitor who pressed nothing is stored with no tag — the sales team
  must be able to trust the column.
- **Five process steps**, the same `ProcessSteps` component the segment pages
  use. The fifth — «الربط بطلبات أمد الحرف» — is what separates this from a
  training institute.
- **Track cards are fields, not markup.** Duration badge, summary, the outcome
  line and an optional next-cohort line, all per record. The outcome is now
  **required in the panel**: `ContentRegistry` grew a `required` list and
  `ResourceController` turns it into `required_with` against the other fields
  of the *same locale*, so a wholly blank English column still means "not
  translated" and still deletes its row (§12).
- **Three training figures from the one register**, chosen by the `stats`
  section's own `keys` setting and re-ordered to match it. `training-graduates`
  and `training-to-production` are seeded **with labels and no value**:
  `ImpactMetric::visible()` now skips a row whose `value_numeric` is null, so
  typing the number in the panel is the only step needed to publish it. The
  page must never fall back to the site-wide set — «ساعة تدريب» beside «قطعة
  حرفية سُلّمت» would be a training claim made of delivery data.
- **`stories.tag`** — one column, so `/training` can ask for graduates of its
  own tracks and `/impact` still shows every story. One record, read twice.
  **Seeded with no tagged stories**: what happened to somebody who took a track
  is theirs to say, and a drafted graduate testimonial is a fabricated
  endorsement, not placeholder copy. **This is content the client owes us** —
  it is the strongest thing this page could put in front of a sponsor.
- **Photographs.** The client supplied three: `sadu.png` illustrates the Sadu
  track, `sojad.png` the artisans' column, `gbs.png` the sponsors' column — the
  last one has the training centre's own sign in the frame, which is the only
  photograph on the site that answers "does the place exist". The catalogue
  photographs that used to sit on the cards were removed: the craft-business
  track was illustrated with a wall clock. **Two are still owed** — the
  palm-frond room and a craft-business session. Their frames render as warm
  placeholders until then.

Guarded by `TrainingPageServesTwoAudiencesTest` (24), including: no price, fee
or promised income anywhere on the page; no track illustrated with a catalogue
photograph; the English page carrying no Arabic.

**One render per test.** The SSR gateway serves a test's first render and
returns an empty document for the second, so a before/after pair inside one
method silently asserts against nothing — it passes `assertStringNotContains`
and then fails the positive half with an empty haystack. Every conditional
section here is therefore two tests, not one.

**Fixed while building it, in components shared by other pages.** These were
already live and are not `/training`'s doing:

- `CardsGrid`, `FaqAccordion` and `Timeline` printed the Arabic keys of
  `settings.items` verbatim on `/en` — an English heading over Arabic cards, on
  four segment pages and four solution pages. All three now read through the
  new `useSettingText` composable (both languages per item, no fallback), and
  every seeded item in `DemoContentSeeder` and `DemoExtrasSeeder` gained its
  `_en` half. `ProcessSteps` and `Timeline` had private copies of that helper;
  they now share one.
- `ui/Button` rendered `href="#lead"` as an Inertia `<Link>`, which issues a
  visit instead of scrolling — the fragment never reaches the server, so the
  page re-rendered at the top. A same-page fragment is now a plain `<a>`. This
  also repairs `/impact`'s two hero actions.

**Not done, and deliberately.** The showroom block asked for in the brief
(«مقابلات التقييم تبدأ من المعرض») lives in `SiteFooter` and is driven by the
global `contact.*` settings, so it is the same block on every page. Giving it a
per-page angle needs a mechanism that does not exist yet; the FAQ answer about
where tracks run carries the point instead.
