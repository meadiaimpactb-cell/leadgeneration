<script setup>
import Container from '@/Components/ui/Container.vue';

/**
 * sections/VideoBlock (§10.5).
 *
 * Manual controls, never autoplay with sound (§10.8). The poster gives the
 * browser something to paint immediately, and `preload="none"` keeps a video
 * the visitor may never play out of the page-weight budget (§15.1).
 */
defineProps({
    heading: { type: String, default: null },
    src: { type: String, default: null },
    poster: { type: String, default: null },
    captionsSrc: { type: String, default: null },
});
</script>

<template>
    <section v-if="src" class="section">
        <Container>
            <h2 v-if="heading">{{ heading }}</h2>
            <video
                class="video"
                controls
                playsinline
                preload="none"
                :poster="poster ?? undefined"
            >
                <source :src="src" />
                <track v-if="captionsSrc" kind="captions" :src="captionsSrc" default />
            </video>
        </Container>
    </section>
</template>

<style scoped>
.video {
    inline-size: 100%;
    margin-block-start: var(--s-7);
    aspect-ratio: 16 / 9;
    border-radius: var(--r-md);
    background: var(--navy-900);
}
</style>
