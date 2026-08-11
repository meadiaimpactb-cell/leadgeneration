<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Logo from '@/Components/ui/Logo.vue';
import LangSwitch from '@/Components/ui/LangSwitch.vue';
import Button from '@/Components/ui/Button.vue';
import { useTranslation } from '@/Composables/useTranslation';

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
const contactUrl = computed(() => `/${locale.value}/contact`);

function toggle(id) {
    openMenu.value = openMenu.value === id ? null : id;
}

function closeAll() {
    openMenu.value = null;
    menuOpen.value = false;
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
                    >
                        <!-- A parent is a real link to its own hub page as
                             well as the trigger for its submenu, so it is a
                             <Link> with a separate disclosure button beside
                             it rather than a button that swallows the page. -->
                        <Link :href="item.url" class="header__link link-weave">
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

                        <ul
                            v-if="item.children?.length"
                            :id="`submenu-${item.id}`"
                            class="submenu"
                            :class="{ 'submenu--open': openMenu === item.id }"
                        >
                            <li v-for="child in item.children" :key="child.id">
                                <Link :href="child.url" class="submenu__link" @click="closeAll">
                                    {{ child.label }}
                                </Link>
                            </li>
                        </ul>
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
.submenu {
    position: absolute;
    inset-block-start: 100%;
    inset-inline-start: 0;
    min-inline-size: 260px;
    padding-block: var(--s-2);
    background: var(--navy-950);
    border: 1px solid var(--hairline-gold);
    list-style: none;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px);
    transition:
        opacity var(--dur-micro) var(--ease),
        transform var(--dur-micro) var(--ease),
        visibility var(--dur-micro);
    z-index: 1;
}

.submenu--open,
.header__item:focus-within .submenu {
    opacity: 1;
    visibility: visible;
    transform: none;
}

.submenu__link {
    display: block;
    padding: var(--s-3) var(--s-5);
    color: rgba(255, 255, 255, 0.82);
    font-size: var(--fs-sm);
    font-weight: 600;
    line-height: 1.4;
    min-block-size: 44px;
    transition: background-color var(--dur-micro) var(--ease);
}

.submenu__link:hover,
.submenu__link:focus-visible {
    background: rgba(220, 173, 117, 0.14);
    color: #fff;
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
.header__mobile {
    display: flex;
    flex-direction: column;
    padding-block-end: var(--s-5);
    border-block-start: 1px solid var(--hairline-gold);
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
</style>
