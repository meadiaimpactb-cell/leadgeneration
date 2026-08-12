<script setup>
import Container from '@/Components/ui/Container.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/TrainingTracks (§10.5) — the training and empowerment programmes.
 *
 * The card's spine is the outcome line: what the trainee walks out holding.
 * It is what separates a track from an awareness workshop, and it is the one
 * thing an institution weighing a sponsorship reads, so it is a required field
 * on the record rather than an optional flourish.
 *
 * Everything on the card is a field — name, duration, summary, outcome, the
 * next cohort — so a track is added, reordered or taken down from the panel
 * with no code change, and a track switched off simply stops appearing.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { root } = useReveal();
</script>

<template>
    <!-- Named so the hero's first action can send an artisan straight here. -->
    <section v-if="items.length" id="tracks" ref="root" class="section tracks-sec">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ul class="tracks">
                <li v-for="program in items" :key="program.id" class="card track reveal">
                    <!--
                        The frame stands whether or not a photograph has
                        arrived, exactly as the story frames do. It used to
                        carry catalogue photography — the entrepreneurship
                        track was illustrated with a wall clock — and a
                        finished product is not what a track is: this is a
                        section about learning, and the honest empty frame
                        says less than a wrong picture does (§22.1).
                    -->
                    <div class="track__frame" :class="{ 'track__frame--empty': !program.image }">
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
                    </div>

                    <Badge v-if="program.durationWeeks" variant="accent">
                        {{
                            t('training.weeks', {
                                count: program.durationWeeks,
                            })
                        }}
                    </Badge>

                    <h3 class="track__name">{{ program.name }}</h3>
                    <p v-if="program.summary" class="track__summary">
                        {{ program.summary }}
                    </p>

                    <!--
                        Optional and empty by default. A card that announces a
                        cohort nobody has scheduled, or carries a month typed
                        into a template last season, is a promise the page
                        cannot keep — so the line exists only while the field
                        holds something.
                    -->
                    <p v-if="program.nextCohort" class="track__cohort">
                        <span class="track__cohort-label">{{ t('training.next_cohort') }}</span>
                        {{ program.nextCohort }}
                    </p>

                    <p v-if="program.outcomes" class="track__outcomes">
                        {{ program.outcomes }}
                    </p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
/* Clears the sticky header when the hero's action jumps here. */
.tracks-sec {
    scroll-margin-block-start: var(--s-9);
}

.tracks {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.track {
    align-items: flex-start;
}

.track__frame {
    inline-size: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: var(--r-sm);
    background: var(--paper-warm);
}

.track__frame--empty {
    background: var(--placeholder-warm);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.track__image {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
}

.track__name {
    font-size: var(--fs-h3);
}

.track__summary {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.track__cohort {
    font-size: var(--fs-sm);
    color: var(--navy-900);
}

.track__cohort-label {
    font-weight: 600;
    color: var(--action-600);
    margin-inline-end: var(--s-1);
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
