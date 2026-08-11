<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/PartnersLogos (§11.1) — the accreditation strip.
 *
 * A slow horizontal loop rather than a static row: institutional logos are
 * scanned, not read, and motion is what makes a visitor scan them at all.
 * The loop holds the list twice and travels exactly -50%, so copy two lands
 * where copy one started and the restart is invisible.
 *
 * Below five logos it does not move. A track narrower than its viewport
 * cannot loop seamlessly — it would show an empty stretch every cycle — so it
 * falls back to a plain scrollable row.
 *
 * Logos are never mirrored in RTL (§12): only directional icons flip.
 */
const props = defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();

const animated = computed(() => props.items.length >= 5);
</script>

<template>
    <section v-if="items.length" ref="root" class="section partners">
        <Container>
            <div class="partners__top">
                <!--
                    Deliberately NOT .mono-label. That class letterspaces its
                    text and sets it in IBM Plex Mono, and this heading is
                    Arabic content from the database: mono has no Arabic
                    glyphs, and letterspacing Arabic breaks the joins between
                    letters, so "شركاء واعتمادات" rendered as loose
                    disconnected characters. The mono treatment is only ever
                    safe on the Latin strings this file controls.
                -->
                <p v-if="heading" class="partners__heading">{{ heading }}</p>
            </div>

            <div class="partners__viewport marquee" :class="{ 'marquee--static': !animated }">
                <div class="marquee__track partners__track">
                    <ul class="partners__copy">
                        <li v-for="partner in items" :key="partner.id" class="partners__cell">
                            <img
                                v-if="partner.logo"
                                class="partners__logo"
                                :src="partner.logo.url"
                                :alt="partner.name"
                                :width="partner.logo.width ?? undefined"
                                :height="partner.logo.height ?? undefined"
                                loading="lazy"
                                decoding="async"
                            />
                            <span v-else class="partners__name">{{ partner.name }}</span>
                        </li>
                    </ul>

                    <!-- The second copy exists only to make the loop seamless;
                         it is the same list, so it is hidden from assistive
                         technology rather than read out twice. -->
                    <ul v-if="animated" class="partners__copy" aria-hidden="true">
                        <li v-for="partner in items" :key="`echo-${partner.id}`" class="partners__cell">
                            <img
                                v-if="partner.logo"
                                class="partners__logo"
                                :src="partner.logo.url"
                                alt=""
                                loading="lazy"
                                decoding="async"
                            />
                            <span v-else class="partners__name">{{ partner.name }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.partners {
    padding-block: 0 var(--section-y);
}

.partners__top {
    padding-block-start: var(--s-7);
    border-block-start: 1px solid var(--hairline);
}

.partners__heading {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--navy-900);
}

.partners__viewport {
    margin-block-start: var(--s-6);
    border-block: 1px solid var(--hairline-soft);
    --marquee-dur: var(--dur-marquee-logos);
    --marquee-fade: 9%;
}

/* The travel is pinned LTR so switching language does not reverse it. */
.partners__track {
    direction: ltr;
}

.partners__copy {
    display: flex;
    flex-shrink: 0;
}

.partners__cell {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    /* Bigger than the strip started out: at 236 × 112 an institutional
       wordmark shrank to the point where the entity was no longer
       identifiable, which is the only reason the strip exists. */
    inline-size: 320px;
    block-size: 170px;
    padding-inline: var(--s-6);
    border-inline-end: 1px solid var(--hairline-soft);
}

.partners__logo {
    max-block-size: 84px;
    max-inline-size: 100%;
    inline-size: auto;
    object-fit: contain;
    /*
     * Grey at rest so no single partner's brand colour dominates the strip,
     * and full colour on hover or keyboard focus. Focus matters as much as
     * hover here: a keyboard user should get the same acknowledgement.
     */
    filter: grayscale(1);
    opacity: 0.7;
    transition:
        filter var(--dur-el) var(--ease),
        opacity var(--dur-el) var(--ease);
}

.partners__cell:hover .partners__logo,
.partners__cell:focus-within .partners__logo {
    filter: grayscale(0);
    opacity: 1;
}

.partners__name {
    color: var(--text-muted);
    font-size: var(--fs-sm);
    text-align: center;
}
</style>
