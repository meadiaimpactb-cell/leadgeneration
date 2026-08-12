<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import Container from '@/Components/ui/Container.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';

/**
 * One artisan's story in full (§5).
 *
 * Deliberately the plainest page on the site: a photograph, the words, who
 * said them. The section on /impact has to compete with four counters and a
 * report shelf; here nothing competes, which is the only reason to give a
 * story its own page at all.
 */
defineProps({
    story: { type: Object, default: () => ({}) },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});
</script>

<template>
    <PublicLayout :seo="seo">
        <Breadcrumb :items="breadcrumbs" />

        <article class="section">
            <Container>
                <div class="story">
                    <div
                        class="story__frame"
                        :class="{ 'story__frame--empty': !story.image }"
                    >
                        <img
                            v-if="story.image"
                            class="story__image"
                            :src="story.image.webp ?? story.image.url"
                            :alt="story.image.alt ?? ''"
                            :width="story.image.width ?? undefined"
                            :height="story.image.height ?? undefined"
                            fetchpriority="high"
                            decoding="async"
                        />
                    </div>

                    <div class="story__text">
                        <h1 v-if="story.title" class="story__title">{{ story.title }}</h1>

                        <blockquote v-if="story.quote" class="story__quote">
                            {{ story.quote }}
                        </blockquote>

                        <!--
                            The body is a single translated field, entered as
                            paragraphs in the panel. `white-space: pre-line`
                            keeps the artisan's own breaks without asking an
                            editor to write markup.
                        -->
                        <div v-if="story.body" class="story__body">{{ story.body }}</div>

                        <p v-if="story.attribution" class="story__by">{{ story.attribution }}</p>
                    </div>
                </div>
            </Container>
        </article>

        <CtaBand />
    </PublicLayout>
</template>

<style scoped>
.story {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-7);
}

.story__frame {
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: var(--paper-warm);
}

.story__frame--empty {
    background: var(--placeholder-warm);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.story__image {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
}

.story__text {
    min-inline-size: 0;
}

.story__title {
    font-family: var(--font-display);
    font-size: clamp(1.75rem, 3.2vw, 2.75rem);
    line-height: var(--lh-heading);
    color: var(--navy-900);
}

.story__quote {
    margin: var(--s-6) 0 0;
    padding-inline-start: var(--s-4);
    border-inline-start: 2px solid var(--gold-400);
    font-size: 1.1875rem;
    font-weight: 500;
    line-height: 1.8;
    color: var(--navy-900);
}

.story__body {
    margin-block-start: var(--s-6);
    white-space: pre-line;
    line-height: var(--lh-body);
    max-inline-size: 62ch;
}

.story__by {
    margin-block-start: var(--s-6);
    padding-block-start: var(--s-4);
    border-block-start: 1px solid var(--hairline);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 900px) {
    .story {
        grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        gap: var(--s-8);
        align-items: start;
    }
}
</style>
