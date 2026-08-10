<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Logo from '@/Components/ui/Logo.vue';
import LangSwitch from '@/Components/ui/LangSwitch.vue';
import Button from '@/Components/ui/Button.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Sticky header (§11.1): transparent over the navy hero, solid once scrolled.
 *
 * Menu items come from the `navigations` table so the client can reorder them
 * without a developer (§9.1). The header renders nothing but the logo and the
 * CTA until that data exists — it never invents menu labels (§22.1).
 */
const props = defineProps({
    /**
     * True only on pages whose first element is a full-bleed dark hero that
     * the header is meant to float over — currently just the home page.
     *
     * Everywhere else the header MUST paint its own background: white text on
     * a white page renders an invisible header, which is exactly what happened
     * before this prop existed.
     */
    overHero: { type: Boolean, default: false },
});

const { t } = useTranslation();
const page = usePage();

const scrolled = ref(false);
const menuOpen = ref(false);

const locale = computed(() => page.props.locale);
const homeUrl = computed(() => `/${locale.value}`);
const items = computed(() => page.props.navigation?.header ?? []);

/** Solid by default; transparent only while floating over an unscrolled hero. */
const solid = computed(() => !props.overHero || scrolled.value || menuOpen.value);

function onScroll() {
    scrolled.value = window.scrollY > 24;
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header class="header" :class="{ 'header--solid': solid }">
        <Container>
            <div class="header__bar">
                <Link :href="homeUrl" class="header__logo" :aria-label="t('common.home')">
                    <Logo lockup="horizontal" :tone="solid ? 'navy' : 'white'" />
                </Link>

                <nav v-if="items.length" class="header__nav" :aria-label="t('common.menu')">
                    <Link
                        v-for="item in items"
                        :key="item.id"
                        :href="item.url"
                        class="header__link link-weave"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="header__actions">
                    <LangSwitch />
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
                <Link
                    v-for="item in items"
                    :key="item.id"
                    :href="item.url"
                    class="header__mobile-link"
                    @click="menuOpen = false"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </Container>
    </header>
</template>

<style scoped>
.header {
    position: sticky;
    inset-block-start: 0;
    z-index: 100;
    color: var(--text-inverse);
    transition:
        background-color var(--dur-el) var(--ease),
        box-shadow var(--dur-el) var(--ease),
        color var(--dur-el) var(--ease);
}

.header--solid {
    background: var(--paper);
    color: var(--navy-900);
    box-shadow: 0 1px 0 var(--hairline);
}

.header__bar {
    display: flex;
    align-items: center;
    gap: var(--s-5);
    min-block-size: 72px;
}

.header__logo {
    display: block;
    /* Clear space around the mark = the height of the "ا" glyph (§23). */
    padding-block: var(--s-3);
}

.header__nav {
    display: none;
    gap: var(--s-5);
    margin-inline-start: auto;
}

.header__link {
    color: inherit;
    font-weight: 600;
    font-size: var(--fs-sm);
    padding-block: var(--s-3);
}

.header__actions {
    margin-inline-start: auto;
    display: flex;
    align-items: center;
    gap: var(--s-3);
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

.header__mobile {
    display: flex;
    flex-direction: column;
    padding-block-end: var(--s-5);
    border-block-start: 1px solid var(--hairline);
}

.header__mobile-link {
    color: inherit;
    font-weight: 600;
    padding-block: var(--s-4);
    min-block-size: 44px;
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
