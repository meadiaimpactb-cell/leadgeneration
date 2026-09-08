<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * sections/Cards — a generic 3-up card grid driven entirely by the section's
 * `settings.items` (§9.1). Used for "what we offer this sector" and anywhere
 * else the client wants three points.
 */
const props = defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    items: { type: Array, default: () => [] },
    /**
     * `band` puts the same grid on navy. Used where the points are the
     * section's argument rather than a list inside a longer page — the
     * Vision 2030 alignment strip on /impact is the case it was added for.
     */
    variant: {
        type: String,
        default: 'paper',
        validator: (v) => ['paper', 'band'].includes(v),
    },
});

const { root } = useReveal();
const { text } = useSettingText();

/**
 * The cards written in the language being read.
 *
 * `settings` is one JSON column shared by both locales, so each item carries
 * `title` and `title_en`. Without this the Arabic keys printed verbatim on
 * /en, and an English-reading buyer met an English heading over four Arabic
 * cards — which §12 rates as worse than a shorter page, not better.
 */
const written = computed(() =>
    props.items
        .map((item) => ({
            icon: item.icon,
            /*
             * The running number the client wrote on the card, if any.
             *
             * The approved copy for «ماذا نقدم؟» names its four areas
             * «01 — الهدايا المؤسسية» and so on. The number was stored in the
             * section's settings and drawn by nothing, so four
             * approved labels reached the page without the half that ordered
             * them. Not derived from the index: the client wrote it, and a
             * derived counter would silently renumber a set they had
             * deliberately numbered otherwise.
             */
            number: item.number ?? null,
            title: text(item, 'title'),
            body: text(item, 'body'),
        }))
        .filter((item) => item.title || item.body),
);
</script>

<template>
    <section
        v-if="written.length"
        ref="root"
        class="section"
        :class="[`cardsec--${variant}`, variant === 'band' ? 'on-dark' : null]"
    >
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>
            <p v-if="subheading" class="cards__sub reveal">{{ subheading }}</p>

            <ul class="cards">
                <li v-for="(item, i) in written" :key="i" class="card reveal">
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

                    <!-- Latin digits in both languages, like every other
                         counter on the site — useFormat's rule. -->
                    <span v-if="item.number" class="cards__number mono-label">{{ item.number }}</span>
                    <h3 v-if="item.title" class="cards__title">
                        {{ item.title }}
                    </h3>
                    <p v-if="item.body" class="cards__body">{{ item.body }}</p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.cardsec--band {
    background: var(--navy-900);
}

/*
 * On navy the card's paper fill and border would draw four boxes on a dark
 * ground. The cards become plain columns divided by a gold hairline instead —
 * the band is the container, so each card does not need to be one too.
 */
.cardsec--band :deep(.card) {
    background: transparent;
    border: 0;
    padding-inline: 0;
    padding-block-start: var(--s-5);
    border-block-start: 1px solid rgb(var(--gold-rgb) / 0.42);
}

.cardsec--band .cards__title {
    color: #fff;
}

.cardsec--band .cards__body,
.cardsec--band .cards__sub {
    color: rgba(255, 255, 255, 0.74);
}

.cardsec--band .cards__icon {
    background: rgba(255, 255, 255, 0.08);
    color: var(--gold-400);
}

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
    background: rgb(var(--lavender-rgb) / 0.12);
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

/*
 * The counter, in the accent that passes on the ground it sits on.
 *
 * Gold is §10.2's divider and hairline colour, not a text colour on a light
 * surface: #DCAD75 on the warm paper is 1.93:1, which is unreadable and
 * fails AA outright. It reads correctly on navy, so the band overrides it
 * below. Setting gold here and hoping every future card set is a band is how
 * an unreadable number ships.
 */
.cards__number {
    color: var(--action-600);
    font-size: var(--fs-xs);
}

.cardsec--band .cards__number {
    color: var(--gold-400);
}
</style>
