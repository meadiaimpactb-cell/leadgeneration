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
});

const { root } = useReveal();
</script>

<template>
    <section v-if="body || heading" ref="root" class="section">
        <Container>
            <div class="prose reveal">
                <h2 v-if="heading">{{ heading }}</h2>
                <div v-if="body" class="prose__body" v-html="body" />
            </div>
        </Container>
    </section>
</template>

<style scoped>
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
