<script setup>
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * sections/AudienceSplit — one page, two readers, side by side.
 *
 * /training is the only page on this site whose audiences want opposite
 * things: an artisan deciding whether to join a track, and an institution
 * deciding whether to fund one. As a single paragraph, the second audience won
 * and the first arrived at the foot of the page to find a form addressed to
 * somebody else. Two columns is the smallest change that gives each of them a
 * door without weakening the other's argument.
 *
 * Every string is a column's own setting, so the client writes both sides in
 * the panel (§22.1). The button emits rather than links: it has to say who is
 * asking before it moves the visitor to the one form (§6.1), which is not
 * something an href can carry.
 */
defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    /**
     * `[{ title, body, actionLabel, interest, linkLabel, linkUrl }]`, each key
     * doubled with an `_en` variant. `interest` is the tag the lead is stored
     * with — sponsor, trainee — so the vocabulary lives beside the button that
     * sets it rather than in code.
     */
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['choose']);

const { root } = useReveal();
const { text } = useSettingText();
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="section split"
        :aria-labelledby="heading ? 'split-heading' : undefined"
    >
        <Container>
            <h2 v-if="heading" id="split-heading" class="h2 split__title reveal">
                {{ heading }}
            </h2>
            <p v-if="subheading" class="split__sub reveal">{{ subheading }}</p>

            <ul class="split__grid">
                <li v-for="(item, i) in items" :key="i" class="split__col reveal">
                    <!--
                        Optional, and each column stands without it. Where a
                        photograph exists it does the arguing: the artisan's
                        column shows the work being learned, the sponsor's
                        shows what a funded track hands over.
                    -->
                    <img
                        v-if="item.image"
                        class="split__image"
                        :src="item.image.webp ?? item.image.url"
                                :srcset="item.image.srcset ?? undefined"
                                sizes="(min-width: 900px) 50vw, 100vw"
                        :alt="text(item, 'imageAlt') ?? ''"
                        :width="item.image.width ?? undefined"
                        :height="item.image.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />

                    <h3 v-if="text(item, 'title')" class="split__name">
                        {{ text(item, 'title') }}
                    </h3>
                    <p v-if="text(item, 'body')" class="split__body">
                        {{ text(item, 'body') }}
                    </p>

                    <!-- The methodology link on the sponsor side: the impact
                         page states how a track's result is measured, and the
                         claim and its proof must not be two separate copies. -->
                    <Link
                        v-if="text(item, 'linkLabel') && text(item, 'linkUrl')"
                        class="split__link link-weave"
                        :href="text(item, 'linkUrl')"
                    >
                        {{ text(item, 'linkLabel') }}
                    </Link>

                    <Button
                        v-if="text(item, 'actionLabel')"
                        :variant="i === 0 ? 'cta' : 'secondary'"
                        class="split__action"
                        @click="emit('choose', item.interest ?? null)"
                    >
                        {{ text(item, 'actionLabel') }}
                    </Button>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.split__title {
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

.split__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.split__grid {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-8);
    list-style: none;
}

/*
 * A gold rule across the top of each column rather than a card border: the two
 * are a pair of doors, not two items in a list, and boxing them makes the page
 * read as a grid of features again.
 */
.split__col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--s-4);
    padding-block-start: var(--s-5);
    border-block-start: 2px solid var(--gold-400);
}

/*
 * Portrait, and cut with the identity's octagon like every other framed
 * photograph on the site. These are pictures of people at a bench: a 16:9 crop
 * takes the hands out of the frame, which is the only reason the picture is
 * there.
 */
.split__image {
    inline-size: 100%;
    /*
     * Capped rather than full-bleed. At half the container these photographs
     * are the argument's illustration, not its subject — filling the column
     * with a 4:5 crop pushes the button that the section exists for below the
     * fold on a laptop.
     */
    max-inline-size: 232px;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    background: var(--placeholder-warm);
}

.split__name {
    font-family: var(--font-display);
    font-size: var(--fs-h3);
    font-weight: 700;
    line-height: var(--lh-heading);
    color: var(--navy-900);
}

.split__body {
    color: var(--text-muted);
    line-height: var(--lh-body);
    max-inline-size: 46ch;
}

.split__link {
    color: var(--link);
    font-size: var(--fs-sm);
    font-weight: 600;
}

/* Pushed to the foot of its column so both buttons sit on one line however
   long the two paragraphs turn out to be. */
.split__action {
    margin-block-start: auto;
    min-block-size: 48px;
}

@media (min-width: 768px) {
    .split__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--s-8);
    }
}
</style>
