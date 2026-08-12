<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import Container from '@/Components/ui/Container.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import { useTranslation } from '@/Composables/useTranslation';

/** Every published story. /impact shows the latest three and links here. */
defineProps({
    stories: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();
</script>

<template>
    <PublicLayout :seo="seo">
        <Breadcrumb :items="breadcrumbs" />

        <section class="section">
            <Container>
                <h1>{{ t('impact.all_stories') }}</h1>

                <ul class="grid">
                    <li v-for="story in stories" :key="story.id" class="cell">
                        <figure class="story">
                            <div
                                class="story__frame"
                                :class="{ 'story__frame--empty': !story.image }"
                            >
                                <img
                                    v-if="story.image"
                                    class="story__image"
                                    :src="story.image.webp ?? story.image.url"
                                    :alt="story.image.alt ?? ''"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>

                            <figcaption>
                                <blockquote v-if="story.quote" class="story__quote">
                                    {{ story.quote }}
                                </blockquote>
                                <p v-if="story.attribution" class="story__by">
                                    {{ story.attribution }}
                                </p>
                                <Link
                                    v-if="story.hasFullStory"
                                    class="story__link link-weave"
                                    :href="story.url"
                                >
                                    {{ t('impact.read_story') }}
                                </Link>
                            </figcaption>
                        </figure>
                    </li>
                </ul>
            </Container>
        </section>

        <CtaBand />
    </PublicLayout>
</template>

<style scoped>
.grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-8);
    margin-block-start: var(--s-7);
}

.story {
    margin: 0;
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

.story__quote {
    margin: var(--s-6) 0 0;
    padding-inline-start: var(--s-4);
    border-inline-start: 2px solid var(--gold-400);
    font-size: 1.0625rem;
    line-height: 1.8;
    color: var(--navy-900);
}

.story__by {
    margin-block-start: var(--s-5);
    padding-block-start: var(--s-4);
    border-block-start: 1px solid var(--hairline);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.story__link {
    display: inline-block;
    margin-block-start: var(--s-3);
    color: var(--link);
    font-size: var(--fs-sm);
    font-weight: 600;
}

@media (min-width: 640px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1024px) {
    .grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
</style>
