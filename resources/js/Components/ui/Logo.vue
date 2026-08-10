<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The Amad Craft mark (§23).
 *
 * Two sources, in order:
 *
 *  1. A logo the client uploaded on the brand screen. Theirs, so it wins —
 *     the panel accepting an upload it then had no way to display was the
 *     defect this resolves.
 *  2. The identity file shipped with the build, drawn as a CSS mask in
 *     currentColor so the four approved treatments are a colour, not four
 *     files.
 *
 * An uploaded file is rendered as a plain <img>, not a mask: it carries its
 * own colours, and masking it would flatten a two-colour lockup into one.
 * That is also why the brand screen asks for a light-ground and a dark-ground
 * version rather than one file it tries to recolour.
 */
const props = defineProps({
    lockup: {
        type: String,
        default: 'horizontal',
        validator: (v) => ['horizontal', 'stacked'].includes(v),
    },
    tone: {
        type: String,
        default: 'navy',
        validator: (v) => ['navy', 'lavender', 'black', 'white'].includes(v),
    },
});

const { t } = useTranslation();
const page = usePage();

/**
 * `white` is the treatment used over the navy hero, the footer and the admin
 * sidebar — so it maps to the file the client uploaded *for* dark grounds.
 * Every other tone sits on paper.
 */
const uploaded = computed(() => {
    const brand = page.props.brand ?? {};

    return props.tone === 'white' ? brand.logo_dark : brand.logo_light;
});

const src = computed(() => `/brand/logo-${props.lockup}.svg`);

const colour = computed(
    () =>
        ({
            navy: 'var(--navy-900)',
            lavender: 'var(--lavender-500)',
            black: '#000000',
            white: '#FFFFFF',
        })[props.tone]
);
</script>

<template>
    <img
        v-if="uploaded"
        class="logo logo--file"
        :class="`logo--${lockup}`"
        :src="uploaded"
        :alt="t('common.logo_alt')"
    />

    <!--
        Otherwise the built-in mark, loaded as an <img> mask rather than
        inlined: the file is ~12KB of path data and inlining it on every page
        would spend the HTML budget on something the browser caches once.
    -->
    <span
        v-else
        class="logo"
        :class="`logo--${lockup}`"
        :style="{ '--logo-colour': colour, '--logo-src': `url(${src})` }"
        role="img"
        :aria-label="t('common.logo_alt')"
    />
</template>

<style scoped>
.logo {
    display: block;
    background-color: var(--logo-colour);
    -webkit-mask-image: var(--logo-src);
    mask-image: var(--logo-src);
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-size: contain;
    mask-size: contain;
    -webkit-mask-position: center;
    mask-position: center;
}

/*
 * An uploaded file keeps its own colours and its own proportions. `height:
 * auto` overrides the aspect-ratio below, so a client logo that is not the
 * shape of ours is never squashed to fit it.
 */
.logo--file {
    background: none;
    mask-image: none;
    -webkit-mask-image: none;
    block-size: auto;
    max-block-size: 56px;
    object-fit: contain;
    object-position: center;
}

/* Aspect ratios come from the tight viewBoxes in public/brand. */
.logo--horizontal {
    inline-size: 96px;      /* §23 minimum, mobile */
    aspect-ratio: 750.2 / 221.3;
}

.logo--stacked {
    inline-size: 96px;
    aspect-ratio: 516 / 470.4;
}

.logo--file.logo--horizontal,
.logo--file.logo--stacked {
    aspect-ratio: auto;
}

@media (min-width: 1024px) {
    .logo--horizontal,
    .logo--stacked {
        inline-size: 120px; /* §23 minimum, desktop */
    }
}
</style>
