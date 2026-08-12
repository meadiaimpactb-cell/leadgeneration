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
 * One entry: the language you would be switching TO, with its flag.
 *
 * Showing both was tried and reverted. On an Arabic page it put «العربية»
 * beside «English» — one of them inert — and a header that names the language
 * you are already reading is telling you something you can see. The question
 * a switch answers is "can I read this in my language", and one destination
 * answers it without asking anyone to work out which half is the button.
 *
 * The flag carries the recognition; the word carries the meaning.
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

/** Everything except the one being read. */
const others = computed(() => locales.value.filter((l) => !l.current));

/** Which flag stands for which language. Arabic is the Saudi site's Arabic. */
const FLAGS = { ar: 'sa', en: 'us' };

function flagFor(code) {
    return FLAGS[code] ?? 'us';
}
</script>

<template>
    <nav v-if="others.length" class="lang-switch" :aria-label="t('common.switch_language')">
        <a
            v-for="locale in others"
            :key="locale.code"
            :href="locale.url"
            :lang="locale.code"
            :dir="locale.dir"
            class="lang-switch__item link-weave"
            :hreflang="locale.code"
        >
            <FlagIcon :country="flagFor(locale.code)" />
            <span>{{ locale.label }}</span>
        </a>
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
