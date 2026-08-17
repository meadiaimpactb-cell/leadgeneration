<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Logo from '@/Components/ui/Logo.vue';
import ContactDock from '@/Components/sections/ContactDock.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Layouts/CampaignLayout (§11.3) — conversion focus.
 *
 * No navigation. Logo only. One goal per page, and no outbound links except
 * legal, so nothing on the page competes with the lead field.
 */
const props = defineProps({
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
    /** Slug attributed to every lead submitted from this page (§8.3). */
    campaign: { type: String, default: null },
});

const { t } = useTranslation();
const inertia = usePage();

const locale = computed(() => inertia.props.locale);
const legalLinks = computed(() => inertia.props.navigation?.footer_legal ?? []);
const alternates = computed(() => props.seo.alternates ?? []);
</script>

<template>
    <Head :title="seo.fullTitle">
        <meta v-if="seo.description" head-key="description" name="description" :content="seo.description" />
        <link v-if="seo.canonical" head-key="canonical" rel="canonical" :href="seo.canonical" />
        <meta v-if="seo.robots" head-key="robots" name="robots" :content="seo.robots" />
        <link
            v-for="alt in alternates"
            :key="alt.hreflang"
            :head-key="`alt-${alt.hreflang}`"
            rel="alternate"
            :hreflang="alt.hreflang"
            :href="alt.href"
        />
        <meta head-key="og:title" property="og:title" :content="seo.fullTitle" />
    </Head>

    <p v-if="previewing" class="preview-bar" role="status">
        {{ t('admin.preview_notice') }}
    </p>

    <header class="chead">
        <Container>
            <Link :href="`/${locale}`" :aria-label="t('common.home')">
                <Logo lockup="horizontal" tone="navy" />
            </Link>
        </Container>
    </header>

    <main id="main">
        <slot />
    </main>

    <footer class="cfoot">
        <Container>
            <nav v-if="legalLinks.length" :aria-label="t('common.nav_legal')" class="cfoot__legal">
                <Link
                    v-for="item in legalLinks"
                    :key="item.id"
                    :href="item.url"
                    class="cfoot__link link-weave"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </Container>
    </footer>

    <!-- `channels` off: §11.3 allows no outbound link on a campaign page except
         legal, and a tel:/WhatsApp button is a way off the page. The lead
         field stays, because it is the page's one goal. -->
    <ContactDock v-if="!previewing" :channels="false" :campaign="campaign" />
</template>

<style scoped>
.preview-bar {
    padding: var(--s-3) var(--s-5);
    background: var(--gold-400);
    color: var(--navy-900);
    font-size: var(--fs-sm);
    font-weight: 600;
    text-align: center;
}

.chead {
    padding-block: var(--s-5);
    border-block-end: 1px solid var(--hairline);
}

.cfoot {
    padding-block: var(--s-7);
    border-block-start: 1px solid var(--hairline);
}

.cfoot__legal {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-5);
}

.cfoot__link {
    font-size: var(--fs-sm);
    color: var(--text-muted);
}
</style>
