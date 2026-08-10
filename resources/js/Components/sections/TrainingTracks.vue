<script setup>
import Container from '@/Components/ui/Container.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/TrainingTracks (§10.5) — the training and empowerment programmes.
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

            <ul class="tracks">
                <li v-for="program in items" :key="program.id" class="card track reveal">
                    <img
                        v-if="program.image"
                        class="track__image"
                        :src="program.image.webp ?? program.image.url"
                        :alt="program.image.alt ?? ''"
                        :width="program.image.width ?? undefined"
                        :height="program.image.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />

                    <Badge v-if="program.durationWeeks" variant="accent">
                        {{ t('training.weeks', { count: program.durationWeeks }) }}
                    </Badge>

                    <h3 class="track__name">{{ program.name }}</h3>
                    <p v-if="program.summary" class="track__summary">{{ program.summary }}</p>

                    <p v-if="program.outcomes" class="track__outcomes">{{ program.outcomes }}</p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.tracks {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.track {
    align-items: flex-start;
}

.track__image {
    inline-size: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border-radius: var(--r-sm);
}

.track__name {
    font-size: var(--fs-h3);
}

.track__summary {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.track__outcomes {
    margin-block-start: var(--s-2);
    padding-block-start: var(--s-3);
    border-block-start: 1px solid var(--hairline);
    font-size: var(--fs-sm);
    color: var(--navy-900);
}

@media (min-width: 640px) {
    .tracks {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .tracks {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
