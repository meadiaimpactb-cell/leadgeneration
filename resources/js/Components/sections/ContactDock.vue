<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import LeadModal from '@/Components/forms/LeadModal.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/ContactDock — the contact affordance that is always on screen.
 *
 * Why it exists: the site's one metric is institutional leads (§1), and until
 * now the only way to reach the field was to scroll to the bottom of whatever
 * page you were on. A buyer who has read enough at 40% of the page had no way
 * to act on it without hunting. This puts the same single field one tap away
 * from every scroll position.
 *
 * What it is NOT: a second form. It opens `LeadModal`, which wraps the one
 * `LeadField` every other block on the site uses — same component, same
 * endpoint, same §6.1 configuration. There is still exactly one form.
 *
 * Direct channels sit beside it because a government buyer will often just
 * call. The number and WhatsApp come from `settings`, so they are the client's
 * to change and are absent from the markup entirely when unset (§22.9).
 *
 * It hides itself while an in-page lead form is visible: two calls to action
 * competing in the same viewport reads as pressure, and §4 forbids a sales
 * push. Sixteen years of scroll-jacked sites have taught buyers to distrust
 * anything that follows them, so this stays quiet, small and dismissible.
 */
const props = defineProps({
    /** Campaign pages allow no outbound links except legal (§11.3). */
    channels: { type: Boolean, default: true },
    campaign: { type: String, default: null },
});

const { t } = useTranslation();
const page = usePage();

const settings = computed(() => page.props.settings ?? {});
const locale = computed(() => page.props.locale);

const enabled = computed(() => settings.value['site.contact_dock'] !== false);

const phone = computed(() => (props.channels ? (settings.value['contact.phone'] ?? null) : null));
const whatsapp = computed(() => (props.channels ? (settings.value['contact.whatsapp'] ?? null) : null));

const whatsappHref = computed(() =>
    whatsapp.value ? `https://wa.me/${String(whatsapp.value).replace(/\D/g, '')}` : null
);

/** Copy is content, so it comes from settings, never from this file (§0.1). */
const heading = computed(() => settings.value[`contact.dock_heading.${locale.value}`] ?? null);
const reassurance = computed(() => settings.value[`contact.dock_note.${locale.value}`] ?? null);

const modalOpen = ref(false);
const dismissed = ref(false);
const formOnScreen = ref(false);

const visible = computed(() => enabled.value && !dismissed.value && !formOnScreen.value);

let observer = null;

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') return;

    // Watch every in-page lead form. While one is on screen the dock steps
    // aside rather than duplicating it.
    const forms = document.querySelectorAll('.lead');

    if (forms.length === 0) return;

    const showing = new Set();

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) showing.add(entry.target);
                else showing.delete(entry.target);
            }

            formOnScreen.value = showing.size > 0;
        },
        { rootMargin: '-10% 0px -10% 0px' }
    );

    forms.forEach((form) => observer.observe(form));
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <!--
        Rendered in place rather than teleported to <body>. Inertia's SSR pass
        returns only the app's own markup, so a <Teleport> target outside it
        produces nothing server-side — the whole dock appeared only after
        hydration, which is exactly the flash of missing CTA it exists to
        prevent. `position: fixed` needs no particular parent, only an
        ancestor chain free of transform/filter, which the layout is.
    -->
    <div v-if="visible" class="dock">
        <button type="button" class="dock__cta" @click="modalOpen = true">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                <path
                    d="M4 6h16v11H9l-5 4V6z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                />
            </svg>
            {{ t('leads.dock_cta') }}
        </button>

        <a v-if="phone" class="dock__channel" :href="`tel:${phone}`" :aria-label="t('contact.phone')">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                <path
                    d="M6.5 3h3l1.5 4-2 1.5a12 12 0 006.5 6.5L17 13l4 1.5v3a2 2 0 01-2.2 2A17 17 0 013.1 5.2 2 2 0 015 3h1.5z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                />
            </svg>
        </a>

        <a
            v-if="whatsappHref"
            class="dock__channel"
            :href="whatsappHref"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="t('contact.whatsapp')"
        >
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                <path
                    d="M3.5 20.5l1.3-4.4A8.2 8.2 0 1120.5 12a8.4 8.4 0 01-12.4 7.2l-4.6 1.3z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                />
                <path
                    d="M9 9.5c0 3 2.5 5.5 5.5 5.5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                />
            </svg>
            <span class="visually-hidden">{{ t('common.external_link') }}</span>
        </a>

        <button
            type="button"
            class="dock__dismiss"
            :aria-label="t('common.close')"
            @click="dismissed = true"
        >
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                <path
                    d="M6 6l12 12M18 6L6 18"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                />
            </svg>
        </button>
    </div>

    <LeadModal
        :open="modalOpen"
        :heading="heading"
        :reassurance="reassurance"
        :campaign="campaign"
        @close="modalOpen = false"
    />
</template>

<style scoped>
.dock {
    position: fixed;
    inset-block-end: var(--s-4);
    /* Logical, so it docks bottom-left in Arabic and bottom-right in English
       — the side the thumb reaches in each reading direction. */
    inset-inline-start: var(--s-4);
    z-index: 60;
    display: flex;
    align-items: center;
    gap: var(--s-2);
    padding: var(--s-2);
    /* --r-md, not --r-pill: §10.4 reserves the pill radius for filters. */
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: var(--shadow-card);
    max-inline-size: calc(100vw - var(--s-4) * 2);
}

.dock__cta {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    padding-inline: var(--s-4);
    border-radius: var(--r-sm);
    /* --action-600, not the raw brand orange: white text on #D7653B only
       clears AA at 18px/700 and this label is smaller (§10.2). */
    background: var(--action-600);
    color: #fff;
    font-size: var(--fs-sm);
    font-weight: 700;
    white-space: nowrap;
    transition: background-color var(--dur-micro) var(--ease);
}

.dock__cta:hover {
    background: #9d4726;
}

.dock__channel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 44px;
    block-size: 44px;
    border-radius: var(--r-sm);
    background: var(--navy-100);
    color: var(--navy-900);
    transition: background-color var(--dur-micro) var(--ease);
}

.dock__channel:hover {
    background: var(--navy-900);
    color: #fff;
}

.dock__dismiss {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 32px;
    block-size: 44px;
    color: var(--muted);
}

.dock__dismiss:hover {
    color: var(--navy-900);
}

@media (prefers-reduced-motion: no-preference) {
    .dock {
        animation: dock-in var(--dur-el) var(--ease);
    }
}

@keyframes dock-in {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
}
</style>
