# Full Build Prompt — Amad Craft B2B Corporate Website
### Laravel 11 + Inertia + Vue 3 (SSR) + MySQL/phpMyAdmin — Arabic/English — Figma-grade design spec

> **How to use this file:** This is the project's Single Source of Truth. Paste it in full as context for your coding agent (Claude Code / Cursor), or place it at the repo root as `PROJECT_BRIEF.md` and reference it from `CLAUDE.md`. Every later request is measured against this file. Anything not stated here is out of scope until approved in writing.

---

## 0. Role and Assignment

You are a **full-stack engineer + UI/product designer**, tasked with building the corporate website for **Amad Craft (أمد الحرف)** from scratch — no ready-made templates, no off-the-shelf CMS.

Working method: **plan → prototype ONE page and get it approved → build → self-review → deliver.** Do not write production code before the prototype is approved.

**Three unbreakable constraints:**
1. **Do not write marketing content, messaging, or copy.** All content arrives ready from Amad Craft. You build the containers; the text comes from the admin panel. Only permitted: functional UI strings (buttons, alerts, error messages), stored in translation files.
2. **Do not build any commercial function.** No cart, no payment, no orders, no inventory, no logistics, no invoicing, no tax, no currencies.
3. **No hardcoded content.** The site is 100% dynamic; every text, image, number, link and button is managed from the admin panel and stored in the database.

---

## 1. Project Nature and Objective

**What it is:** A B2B corporate showcase website. Its job is to convince an institutional visitor, then push them to make contact via a call to action.

**What it is not:** Not an e-commerce store, and not a replacement for the existing Zid store.

**The single measurable objective:**
> **The number and quality of institutional Leads arriving through the site.**

Sales are not measured from this site because no selling happens on it. Every design and technical decision must be justified by its effect on this single metric.

**What the site delivers:**
- Presents Amad Craft's services and what the client gains from them.
- Highlights differentiation: why Amad Craft and not others (marketing copy arrives ready).
- Presents products for information only, with no push toward direct purchase.
- Call-to-action buttons with specific wording, distributed inside the content, all leading to **one single contact field**.

---

## 2. Scope

### 2.1 In scope
| # | Item |
|---|---|
| 1 | Corporate introductory content |
| 2 | Solutions pages for companies |
| 3 | The simple contact field (replaces request and qualification forms) |
| 4 | CRM integration |
| 5 | Marketing campaign pages + ability to create future landing pages without redevelopment |
| 6 | Impact and reports section |
| 7 | Training and empowerment section |
| 8 | Custom admin panel with tiered permissions |
| 9 | Arabic and English versions |
| 10 | Integrations, measurement, and SEO |

### 2.2 Out of scope — never build
- Shopping cart, payment links, checkout pages.
- Logistics, inventory management, shipping.
- Accounting, tax invoicing, multi-currency.
- Purchase orders or product selection and purchasing.
- Any e-commerce store function.

### 2.3 Explicitly cancelled — do not build (may appear in older drafts)
- ❌ Institutional quote-request form.
- ❌ The main form, qualification forms, artisan forms, partner and entity forms — **all replaced by one contact field**.
- ❌ A standalone "Request a Quote" page.
- ❌ Receiving detailed quote requests and warranty tiers in their expanded format.
- ❌ Unified sales measurement between the site and the store, and sales attribution to source.
- ❌ Any content or marketing copy written by the development side.
- ❌ Any commercial function: payment, cart, inventory, logistics, invoicing, currencies.
- ❌ "Page five" content and the content map — these arrive ready from Amad Craft.

---

## 3. Target Audience (deliberately narrow, not general)

| Segment | Description | Priority |
|---|---|---|
| Government & semi-government entities | e.g. Diriyah, NEOM and similar | **First** |
| Specific private-sector companies | Only the named companies, not the private sector at large | Second |
| Partners | Event companies, exhibitions and conferences, affiliate marketing firms — all treated as companies | Third |
| Artisans | Only four specific types are targeted; anyone else is not | Fourth |

> These segments and their content arrive ready in the content file. Your job: build a `sectors` data model that holds them dynamically, plus one sector page per segment.

---

## 4. Relationship with the E-commerce Store (Zid)

The existing Zid store (`amadcraft.sa`) **continues to operate as is, with no change to its functions or products**. The new site runs alongside it, not instead of it.

**Separation of the two channels:**

| | Store (Zid) | Corporate site (this project) |
|---|---|---|
| Model | C2B | B2B |
| Audience | Individual consumers | Companies, government entities, donor bodies, artisans |
| Purpose | Buying a piece or a gift, no customization | Presenting capabilities and impact, receiving contact |
| Cycle | Instant: pick product → buy | Long: customization + branding + quantities + technical proposal; may take two months |

**Binding linking rules:**
- A link to the store may exist on company pages for whoever wants to go there — **with no payment and no sales push**. The site does not steer visitors to direct purchase.
- The store does not steer institutional visitors to a "Request a Quote" page — no such page exists; only a CTA and a contact field.
- **No unified sales measurement between the channels**, because the corporate site involves no selling. Its only measurement: number and quality of leads.
- Deal closing happens through the sales team and the CRM (currently tracked via Trello).

---

## 5. Sitemap and Pages

> The final map and its sections arrive in the content file from Amad Craft. Build the structure below as a flexible data model that allows adding, removing and reordering pages and sections from the admin panel without code.

```
/{locale}/                          Home
/{locale}/about                     About — the movement, vision, civilizational impact
/{locale}/solutions                 Solutions for companies (index)
/{locale}/solutions/{slug}          Single solution page
/{locale}/sectors/{slug}            Sector page (government / private / partners / artisans)
/{locale}/products                  Product showcase — informational only
/{locale}/impact                    Impact & reports (numbers + artisan stories + publishable reports)
/{locale}/training                  Training & empowerment
/{locale}/partners                  Partners & accreditations
/{locale}/contact                   Contact — one field
/{locale}/c/{campaign-slug}         Campaign and landing pages
/{locale}/legal/{slug}              Privacy & terms
/sitemap.xml  /robots.txt
```

**Governing rule:** Each of the four main sections (corporate, solutions, impact, training) ends with a call to action leading to the single contact field.

---

## 6. Lead Capture — The Most Important Mechanism on the Site

**Governing rule:** the fewest possible steps and the least possible data, so the prospect does not abandon the site, and to protect their data privacy.

### 6.1 Form specification (binding, literally)

| Field | Required? | Spec |
|---|---|---|
| Contact method | ✅ **The only required one** | A single field accepting **email or mobile number** — the visitor chooses. Auto-detected; no type selector. |
| Message | ❌ Optional | One line, where the visitor writes what they want (e.g. "I have a conference, contact me"). |
| Name | 🚫 **Never asked** | No name and no additional data whatsoever. |

- **One form only** for all segments: artisans, partners, government entities, private sector — no separate forms.
- The rest of the data is completed by the sales team during follow-up and closing.
- Protection: honeypot + rate limiting + Cloudflare Turnstile (no intrusive visible CAPTCHA).

### 6.2 What happens after submission (full chain)
1. The request is stored in the site database (`leads`).
2. It is sent **immediately** to the CRM via a queued job.
3. A direct notification reaches the Amad Craft team (email + extensible webhook for WhatsApp/Slack).
4. The sender receives a confirmation message.
5. The **marketing source** is stored with every request (full UTM + referrer + landing page + gclid/fbclid) to track conversions and goals.

### 6.3 CRM architecture (critical design point)
The current system is **Odoo's CRM**. The likely direction is retiring it and moving to **Zid's CRM**, so the site and store run on one system.

> **Therefore: do not couple to a single provider.** Build an abstraction layer:
> ```php
> interface CrmDriver { public function pushLead(Lead $lead): CrmResult; }
> ```
> With implementations: `OdooCrmDriver`, `ZidCrmDriver`, `WebhookCrmDriver` (fallback), `NullCrmDriver` (dev). The provider is chosen from `config/crm.php` + an env variable and is swappable **without code changes**.
> **At minimum: the site must be ready to integrate with Odoo at launch.**
> Without CRM integration, requests would only reach email with no organized follow-up — **not acceptable**.

Log every sync attempt in `crm_sync_logs` with automatic retry (exponential backoff) and an alert on final failure.

---

## 7. Technical Architecture

### 7.1 Approved stack
| Layer | Technology |
|---|---|
| Backend | **Laravel 11** (PHP 8.3) |
| Frontend | **Vue 3** (Composition API + `<script setup>`) + **Vite** |
| Bridge | **Inertia.js** with **SSR enabled** |
| Database | **MySQL 8** managed via **phpMyAdmin** |
| Styling | Tailwind CSS + a custom CSS token layer (RTL-first) |
| Queues | Laravel Queue (database or Redis) + Supervisor |
| Cache | Redis or file — page cache for public routes |
| Permissions | `spatie/laravel-permission` |
| Media | `spatie/laravel-medialibrary` + automatic WebP/AVIF conversions |
| Audit | `spatie/laravel-activitylog` |
| i18n | Translation-table pattern — **no opaque "magic" translation packages** |

### 7.2 Why Inertia + SSR and not a decoupled SPA (binding architectural decision)
SEO is a hard requirement here. A pure Vue SPA serves empty HTML to crawlers and damages indexing. **Inertia with SSR** gives you:
- Fully rendered HTML from the server for every page (correct indexing + fast FCP).
- A complete, reactive Vue experience on the client.
- Real `<a>` links and correct HTTP statuses (200/301/404) — essential for SEO.
- Simplicity: no need to build, document and secure a separate API per page.

> Acceptable fallback only if SSR proves impossible: Blade as the shell + Vue islands for interactive components. **Forbidden: a pure client-rendered SPA.**

### 7.3 Repository structure
```
amadcraft-b2b/
├── app/
│   ├── Http/Controllers/{Public,Admin}/
│   ├── Http/Requests/
│   ├── Models/
│   ├── Services/Crm/{CrmManager,Drivers/}
│   ├── Services/Seo/{MetaBuilder,SitemapGenerator,SchemaBuilder}
│   ├── Actions/Leads/{StoreLead,NotifyTeam,PushToCrm}
│   ├── Jobs/  ├── Notifications/  ├── Policies/
├── resources/
│   ├── js/
│   │   ├── Pages/{Public,Admin}/        Inertia pages
│   │   ├── Components/{ui,sections,forms,admin}/
│   │   ├── Composables/  ├── Layouts/  ├── ssr.js  └── app.js
│   ├── css/{tokens.css, base.css, app.css}
│   └── lang/{ar,en}/
├── database/{migrations,seeders,factories}/
├── public/fonts/            brand fonts as subsetted woff2
├── tests/{Feature,Unit}/
├── CLAUDE.md                coding-agent instructions
└── PROJECT_BRIEF.md         this file
```

### 7.4 Code rules
- PSR-12 + Laravel Pint. ESLint + Prettier for Vue.
- Form Requests for every input. Policies for every admin resource.
- No N+1 queries (use `with()`, use lazy loading carefully) — watch with Laravel Debugbar in dev.
- Every UI string through `__()` or `$t()` — **no literal text in templates**.
- Migrations always reversible (`down`). Seeders for structural data only (no dummy marketing content in production).
- Feature tests are mandatory for the full lead path (submit → store → CRM → notify → confirm).

---

## 8. Database Schema (MySQL)

> Pattern: base table + `*_translations` table keyed by `locale`. Every translation table carries `unique(parent_id, locale)`.

### 8.1 Content and pages
```sql
pages(id, slug, template, parent_id, sort_order, status[draft|published],
      published_at, is_indexable, layout_settings JSON, created_by, timestamps, soft_deletes)

page_translations(id, page_id, locale, title, subtitle, excerpt,
      meta_title, meta_description, og_image_id, canonical_override, unique(page_id,locale))

sections(id, sectionable_type, sectionable_id, type, sort_order,
      is_active, settings JSON, timestamps)
      -- type: hero | rich_text | stats | cards | logos | gallery | video
      --       testimonial | cta_band | accordion | timeline | contact_block

section_translations(id, section_id, locale, heading, subheading, body, cta_label, cta_url)
```

### 8.2 Domain entities
```sql
solutions(id, slug, icon, sort_order, is_active, hero_media_id, timestamps)
solution_translations(id, solution_id, locale, name, summary, body, meta_title, meta_description)

sectors(id, slug, key[government|private|partners|artisans], sort_order, is_active)
sector_translations(id, sector_id, locale, name, summary, body, meta_title, meta_description)

showcase_products(id, slug, category_id, sort_order, is_active,
      external_store_url NULL,          -- informational store link only, no price, no buying
      primary_media_id, timestamps)
showcase_product_translations(id, product_id, locale, name, description, craft_technique, meta_title, meta_description)

impact_metrics(id, key, value_numeric, value_suffix, year, sort_order, is_active)
impact_metric_translations(id, metric_id, locale, label, note)

stories(id, slug, person_media_id, sort_order, is_published, published_at)
story_translations(id, story_id, locale, title, body, quote, attribution)

reports(id, slug, year, file_media_id, cover_media_id, is_public, sort_order)
report_translations(id, report_id, locale, title, summary)

training_programs(id, slug, duration_weeks, sort_order, is_active)
training_program_translations(id, program_id, locale, name, summary, body, outcomes)

partners(id, name, logo_media_id, website_url, type[partner|accreditation|client], sort_order, is_active)
partner_translations(id, partner_id, locale, display_name, note)
```

### 8.3 Campaigns and leads
```sql
campaigns(id, slug, is_active, starts_at, ends_at,
      default_utm_source, default_utm_medium, default_utm_campaign,
      template, settings JSON, timestamps)
campaign_translations(id, campaign_id, locale, title, meta_title, meta_description)

leads(id, uuid,
      contact_value,                -- email or phone, normalized
      contact_type ENUM('email','phone'),
      message NULL,                 -- optional single line
      locale, page_url, referrer,
      utm_source, utm_medium, utm_campaign, utm_term, utm_content,
      gclid NULL, fbclid NULL,
      campaign_id NULL, sector_hint NULL,
      user_agent, ip_hash,          -- hashed, never raw IP (privacy)
      status ENUM('new','contacted','qualified','won','lost') DEFAULT 'new',
      crm_status ENUM('pending','synced','failed') DEFAULT 'pending',
      crm_provider NULL, crm_external_id NULL, crm_synced_at NULL,
      timestamps, index(created_at), index(crm_status))

crm_sync_logs(id, lead_id, provider, attempt, http_status, request JSON, response JSON, error NULL, created_at)
```

### 8.4 System
```sql
users(id, name, email, password, is_active, last_login_at, timestamps)
roles / permissions / model_has_roles          -- spatie
media                                          -- spatie medialibrary
media_translations(id, media_id, locale, alt_text, caption)

navigations(id, key[header|footer_main|footer_legal], is_active)
navigation_items(id, navigation_id, parent_id, url, route_name, linkable_type, linkable_id, sort_order, is_active)
navigation_item_translations(id, item_id, locale, label)

settings(id, group, key, value JSON, is_public)  -- contact info, social, tracking IDs, SEO defaults
redirects(id, from_path, to_path, status_code DEFAULT 301, hits, is_active)  -- SEO protection
ctas(id, key, style, target_type, target_value, is_active)
cta_translations(id, cta_id, locale, label)
activity_log                                    -- spatie
```

---

## 9. Admin Panel

Route `/admin`. Built on the same stack (Inertia + Vue), **no Filament, no Nova** — a custom panel as required, with an Arabic RTL interface by default.

### 9.1 Mandatory capabilities
- Add, edit and delete **pages, sections and content** with no technical help needed.
- **Section Builder**: drag-and-drop ordering of sections within a page, section-type selection, bilingual content entry.
- **Create marketing campaign pages with complete ease** — critical, because the campaigns team will be the ones using it. A 3-step wizard: pick template → fill content → publish.
- Manage menus (header/footer), images, videos and files.
- **Save drafts and preview before publishing** (preview token via a secret link).
- **Tiered permissions**: `super-admin` (everything) / `editor` (content only) / `campaign-manager` (campaigns and landing pages) / `sales` (leads only — read, export, change status).
- **Export the leads list at any time** (CSV / XLSX) with filters by date, campaign, status and source.
- Internal measurement dashboard (see §12).
- Audit trail: who changed what and when.
- Side-by-side bilingual editor (AR | EN) with a "translation missing" indicator.

### 9.2 Panel security rules
- 2FA optional for admins, mandatory for `super-admin`.
- Rate limiting on login, lockout after 5 attempts.
- Policies on every resource — never rely on hiding buttons alone.
- Uploads: real MIME verification, size limits, storage outside `public` with signed URLs for private files.

---

## 10. UI/UX Design System — Figma-grade handoff

### 10.1 Design direction

**The idea:** *Corporate Weave.* Amad Craft turns craft into an institutional product — and the design embodies that: **a strict corporate structure (grid, calm, trust) crossed by a single craft thread.**

**Signature element:** the **Sadu thread** — a thin woven hairline SVG pattern derived from Sadu weaving motifs, appearing in exactly three places:
1. As the divider between sections (replacing the conventional grey rule).
2. As a very faint ground behind the impact (numbers) section.
3. As the underline on links and secondary buttons on hover — weaving itself right-to-left over 300ms.

> Do not use it in a fourth place. Boldness is spent in one location; everything around it stays quiet and disciplined.

**Explicitly avoided:** the cream background + high-contrast serif + clay accent default look, decorative gradients, floating icon clusters, and glassmorphism cards.

### 10.2 Color tokens (from the approved identity)

| Name | Hex | RGB | Use |
|---|---|---|---|
| **Navy Blue** | `#002546` | 0, 37, 70 | Dominant: header, hero, footer, headings, primary buttons |
| **Lavender Purple** | `#8685D8` | 134, 133, 216 | Secondary interactive: links, hover states, data indicators |
| **Burnt Orange** | `#D7653B` | 215, 101, 59 | Accent: calls to action, badges, focus underline |
| **Light Gold** | `#DCAD75` | 220, 173, 117 | Premium: dividers, hairline borders, impact-section ground |

**Mandatory derivations (to guarantee AA accessibility):**
```
--action-600: #B4522C   /* darkened orange — for buttons with white text: 4.6:1 contrast ✅ */
--navy-800:   #06345C   --navy-700: #0B4370   --navy-100: #E3EAF1
--lavender-700: #5F5EBE  /* for text on white: 4.7:1 contrast ✅ */
--gold-100:   #F6ECDD   --sand: #F3EEE7   /* warm backgrounds, used sparingly */
--ink:        #10161C   --muted: #5A6B7B   --hairline: rgba(0,37,70,0.12)
--paper:      #FFFFFF   --paper-alt: #F7F9FB
```

**Usage rules:**
- Primary buttons: `--navy-900` background + white text (15:1).
- Main CTA on a dark ground: `#D7653B` background + `#FFFFFF` text **only at 18px/700 or larger**; for smaller sizes use `--action-600`.
- `#8685D8` is never used as body text on white — use `--lavender-700`.
- Never more than two accent colors on one screen.

### 10.3 Typography

| Role | Typeface | Note |
|---|---|---|
| Display — Arabic | **SaudiFont (الخط السعودي)** — brand face | Convert to `woff2` with Arabic + numeral subsetting |
| Display — Latin | **Kefa** — brand face | From the identity files (`Kefa.ttc`) |
| Body — Arabic | **IBM Plex Sans Arabic** (alt: Tajawal) | High legibility for long corporate copy |
| Body — Latin | **IBM Plex Sans** | |
| Numbers/data | IBM Plex Sans — `font-variant-numeric: tabular-nums` | For impact numbers and dashboards |

> ⚠️ Verify web-use licensing for the brand faces before launch. Self-host them (`/public/fonts`) with `font-display: swap`, loading only two weights per family.

**Fluid type scale:**
```css
--fs-display:  clamp(2.5rem, 1.6rem + 3.6vw, 4.5rem);   /* 40 → 72 */
--fs-h1:       clamp(2rem,   1.4rem + 2.4vw, 3.25rem);  /* 32 → 52 */
--fs-h2:       clamp(1.5rem, 1.2rem + 1.4vw, 2.25rem);  /* 24 → 36 */
--fs-h3:       clamp(1.25rem,1.1rem + 0.7vw, 1.5rem);   /* 20 → 24 */
--fs-body-lg:  1.125rem;  --fs-body: 1rem;  --fs-sm: 0.875rem;  --fs-xs: 0.75rem;
```
**Line height:** Arabic needs more room — Arabic body `1.85`, Latin `1.65`, headings `1.2`, display `1.1`.
**Letter spacing:** Arabic always `0` (never apply letter-spacing to Arabic). Latin headings `-0.02em`.

### 10.4 Grid and spacing

| Size | Breakpoint | Columns | Margin | Gutter |
|---|---|---|---|---|
| Mobile | 0–639 | 4 | 20px | 16px |
| Tablet | 640–1023 | 8 | 32px | 20px |
| Desktop | 1024–1439 | 12 | 48px | 24px |
| Wide | 1440+ | 12 (1320px container) | auto | 24px |

**Spacing scale (base 4):** `4, 8, 12, 16, 24, 32, 40, 56, 72, 96, 128`
**Section vertical padding:** mobile `72px`, desktop `128px`.
**Radii:** `--r-sm: 4px` (inputs, badges) / `--r-md: 12px` (cards) / `--r-pill: 999px` (filters only). No arbitrary large radii.
**Shadow:** exactly one — `0 1px 2px rgba(0,37,70,.06), 0 8px 24px rgba(0,37,70,.06)` — for raised cards only.

### 10.5 Component inventory (Figma-style)

Naming: `Category/Component/Variant` with explicit props.

**Primitives (ui/)**
| Component | Variants | States |
|---|---|---|
| `ui/Button` | primary, secondary, ghost, link | default, hover, focus-visible, active, disabled, loading |
| `ui/Input` | text, textarea-1line | default, focus, filled, error, disabled |
| `ui/Badge` | sector, year, status | — |
| `ui/Card` | solution, story, report, product, program | default, hover (2px lift) |
| `ui/Divider` | plain, **sadu** (signature) | — |
| `ui/Accordion` | — | collapsed, expanded |
| `ui/Tabs` | underline | idle, active |
| `ui/Breadcrumb` | — | — |
| `ui/LangSwitch` | inline, dropdown | — |
| `ui/Skeleton` | text, card, media | — |
| `ui/Toast` | success, error | — |

**Sections (sections/)**
`Hero` · `IntroStatement` · `SolutionsGrid` · `SectorSpotlight` · `ImpactStats` · `StoryCarousel` · `ProductShowcase` · `PartnersLogos` · `ReportsList` · `TrainingTracks` · `CtaBand` · `RichText` · `MediaSplit` · `VideoBlock` · `FaqAccordion` · `ContactBlock`

**Forms (forms/)**
`LeadField` (the unified field) · `LeadInline` (embedded inside a CTA band) · `LeadModal` (opened from any button)

**Layouts (Layouts/)**
`PublicLayout` (sticky header + footer) · `CampaignLayout` (no nav — conversion focus) · `AdminLayout`

### 10.6 Critical component spec: `forms/LeadField`

```
┌──────────────────────────────────────────────────────────┐
│  [ Heading — from admin panel ]                          │
│  [ Short reassurance line — from admin panel ]           │
│                                                          │
│  ┌────────────────────────────────────┐  ┌────────────┐  │
│  │ Your email or mobile number        │  │  Contact   │  │
│  └────────────────────────────────────┘  └────────────┘  │
│  ⌄ Add a message (optional)                              │
│  ┌──────────────────────────────────────────────────┐    │
│  │ One line…                                        │    │
│  └──────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────┘
```
- Field height 56px, text 18px (prevents iOS auto-zoom).
- `inputmode="text"`, `autocomplete="email tel"`, `dir="auto"` on the field (so Latin emails/numbers behave inside an RTL page).
- Validation on submit only, never while typing. Error message below the field in `--action-600`, explaining what went wrong and how to fix it.
- Submit button moves to `loading`, then to confirmation **in place** (no page transition), with `aria-live="polite"`.
- The message field is collapsed by default — keeps cognitive load minimal.
- **Success state:** short message + the Sadu thread weaving itself underneath. No modal, no redirect.

### 10.7 Motion
- Easing `cubic-bezier(.2,.7,.3,1)`. Durations: micro `160ms` / element `300ms` / scroll reveal `450ms`.
- Section reveal: `opacity 0→1` + `translateY 16px→0`, once only, with 60ms stagger inside grids.
- Impact counters: count up once on entry, over 900ms.
- **`prefers-reduced-motion: reduce` disables all of the above** and renders final states.

### 10.8 Accessibility — WCAG 2.2 AA
- Text/background contrast ≥ 4.5:1 (≥ 3:1 for large text and functional icons).
- `:focus-visible` always visible: 2px `--lavender-700` ring with 2px offset. Never `outline: none` without a replacement.
- Full keyboard navigation, logical order, "skip to content" link.
- Semantic HTML: `<header> <nav> <main> <section> <footer>`, one `<h1>` per page, correct heading order.
- Every image has `alt` managed from the admin panel (`media_translations`). Decorative images use `alt=""`.
- Video: manual controls, no autoplay with sound.
- Touch targets ≥ 44×44px.

---

## 11. Screen Specs (Wireframes)

### 11.1 Home
```
┌─────────────────────────────────────────────────────────────┐
│ [logo]   Solutions  Sectors  Impact  Training  About [AR] [Contact]│ sticky; transparent over hero then solid
├─────────────────────────────────────────────────────────────┤
│  HERO — navy ground, generous negative space                │
│  Wide headline (brand face) + one line + [CTA] [secondary]  │
│  Media: vertically cropped craft image/video on one side    │
├──── Sadu divider ──────────────────────────────────────────┤
│  Intro statement — one large paragraph, no imagery          │
├─────────────────────────────────────────────────────────────┤
│  Solutions — 3-up grid (icon + name + line + link)          │
├─────────────────────────────────────────────────────────────┤
│  Sectors — 4 cards (government / private / partners / artisans)│
├──── faint Sadu ground ─────────────────────────────────────┤
│  Impact — 3–4 large numbers + caption + [Reports →]         │
├─────────────────────────────────────────────────────────────┤
│  Artisan stories — horizontal carousel, image + quote       │
├─────────────────────────────────────────────────────────────┤
│  Products — informational only, no prices, no buy buttons   │
├─────────────────────────────────────────────────────────────┤
│  Partners & accreditations — greyscale logo strip, colors on hover│
├─────────────────────────────────────────────────────────────┤
│  CTA Band — navy ground + LeadInline (one field + button)   │
├─────────────────────────────────────────────────────────────┤
│  Footer — nav, contact, social, store link (informational), legal│
└─────────────────────────────────────────────────────────────┘
```

### 11.2 Sector page `/sectors/{slug}`
```
Breadcrumb › focused hero (title + line + CTA)
› "What we offer this sector" — 3 cards
› MediaSplit: image + text (how we work with you)
› Sector-specific impact numbers
› Client logos for this sector
› FAQ (accordion)
› CtaBand + LeadInline
```

### 11.3 Campaign page `/c/{slug}`
```
No navigation. Logo only + [Contact].
Hero (campaign title) › 3 value points › trust proof (logos/number) › large LeadInline › minimal footer.
One goal per page. No outbound links except legal.
```

### 11.4 Admin — leads list
```
┌ Filters: [Date] [Campaign] [Source] [Status] [CRM state]   [Export CSV/XLSX] ┐
├ Date | Contact | Message | Source/Campaign | Status ▾ | CRM ● | ⋯            ┤
└ Row click → side panel: full detail + UTM + CRM sync log + change status     ┘
```

---

## 12. Internationalization (i18n)

- Two languages: **Arabic and English only**.
- **The English version is essential, not secondary** — many of the target segments, especially semi-government entities, have staff who do not speak Arabic.
- Structure: locale prefix in the path `/ar/...` and `/en/...`, with a root redirect based on `Accept-Language` and the preference stored in a cookie.
- `<html lang="ar" dir="rtl">` ⇄ `<html lang="en" dir="ltr">` — switched automatically.
- **RTL-first**: use logical properties exclusively (`margin-inline-start`, `padding-inline`, `inset-inline`) — no `left/right` in CSS.
- Directional icons (arrows, chevrons) mirror; logos, photos and numbers do not.
- Reciprocal `hreflang` between versions + `x-default`.
- UI strings in `resources/lang/{ar,en}`; site content in translation tables.
- Missing-translation behavior: if an English translation is absent, **do not fall back to Arabic** — hide the page from the English version and English sitemap, and flag it in the admin panel.

> ⚠️ **Management decision required:** does the English version launch in phase two or with the first launch? Build for both, and gate the English version behind a `settings` switch.

---

## 13. SEO

The site is delivered fully technically prepared:
- Correct, search-friendly heading and URL structure (properly encoded Arabic slugs).
- `Meta Title` and `Meta Description` **editable per page and per language** from the admin panel.
- `sitemap.xml` **auto-updated** (index + per-language sitemaps).
- Manageable `robots.txt`.
- Appropriate Schema.org: `Organization`, `WebSite`, `BreadcrumbList`, `Service`, `FAQPage`, `Article` for stories.
- Optimized images: WebP/AVIF, `srcset`, explicit `width/height`, `loading="lazy"` except the hero, `fetchpriority="high"` for the hero.
- High page speed (see §14).
- Canonical per page + `hreflang`.
- A **`redirects` table** to manage 301s from the admin panel — essential to protect indexing of legacy URLs.
- Handling technical errors affecting indexing: correct 404s, no redirect chains, no duplicate content.
- **An SEO specialist is engaged** to build the site around the keywords the target audience actually searches — the keyword list arrives as an input and is applied across titles, descriptions and page structure.

> This does not include a guarantee of any particular search ranking.

---

## 14. Integrations and Measurement

### 14.1 Required integrations
| Tool | Purpose | Note |
|---|---|---|
| **Google Analytics 4** | Measurement and retargeting | **Very important** |
| **Google Tag Manager** | Tag management | All tags injected through it |
| **Google Search Console** | Indexing and performance | Ownership verification |
| **Google Business Profile + Maps** | Improve Google Maps ranking | |
| **Meta Pixel** | Tracking and targeting | |
| **Meta Conversions API** | Server-side conversions | From Laravel, with data hashing |
| **Microsoft Clarity** | Session recording and heatmaps | |
| **CRM** (Zid / Odoo) | Receive requests immediately | Via the abstraction layer in §6.3 |
| Form/conversion tracking | `lead_submitted` event | dataLayer + server-side |

**Binding rule:** all integrations are implemented **using accounts owned by Amad Craft**. No account is created under any other party's name.
All tracking IDs (GA4 ID, GTM ID, Pixel ID…) are managed from `settings` in the admin panel, **never in code**.

### 14.2 Measurement and reporting
Management can see from the site:
- Number of visitors and their sources.
- Most-viewed pages.
- Requests received per campaign.
- Visitor-to-lead conversion rate.
- Periodic report dashboards built on this data.

> **Note:** sales are not measured from this site because no selling happens on it. The only true indicator is the number and quality of leads arriving through it.

---

## 15. Performance, Quality and Security

### 15.1 Performance budget (measured in Lighthouse mobile / slow 4G)
| Metric | Limit |
|---|---|
| LCP | ≤ 2.0s |
| INP | ≤ 200ms |
| CLS | ≤ 0.05 |
| Initial JS (gzipped) | ≤ 180KB |
| Initial CSS | ≤ 60KB |
| First page weight | ≤ 1.2MB |
| Lighthouse (Perf/A11y/Best/SEO) | ≥ 90 each |

**Techniques:** SSR + per-page code splitting, lazy loading of below-the-fold sections, preload one font only, HTTP cache + Laravel page cache for public routes, Brotli compression, CDN for media.

### 15.2 Quality standards
- High load speed — **essential**.
- Full responsiveness on mobile and all modern devices and screens.
- **Full Arabic language support** (RTL, diacritics, numerals, correct text truncation).
- Easy administration without technical help.
- Accessibility for people with disabilities.
- Security and data protection.
- Continuity, scalability and independence.
- Ready to publish, free of operational defects, meeting all agreed features.

### 15.3 Security
- Forced HTTPS + HSTS + active SSL certificate.
- Security headers: CSP, `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`.
- CSRF on all forms, rate limiting on `POST /leads` (e.g. 5/min/IP).
- Store a hash of the IP, never the raw address.
- Scheduled backups of database and media (`spatie/laravel-backup`) with a documented restore test.
- No secrets in the repository — `.env` only, with a complete `.env.example`.
- Audit log for every administrative change.

---

## 16. Domain, Hosting and Deployment

- Connect the site to the domain and hosting and activate it fully.
- Option on the table: move the store to a sub-address under the same domain, and assign a sub-domain for the B2B corporate site.
- **Hard condition:** none of this may affect the SEO built over the past years.
- **If the move would affect SEO, it is not executed at all**; the store stays on its domain, and a sub-domain is assigned to the corporate site. The technical decision rests with the implementer; the condition is **protecting SEO**.
- In all cases: the store does not go down, its administration method does not change, no data or orders are lost, and **automatic 301 redirects for all existing links** must be in place without interruption.

**Environments:** `local` → `staging` (password-protected + `noindex`) → `production`.
**Deployment:** Git + a pipeline: `composer install --no-dev` → `npm ci && npm run build` → `php artisan migrate --force` → SSR build → `optimize` → restart queues and SSR.

---

## 17. Delivery Plan (Sprints)

| Phase | Output | Exit criterion |
|---|---|---|
| **0 — Setup** | Repo, environment, `.env`, Tailwind + tokens, fonts, Inertia + SSR working | A blank page renders server-side |
| **1 — Planning** | Final sitemap from the content file, customer journey, section inventory | Amad Craft approves the map |
| **2 — Prototype** | **One page only** (Home), fully designed with real content | **Formal approval** — nothing else is built before it |
| **3 — DB + Admin** | All tables + pages/sections CRUD + permissions + media | The Amad Craft team creates a full page unaided |
| **4 — Public frontend** | All pages and sections, Arabic version | Design review + identity compliance |
| **5 — Leads + CRM** | `LeadField` + storage + notifications + confirmation + CRM sync + UTM | End-to-end test passes 10/10 |
| **6 — English + SEO** | Translation, hreflang, sitemap, schema, redirects | Screaming Frog crawl with no critical errors |
| **7 — Integrations** | GA4, GTM, Pixel, CAPI, Clarity, Search Console, dashboard | `lead_submitted` appears in GA4 and Meta |
| **8 — Perf + a11y** | Optimization, Lighthouse, keyboard and screen-reader pass | All scores ≥ 90 |
| **9 — Campaigns** | Campaign page template + creation wizard | Campaigns team builds a landing page in < 10 min |
| **10 — Launch & handover** | Domain, SSL, 301s, backups, training session, credentials handover | §18 and §19 |

> **Operational recommendation:** content preparation, number collection and product photography start **in parallel** with the technical build, not after it — late content is what delays projects most. The agreement is that content arrives ready and agreed before implementation starts.

---

## 18. Definition of Done — the project is complete when

- [ ] All sections and pages are implemented.
- [ ] All forms work successfully.
- [ ] All integrations succeed.
- [ ] **Old store links keep working without interruption.**
- [ ] The defined quality standards are met (§15).
- [ ] All deliverables are handed over (§19).
- [ ] Final management approval.

---

## 19. Final Deliverables and Handover

| # | Deliverable |
|---|---|
| 1 | A complete, published website |
| 2 | **Full ownership of the site, all its components and data, to Amad Craft** — IP is clear and not up for discussion |
| 3 | A full-permission admin account |
| 4 | Site control panel + login credentials |
| 5 | **Complete source code** (Laravel + Vue.js) with the code repository |
| 6 | Database via phpMyAdmin (dump + access) |
| 7 | Hosting credentials |
| 8 | Domain and registration credentials |
| 9 | Usernames and passwords for every system |
| 10 | **All integration keys and APIs (API Keys)** |
| 11 | A complete backup |
| 12 | A team training session |
| 13 | A measurement dashboard |
| 14 | A short site-administration guide + technical `README.md` |

> **Why full handover is insisted on:** so Amad Craft can hand the site to any external party and operate it without depending on the original developer.
> **Rule:** no permissions, copies or means are retained that would prevent Amad Craft from managing the site independently after handover.

---

## 20. Decisions Required from Management (non-technical inputs that govern success)

| # | Decision | Impact |
|---|---|---|
| 1 | Approve the CRM the forms will connect to (**Zid or Odoo**) | Determines the active driver at launch |
| 2 | Approve the sitemap and its sections (within the content file) | Unblocks phase 1 |
| 3 | Name the content owner inside the team after launch | Admin permissions |
| 4 | Name who responds to incoming requests | Notification target and response time |
| 5 | Approve the English version's launch timing | Phase 6 |
| 6 | Decide on the domain move based on its SEO impact | Phase 10 |

---

## 21. Post-Launch Success Metrics

1. Number of institutional leads.
2. Share of qualified leads among them.
3. Visitor-to-lead conversion rate.
4. Response time to requests.

> These metrics are required and accepted in principle, but **their target numbers are deferred until after launch**, since their suitability isn't known yet. Build the dashboard to display them; do not impose numeric targets.

---

## 22. Hard Constraints for the Coding Agent (read before every session)

1. **Do not invent content.** If text is missing, place a clear placeholder in the admin panel and report it — never write marketing copy.
2. **Do not add any feature not stated here.** If you see a need, propose it; do not implement it.
3. **Do not build any commercial function** (§2.2), however logical it seems.
4. **Do not use ready-made templates or a CMS** — interfaces are coded from scratch with Vue components following the approved identity.
5. **Never put literal text in templates** — everything goes through translation or the database.
6. **Never use `left/right` in CSS** — logical properties only (RTL-first).
7. **Never break indexing of legacy URLs** — every path change goes through the `redirects` table.
8. **Never couple directly to one CRM provider** — only through the abstraction layer.
9. **Never put keys or tracking IDs in code** — they live in `settings` or `.env`.
10. On any ambiguity: **ask before you build.** Every unclear point is raised for discussion before implementation begins.

---

## 23. Appendix: Approved Visual Identity

**Logo:** two lockups — horizontal (one line) and stacked (two lines), in four color treatments: Navy `#002546`, Lavender `#8685D8`, black, and white (for dark grounds). Use SVG exclusively on the web. Clear space around the logo = the height of the "ا" glyph in the Arabic wordmark. Minimum width: 120px desktop / 96px mobile.

**The four official colors:**

| Color | Hex | RGB | HSB | CMYK |
|---|---|---|---|---|
| Navy Blue | `#002546` | 0, 37, 70 | 208, 100%, 27% | 100, 85, 43, 46 |
| Lavender Purple | `#8685D8` | 134, 133, 216 | 241, 38%, 85% | 50, 48, 0, 0 |
| Burnt Orange | `#D7653B` | 215, 101, 59 | 16, 73%, 84% | 11, 73, 86, 1 |
| Light Gold | `#DCAD75` | 220, 173, 117 | 33, 47%, 86% | 14, 33, 60, 0 |

**Fonts:** the Arabic brand face (SaudiFont) + Kefa for Latin — from the Amad Craft identity folder. Convert to `woff2` with subsetting and self-host.

**Craft visual language:** Sadu, palm-frond weaving (khoos), zari metallic thread, Al-Ahsa engraving — used as the source of the signature pattern and imagery, never as random ornament.
