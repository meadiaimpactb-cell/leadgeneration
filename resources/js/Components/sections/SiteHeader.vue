<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Logo from '@/Components/ui/Logo.vue';
import LangSwitch from '@/Components/ui/LangSwitch.vue';
import Button from '@/Components/ui/Button.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useHashCta } from '@/Composables/useHashCta';
import { useScrollSpy } from '@/Composables/useScrollSpy';

/**
 * The header (§11.1) — a navy bar at the top of every page.
 *
 * It used to be sticky and transparent, turning solid past 24px of scroll.
 * That produced a header whose text was white on a warm page whenever the
 * scroll state and the paint state disagreed — reliably invisible. The state
 * that could be wrong is gone: the bar always paints its own navy ground and
 * always sets white on it, so there is no combination of scroll position,
 * hydration timing or page template that can hide it.
 *
 * Menu items come from the `navigations` table so the client can reorder them
 * without a developer (§9.1), and an item with children renders as a
 * dropdown. The header renders nothing but the logo and the CTA until that
 * data exists — it never invents menu labels (§22.1).
 */
const props = defineProps({
    /**
     * True on pages whose first element is a full-bleed navy hero. It does
     * not decide the header's colour — only whether the bar keeps the gold
     * hairline that separates it from what follows. Over the hero the two
     * grounds are the same navy, so the rule stays; elsewhere it goes.
     */
    overHero: { type: Boolean, default: false },
});

const { t } = useTranslation();
const page = usePage();

const menuOpen = ref(false);
/** Which top-level item has its submenu open. One at a time, by id. */
const openMenu = ref(null);

const locale = computed(() => page.props.locale);
const homeUrl = computed(() => `/${locale.value}`);
const items = computed(() => page.props.navigation?.header ?? []);

const settings = computed(() => page.props.settings ?? {});

/**
 * The header's call to action.
 *
 * Label comes from `settings`, keyed by locale, so it is edited on the
 * contact screen rather than written here (§22.1). Unset means no button.
 */
const ctaLabel = computed(() => settings.value[`contact.header_cta.${locale.value}`] ?? null);
/*
 * The header's CTA target.
 *
 * `/{locale}#contact` since the landing-page decision — `/{locale}/contact`
 * is one of the retired paths and answers 301, so the site's most-pressed
 * button was costing every visitor a redirect to reach its own form.
 */
const contactUrl = computed(() => `/${locale.value}#contact`);

const { onHashCta } = useHashCta();

/**
 * Menu entries that point at a section of the page being read.
 *
 * The landing page's five entries are stored as `/{locale}#anchor` so they
 * work from anywhere: from the legal pages they navigate, and here they
 * should scroll. Which of the two it is cannot be decided when the menu is
 * built — only against the URL currently open.
 */
function inPage(url) {
    if (typeof url !== 'string' || !url.includes('#')) {
        return null;
    }

    const [path, fragment] = url.split('#');
    const here = page.url.split('#')[0].split('?')[0];

    return path === '' || path === here ? `#${fragment}` : null;
}

const anchors = computed(() =>
    items.value.map((item) => inPage(item.url)).filter(Boolean).map((href) => href.slice(1))
);

const { active } = useScrollSpy(anchors);

function isCurrentSection(item) {
    const anchor = inPage(item.url);

    return anchor !== null && anchor.slice(1) === active.value;
}

/**
 * A menu link that targets this page scrolls; anything else navigates.
 *
 * The anchor is left in the markup either way, so the link works with the
 * keyboard, with middle-click, and before the JavaScript has run.
 */
function onItemClick(event, item) {
    const anchor = inPage(item.url);

    if (anchor !== null) {
        closeAll();
        onHashCta(event, anchor);
    }
}

function toggle(id) {
    openMenu.value = openMenu.value === id ? null : id;
}

function closeAll() {
    openMenu.value = null;
    menuOpen.value = false;
}

/**
 * Keyboard on the solutions panel.
 *
 * Escape closes and hands focus back to the control that opened it — leaving
 * it inside a hidden panel is the classic way a keyboard user gets stranded.
 * The arrows walk the rows, because a panel that only a mouse can traverse is
 * a panel half the visitors cannot use.
 */
function onPanelKeydown(event, itemId) {
    if (event.key === 'Escape') {
        openMenu.value = null;
        event.currentTarget.closest('.header__item')?.querySelector('.header__caret')?.focus();

        return;
    }

    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
        return;
    }

    event.preventDefault();
    openMenu.value = itemId;

    const links = [
        ...event.currentTarget.closest('.header__item').querySelectorAll('.panel__link'),
    ];

    if (links.length === 0) {
        return;
    }

    const at = links.indexOf(document.activeElement);
    const step = event.key === 'ArrowDown' ? 1 : -1;
    // Wraps at both ends: from the last row down returns to the first.
    const next = at === -1 ? (step === 1 ? 0 : links.length - 1) : (at + step + links.length) % links.length;

    links[next].focus();
}

/**
 * The CTA takes the visitor to the form, wherever the shortest path to it is:
 * if this page already carries one, it scrolls there and puts the cursor in
 * the field; otherwise it goes to the contact page.
 *
 * Scrolling beats opening a modal here. The form at the foot of the page is
 * the same component with the same endpoint, and sending someone to it keeps
 * one visible form per page instead of stacking a second copy over it (§6.1).
 */
function start() {
    closeAll();

    if (typeof document === 'undefined') return;

    const form = document.querySelector('.lead');

    if (!form) {
        router.visit(contactUrl.value);

        return;
    }

    form.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // After the scroll settles, not before — focusing first makes the browser
    // jump to the field and cancels the smooth scroll.
    window.setTimeout(() => form.querySelector('input[name="contact"]')?.focus(), 600);
}
</script>

<template>
    <header class="header" :class="{ 'header--merged': overHero }" @mouseleave="openMenu = null">
        <Container>
            <div class="header__bar">
                <Link :href="homeUrl" class="header__logo" :aria-label="t('common.home')">
                    <Logo lockup="horizontal" tone="white" />
                </Link>

                <nav v-if="items.length" class="header__nav" :aria-label="t('common.menu')">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="header__item"
                        @mouseenter="item.children?.length && (openMenu = item.id)"
                        @mouseleave="item.children?.length && (openMenu = null)"
                        @keydown="item.children?.length && onPanelKeydown($event, item.id)"
                    >
                        <!-- A parent is a real link to its own hub page as
                             well as the trigger for its submenu, so it is a
                             <Link> with a separate disclosure button beside
                             it rather than a button that swallows the page. -->
                        <!--
                            A plain <a> when it targets this page: Inertia
                            intercepts <Link> and issues a visit, so the
                            fragment never reaches the browser and the page
                            re-renders at the top instead of scrolling.
                        -->
                        <a
                            v-if="inPage(item.url)"
                            :href="item.url"
                            class="header__link link-weave"
                            :class="{ 'is-current': isCurrentSection(item) }"
                            :aria-current="isCurrentSection(item) ? 'location' : undefined"
                            @click="onItemClick($event, item)"
                        >
                            {{ item.label }}
                        </a>

                        <Link v-else :href="item.url" class="header__link link-weave">
                            {{ item.label }}
                        </Link>

                        <button
                            v-if="item.children?.length"
                            class="header__caret"
                            type="button"
                            :aria-expanded="openMenu === item.id"
                            :aria-controls="`submenu-${item.id}`"
                            :aria-label="item.label"
                            @click="toggle(item.id)"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="14"
                                height="14"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>

                        <!--
                            A panel, not a list of links.

                            It reuses the three signatures the identity already
                            owns rather than inventing decoration: the running
                            section numbering (01–04), the Sadu weave as the
                            top edge, and the octagon cut on the lower corners.
                            The visitor should read it as a piece of the same
                            cloth as the pages it leads to.
                        -->
                        <div
                            v-if="item.children?.length"
                            :id="`submenu-${item.id}`"
                            class="panel"
                            :class="{ 'panel--open': openMenu === item.id }"
                        >
                            <span class="sadu-strip sadu-weave panel__edge" aria-hidden="true" />

                            <ul class="panel__list">
                                <li
                                    v-for="(child, i) in item.children"
                                    :key="child.id"
                                    class="panel__row"
                                    :style="{ '--row': i }"
                                >
                                    <Link :href="child.url" class="panel__link" @click="closeAll">
                                        <!-- Two digits, Latin, never mirrored —
                                             the same counter the pages use. -->
                                        <span class="panel__num" aria-hidden="true">
                                            {{ String(i + 1).padStart(2, '0') }}
                                        </span>

                                        <span class="panel__text">
                                            <span class="panel__label">{{ child.label }}</span>
                                            <span v-if="child.description" class="panel__desc">
                                                {{ child.description }}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <div class="header__actions">
                    <LangSwitch />
                    <Button v-if="ctaLabel" variant="cta" class="header__cta" @click="start">
                        {{ ctaLabel }}
                    </Button>
                </div>

                <button
                    class="header__toggle"
                    type="button"
                    :aria-expanded="menuOpen"
                    aria-controls="header-mobile-nav"
                    :aria-label="menuOpen ? t('common.close_menu') : t('common.open_menu')"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="header__toggle-bar" aria-hidden="true" />
                    <span class="header__toggle-bar" aria-hidden="true" />
                </button>
            </div>

            <nav
                v-show="menuOpen"
                id="header-mobile-nav"
                class="header__mobile"
                :aria-label="t('common.menu')"
            >
                <div v-for="item in items" :key="item.id" class="header__mobile-item">
                    <div class="header__mobile-row">
                        <Link :href="item.url" class="header__mobile-link" @click="closeAll">
                            {{ item.label }}
                        </Link>

                        <!-- The disclosure is its own control, so tapping the
                             label still opens the hub page rather than only
                             unfolding the list under it. -->
                        <button
                            v-if="item.children?.length"
                            class="header__mobile-caret"
                            type="button"
                            :aria-expanded="openMenu === item.id"
                            :aria-controls="`m-submenu-${item.id}`"
                            :aria-label="item.label"
                            @click="toggle(item.id)"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="18"
                                height="18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>
                    </div>

                    <ul
                        v-if="item.children?.length"
                        v-show="openMenu === item.id"
                        :id="`m-submenu-${item.id}`"
                        class="header__mobile-sub"
                    >
                        <li v-for="child in item.children" :key="child.id">
                            <Link :href="child.url" class="header__mobile-link header__mobile-link--child" @click="closeAll">
                                {{ child.label }}
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- The one action, kept inside the panel so a phone visitor
                     never has to close the menu to find it. -->
                <Button v-if="ctaLabel" variant="cta" class="header__mobile-cta" @click="start">
                    {{ ctaLabel }}
                </Button>
            </nav>
        </Container>
    </header>
</template>

<style scoped>
/*
 * Sticky, and legible in every state — the two are only in tension if the
 * colour depends on the scroll position, which is exactly the bug this
 * replaced. The ground is unconditional, so the bar can follow the reader
 * down the page without ever being able to disappear into it.
 */
.header {
    position: sticky;
    inset-block-start: 0;
    z-index: var(--z-header);
    background: var(--navy-900);
    color: var(--text-inverse);
}

/* Over the hero both grounds are the same navy, so a gold hairline is what
   separates the bar from the content. Elsewhere the colour change does it. */
.header--merged {
    border-block-end: 1px solid var(--hairline-gold);
}

.header__bar {
    display: flex;
    align-items: center;
    gap: var(--s-5);
    min-block-size: var(--header-h);
}

.header__logo {
    display: flex;
    flex-direction: column;
    gap: var(--s-1);
    /* Clear space around the mark = the height of the "ا" glyph (§23). */
    padding-block: var(--s-3);
}

.header__nav {
    display: none;
    gap: var(--s-5);
    margin-inline-start: auto;
}

.header__item {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--s-1);
}

.header__link {
    color: inherit;
    font-weight: 600;
    font-size: var(--fs-sm);
    padding-block: var(--s-3);
}

.header__caret {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 24px;
    block-size: 24px;
    color: var(--gold-400);
    transition: transform var(--dur-micro) var(--ease);
}

.header__caret[aria-expanded='true'] {
    transform: rotate(180deg);
}

/* ---- Desktop submenu ---- */
/*
 * Hidden by visibility rather than display:none, so it is in the DOM for a
 * crawler and can transition — and so `aria-expanded` describes something
 * that genuinely exists.
 */
/* ---- Solutions panel (desktop) ---------------------------------- */

.panel {
    position: absolute;
    inset-block-start: 100%;
    inset-inline-start: 0;
    min-inline-size: 320px;
    background: var(--navy-950);
    box-shadow: var(--shadow-card);
    /* Octagon on the lower corners only: the panel hangs from the header,
       so its top edge is a join, not a corner. */
    clip-path: polygon(
        0 0, 100% 0,
        100% calc(100% - 9px), calc(100% - 9px) 100%,
        9px 100%, 0 calc(100% - 9px)
    );
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition:
        opacity var(--dur-el) var(--ease),
        transform var(--dur-el) var(--ease),
        visibility var(--dur-el);
    z-index: 1;
}

.panel--open,
.header__item:focus-within .panel {
    opacity: 1;
    visibility: visible;
    transform: none;
}

/*
 * The hover bridge. The panel starts flush against the header, so there is
 * no gap for the pointer to cross and no dead strip that closes it midway.
 */
.panel__edge {
    display: block;
    --sadu-tile: 3px;
    --sadu-colour: var(--gold-400);
    block-size: 3px;
}

.panel__list {
    list-style: none;
    margin: 0;
    padding-block: var(--s-2);
}

/*
 * Rows arrive in sequence on open and leave together on close. The delay is
 * on the open state only — a staggered close reads as the panel struggling
 * to get out of the way.
 */
.panel--open .panel__row,
.header__item:focus-within .panel__row {
    animation: panel-row var(--dur-el) var(--ease) backwards;
    animation-delay: calc(var(--row, 0) * 40ms);
}

@keyframes panel-row {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: none; }
}

.panel__link {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--s-4);
    padding: var(--s-3) var(--s-5);
    min-block-size: 56px;
    color: rgba(255, 255, 255, 0.82);
    transition: padding-inline-start var(--dur-micro) var(--ease);
}

/*
 * The gold bar on the reading edge. `inset-inline-start` puts it on the
 * right in Arabic and the left in English with no second rule.
 */
.panel__link::before {
    content: '';
    position: absolute;
    inset-inline-start: 0;
    inset-block: var(--s-2);
    inline-size: 2px;
    background: var(--gold-400);
    transform: scaleY(0);
    transition: transform var(--dur-micro) var(--ease);
}

/*
 * The 4px nudge is padding, not a transform.
 *
 * `translateX` needs a sign that flips with direction and there is no token
 * for one; `padding-inline-start` moves the row toward the reading edge in
 * both languages with a single rule and no arithmetic.
 */
.panel__link:hover,
.panel__link:focus-visible {
    padding-inline-start: calc(var(--s-5) + 4px);
}

.panel__link:hover::before,
.panel__link:focus-visible::before {
    transform: scaleY(1);
}

.panel__link:focus-visible {
    outline: 2px solid var(--gold-400);
    outline-offset: -2px;
}

.panel__num {
    flex: 0 0 auto;
    font-family: var(--font-mono);
    font-size: 0.6875rem;
    letter-spacing: 0.08em;
    color: var(--gold-400);
    /* A counter is Latin in both locales and must never mirror. */
    direction: ltr;
    transition: color var(--dur-micro) var(--ease);
}

.panel__link:hover .panel__num,
.panel__link:focus-visible .panel__num {
    color: var(--orange-500);
}

.panel__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-inline-size: 0;
}

.panel__label {
    font-size: var(--fs-sm);
    font-weight: 600;
    line-height: 1.35;
    color: var(--text-inverse);
}

.panel__desc {
    font-size: 0.75rem;
    line-height: 1.45;
    color: rgba(255, 255, 255, 0.58);
}

@media (prefers-reduced-motion: reduce) {
    .panel,
    .panel__link,
    .panel__link::before,
    .panel__num {
        transition: none;
    }

    .panel--open .panel__row,
    .header__item:focus-within .panel__row {
        animation: none;
    }
}

.header__actions {
    margin-inline-start: auto;
    display: flex;
    align-items: center;
    gap: var(--s-4);
}

/*
 * Two properties that are not optional on any button in this layout: without
 * them a two-word Arabic label wraps and the control grows to two lines,
 * dragging the whole bar's height with it.
 */
.header__cta {
    white-space: nowrap;
    flex-shrink: 0;
    /* Shorter than the page's own CTAs: the header is not where the primary
       action lives, it is where it is always reachable. */
    min-block-size: 46px;
}

.header__toggle {
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    inline-size: 44px;
    block-size: 44px;
    align-items: center;
}

.header__toggle-bar {
    display: block;
    inline-size: 22px;
    block-size: 2px;
    background: currentColor;
}

/* ---- Mobile panel ---- */
/*
 * The panel scrolls itself, because the bar it hangs from does not.
 *
 * The header is `position: sticky`, and a sticky box has no scroll of its
 * own — the page scrolls past it. So on a phone held sideways (667×375,
 * 844×390) an open menu with a submenu unfolded ran off the bottom of the
 * screen and there was no gesture that could reach the last items or the
 * CTA underneath them: scrolling the page moved the content, not the panel.
 *
 * `svh`, not `vh`: the browser chrome on iOS is counted, so the panel is
 * bounded by the space actually visible rather than by the space that
 * exists once the address bar has retracted.
 */
.header__mobile {
    display: flex;
    flex-direction: column;
    padding-block-end: var(--s-5);
    border-block-start: 1px solid var(--hairline-gold);
    max-block-size: calc(100svh - var(--header-h));
    overflow-y: auto;
    /* The page must not start scrolling when the panel reaches its end. */
    overscroll-behavior: contain;
}

.header__mobile-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
}

.header__mobile-link {
    color: inherit;
    font-weight: 600;
    padding-block: var(--s-4);
    min-block-size: 44px;
    display: flex;
    align-items: center;
}

.header__mobile-link--child {
    font-weight: 500;
    font-size: var(--fs-sm);
    color: rgba(255, 255, 255, 0.78);
    padding-block: var(--s-3);
}

.header__mobile-caret {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 44px;
    block-size: 44px;
    flex-shrink: 0;
    color: var(--gold-400);
    transition: transform var(--dur-micro) var(--ease);
}

.header__mobile-caret[aria-expanded='true'] {
    transform: rotate(180deg);
}

.header__mobile-sub {
    list-style: none;
    padding-inline-start: var(--s-5);
    border-inline-start: 1px solid var(--hairline-gold);
    margin-block-end: var(--s-2);
}

.header__mobile-cta {
    margin-block-start: var(--s-4);
    white-space: nowrap;
}

/* The CTA is the one control worth its width on a phone; the language
   switch and the nav are behind the toggle. */
@media (max-width: 519px) {
    .header__cta {
        display: none;
    }
}

@media (min-width: 1024px) {
    .header__nav {
        display: flex;
    }

    /* On desktop the nav takes the auto margin, so the actions must not. */
    .header__actions {
        margin-inline-start: 0;
    }

    .header__toggle,
    .header__mobile {
        display: none;
    }
}

/*
 * The section being read. A plain gold rule — NOT the Sadu thread, which
 * §10.1 fixes at exactly three homes and a nav underline is not one of them.
 * Gold as a hairline is §10.2's own listed use.
 */
.header__link.is-current {
    position: relative;
    color: #fff;
}

.header__link.is-current::after {
    content: "";
    position: absolute;
    inset-inline-start: 0;
    inset-block-end: 0;
    inline-size: 100%;
    block-size: 2px;
    background: var(--gold-400);
}
</style>
