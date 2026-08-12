<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/RichText (§10.5).
 *
 * `body` is HTML authored in the admin panel's editor. It is rendered with
 * v-html, which is safe here only because the editor is behind authentication
 * and its output is sanitised server-side before it is stored — never render
 * visitor-supplied HTML through this component.
 */
defineProps({
    heading: { type: String, default: null },
    body: { type: String, default: null },
    /**
     * An optional photograph beside the prose, cut to the octagon.
     *
     * Added for /about, where the opening story is the page's whole argument
     * and a full-width column of text with nothing beside it reads as a
     * document rather than as an editorial spread. Every other rich_text
     * section on the site has no image and is untouched by this.
     */
    image: { type: Object, default: null },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="body || heading" ref="root" class="section">
        <Container>
            <div class="editorial" :class="{ 'editorial--illustrated': image }">
                <div class="prose reveal">
                    <h2 v-if="heading">{{ heading }}</h2>
                    <div v-if="body" class="prose__body" v-html="body" />
                </div>

                <figure v-if="image" class="editorial__figure reveal">
                    <div class="editorial__frame cut-framed">
                        <img
                            class="editorial__img cut"
                            :src="image.webp ?? image.url"
                            :alt="image.alt ?? ''"
                            :width="image.width ?? undefined"
                            :height="image.height ?? undefined"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>

                    <figcaption v-if="image.caption" class="editorial__caption">
                        {{ image.caption }}
                    </figcaption>
                </figure>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.editorial {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-8);
    align-items: center;
}

.editorial__figure {
    margin: 0;
}

.editorial__img {
    display: block;
    inline-size: 100%;
    block-size: 100%;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    background: var(--placeholder-warm);
}

.editorial__caption {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1024px) {
    /*
     * The text column keeps its comfortable measure and the photograph takes
     * the rest — the opposite of an even split, which would either stretch
     * the line length past readable or shrink the establishing shot to a
     * thumbnail.
     */
    .editorial--illustrated {
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
        gap: var(--s-10);
    }
}

.prose {
    max-inline-size: 68ch;
}

.prose__body {
    margin-block-start: var(--s-5);
    font-size: var(--fs-body-lg);
    color: var(--text);
}

.prose__body :deep(p) {
    margin-block-end: var(--s-4);
}

.prose__body :deep(h3) {
    margin-block: var(--s-7) var(--s-3);
}

.prose__body :deep(ul),
.prose__body :deep(ol) {
    margin-block-end: var(--s-4);
    padding-inline-start: var(--s-5);
    list-style: disc;
}

.prose__body :deep(li) {
    margin-block-end: var(--s-2);
}

.prose__body :deep(a) {
    color: var(--link);
    text-decoration: underline;
    text-underline-offset: 3px;
}

.prose__body :deep(strong) {
    font-weight: 600;
    color: var(--navy-900);
}
</style>
