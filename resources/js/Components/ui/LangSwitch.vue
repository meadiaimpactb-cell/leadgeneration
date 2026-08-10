<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * ui/LangSwitch (§10.5, §12).
 *
 * Renders real <a href> links to the same page in the other locale, produced
 * server-side by HandleInertiaRequests. Not an Inertia <Link>: switching
 * locale changes <html lang> and <html dir>, which is a document-level
 * change, so a full navigation is the correct behaviour.
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
const others = computed(() => locales.value.filter((l) => !l.current));
</script>

<template>
    <nav v-if="others.length" class="lang-switch" :aria-label="t('common.switch_language')">
        <a
            v-for="locale in others"
            :key="locale.code"
            :href="locale.url"
            :lang="locale.code"
            :dir="locale.dir"
            class="lang-switch__link link-weave"
            :hreflang="locale.code"
        >
            {{ locale.label }}
        </a>
    </nav>
</template>

<style scoped>
.lang-switch {
    display: inline-flex;
    gap: var(--s-3);
    align-items: center;
}

.lang-switch__link {
    color: inherit;
    font-size: var(--fs-sm);
    font-weight: 600;
    padding: var(--s-2);
    /* Keeps the 44px touch target without a visually large control. */
    min-inline-size: 44px;
    min-block-size: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>
