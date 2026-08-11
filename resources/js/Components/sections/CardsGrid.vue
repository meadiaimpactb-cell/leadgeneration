<script setup>
import Container from '@/Components/ui/Container.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/Cards — a generic 3-up card grid driven entirely by the section's
 * `settings.items` (§9.1). Used for "what we offer this sector" and anywhere
 * else the client wants three points.
 */
defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>
            <p v-if="subheading" class="cards__sub reveal">{{ subheading }}</p>

            <ul class="cards">
                <li v-for="(item, i) in items" :key="i" class="card reveal">
                    <!--
                        A line glyph from the icon set, never the raw string.
                        Emoji were rendering here as the platform's own
                        multi-colour artwork — one of them arrived as an
                        Instagram-style gradient — which is a second brand's
                        palette sitting inside a card that is otherwise built
                        entirely from this one's tokens.
                    -->
                    <span v-if="item.icon" class="cards__icon">
                        <NavIcon :name="item.icon" :size="28" :weight="1.4" :muted="false" />
                    </span>
                    <h3 v-if="item.title" class="cards__title">{{ item.title }}</h3>
                    <p v-if="item.body" class="cards__body">{{ item.body }}</p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.cards__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.cards {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

/* A faint disc behind the glyph — the one place §10.2 lets the lavender
   appear, at an opacity where it reads as tint rather than as a colour. */
.cards__icon {
    display: inline-grid;
    place-items: center;
    inline-size: 48px;
    block-size: 48px;
    border-radius: 50%;
    background: rgba(134, 133, 216, 0.12);
    color: var(--navy-900);
}

.cards__title {
    font-size: var(--fs-h3);
}

.cards__body {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 640px) {
    .cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .cards {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
