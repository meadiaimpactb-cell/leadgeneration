<script setup>
import { computed } from 'vue';
import Button from '@/Components/ui/Button.vue';
import { useHashCta } from '@/Composables/useHashCta';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * sections/Hero (§11.1) — two real panes: copy and media.
 *
 * The panes are grid tracks, never an absolutely-positioned image with text
 * laid over it. That distinction is the whole reason this hero reads as
 * designed rather than as a stock overlay: white type over a moving workshop
 * shot cannot be made to pass 4.5:1 at every frame, and a scrim heavy enough
 * to force it turns the craft — the thing being sold — into a grey field.
 * Side by side, the type sits on flat navy and the piece is seen in full.
 *
 * Everything renders from props. If the client has not entered a headline yet,
 * nothing renders rather than a placeholder shipping to production (§0.1,
 * §22.1) — and the figures below come from the impact table, never from here.
 */
const props = defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    secondaryLabel: { type: String, default: null },
    secondaryUrl: { type: String, default: null },
    image: { type: Object, default: null },
    /** Impact figures, when the page has them. Never invented here. */
    trust: { type: Array, default: () => [] },
    /**
     * Several audience actions instead of one primary and one secondary.
     *
     * `[{ label, source }]`. The landing page addresses three audiences from
     * the same hero, and each button records which one pressed it so the lead
     * carries its segment without a third field being put to the visitor.
     *
     * When empty — every other page — the named ctaLabel/secondaryLabel pair
     * below renders exactly as it always has. Nothing about the existing
     * composition changes; this only adds a second way to fill the same row.
     */
    ctas: { type: Array, default: () => [] },
    /**
     * Prose inside the copy pane, under the subtitle.
     *
     * The landing page's approved introduction is four short paragraphs and
     * they belong beside the film, not in a band under it: the two-pane
     * composition IS the design, and moving the copy out of it leaves a
     * full-width column of text that reads as a document.
     */
    body: { type: String, default: null },
    /**
     * The section's raw settings, for the bilingual eyebrow.
     *
     * `settings` is one JSON column shared by both locales, so a label that
     * differs between them carries `eyebrow` and `eyebrow_en` on the same
     * row. Without this the Arabic page printed «B2B · CRAFT SUPPLY» — a
     * Latin phrase, twice, on a right-to-left page written entirely in
     * Arabic.
     *
     * An empty Arabic value means no label AND no spine: both are gated on
     * it, and neither is invented here to fill the gap (§22.1).
     */
    settings: { type: Object, default: () => ({}) },
    /**
     * Set the subtitle at display scale, in gold.
     *
     * The approved hero puts its second line at headline size in gold — on
     * the pages that ship one, that line is the heading's own second line.
     * The landing page keeps its subtitle a separate field, so this gives it
     * the same weight without folding it into the <h1>.
     */
    displaySub: { type: Boolean, default: false },
});

const emit = defineEmits(['segment']);

/**
 * The headline is split on newlines so the client controls where it breaks.
 * Each line is then held on one line: an orphaned word under a 70px display
 * face is the single most visible way this composition fails.
 */
const headingLines = computed(() =>
    (props.heading ?? '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
);

/**
 * A still or a moving file. `.mp4`/`.webm` become a muted looping <video>;
 * anything else stays an <img>. Detected from the URL rather than declared,
 * because the media library gives us a URL and a mime type, not a role.
 */
const mediaUrl = computed(() => props.image?.webp ?? props.image?.url ?? null);

const isVideo = computed(() => /\.(mp4|webm)(\?|$)/i.test(mediaUrl.value ?? ''));

/**
 * Whether the secondary action downloads a file rather than navigating.
 *
 * Read off the URL, not declared: the client sets the link in the admin panel
 * and a document extension is the only reliable signal that the button hands
 * something over. A download that looks like a link is the small dishonesty
 * that makes people stop trusting buttons.
 */
const secondaryDownloads = computed(() =>
    /\.(pdf|docx?|pptx?|zip)(\?|$)/i.test(props.secondaryUrl ?? '')
);

/**
 * A hash CTA scrolls to the form and puts the caret in its first field. The
 * routine now lives in `useHashCta` because SegmentHero needs the same one —
 * see the composable for why an anchor alone is not enough.
 */
const { onHashCta } = useHashCta();
const { text } = useSettingText();

/** The eyebrow in the language being read; the plain prop is the fallback. */
const label = computed(() => text(props.settings, 'eyebrow') ?? props.eyebrow);

/**
 * The audience actions, in the language being read.
 *
 * `settings` is one JSON column shared by both locales, so each action stores
 * `label` and `label_en` on the same row — the shape every other repeatable
 * block on this page already uses. The loop printed `action.label` raw, which
 * left three Arabic buttons standing at the foot of an English hero while the
 * English labels sat in the database unread.
 *
 * Filtered rather than fallen back to: §12 is explicit that an English visitor
 * is never served Arabic, so an action nobody has translated is one button
 * fewer — not one Arabic button among three English ones.
 */
const actions = computed(() =>
    props.ctas
        .map((action) => ({ source: action.source ?? null, label: text(action, 'label') }))
        .filter((action) => action.label)
);

/**
 * The still that stands in until the moving file arrives. For a video this is
 * the native `poster`; for a heavy GIF — which has no poster attribute — it is
 * painted as the pane's own background, so the pane is never an empty
 * rectangle during the download.
 */
const paneGround = computed(() =>
    props.image?.poster && !isVideo.value
        ? { backgroundImage: `url("${props.image.poster}")` }
        : null
);
</script>

<template>
    <!--
        `on-dark` is not decoration: it is what switches .btn--secondary from
        navy text to white. Without it the outlined button rendered navy on
        navy — the border was visible and the label was not.
    -->
    <section class="hero on-dark" :class="{ 'hero--has-media': mediaUrl }">
        <div class="hero__panes">
            <!-- Copy first in the DOM: it is what a screen reader and a
                 crawler should meet first, and in RTL the grid still places
                 it on the reading edge. -->
            <div class="hero__copy">
                <!--
                    The vertical lettering in the seam between the two panes.

                    Drawn only when the client has written an eyebrow for this
                    language: the Arabic page's is deliberately empty, because
                    the approved wording is Latin («B2B · CRAFT SUPPLY») and
                    the Arabic site carries no English. An empty seam is the
                    correct answer there, not a missing one.
                -->
                <span v-if="label" class="hero__spine mono-label" aria-hidden="true">
                    {{ label }}
                </span>

                <div class="hero__inner">
                    <p v-if="label" class="hero__eyebrow">
                        <span class="sadu-mark sadu-weave" aria-hidden="true" />
                        <span class="mono-label mono-label--gold">{{ label }}</span>
                    </p>

                    <h1 v-if="headingLines.length" class="hero__heading">
                        <span
                            v-for="(line, i) in headingLines"
                            :key="i"
                            class="hero__line"
                            :class="{ 'hero__line--accent': i > 0 }"
                        >{{ line }}</span>
                    </h1>

                    <p
                        v-if="subheading"
                        class="hero__sub"
                        :class="{ 'hero__sub--display': displaySub }"
                    >{{ subheading }}</p>

                    <div v-if="body" class="hero__body" v-html="body" />

                    <!--
                        The audience actions, when a page supplies them. Real
                        anchors to a real place on this page, so they work
                        with the keyboard and before the JavaScript has run.
                    -->
                    <div v-if="actions.length" class="hero__actions">
                        <Button
                            v-for="(action, i) in actions"
                            :key="i"
                            :variant="i === 0 ? 'cta-lg' : 'secondary'"
                            href="#contact"
                            @click="emit('segment', $event, action.source)"
                        >
                            {{ action.label }}
                        </Button>
                    </div>

                    <div v-else-if="ctaLabel || secondaryLabel" class="hero__actions">
                        <Button v-if="ctaLabel" variant="cta-lg" :href="ctaUrl" @click="onHashCta($event, ctaUrl)">
                            {{ ctaLabel }}
                        </Button>
                        <Button
                            v-if="secondaryLabel"
                            variant="secondary"
                            :href="secondaryUrl"
                            :external="secondaryDownloads"
                            :download="secondaryDownloads ? '' : undefined"
                        >
                            <template v-if="secondaryDownloads" #icon-start>
                                <svg
                                    class="hero__download"
                                    viewBox="0 0 24 24"
                                    width="17"
                                    height="17"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M12 4v12" />
                                    <path d="M7 12l5 5 5-5" />
                                    <path d="M4 20h16" />
                                </svg>
                            </template>
                            {{ secondaryLabel }}
                        </Button>
                    </div>
                </div>
            </div>

            <!--
                The media pane. Its inner edge is cut into Sadu teeth with
                clip-path — the one place the motif cannot be a background,
                because it is removing the pane rather than drawing on it.
            -->
            <div v-if="mediaUrl" class="hero__media" :style="paneGround">
                <video
                    v-if="isVideo"
                    class="hero__asset"
                    autoplay
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    :poster="image?.poster ?? undefined"
                    :aria-label="image?.alt || undefined"
                >
                    <source :src="mediaUrl" />
                </video>
                <img
                    v-else
                    class="hero__asset"
                    :src="mediaUrl"
                    :srcset="image?.srcset ?? undefined"
                    sizes="100vw"
                    :alt="image?.alt ?? ''"
                    :width="image?.width ?? undefined"
                    :height="image?.height ?? undefined"
                    fetchpriority="high"
                    decoding="async"
                />
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero {
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--navy-900);
    color: var(--text-inverse);
    /* The header is sticky and sits above this, so the pane grid begins
       below it without any negative-margin trickery. */
    min-block-size: 620px;
}

.hero__panes {
    flex: 1;
    min-block-size: 0;
    display: grid;
    /* One column on a phone: the media on top at a portrait crop, the copy
       under it. Two panes side by side below ~900px leave neither enough
       width to be worth having. */
    grid-template-columns: 1fr;
}

.hero__copy {
    position: relative;
    display: flex;
    align-items: center;
    padding-block: var(--s-8);
    padding-inline: var(--margin);
    /* Media first visually on mobile, copy second. */
    order: 2;
}

.hero__inner {
    inline-size: 100%;
    max-inline-size: 720px;
    animation: hero-rise 800ms var(--ease) both;
}

@keyframes hero-rise {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero__inner {
        animation: none;
    }
}

/* The vertical lettering in the seam. Hidden until there is a seam. */
.hero__spine {
    display: none;
}

.hero__eyebrow {
    display: flex;
    align-items: center;
    gap: var(--s-4);
    margin-block-end: var(--s-6);
}

.hero__heading {
    margin: 0;
    font-family: var(--font-display);
    font-size: clamp(2.25rem, 4.1vw, 4.375rem);
    font-weight: 700;
    line-height: 1.08;
    letter-spacing: -0.01em;
    color: #fff;
}

/*
 * Each line is its own block. The client decides where the headline breaks by
 * pressing Enter in the admin panel.
 *
 * `nowrap` is the wide-screen half of that promise only — see the 900px block
 * below. The client chooses the break once, looking at a desktop, and a line
 * tuned for a 720px copy pane does not fit 280px of phone: at that width the
 * display face is still 36px, and nothing in this codebase clips horizontally
 * (there is no `overflow-x: hidden` on html or body, deliberately, because it
 * breaks the sticky header). A line one word too long therefore did not
 * overflow its box — it gave the whole page a horizontal scrollbar.
 *
 * So below the seam the browser is allowed to wrap. The authored break still
 * shows, because each line is its own block; it simply is no longer the only
 * break permitted.
 */
.hero__line {
    display: block;
}

.hero__line--accent {
    color: var(--gold-400);
}

.hero__sub {
    margin-block-start: var(--s-6);
    font-size: 1.1875rem;
    line-height: var(--lh-body);
    color: rgba(255, 255, 255, 0.78);
    max-inline-size: 50ch;
}

/* The same weight the heading's accent line carries on the pages that use
   one — display scale, gold, tight leading. */
.hero__sub--display {
    margin-block-start: var(--s-4);
    font-family: var(--font-display);
    font-size: clamp(1.5rem, 2.4vw, 2.5rem);
    font-weight: 700;
    line-height: 1.18;
    color: var(--gold-400);
    max-inline-size: 22ch;
}

.hero__body {
    margin-block-start: var(--s-5);
    max-inline-size: 52ch;
    color: rgba(255, 255, 255, 0.76);
}

.hero__body :deep(p) {
    margin-block-start: var(--s-3);
    font-size: var(--fs-body);
    line-height: var(--lh-body);
}

/* The two closing lines are the turn the page rests on — «ليست مهمتنا أن
   نبيعك منتجاً» and the line that answers it. Gold, at the same size. */
.hero__body :deep(p:nth-last-child(-n + 2)) {
    color: var(--gold-400);
}

.hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-start: var(--s-8);
}

/* Gold, so the glyph reads as part of the button's border rather than as
   another piece of white text competing with the label. */
.hero__download {
    color: var(--gold-400);
    flex-shrink: 0;
}

/* ---- Media pane ---- */
.hero__media {
    position: relative;
    order: 1;
    overflow: hidden;
    min-block-size: 300px;
    aspect-ratio: 4 / 5;
    /*
     * Shown while the file is in flight, and behind any transparency. The
     * poster set inline overrides the ruling; the ruling is what shows when
     * there is no poster.
     */
    background-color: var(--navy-800);
    background-image: repeating-linear-gradient(
        45deg,
        rgb(var(--gold-rgb) / 0.12) 0 2px,
        transparent 2px 10px
    );
    background-size: cover;
    background-position: center;
}

.hero__asset {
    position: absolute;
    inset: 0;
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
    object-position: center;
}

/*
 * There is no scrim over the media, deliberately.
 *
 * The reference laid a navy gradient across the photograph to blend it toward
 * the seam. Nothing is set over this pane — the copy sits on its own navy
 * track beside it — so the gradient bought no contrast and cost the craft:
 * the pieces being sold were shown through a grey wash. Removing it also
 * retires one of the three physical-direction exceptions this file carried.
 */

@media (min-width: 900px) {
    .hero {
        min-block-size: 820px;
        block-size: calc(100svh - var(--header-h));
    }

    /* From here the copy pane is wide enough to honour the authored break
       literally: the client's Enter is the only place the headline may
       break, and the browser is forbidden from adding its own. */
    .hero__line {
        white-space: nowrap;
    }

    .hero__panes {
        /* Not 50/50: the copy pane carries a fixed measure, the media pane
           takes what is left, and 49/51 is where the headline's longest line
           stops competing with the seam. */
        grid-template-columns: 49% 51%;
    }

    .hero__copy {
        order: 1;
        padding-inline: var(--s-9) var(--seam-gutter);
    }

    .hero__media {
        order: 2;
        aspect-ratio: auto;
        min-block-size: 0;

        /*
         * The Sadu tooth edge — 24 steps of 4.16%, cut out of the pane's
         * inline-end side. clip-path takes physical coordinates only, so this
         * is the second documented exception; the LTR rule below mirrors
         * every step.
         *
         * It must stay in sync with --sadu-tooth, which --seam-gutter is
         * sized to clear.
         */
        clip-path: polygon(
            0 0,
            calc(100% - 22px) 0, 100% 4.16%, calc(100% - 22px) 8.33%, 100% 12.5%,
            calc(100% - 22px) 16.66%, 100% 20.83%, calc(100% - 22px) 25%, 100% 29.16%,
            calc(100% - 22px) 33.33%, 100% 37.5%, calc(100% - 22px) 41.66%, 100% 45.83%,
            calc(100% - 22px) 50%, 100% 54.16%, calc(100% - 22px) 58.33%, 100% 62.5%,
            calc(100% - 22px) 66.66%, 100% 70.83%, calc(100% - 22px) 75%, 100% 79.16%,
            calc(100% - 22px) 83.33%, 100% 87.5%, calc(100% - 22px) 91.66%, 100% 95.83%,
            calc(100% - 22px) 100%,
            0 100%
        );
    }

    /* Mirrored: the teeth move to the pane's physical left, which is the
       seam in an LTR document. */
    html[dir='ltr'] .hero__media {
        clip-path: polygon(
            100% 0,
            22px 0, 0 4.16%, 22px 8.33%, 0 12.5%,
            22px 16.66%, 0 20.83%, 22px 25%, 0 29.16%,
            22px 33.33%, 0 37.5%, 22px 41.66%, 0 45.83%,
            22px 50%, 0 54.16%, 22px 58.33%, 0 62.5%,
            22px 66.66%, 0 70.83%, 22px 75%, 0 79.16%,
            22px 83.33%, 0 87.5%, 22px 91.66%, 0 95.83%,
            22px 100%,
            100% 100%
        );
    }

    /*
     * Set in the seam, reading bottom-to-top. Pinned to a physical offset
     * rather than a percentage: a percentage moves with the pane width and
     * walks into the headline at some viewport size.
     */
    .hero__spine {
        display: block;
        position: absolute;
        /*
         * `inset-inline-end`, not `left` plus an LTR override putting it
         * back on the right. Those two rules were one logical property
         * written out longhand, and they were the last physical inline
         * offset on the site outside the map crosshair — which is exempt
         * because a map does not flip with the document (§22.6).
         *
         * The rotation stays direction-dependent: `vertical-rl` sets the
         * glyphs the same way in both, and only Arabic wants them turned.
         */
        inset-inline-end: 12px;
        top: 50%;
        transform: translateY(-50%) rotate(180deg);
        writing-mode: vertical-rl;
        font-size: 11px;
        letter-spacing: 0.42em;
        color: rgb(var(--gold-rgb) / 0.5);
        white-space: nowrap;
        pointer-events: none;
    }

    html[dir='ltr'] .hero__spine {
        transform: translateY(-50%);
    }
}
</style>
