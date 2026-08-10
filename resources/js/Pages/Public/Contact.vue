<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import Container from '@/Components/ui/Container.vue';
import LeadField from '@/Components/forms/LeadField.vue';
import MapBlock from '@/Components/sections/MapBlock.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The contact page (§5).
 *
 * The form, the direct channels, and where Amad Craft is. There is no
 * quote-request form and no qualification form — §2.3 replaced every one of
 * them with the single contact field.
 */
const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

const { t } = useTranslation();
const inertia = usePage();

const settings = computed(() => inertia.props.settings ?? {});
const locale = computed(() => inertia.props.locale);

const formCopy = computed(() => props.sections.find((s) => s.type === 'contact_block') ?? {});
const mapCopy = computed(() => props.sections.find((s) => s.type === 'map') ?? {});

const email = computed(() => settings.value['contact.email'] ?? null);
const phone = computed(() => settings.value['contact.phone'] ?? null);
const whatsapp = computed(() => settings.value['contact.whatsapp'] ?? null);
const social = computed(() => settings.value['contact.social'] ?? []);

const address = computed(() => settings.value[`contact.address.${locale.value}`] ?? null);
const hours = computed(() => settings.value[`contact.hours.${locale.value}`] ?? null);
const mapQuery = computed(() => settings.value['contact.map_query'] ?? null);
const mapEmbed = computed(() => settings.value['contact.map_embed_url'] ?? null);
const mapLink = computed(() => settings.value['contact.map_url'] ?? null);

/** Digits only, for a wa.me link. */
const whatsappHref = computed(() =>
    whatsapp.value ? `https://wa.me/${String(whatsapp.value).replace(/\D/g, '')}` : null
);

const channels = computed(() =>
    [
        email.value && { label: t('contact.email'), value: email.value, href: `mailto:${email.value}`, latin: true },
        phone.value && { label: t('contact.phone'), value: phone.value, href: `tel:${phone.value}`, latin: true },
        whatsapp.value && {
            label: t('contact.whatsapp'),
            value: whatsapp.value,
            href: whatsappHref.value,
            latin: true,
            external: true,
        },
    ].filter(Boolean)
);
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <section class="section intro">
            <Container>
                <h1 v-if="page?.title">{{ page.title }}</h1>
                <p v-if="page?.subtitle" class="intro__sub">{{ page.subtitle }}</p>

                <div class="grid">
                    <!-- The one form (§6.1). Its fields come from the admin
                         panel, so this block never needs editing to change. -->
                    <div class="grid__form">
                        <LeadField
                            layout="stacked"
                            :heading="formCopy.heading"
                            :reassurance="formCopy.subheading"
                            :submit-label="formCopy.ctaLabel"
                        />
                    </div>

                    <aside v-if="channels.length || social.length" class="grid__aside">
                        <h2 class="aside__title">{{ t('contact.direct_channels') }}</h2>

                        <dl v-if="channels.length" class="channels">
                            <template v-for="channel in channels" :key="channel.label">
                                <dt class="channels__label">{{ channel.label }}</dt>
                                <dd class="channels__value">
                                    <a
                                        class="link-weave"
                                        :class="{ latin: channel.latin }"
                                        :href="channel.href"
                                        :rel="channel.external ? 'noopener noreferrer' : undefined"
                                        :target="channel.external ? '_blank' : undefined"
                                    >{{ channel.value }}</a>
                                </dd>
                            </template>
                        </dl>

                        <template v-if="social.length">
                            <h2 class="aside__title aside__title--spaced">{{ t('contact.social') }}</h2>
                            <ul class="social">
                                <li v-for="channel in social" :key="channel.url">
                                    <a
                                        class="link-weave"
                                        :href="channel.url"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                    >
                                        {{ channel.label }}
                                        <span class="visually-hidden">{{ t('common.external_link') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </template>
                    </aside>
                </div>
            </Container>
        </section>

        <SaduDivider />

        <MapBlock
            :heading="mapCopy.heading ?? t('contact.map')"
            :address="address"
            :hours="hours"
            :embed-url="mapEmbed"
            :query="mapQuery"
            :link-url="mapLink"
        />

        <SectionRenderer :sections="sections" :skip="['contact_block', 'map']" />
    </PublicLayout>
</template>

<style scoped>
.intro__sub {
    margin-block-start: var(--s-4);
    font-size: var(--fs-body-lg);
    color: var(--text-muted);
    max-inline-size: 56ch;
}

.grid {
    display: grid;
    gap: var(--s-8);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-8);
}

.grid__form {
    max-inline-size: 620px;
}

.grid__aside {
    padding: var(--s-5);
    border-radius: var(--r-md);
    background: var(--paper-alt);
    align-self: start;
}

.aside__title {
    font-size: var(--fs-h3);
}

.aside__title--spaced {
    margin-block-start: var(--s-6);
}

.channels {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--s-2) var(--s-4);
    margin-block-start: var(--s-4);
    font-size: var(--fs-body-lg);
}

.channels__label {
    color: var(--text-muted);
    font-size: var(--fs-sm);
    align-self: center;
}

.channels__value {
    margin: 0;
    overflow-wrap: anywhere;
}

.social {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-4);
    margin-block-start: var(--s-4);
}

.social a {
    display: inline-flex;
    align-items: center;
    min-block-size: 44px;
    font-weight: 600;
}

@media (min-width: 1024px) {
    .grid {
        grid-template-columns: 1.35fr 0.65fr;
        gap: var(--s-10);
    }
}
</style>
