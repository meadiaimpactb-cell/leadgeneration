# Amad Craft — B2B Corporate Site

A bilingual (Arabic / English) B2B showcase website for **Amad Craft (أمد الحرف)**.
Its single purpose is to convince an institutional visitor and capture their contact
details as a lead. Nothing is sold here.

The existing Zid store at `amadcraft.sa` continues to operate unchanged; this site
runs alongside it, never instead of it.

> **Specification:** [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) (Arabic original:
> [`docs/PROJECT_BRIEF.ar.md`](docs/PROJECT_BRIEF.ar.md)) is the Single Source of
> Truth. [`CLAUDE.md`](CLAUDE.md) is the developer's working companion.

---

## Stack

Laravel 12 (PHP 8.3) · Vue 3 · Inertia.js **with SSR** · Vite 7 · Tailwind v4 ·
MySQL 8 · Redis/database queues.

SSR is a hard requirement, not an optimisation: search indexing is a contractual
condition (§13), and a client-rendered SPA serves crawlers an empty document.

---

## Requirements

- PHP **8.3+** with `pdo_mysql`, `mbstring`, `intl`, `gd` (or `imagick`), `zip`
- Composer 2
- Node **20+** and npm
- MySQL **8**
- Redis (optional — the queue and cache default to the database driver)

Laragon on Windows provides all of these.

---

## Local setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create the databases and point `.env` at them:

```sql
CREATE DATABASE amadcraft_b2b      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE amadcraft_b2b_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set `IP_HASH_SALT` in `.env` to a long random string — visitor IPs are stored only
as a salted hash (§15.3), and an unsalted hash of an IPv4 address is trivially
reversible.

Then:

```bash
php artisan migrate --seed      # structural data only; safe to run in production
php artisan amad:create-admin   # interactive — no admin is ever seeded
```

---

## Running it

Development needs three processes:

```bash
php artisan serve       # http://127.0.0.1:8000
npm run dev             # Vite
php artisan queue:work  # the CRM push and notifications are queued
```

To see what a crawler and a first-time visitor actually receive, run the SSR build
instead of `npm run dev`:

```bash
npm run build              # client + SSR bundles
php artisan inertia:start-ssr
php artisan serve
```

---

## Tests

```bash
php artisan test
```

Tests run against **MySQL** (`amadcraft_b2b_test`), not SQLite. The schema leans on
`ENUM` columns and JSON casts whose behaviour differs between the two, and the leads
table is the one thing that must not surprise anyone in production.

Coverage today: the full lead chain (submit → store → CRM → notify → confirm),
contact auto-detection, anti-spam guards, rate limiting, locale routing and hreflang.

---

## Content

The site is **100% dynamic**. Every heading, paragraph, number, image, link and
button is managed from the admin panel and stored in the database. There is no
hardcoded copy anywhere, by design (§0.3).

`resources/lang/{ar,en}` holds *functional* strings only — buttons, labels, error
messages. Marketing copy is never written by the development side (§0.1).

Consequently, **a freshly seeded site renders its layout with empty containers.**
That is correct behaviour, not a bug. It fills in as Amad Craft enters content.

---

## Brand assets

Sourced from the identity folder (`هوية امد الحرف/`) and processed into
`public/`:

- `public/fonts/` — SaudiWeb (brand display) and IBM Plex Sans / Sans Arabic (body),
  self-hosted `woff2`, two weights per family, `font-display: swap`.
- `public/brand/` — both logo lockups as SVG, cropped to a tight `viewBox` and
  redrawn in `currentColor` so the four approved colour treatments are a CSS class
  rather than four files.

`tools/` holds the small Node scripts that produced them; they are reproducible, not
one-off manual edits.

> **Licensing:** web-embedding rights for the Latin display face (Kefa) are not yet
> confirmed, so it is not bundled. Latin display currently uses SaudiWeb. See
> `CLAUDE.md` → *Deviations*.

---

## CRM

Leads are pushed through an abstraction layer (`App\Services\Crm\CrmDriver`) with
four implementations: `odoo`, `zid`, `webhook` (a signed generic fallback) and
`null` (local development). The active one is chosen with `CRM_DRIVER` in `.env` —
switching providers never requires a code change (§6.3).

Every attempt is written to `crm_sync_logs`, retried with exponential backoff, and
raises an email alert if it ultimately fails. A lead is never silently lost.

**Before launch:** `CRM_DRIVER=null` must not reach production, and the Odoo field
mapping needs verifying against the live instance — see the note at the top of
`OdooCrmDriver`.

---

## Status

Built: project setup, database schema, models, the home-page prototype, and the
complete lead-capture and CRM path.

Not built yet: the admin panel, the remaining public pages, campaign pages, the
sitemap and schema builders, third-party integrations, and the CSP header.

See `CLAUDE.md` for the detailed phase status.
