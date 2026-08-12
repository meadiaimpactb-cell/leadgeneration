# DESIGN SYSTEM

Extracted from the approved v2 home page, so the rest of the site can be built
from the same parts rather than redesigned page by page.

Everything here already exists in code. This file says **where** and **why**;
`resources/css/tokens.css` and `resources/css/components.css` are the source of
truth, and nothing below may be re-typed as a literal inside a component.

---

## 1. Colour

Identity values from §23. Do not invent others.

| Token | Value | Use |
|---|---|---|
| `--navy-900` | `#002546` | Header, footer, dark bands, headings on light |
| `--navy-950` | `#001A31` | News strip and footer — the frame around the page |
| `--navy-800` | `#06345C` | Form fields on navy, media ground |
| `--paper-warm` | `#FBF8F3` | **The page ground.** Not white |
| `--paper` | `#FFFFFF` | Surfaces that must lift off the ground |
| `--action-600` | `#B4522C` | CTA, "learn more", hover, the accordion `+` |
| `--orange-500` | `#D7653B` | Identity orange — legible with white text only at ≥18px/700, which is what `.btn--cta-lg` is for |
| `--gold-400` | `#DCAD75` | Rules, Sadu, section numbers, focus ring |
| `--lavender-700` | `#5F5EBE` | Rare. Never a section ground, never a CTA |
| `--sand` | `#F3EEE7` | Row hover |
| `--placeholder-warm` | `#E9E1D6` | What an image box shows before its file arrives |

**Text on navy is cream at 82%, not pure white** — see `.shero__sub`. White on
navy at body size reads cold against a warm identity.

**Proportion:** ~60% light, ~30% navy, ~8% gold, ~2% orange. The orange works
because it is rare; spending it on a second control per screen is what breaks
it.

**Hairlines** are three weights: `--hairline` (.16), `--hairline-soft` (.12)
inside repeating strips, `--hairline-gold` (.28) on navy.

---

## 2. Type

Arabic: **IBM Plex Sans Arabic**, display face **SaudiWeb**.
Latin technical voice: **IBM Plex Mono**, self-hosted, Latin subset only.

- `.display` — hero headline. Capped at 18ch: past that it stops being a
  statement.
- `.h2` / `.h3` — section and card headings, brand face.
- Body — `--lh-body-ar: 1.85`, `--lh-body-en: 1.65`.
- `.mono-label` — section numbering, lockup subtitles, figure captions, phone
  and email fields.

**`.mono-label` is `display: inline-block`, and that is load-bearing.** It sets
`direction: ltr` so `01 / 05` reads correctly; on a block element that also
decides where the line sits, which detached every section number and footer
heading from the Arabic content beneath it. As an inline-block the parent
places the box and the element only orders its own glyphs.

**Never letterspace Arabic.** `--tracking: 0` under `html[lang="ar"]`. The mono
treatment is only ever safe on Latin strings the code controls — applying it to
a database heading broke the joins between Arabic letters.

**Digits are Latin in both languages.** Everything numeric goes through
`useFormat`; `NumeralFormattingTest` fails the build otherwise.

---

## 3. Shape

### The octagon cut — `.cut`, `.cut--deep`
The image signature, taken from the company's own gift boxes and display
cases. `--cut-soft` for cards and photographs, `--cut-deep` for the map and
anything full-width where a small chamfer disappears.

`clip-path`, not `border-radius`: a chamfer is a straight cut, and rounding it
turns a made object into a web component. It is symmetrical on both axes, so
unlike the hero's tooth edge it needs no mirroring in LTR.

`.cut-framed` is a gold box behind a cut box — `clip-path` removes a border
along with the corner, so a gold edge has to be a layer, not a declaration.

### The Sadu thread
A closed vocabulary of seven placements, listed at the top of
`components.css`. Do not add an eighth. It is drawn with gradients, never an
image, so it takes its colour from a token and stays crisp at any size.

### Section numbering
`01 / 05 · MANIFESTO`, from `ui/SectionIndex`. Both halves are **derived**:
the position from the section's place in the `sections` rows, the caption from
the section's own type slug. Deleting a block in the panel renumbers the rest.

`layout="stacked"` puts the number and caption on separate lines for narrow
margin columns — on one line in 190px the string wraps mid-run, and a wrapped
bidi fragment reorders, which is how `01 / 05` once rendered as `05 / 01`.

### Depth
Gold hairlines, not heavy shadows. Cards lift 2–4px on hover and gain a gold
edge; they never scale — a scale transform on a chamfered box softens the cut
for the duration of the animation, which is the one thing this shape must not
do.

---

## 4. Motion

- `--dur-micro` 160ms hover · `--dur-el` 300ms · `--dur-reveal` 450ms.
- One easing: `--ease: cubic-bezier(.2,.7,.3,1)`.
- Scroll reveal is once only, 16px rise — `useReveal`.
- Counters climb on entry — `useCountUp`.
- `prefers-reduced-motion` zeroes every duration **at the token**, so no
  component can forget to honour it.

---

## 5. Grid

`--container: 1320px`, gutters and margins from tokens, breakpoints
640 / 900 / 1024 / 1440.

Mobile: sections stack copy-before-image in heroes, targets ≥44px, dropdowns
become accordions.

---

## 6. Direction

RTL first. Logical properties only. There are exactly **four** physical
exceptions in the whole codebase, each with an explicit LTR override and a
comment saying why:

1. `Hero.vue` — the vertical seam lettering (`left: 12px`).
2. `Hero.vue` — the Sadu tooth `clip-path`; clip-path takes no logical values.
3. `SiteFooter.vue` — the map's centre mark; a point on a map does not flip.
4. `--scrim-away` in tokens — gradients have no logical direction, so the one
   physical direction is named once and flipped with the document.

---

## 7. Rules that produced defects

Each of these is here because it broke something real.

- **`settings` is one JSON column, not per-locale.** Repeatable text inside it
  carries both languages on each item (`title` / `title_en`). There is no
  fallback to Arabic — §12 forbids serving Arabic to an English visitor, so a
  missing English string renders nothing.
- **Two SSR renders in one test return the first render's markup.** One render
  per test.
- **Routes are keyed by method + URI.** A second route on the same URI
  replaces the first however it is constrained.
- **`belongsToMany` guesses the pivot name alphabetically.** Name it.
- **A gap-over-coloured-background grid** paints through cells the items do not
  fill. Use a `box-shadow` ring per cell instead.
- **`class="x"` plus `:class` merge into one attribute**, silently breaking any
  check that looks for the attribute verbatim.
- **Two near-whites side by side read as unfinished, not as two options.** The
  contact card sat on `--paper-alt` beside a form on `--paper-warm`: a few
  shades apart, which the eye reads as a box that failed to load rather than as
  an alternative worth taking. An alternative to the primary path gets the
  opposite ground — navy with a gold hairline, the same weighting the CTA band
  and the footer already use.
- **An account list is a set of marks, not a row of words.** «Snapchat Facebook
  X Instagram» printed as four text links three sections above the footer
  showing the same four accounts as icons. `ui/SocialLinks` owns the glyph
  table and both read from it; the glyph is matched on the link's own host, so
  a client who pastes a URL never has to pick an icon, and an unknown network
  gets a globe rather than a wrong brand mark.

---

## 7b. Rhythm — never navy on navy

**Two navy sections may never sit directly against each other.** Between them
there is always a light section, a floating card, or a Sadu divider on a light
ground.

Navy is roughly 30% of the page and it is the identity's weight. Two navy
blocks in sequence do not read as two sections; they read as one very tall
field with a horizontal line in it, and whatever is in the second block gets
absorbed into the first. On /impact this cost the page its argument: the
figures sat in a navy band immediately under the navy hero, so four numbers
that exist to be *evidence for* the claim above them looked like part of the
header.

The three legal separators, in order of preference:

1. **A floating card** — `ImpactStats variant="overlap"`. Cream, octagon-cut,
   riding the navy edge on a negative margin. Use this when the second block
   is evidence for the first; the overlap is what says "these belong together".
2. **A light section** — any `.section` on `--bg`.
3. **A Sadu divider on a light ground** — `SaduDivider`, never between two
   dark blocks, where it reads as a stripe rather than a seam.

The footer's showroom block is navy and it closes every page, so the last
section before the footer must be light. `CtaBand` is navy but sits inside a
light-grounded outer section, which satisfies the rule — the card is navy, the
band around it is not.

---

## 8. Components that exist to stop a duplicate

- **`ui/SocialLinks`** — the social glyph table, `dark` (gold on navy) and
  `paper` tones. Was inline in `SiteFooter`.
- **`ui/ReportCover`** — a document face in the identity for a report with no
  uploaded cover. Was a stock photograph.
- **`ui/SectionIndex`** — the «03 / 05 · voices» running index.

Before adding a second copy of any of these, use the component. That is the
whole reason each one was extracted.
