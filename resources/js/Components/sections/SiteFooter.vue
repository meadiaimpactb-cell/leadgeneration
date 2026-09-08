<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Logo from '@/Components/ui/Logo.vue';
import SocialLinks from '@/Components/ui/SocialLinks.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Footer (§11.1): nav, contact, social, the informational store link, legal.
 *
 * The store link is present for anyone who wants to go there and carries no
 * sales push — §4 is explicit that this site never steers a visitor toward a
 * direct purchase.
 *
 * Column headings are Latin micro-labels in both languages — `.mono-label`
 * sets `direction: ltr` for exactly that. This docblock used to say they were
 * the navigation slot's own key; they were not, they were four English words
 * typed into this template, which made them the only text on every page of
 * the site that Amad Craft could not change (§22.5). They come from
 * resources/lang now, still Latin, and editable.
 */
/**
 * `showLocation` is false on a page that carries its own location section —
 * see `PublicLayout`'s `hasOwnLocation`. Default true, so a new page keeps the
 * showroom block unless it deliberately takes the question over.
 */
defineProps({
    showLocation: { type: Boolean, default: true },
});

const { t } = useTranslation();
const page = usePage();

const settings = computed(() => page.props.settings ?? {});
const locale = computed(() => page.props.locale);

const mainLinks = computed(() => page.props.navigation?.footer_main ?? []);
const companyLinks = computed(() => page.props.navigation?.footer_company ?? []);
const legalLinks = computed(() => page.props.navigation?.footer_legal ?? []);

const email = computed(() => settings.value['contact.email'] ?? null);
const phone = computed(() => settings.value['contact.phone'] ?? null);
const storeUrl = computed(() => settings.value['store.url'] ?? null);
const storeLabel = computed(() => settings.value[`store.label.${locale.value}`] ?? null);
const social = computed(() => settings.value['contact.social'] ?? []);
const copyright = computed(() => settings.value[`site.copyright.${locale.value}`] ?? null);
const blurb = computed(() => settings.value[`site.footer_blurb.${locale.value}`] ?? null);

/**
 * The showroom block. Every value is a setting the client already owns —
 * address, opening hours, the embed URL and the directions link — so nothing
 * here is written in this file, and the whole block hides when the embed URL
 * is unset rather than showing a grey rectangle (§22.1).
 */
const mapEmbed = computed(() => settings.value['contact.map_embed_url'] ?? null);
const mapUrl = computed(() => settings.value['contact.map_url'] ?? null);
const address = computed(() => settings.value[`contact.address.${locale.value}`] ?? null);
const hours = computed(() => settings.value[`contact.hours.${locale.value}`] ?? null);
const locationHeading = computed(() => settings.value[`contact.location_heading.${locale.value}`] ?? null);
const locationNote = computed(() => settings.value[`contact.location_note.${locale.value}`] ?? null);
const directionsLabel = computed(() => settings.value[`contact.directions_label.${locale.value}`] ?? null);

/** The address is stored multi-line; the summary line is its first line. */
const addressLine = computed(() =>
    (address.value ?? '')
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean)
        .slice(1)
        .join(' · ') || null
);

/**
 * The coordinates printed across the top of the frame, read out of the embed
 * URL rather than stored twice.
 *
 * Google writes them two ways. A place embed carries `!2d<lng>!3d<lat>` —
 * longitude FIRST, which is the reverse of how they are spoken and the reason
 * the first version of this printed nothing. A query embed carries
 * `q=<lat>,<lng>` in the usual order. Both are matched; no match prints no
 * line, which is correct, because a made-up coordinate on a map is worse than
 * none at all.
 */
const coordinates = computed(() => {
    const url = mapEmbed.value ?? '';

    const place = url.match(/!2d(-?\d+\.\d+)!3d(-?\d+\.\d+)/);
    const query = url.match(/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/);

    let lat, lng;

    if (place) {
        [, lng, lat] = place;
    } else if (query) {
        [, lat, lng] = query;
    } else {
        return null;
    }

    return `${Number(lat).toFixed(4)}° N · ${Number(lng).toFixed(4)}° E`;
});

/** "أمد الحرف · Amad Craft" — both names the client already maintains. */
const placeName = computed(() => {
    const names = [settings.value['site.name.ar'], settings.value['site.name.en']].filter(Boolean);

    return names.length ? names.join(' · ') : null;
});


</script>

<template>
    <footer class="footer on-dark">
        <!-- Sadu use 6 of 7: the woven band that closes the page. -->
        <div class="sadu-strip sadu-weave footer__strip" aria-hidden="true" />

        <Container>
            <!-- The showroom, above the link columns. Hidden until the client
                 has set a map, and hidden on a page that answers "where are
                 you" itself. -->
            <!-- `id="visit"` is a link target, not decoration: /about's hero
                 action is «زوروا معرضنا» and lands here. Anything else that
                 wants to send a visitor to the premises uses the same anchor
                 rather than a second copy of the address. -->
            <section
                v-if="showLocation && mapEmbed"
                id="visit"
                class="visit"
                aria-labelledby="visit-heading"
            >
                <div class="visit__copy">
                    <p class="mono-label mono-label--tight mono-label--gold">{{ t('common.label_location') }}</p>

                    <h2 v-if="locationHeading" id="visit-heading" class="visit__title">
                        {{ locationHeading }}
                    </h2>

                    <p v-if="locationNote" class="visit__note">{{ locationNote }}</p>

                    <ul class="visit__facts">
                        <li v-if="addressLine" class="visit__fact">
                            <svg
                                class="visit__icon"
                                viewBox="0 0 24 24"
                                width="18"
                                height="18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                aria-hidden="true"
                            >
                                <path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z" />
                                <circle cx="12" cy="10" r="2.6" />
                            </svg>
                            {{ addressLine }}
                        </li>
                        <li v-if="hours" class="visit__fact">
                            <svg
                                class="visit__icon"
                                viewBox="0 0 24 24"
                                width="18"
                                height="18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3.5 2" />
                            </svg>
                            {{ hours }}
                        </li>
                    </ul>

                    <a
                        v-if="mapUrl && directionsLabel"
                        class="btn btn--cta visit__cta"
                        :href="mapUrl"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        {{ directionsLabel }}
                        <span class="visually-hidden">{{ t('common.external_link') }}</span>
                    </a>
                </div>

                <!--
                    The frame is two clipped boxes, one inside the other: the
                    outer is gold and shows only as a 1.5px edge, the inner
                    carries the map. A border cannot follow a clip-path, so
                    this is how the cut corners keep their outline.
                -->
                <div class="visit__frame">
                    <div class="visit__inner">
                        <iframe
                            class="visit__map"
                            :src="mapEmbed"
                            :title="locationHeading ?? t('common.home')"
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                        />

                        <!-- Warms the map into the identity's palette without
                             touching the tiles themselves. -->
                        <span class="visit__wash" aria-hidden="true" />
                        <span class="visit__glow" aria-hidden="true" />
                        <span class="visit__grid" aria-hidden="true" />

                        <!-- The mark: two pulsing rings, a gold diamond, and
                             the two sighting lines it sits on. -->
                        <span class="visit__mark" aria-hidden="true">
                            <span class="visit__pulse" />
                            <span class="visit__pulse visit__pulse--late" />
                            <span class="visit__pin" />
                            <span class="visit__cross visit__cross--x" />
                            <span class="visit__cross visit__cross--y" />
                        </span>

                        <p v-if="coordinates" class="visit__coords mono-label" aria-hidden="true">
                            {{ coordinates }}
                        </p>

                        <!--
                            A full-cover link, as the approved design has it.
                            It also settles a real problem: an interactive
                            embed inside a footer swallows the page scroll on
                            a trackpad and traps a touch drag on a phone. Here
                            the map is a picture that opens Maps.
                        -->
                        <a
                            v-if="mapUrl"
                            class="visit__open"
                            :href="mapUrl"
                            rel="noopener noreferrer"
                            target="_blank"
                            :aria-label="directionsLabel ?? placeName ?? t('common.external_link')"
                        >
                            <span v-if="placeName" class="visit__chip">
                                <svg
                                    viewBox="0 0 24 24"
                                    width="15"
                                    height="15"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    aria-hidden="true"
                                >
                                    <path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z" />
                                    <circle cx="12" cy="10" r="2.6" />
                                </svg>
                                {{ placeName }}
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <div class="footer__grid">
                <div class="footer__brand">
                    <!-- The lockup carries its own Latin wordmark; setting it
                         again as type printed the name twice. -->
                    <Logo lockup="stacked" tone="white" />
                    <p v-if="blurb" class="footer__blurb">{{ blurb }}</p>
                </div>

                <nav v-if="mainLinks.length" class="footer__col" :aria-label="t('common.menu')">
                    <p class="mono-label mono-label--tight mono-label--gold footer__head">{{ t('common.label_sitemap') }}</p>
                    <ul class="footer__list">
                        <li v-for="item in mainLinks" :key="item.id">
                            <Link :href="item.url" class="footer__link">{{ item.label }}</Link>
                        </li>
                    </ul>
                </nav>

                <nav v-if="companyLinks.length || storeUrl" class="footer__col" :aria-label="t('common.nav_company')">
                    <p class="mono-label mono-label--tight mono-label--gold footer__head">{{ t('common.label_company') }}</p>
                    <ul class="footer__list">
                        <li v-for="item in companyLinks" :key="item.id">
                            <Link :href="item.url" class="footer__link">{{ item.label }}</Link>
                        </li>
                        <!-- Informational only. No price, no buy (§4). -->
                        <li v-if="storeUrl && storeLabel">
                            <a
                                class="footer__link"
                                :href="storeUrl"
                                rel="noopener noreferrer"
                                target="_blank"
                            >
                                {{ storeLabel }}
                                <span class="visually-hidden">{{ t('common.external_link') }}</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <div v-if="email || phone || social.length" class="footer__col">
                    <p class="mono-label mono-label--tight mono-label--gold footer__head">{{ t('common.label_contact') }}</p>

                    <ul class="footer__list">
                        <li v-if="email">
                            <a class="footer__link footer__link--mono" :href="`mailto:${email}`">
                                {{ email }}
                            </a>
                        </li>
                        <!-- A phone number is a Latin run whatever the page
                             direction, or the country code lands at the wrong
                             end of it. -->
                        <li v-if="phone">
                            <a
                                class="footer__link footer__link--mono"
                                :href="`tel:${phone}`"
                                dir="ltr"
                            >
                                {{ phone }}
                            </a>
                        </li>
                    </ul>

                    <!-- The glyph table moved to `ui/SocialLinks`, which the
                         contact card also draws from. It lived here alone
                         while that card printed the same accounts as words. -->
                    <SocialLinks :items="social" tone="dark" class="footer__social" />
                </div>
            </div>

            <div class="footer__base">
                <p v-if="copyright" class="footer__copyright">{{ copyright }}</p>

                <nav v-if="legalLinks.length" class="footer__legal" :aria-label="t('common.nav_legal')">
                    <Link
                        v-for="item in legalLinks"
                        :key="item.id"
                        :href="item.url"
                        class="footer__link link-weave"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>
        </Container>
    </footer>
</template>

<style scoped>
.footer {
    position: relative;
    padding-block: 0 var(--s-7);
    /* One step darker than the page's navy, so the footer reads as the frame
       around the document rather than as its last section. */
    background: var(--navy-950);
}

.footer__strip {
    --sadu-tile: 10px;
    --sadu-colour: rgb(var(--gold-rgb) / 0.7);
}

/* ---- The showroom block ---- */
.visit {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-7);
    align-items: center;
    padding-block: var(--s-9);
    border-block-end: 1px solid rgba(255, 255, 255, 0.1);
}

.visit__copy {
    min-inline-size: 0;
}

.visit__title {
    margin-block-start: var(--s-4);
    font-family: var(--font-display);
    font-size: clamp(1.5rem, 2.4vw, 2.125rem);
    font-weight: 700;
    line-height: var(--lh-heading);
    letter-spacing: -0.01em;
    color: #fff;
}

.visit__note {
    margin-block-start: var(--s-4);
    font-size: var(--fs-body);
    line-height: var(--lh-body);
    color: rgba(255, 255, 255, 0.7);
    max-inline-size: 38ch;
}

.visit__facts {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
    margin-block-start: var(--s-6);
    list-style: none;
}

.visit__fact {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    font-size: 0.9375rem;
    color: rgba(255, 255, 255, 0.78);
}

.visit__icon {
    flex-shrink: 0;
    color: var(--gold-400);
}

.visit__cta {
    margin-block-start: var(--s-6);
    white-space: nowrap;
    flex-shrink: 0;
    min-block-size: 52px;
}

/*
 * Cut corners, drawn as a clip-path on a gold box with the map inset 1.5px
 * inside it. The shape is symmetrical on both axes, so unlike the hero's
 * tooth edge it needs no mirroring for LTR.
 */
.visit__frame {
    position: relative;
    aspect-ratio: 5 / 4;
    background: rgb(var(--gold-rgb) / 0.55);
    /* The corner cut scales with the frame so it stays the same proportion of
       the shape on a phone as on a desktop; 64px is the design's value at the
       size it was drawn. */
    --cut: clamp(28px, 7vw, 64px);
    clip-path: polygon(
        var(--cut) 0, calc(100% - var(--cut)) 0,
        100% var(--cut), 100% calc(100% - var(--cut)),
        calc(100% - var(--cut)) 100%, var(--cut) 100%,
        0 calc(100% - var(--cut)), 0 var(--cut)
    );
}

.visit__inner {
    position: absolute;
    inset: 1.5px;
    overflow: hidden;
    background: var(--navy-800);
    /* One pixel tighter than the frame, which is what leaves the gold showing
       as an edge rather than as a band. */
    --cut: clamp(27px, 7vw, 63px);
    clip-path: polygon(
        var(--cut) 0, calc(100% - var(--cut)) 0,
        100% var(--cut), 100% calc(100% - var(--cut)),
        calc(100% - var(--cut)) 100%, var(--cut) 100%,
        0 calc(100% - var(--cut)), 0 var(--cut)
    );
}

.visit__map {
    position: absolute;
    inset: 0;
    inline-size: 100%;
    block-size: 100%;
    border: 0;
    filter: grayscale(1) sepia(0.42) saturate(1.15) brightness(1.06) contrast(0.94);
}

/* Both overlays let clicks through to the map underneath. */
.visit__wash {
    position: absolute;
    inset: 0;
    background: var(--gold-400);
    mix-blend-mode: color;
    opacity: 0.42;
    pointer-events: none;
}

.visit__glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.28), transparent 52%);
    pointer-events: none;
}

.visit__grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgb(var(--navy-rgb) / 0.1) 1px, transparent 1px),
        linear-gradient(90deg, rgb(var(--navy-rgb) / 0.1) 1px, transparent 1px);
    background-size: 44px 44px;
    pointer-events: none;
}

/* ---- The location mark ---- */
/*
 * A zero-size anchor at the centre. Every piece hangs off it with physical
 * offsets, which is correct here and not an RTL slip: this is a point on a
 * map, and a map does not flip with the document.
 */
.visit__mark {
    position: absolute;
    top: 50%;
    left: 50%;
    inline-size: 0;
    block-size: 0;
    pointer-events: none;
}

@keyframes visit-pulse {
    from {
        transform: scale(0.6);
        opacity: 0.85;
    }
    to {
        transform: scale(2.6);
        opacity: 0;
    }
}

.visit__pulse {
    position: absolute;
    top: -13px;
    left: -13px;
    inline-size: 26px;
    block-size: 26px;
    border: 1px solid var(--gold-400);
    border-radius: 50%;
    animation: visit-pulse 2.8s ease-out infinite;
}

/* The second ring starts half a cycle late, so the two read as one steady
   beat rather than as a single ring restarting. */
.visit__pulse--late {
    animation-delay: 1.4s;
}

.visit__pin {
    position: absolute;
    top: -7px;
    left: -7px;
    inline-size: 14px;
    block-size: 14px;
    background: var(--gold-400);
    transform: rotate(45deg);
    box-shadow: 0 0 0 4px rgb(var(--navy-rgb) / 0.85);
}

/* Sighting lines: long enough to cross any frame, fading out at both ends. */
.visit__cross {
    position: absolute;
}

.visit__cross--x {
    top: -0.5px;
    left: -1000px;
    inline-size: 2000px;
    block-size: 1px;
    background: linear-gradient(
        to right,
        transparent,
        rgb(var(--gold-rgb) / 0.5) 42%,
        rgb(var(--gold-rgb) / 0.5) 58%,
        transparent
    );
}

.visit__cross--y {
    left: -0.5px;
    top: -1000px;
    block-size: 2000px;
    inline-size: 1px;
    background: linear-gradient(
        to bottom,
        transparent,
        rgb(var(--gold-rgb) / 0.5) 42%,
        rgb(var(--gold-rgb) / 0.5) 58%,
        transparent
    );
}

@media (prefers-reduced-motion: reduce) {
    .visit__pulse {
        animation: none;
        opacity: 0.5;
    }
}

.visit__coords {
    position: absolute;
    inset-block-start: 18px;
    inset-inline: 0;
    text-align: center;
    font-size: 10.5px;
    letter-spacing: 0.16em;
    color: var(--navy-900);
    opacity: 0.62;
    pointer-events: none;
}

.visit__open {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-block-end: 18px;
    background: linear-gradient(to top, rgba(0, 26, 49, 0.55), rgba(0, 26, 49, 0) 38%);
}

.visit__chip {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    flex-shrink: 0;
    white-space: nowrap;
    padding: 10px 16px;
    background: var(--navy-950);
    border: 1px solid rgb(var(--gold-rgb) / 0.45);
    color: #fff;
    font-size: 0.84375rem;
    font-weight: 600;
}

.visit__chip svg {
    color: var(--gold-400);
    flex-shrink: 0;
}

.visit__open:hover .visit__chip,
.visit__open:focus-visible .visit__chip {
    border-color: var(--gold-400);
}

.footer__grid {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
    padding-block-start: var(--s-10);
}

.footer__brand {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--s-2);
}

.footer__blurb {
    margin-block-start: var(--s-4);
    font-size: 0.90625rem;
    line-height: var(--lh-body);
    color: rgba(255, 255, 255, 0.6);
    max-inline-size: 32ch;
}

.footer__col {
    min-inline-size: 0;
}

/*
 * All four column headings sit on one line, whatever the first column's logo
 * is doing. Without the fixed height the brand column's lockup pushed its
 * neighbours' headings out of alignment, which is what made the row read as
 * three unrelated lists.
 */
/* inline-flex, not flex: .mono-label is inline-block precisely so the
   parent's direction places it, and `display: flex` would take that back. */
.footer__head {
    display: inline-flex;
    align-items: center;
    min-block-size: 24px;
    margin-block-end: var(--s-4);
}

.footer__list {
    display: flex;
    flex-direction: column;
    list-style: none;
}

.footer__link {
    display: inline-flex;
    align-items: center;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.90625rem;
    /*
     * The 44px target comes from the padding, not from a min-height on a
     * flex row. As a min-height it stretched every link to 44px and stacked
     * four of them into a 176px column of mostly empty space.
     *
     * 13px, not `--s-3`: at 12px the box measured 42.13px in the browser
     * (12 + 12 + an 18.125px line), so the rule above described an intent the
     * layout missed by two pixels. This is the smallest value that makes the
     * comment true.
     */
    padding-block: 13px;
    /* And a floor, because `--mono` below sets a smaller font and its line
       box came out a pixel short of 44 on the padding alone. */
    min-block-size: 44px;
    line-height: 1.25;
    transition: color var(--dur-micro) var(--ease);
}

.footer__link--mono {
    font-family: var(--font-mono);
    font-size: 0.84375rem;
    letter-spacing: 0.02em;
}

.footer__link:hover,
.footer__link:focus-visible {
    color: var(--text-inverse);
}

/* Only the spacing. The marks themselves are `ui/SocialLinks`, which took
   this block's gold-on-navy treatment with it as the shared default. */
.footer__social {
    margin-block-start: var(--s-5);
}

.footer__base {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-4);
    justify-content: space-between;
    align-items: center;
    margin-block-start: var(--s-9);
    padding-block-start: var(--s-5);
    border-block-start: 1px solid rgba(255, 255, 255, 0.1);
}

.footer__copyright {
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.8125rem;
}

.footer__legal {
    display: flex;
    gap: var(--s-5);
}

.footer__legal .footer__link {
    padding-block: 0;
    font-size: 0.8125rem;
    color: rgba(255, 255, 255, 0.45);
}

@media (min-width: 640px) {
    .footer__grid {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--s-8) var(--s-7);
    }
}

@media (min-width: 900px) {
    .visit {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
        gap: var(--s-8);
        padding-block: var(--s-10) var(--s-9);
    }
}

@media (min-width: 1024px) {
    .footer__grid {
        /* The brand column is widest because it carries the lockup and a
           sentence; the three link columns are equal so their headings read
           as one row of labels. */
        grid-template-columns: 1.6fr repeat(3, minmax(0, 1fr));
        gap: var(--s-7);
        padding-block-start: var(--s-11);
    }
}
</style>
