<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/Components/sections/SiteHeader.vue';
import SiteFooter from '@/Components/sections/SiteFooter.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Layouts/PublicLayout — sticky header + footer (§10.5).
 *
 * Owns the document head. Everything in it comes from the server-built SEO
 * block, so titles and descriptions stay editable per page and per language
 * from the admin panel (§13).
 */
const props = defineProps({
    seo: { type: Object, default: () => ({}) },
    /**
     * Set only by pages that open with a full-bleed dark hero the header is
     * meant to float over. Off by default so a new page can never ship with an
     * invisible white-on-white header.
     */
    overHero: { type: Boolean, default: false },
    /** True when reached through a draft's secret preview link (§9.1). */
    previewing: { type: Boolean, default: false },
    /**
     * Set by a page that carries a location section of its own.
     *
     * The footer's showroom block is the site-wide answer to "where are you".
     * On /contact the page already answers it, and the two together printed
     * one address, one set of opening hours and one map twice within a short
     * page. The page that owns the question keeps it; the global block steps
     * aside.
     */
    hasOwnLocation: { type: Boolean, default: false },
});

const { t } = useTranslation();
const alternates = computed(() => props.seo.alternates ?? []);
</script>

<template>
    <Head :title="seo.fullTitle">
        <meta v-if="seo.description" head-key="description" name="description" :content="seo.description" />
        <meta v-if="seo.keywords" head-key="keywords" name="keywords" :content="seo.keywords" />
        <link v-if="seo.canonical" head-key="canonical" rel="canonical" :href="seo.canonical" />
        <meta v-if="seo.robots" head-key="robots" name="robots" :content="seo.robots" />

        <!-- Reciprocal hreflang + x-default (§13). Only locales the page
             genuinely exists in are listed. -->
        <link
            v-for="alt in alternates"
            :key="alt.hreflang"
            :head-key="`alt-${alt.hreflang}`"
            rel="alternate"
            :hreflang="alt.hreflang"
            :href="alt.href"
        />

        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:title" property="og:title" :content="seo.fullTitle" />
        <meta
            v-if="seo.description"
            head-key="og:description"
            property="og:description"
            :content="seo.description"
        />
        <meta v-if="seo.canonical" head-key="og:url" property="og:url" :content="seo.canonical" />
        <meta v-if="seo.image" head-key="og:image" property="og:image" :content="seo.image" />
        <meta v-if="seo.siteName" head-key="og:site_name" property="og:site_name" :content="seo.siteName" />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="seo.image ? 'summary_large_image' : 'summary'"
        />
    </Head>

    <p v-if="previewing" class="preview-bar" role="status">
        {{ t('admin.preview_notice') }}
    </p>

    <!--
        Anything that must sit above the header — currently only the home
        page's news strip. A slot rather than a component call, so a page that
        has no strip costs nothing and the layout stays ignorant of what goes
        in it.
    -->
    <slot name="ticker" />

    <!--
        The header, overridable by the page.

        Every multi-page route uses the default and is untouched. The single
        landing page supplies its own, because its menu scrolls to sections of
        the page it is already on rather than navigating to other pages — a
        different control, not a variant of this one.
    -->
    <slot name="header">
        <SiteHeader :over-hero="overHero && !previewing" />
    </slot>

    <main id="main">
        <slot />
    </main>

    <SiteFooter :show-location="!hasOwnLocation" />

    <!--
        The floating contact dock is gone at the client's request. The contact
        paths that remain are the header button, the CTA band above the footer,
        and the footer's own email and phone — all of which lead to the same
        single form (§6.1), so nothing was lost but the overlay.
    -->

</template>

<style scoped>
/* Deliberately loud: an editor must never mistake a draft preview for the
   live site, and a visitor must never land here without knowing. */
.preview-bar {
    padding: var(--s-3) var(--s-5);
    background: var(--gold-400);
    color: var(--navy-900);
    font-size: var(--fs-sm);
    font-weight: 600;
    text-align: center;
}
</style>
