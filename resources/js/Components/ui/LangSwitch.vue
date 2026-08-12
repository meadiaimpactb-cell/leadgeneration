<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FlagIcon from '@/Components/ui/FlagIcon.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * ui/LangSwitch (§10.5, §12).
 *
 * Renders real <a href> links to the same page in the other locale, produced
 * server-side by HandleInertiaRequests. Not an Inertia <Link>: switching
 * locale changes <html lang> and <html dir>, which is a document-level
 * change, so a full navigation is the correct behaviour.
 *
 * Both languages are shown, with the current one marked rather than removed.
 * It used to show only the other one — a single word reading «English» that
 * says nothing about what you are reading now, so a visitor arriving on the
 * English site could not tell at a glance whether they were on it. Two
 * entries, one of them marked, answers "where am I" and "where can I go" at
 * the same time.
 *
 * Hidden entirely when only one locale is live — English launch timing is a
 * management decision gated by a setting (§12, §20.5).
 */
defineProps({
    variant: {
        type: String,
        default: 'inline',
        validator: (v) => ['inline', 'dropdown'].includes(v),
    },
});

const { t } = useTranslation();
const page = usePage();

const locales = computed(() => page.props.locales ?? []);

/** Which flag stands for which language. Arabic is the Saudi site's Arabic. */
const FLAGS = { ar: 'sa', en: 'us' };

function flagFor(code) {
    return FLAGS[code] ?? 'us';
}
</script>

<template>
    <nav v-if="locales.length > 1" class="lang-switch" :aria-label="t('common.switch_language')">
        <template v-for="locale in locales" :key="locale.code">
            <!--
                The current language is a marker, not a link. A link to the
                page you are already on is a control that does nothing, and
                `aria-current` is how a screen reader is told which is which.
            -->
            <span
                v-if="locale.current"
                class="lang-switch__item is-current"
                :lang="locale.code"
                :dir="locale.dir"
                aria-current="true"
            >
                <FlagIcon :country="flagFor(locale.code)" />
                <span>{{ locale.label }}</span>
            </span>

            <a
                v-else
                :href="locale.url"
                :lang="locale.code"
                :dir="locale.dir"
                class="lang-switch__item link-weave"
                :hreflang="locale.code"
            >
                <FlagIcon :country="flagFor(locale.code)" />
                <span>{{ locale.label }}</span>
            </a>
        </template>
    </nav>
</template>

<style scoped>
.lang-switch {
    display: inline-flex;
    gap: var(--s-2);
    align-items: center;
}

.lang-switch__item {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    color: inherit;
    font-size: var(--fs-sm);
    font-weight: 600;
    padding-inline: var(--s-2);
    /* Unchanged from the single-link version, so the header keeps its height
       and its alignment exactly (§11.1). */
    min-inline-size: 44px;
    min-block-size: 44px;
    justify-content: center;
}

/*
 * The one you are reading is quieter, not louder.
 *
 * The link is the thing to press; marking the current language with weight or
 * the accent colour would make the inert item the loudest thing in the
 * header — and §10.2 spends the accent on calls to action.
 */
.lang-switch__item.is-current {
    opacity: 0.55;
    cursor: default;
}

/* On a phone the names go and the flags carry it, which is what keeps the
   header one line — the same trade the CTA already makes. */
@media (max-width: 480px) {
    .lang-switch__item span:last-child {
        display: none;
    }

    .lang-switch__item {
        min-inline-size: 44px;
        padding-inline: var(--s-1);
    }
}
</style>
