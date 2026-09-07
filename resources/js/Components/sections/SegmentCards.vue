<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';
import { useLeadSource } from '@/Composables/useLeadSource';

/**
 * sections/SegmentCards — "choose what matters to you".
 *
 * Three cards, one per audience, each with a button that goes to the single
 * form and tags the lead with that audience. It is `cards` plus a routed call
 * to action, and it is a separate type because `cards` deliberately has no
 * button: the moment a card grid could carry one, every card grid on the site
 * becomes a place someone might put a second form.
 *
 * These cards are NOT the navbar's targets. The brief is explicit that
 * «الجهات / الشركاء / الحرفيون» in the header point at the detailed sections
 * further down, not at these summaries.
 */
const props = defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    /** `[{ source, title, body, label }]`, each doubled with an `_en`. */
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
const { text } = useSettingText();
const { goToForm } = useLeadSource();

const written = computed(() =>
    props.items
        .map((item) => ({
            source: item.source ?? null,
            title: text(item, 'title'),
            body: text(item, 'body'),
            label: text(item, 'label'),
        }))
        .filter((item) => item.title || item.body)
);
</script>

<template>
    <section v-if="written.length" ref="root" class="section segments">
        <Container>
            <h2 v-if="heading" class="h2 reveal">{{ heading }}</h2>
            <p v-if="subheading" class="segments__sub reveal">{{ subheading }}</p>

            <ul class="segments__grid">
                <li v-for="(item, i) in written" :key="i" class="segments__card reveal">
                    <h3 v-if="item.title" class="segments__title">{{ item.title }}</h3>
                    <p v-if="item.body" class="segments__body">{{ item.body }}</p>

                    <!--
                        A real anchor to a real place on this page, so it works
                        with the keyboard, with middle-click, and before the
                        JavaScript has run. The handler only adds the segment
                        tag and the smooth scroll on top of that.
                    -->
                    <Button
                        v-if="item.label"
                        class="segments__cta"
                        variant="secondary"
                        href="#contact"
                        @click="goToForm($event, item.source)"
                    >
                        {{ item.label }}
                    </Button>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.segments__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.segments__grid {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
    padding: 0;
    list-style: none;
}

/*
 * The three routed cards.
 *
 * These are the page's first real fork — a visitor decides here which of the
 * three arguments below is theirs — so they carry more weight than the card
 * grids around them: the octagon cut and a gold rule along the reading edge.
 *
 * No shadow: `clip-path` clips the element's own painting, a box-shadow
 * included, so a cut card cannot also be a raised one. The hover is a lift
 * and the rule brightening, which is enough on this warm ground.
 */
.segments__card {
    --c: var(--cut-soft);
    position: relative;
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
    padding: var(--s-6) var(--s-5);
    border: 1px solid var(--hairline);
    background: var(--paper);
    clip-path: polygon(
        var(--c) 0, 100% 0, 100% calc(100% - var(--c)),
        calc(100% - var(--c)) 100%, 0 100%, 0 var(--c)
    );
    transition: transform var(--dur-el) var(--ease),
                border-color var(--dur-el) var(--ease);
}

/* The gold rule on the reading edge — §10.2's own use of the colour, and
   what tells the eye these three are a set to choose from. */
.segments__card::before {
    content: "";
    position: absolute;
    inset-block: var(--s-5);
    inset-inline-start: 0;
    inline-size: 2px;
    background: var(--gold-400);
}

.segments__card:hover {
    transform: translateY(-3px);
    border-color: var(--gold-400);
}

@media (prefers-reduced-motion: reduce) {
    .segments__card {
        transition: none;
    }

    .segments__card:hover {
        transform: none;
    }
}

.segments__title {
    font-size: var(--fs-h3);
}

.segments__body {
    color: var(--text-muted);
    /* Body size: this is where a visitor decides which of the three
       arguments below is theirs, and it is read, not skimmed. */
    font-size: var(--fs-body);
    line-height: var(--lh-body);
    /* Pushes the button to the foot of every card, so three cards of
       different lengths still line their actions up. */
    flex: 1 1 auto;
}

.segments__cta {
    align-self: start;
}

@media (min-width: 900px) {
    .segments__grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
