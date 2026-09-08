# CLAUDE.md — Amad Craft B2B corporate site

**Read [PROJECT_BRIEF.md](PROJECT_BRIEF.md) before doing anything.** It is the Single
Source of Truth. Arabic original: [docs/PROJECT_BRIEF.ar.md](docs/PROJECT_BRIEF.ar.md).
Anything not stated there is out of scope until approved in writing.

This file is the working companion to the brief: how the code is laid out, what has
been built, and the rules that are easiest to break by accident.

---

## The one metric

Number and quality of institutional **leads** arriving through the site (§1).
Nothing on this site is sold. Every design and technical decision is justified by
its effect on that single number — or it is not made.

---

## The ten hard constraints (§22) — reread before every session

1. **Never invent content.** If copy is missing, leave the container empty and
   report it. Do not write marketing text. Ever.
2. **Never add a feature not in the brief.** Propose it; do not build it.
3. **Never build a commercial function** — no cart, payment, orders, inventory,
   logistics, invoicing, tax, currencies (§2.2). However logical it seems.
4. **No ready-made templates or CMS.** Interfaces are hand-coded Vue components
   on the approved identity.
5. **No literal user-facing text in templates.** Everything goes through
   `__()` / `$t()` (UI strings) or the database (content).
6. **No `left`/`right` in CSS.** Logical properties only — `margin-inline-start`,
   `padding-inline`, `inset-inline`. RTL-first.
7. **Never break legacy URLs.** Every path change goes through the `redirects` table.
8. **Never couple to one CRM provider.** Only through `App\Services\Crm\CrmDriver`.
9. **Never put keys or tracking IDs in code.** They live in `settings` or `.env`.
10. **On any ambiguity: ask before building.**

---

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel **12** (PHP 8.3) — see *Deviations* below |
| Frontend | Vue 3 (`<script setup>`) + Vite 7 |
| Bridge | Inertia.js **with SSR enabled** |
| Database | MySQL 8 (`amadcraft_b2b`), phpMyAdmin |
| Styling | Tailwind v4 + a custom CSS token layer, RTL-first |
| Permissions / media / audit | `spatie/laravel-permission`, `-medialibrary`, `-activitylog` |
| i18n | Translation tables + `resources/lang/{ar,en}` |

SSR is not optional. A pure client-rendered SPA is forbidden (§7.2) because
indexing is a hard requirement. If the SSR process is not running, the site is not
shippable.

---

## Deviations from the brief (deliberate, documented)

| Brief says | Built as | Why |
|---|---|---|
| Laravel 11 | Laravel **12** | Every 11.x release is now blocked by Composer's security audit — the branch is past its security window. §15.3 mandates security; §7.1 mandates Laravel 11. The two conflict, and security wins. Laravel 12 has an identical skeleton and API surface, so nothing else in the brief changes. |
| Kefa for Latin display | SaudiWeb for both | Kefa ships as a `.ttc` with unverified web-embedding rights. §10.3 already flags "verify web-use licensing before launch". Until Amad Craft confirms it, Latin display falls back to SaudiWeb, which carries Latin glyphs. Swap is a two-line change in `base.css` + `tokens.css`. |
| `resources/css/{tokens,base,app}.css` | plus `components.css` | Splitting the component layer out of `app.css` keeps each file readable. Structural refinement, not a scope change. |

Anything else that departs from the brief is a bug — fix it or flag it.

---

## Layout

```
app/
├── Actions/Leads/StoreLead.php        persist a lead, start the §6.2 chain
├── Console/Commands/                  amad:create-admin
├── Http/
│   ├── Controllers/Public/            HomeController, LeadController, LocaleRedirect
│   ├── Middleware/                    SetLocale, HandleInertiaRequests, SecurityHeaders
│   ├── Requests/StoreLeadRequest.php  the one form's validation
│   └── Resources/                     what reaches the browser
├── Jobs/PushLeadToCrm.php             queued, backoff from config/crm.php
├── Models/
│   ├── Concerns/HasTranslations.php   the translation-table pattern
│   ├── Concerns/HasSections.php       the section builder
│   └── …                              one *Translation model per entity
├── Notifications/                     NewLeadReceived, CrmSyncFailed
├── Services/
│   ├── Crm/{CrmDriver,CrmResult,CrmManager,Drivers/}
│   └── Seo/MetaBuilder.php
└── Support/                           Settings, Locales, NavigationBuilder, ContactValue

resources/
├── css/{tokens,base,components,app}.css
├── js/
│   ├── Components/{ui,sections,forms}/
│   ├── Composables/                   useReveal, useCountUp, useTranslation
│   ├── Layouts/PublicLayout.vue
│   ├── Pages/Public/                  Inertia pages
│   ├── plugins/i18n.js
│   └── app.js · ssr.js
└── lang/{ar,en}/                      UI strings ONLY
```

---

## Built so far

- **Phase 0 — setup.** Complete. Laravel + Inertia + Vue + SSR renders server-side;
  MySQL wired; brand fonts self-hosted as subset `woff2`; logos normalised to tight
  `viewBox` + `currentColor`; full token layer.
- **Phase 3a — schema.** Complete. All tables from §8, migrated clean against MySQL 8.
- **Phase 3b — models.** Complete. Translation pattern, section builder, scopes.
  Structural seeders (roles, sectors, pages, settings, menus) — no marketing content.
- **Phase 2 — Home prototype.** Structure complete, **awaiting content and approval**.
- **Phase 5 — leads + CRM.** Complete and tested: `LeadField`, `StoreLead`, the
  driver abstraction with four drivers, queued push, sync logging, notifications.

**Not built yet:** admin panel (§9), remaining public pages (§5), campaigns (§11.3),
sitemap/schema builders (§13), integrations (§14), CSP (§15.3).

---

## Rules that are easy to break

**Content.** `resources/lang/` is for buttons, labels and error messages. A headline,
a paragraph, a value proposition or a CTA phrase is *content* and belongs in a
translation table, entered through the admin panel. If you catch yourself writing a
sentence a marketer would have opinions about, stop.

**Missing translations do not fall back.** `HasTranslations::t()` returns `null` when
the locale's row is absent, on purpose (§12). Serving Arabic to an English-speaking
government buyer is worse than hiding the page. Use `->translatedIn($locale)` to keep
untranslated records out of the English site and its sitemap.

**`Settings::get()` takes a literal key, not a path.** `"site.english_enabled"` is one
array key. `data_get()` would read it as `$all['site']['english_enabled']` and always
return the default.

**`env('X')` where X is the string `null` yields PHP `null`.** This bit `CRM_DRIVER=null`
once already — see the `?:` in `config/crm.php`. Never call `env()` outside `config/`.

**Inertia resources are unwrapped.** `JsonResource::withoutWrapping()` is set in
`AppServiceProvider`. Without it every `:items="sectors"` receives `{data: […]}` and
silently renders nothing.

**The Sadu thread has exactly three homes** (§10.1): section divider, the faint ground
behind the impact numbers, the hover underline. There is no fourth. Boldness is spent
once.

**Colour contrast is built into the tokens.** `#D7653B` on white only clears AA at
18px/700 or larger — that is what `.btn--cta-lg` is for. Everywhere else use
`--action-600`. `#8685D8` is never body text on white; use `--lavender-700`.

**Never write a brand colour as a literal.** The four identity colours are settings
rows the client edits on `/admin/brand`, and `App\Support\Palette` derives ten shades
from them into a `<style>` block in `app.blade.php`. A hex or an `rgba(0, 37, 70, …)`
anywhere in a stylesheet, a scoped block or a template is a colour that stays the old
one after a rebrand — which is how the Sadu thread, the loading bar and a hundred
and three washes came to be the things a rebrand could not reach.
Use `var(--navy-900)` for the colour and `rgb(var(--navy-rgb) / .08)` for a tint of
it. The defaults in `tokens.css` are the only place the identity's hexes appear.

**`Model::preventLazyLoading()` is on in local.** An N+1 throws in development rather
than shipping (§7.4).

---

## Commands

```bash
# Development — three processes
php artisan serve
npm run dev
php artisan queue:work            # the CRM push is queued

# SSR (required for a faithful render; mandatory in staging/production)
npm run build                     # builds client + SSR bundles
php artisan inertia:start-ssr     # RESTART this after every build — it loads
                                  # bootstrap/ssr/ssr.js once, at boot, so a
                                  # rebuild alone leaves the old markup being
                                  # served while the browser bundle is new.

# Database
php artisan migrate --seed        # structural data only, safe in production
php artisan amad:create-admin     # interactive; never seed an admin

# Catalogue — reads amadcraft.sa, writes names/images/categories only.
# Never price, stock or any purchase action (§2.2). Safe to re-run.
php artisan amad:import-store --dry-run
php artisan amad:import-store

# Tests — run against MySQL (amadcraft_b2b_test), not SQLite
php artisan test

# Style
./vendor/bin/pint
```

---

## Before you say something is done

- `php artisan test` passes.
- The page renders **server-side**: `curl` it with the SSR server up and confirm the
  content is in the HTML, not just in the `data-page` JSON.
- Both `/ar` and `/en` render, with the right `lang` and `dir`.
- No literal user-facing string in any template.
- No `left`/`right` in any CSS you touched.
- Anything you could not finish is stated plainly, not quietly dropped.
