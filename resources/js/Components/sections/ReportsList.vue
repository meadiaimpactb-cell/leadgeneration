<script setup>
import Container from '@/Components/ui/Container.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/ReportsList (§10.5) — publishable impact reports.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ul class="reports">
                <li v-for="report in items" :key="report.id" class="card report reveal">
                    <img
                        v-if="report.cover"
                        class="report__cover"
                        :src="report.cover.webp ?? report.cover.url"
                        :alt="report.cover.alt ?? ''"
                        :width="report.cover.width ?? undefined"
                        :height="report.cover.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />

                    <Badge v-if="report.year" class="tabular">{{ report.year }}</Badge>
                    <h3 class="report__title">{{ report.title }}</h3>
                    <p v-if="report.summary" class="report__summary">{{ report.summary }}</p>

                    <a
                        v-if="report.fileUrl"
                        class="report__link link-weave"
                        :href="report.fileUrl"
                        download
                    >
                        {{ t('impact.download') }}
                        <span v-if="report.fileSize" class="report__size latin">
                            ({{ report.fileSize }})
                        </span>
                    </a>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.reports {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.report {
    align-items: flex-start;
}

.report__cover {
    inline-size: 100%;
    aspect-ratio: 3 / 4;
    object-fit: cover;
    border-radius: var(--r-sm);
}

.report__title {
    font-size: var(--fs-h3);
}

.report__summary {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.report__link {
    margin-block-start: auto;
    padding-block-start: var(--s-3);
    color: var(--link);
    font-size: var(--fs-sm);
    font-weight: 600;
}

.report__size {
    color: var(--text-muted);
    font-weight: 400;
}

@media (min-width: 640px) {
    .reports {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .reports {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
