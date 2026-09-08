<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Workspace from '@/Components/admin/Workspace.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import Logo from '@/Components/ui/Logo.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The brand assets and the four identity colours (§10, §19, §23).
 *
 * The palette used to be read-only, and the screen explained at length why.
 * It is editable as of the client's request of 8 September 2026, and the
 * objection it used to make is answered rather than dropped: App\Support\Palette
 * derives the two shades that carry a contrast guarantee by measuring the
 * guarantee, so they hold whatever is picked, and this screen measures and
 * shows the rest at the moment the choice is made.
 */
const props = defineProps({
    assets: { type: Object, default: () => ({}) },
    /** The four chosen colours, keyed by family name. */
    palette: { type: Object, default: () => ({}) },
    /** The four §23 approved, for the badge and the way back. */
    identity: { type: Object, default: () => ({}) },
    /** Every custom property the theme reads, derived server-side. */
    derived: { type: Object, default: () => ({}) },
    /** @type {{pair: string, ratio: number, minimum: number, passes: boolean}[]} */
    contrast: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const busy = ref(null);

const SLOTS = ['logo_light', 'logo_dark', 'favicon', 'og_image'];

/* ------------------------------------------------------------------
   THE PALETTE
   ------------------------------------------------------------------ */

const FAMILIES = ['navy', 'lavender', 'orange', 'gold'];

/** The working copy. Nothing is written until Save. */
const chosen = ref({ ...props.palette });
const saving = ref(false);

/** Only the six-digit form is a colour; anything else is still being typed. */
const isHex = (value) => /^#[0-9a-fA-F]{6}$/.test(value ?? '');

const dirty = computed(() => FAMILIES.some((f) => chosen.value[f] !== props.palette[f]));
const valid = computed(() => FAMILIES.every((f) => isHex(chosen.value[f])));
const isIdentity = computed(() =>
    FAMILIES.every((f) => (chosen.value[f] ?? '').toUpperCase() === props.identity[f])
);

/**
 * The typed field only reaches the model once it spells a colour.
 *
 * Bound directly, every keystroke of "#0B4370" would be committed — and
 * "#0B437" is a value the swatch cannot show and the server would reject. The
 * field keeps whatever is typed; the palette takes it when it is a colour.
 */
function type(family, value) {
    const hex = value.startsWith('#') ? value : `#${value}`;

    if (isHex(hex)) {
        chosen.value[family] = hex.toUpperCase();
    }
}

function save() {
    if (!valid.value) return;

    saving.value = true;

    router.put('/admin/brand/palette', { ...chosen.value }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
}

async function reset() {
    if (!(await confirmDialog({ message: t('admin.confirm_reset') }))) return;

    chosen.value = { ...props.identity };
    save();
}

/**
 * Paint the derived theme onto this document.
 *
 * The colours reach a page through a <style> block in app.blade.php, which is
 * rendered once per full page load. Inertia swaps the page component and
 * leaves the document head alone, so without this a save would change the
 * database, the public site and every later page load — and leave the screen
 * you saved it on looking exactly as it did. "I pressed save and nothing
 * happened" is indistinguishable from a save that failed.
 *
 * The values are the server's own derivation, arriving on the next props.
 * Nothing is computed here: one implementation of the colour maths, in PHP,
 * where the contrast guarantees are enforced.
 */
function paint(tokens) {
    Object.entries(tokens ?? {}).forEach(([property, value]) => {
        document.documentElement.style.setProperty(property, value);
    });
}

/**
 * The shades worth showing, in order.
 *
 * The four channel triplets are dropped: they are the same four colours the
 * pickers above already draw, written the way a stylesheet tints with them,
 * and a swatch of one would be a fifth copy of a colour the screen has shown
 * twice already.
 */
const ramp = computed(() =>
    Object.entries(props.derived).filter(([name]) => !name.endsWith('-rgb'))
);

onMounted(() => paint(props.derived));
watch(() => props.derived, paint);
watch(() => props.palette, (fresh) => {
    chosen.value = { ...fresh };
});

function upload(collection, event) {
    const file = event.target.files?.[0];

    if (!file) return;

    busy.value = collection;

    router.post(
        '/admin/brand',
        { collection, file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                busy.value = null;
                event.target.value = '';
            },
        }
    );
}

async function remove(asset) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;

    router.delete(`/admin/brand/${asset.id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="t('admin.brand')">
        <Workspace>
            <Panel :title="t('admin.brand')">
                <p class="intro">{{ t('admin.brand_hint') }}</p>

                <div class="slots">
                    <div v-for="slot in SLOTS" :key="slot" class="slot">
                        <h3 class="slot__title">{{ t(`admin.brand_${slot}`) }}</h3>
                        <p class="slot__hint">{{ t(`admin.brand_${slot}_hint`) }}</p>

                        <div class="slot__frame" :class="{ 'slot__frame--dark': slot === 'logo_dark' }">
                            <img v-if="assets[slot]" class="slot__img" :src="assets[slot].url" alt="" />

                            <!--
                                What ships with the build, drawn rather than
                                described. Every slot said «using the logo
                                shipped with the system» into an empty box, so
                                the one screen where a client could not see the
                                logo was the logo screen — and there was no way
                                to judge whether replacing it was worth doing.

                                `shipped` makes the component ignore the
                                upload: everywhere else on the site the
                                client's file has to win, and here it must not,
                                or the frame would show the same file twice.

                                The dark frame paints `--navy-900`, so it also
                                shows the mark on whatever dominant colour is
                                currently chosen.
                            -->
                            <Logo
                                v-else-if="slot === 'logo_light'"
                                class="slot__logo"
                                lockup="horizontal"
                                tone="navy"
                                shipped
                            />

                            <Logo
                                v-else-if="slot === 'logo_dark'"
                                class="slot__logo"
                                lockup="horizontal"
                                tone="white"
                                shipped
                            />

                            <!-- The icon set generated from the client's own
                                 artwork by scripts/make-favicons.mjs. -->
                            <img
                                v-else-if="slot === 'favicon'"
                                class="slot__img slot__img--icon"
                                src="/favicon-256x256.png"
                                alt=""
                            />

                            <!-- Nothing ships for this one, and saying so is
                                 the point: an empty share image is why a link
                                 to the site posts with no picture at all. -->
                            <span v-else class="slot__empty">{{ t('admin.brand_og_none') }}</span>
                        </div>

                        <p class="slot__source">
                            {{ assets[slot] ? t('admin.brand_uploaded') : t('admin.brand_default') }}
                        </p>

                        <div class="slot__actions">
                            <label class="btn btn--ghost slot__upload">
                                <span>{{ busy === slot ? t('admin.saving') : t('admin.upload') }}</span>
                                <input type="file" accept="image/*,.ico" @change="(e) => upload(slot, e)" />
                            </label>

                            <button
                                v-if="assets[slot]"
                                class="btn btn--ghost slot__remove"
                                type="button"
                                @click="remove(assets[slot])"
                                    :title="t('admin.delete')"
                                    :aria-label="t('admin.delete')"><NavIcon name="trash" :size="18" :muted="false" /></button>
                        </div>
                    </div>
                </div>
            </Panel>

            <Panel :title="t('admin.brand_palette')">
                <p class="intro">{{ t('admin.brand_palette_hint') }}</p>

                <ul class="palette">
                    <li v-for="family in FAMILIES" :key="family" class="swatch">
                        <!--
                            The native picker, wearing the swatch.

                            `<input type="color">` is the one control every
                            platform already gives its own colour UI to — the
                            eyedropper on desktop, the wheel on a phone — and
                            §7.5 wants no library where the platform answers.
                            It is sized up to be the swatch itself rather than
                            sat beside one, so the thing you press is the
                            thing you are changing.
                        -->
                        <label class="swatch__chip">
                            <input
                                v-model="chosen[family]"
                                class="swatch__picker"
                                type="color"
                                :aria-label="t(`admin.colour_${family}`)"
                            />
                        </label>

                        <input
                            class="swatch__hex latin"
                            type="text"
                            dir="ltr"
                            inputmode="text"
                            maxlength="7"
                            spellcheck="false"
                            :value="chosen[family]"
                            :aria-label="t(`admin.colour_${family}`)"
                            @input="type(family, $event.target.value)"
                        />

                        <!--
                            The approved value rides on the badge's tooltip
                            rather than on a line of its own: it is worth
                            having and not worth a second row, and the way
                            back is a button, not a number to retype.
                        -->
                        <span
                            v-if="chosen[family] !== identity[family]"
                            class="chip chip--warn swatch__badge"
                            :title="`${t('admin.brand_palette_identity')}: ${identity[family]}`"
                        >{{ t('admin.brand_palette_changed') }}</span>
                    </li>
                </ul>

                <div class="actions">
                    <button
                        class="btn btn--primary"
                        type="button"
                        :disabled="!dirty || !valid || saving"
                        @click="save"
                    >{{ saving ? t('admin.saving') : t('admin.brand_palette_save') }}</button>

                    <button
                        v-if="!isIdentity"
                        class="btn btn--ghost"
                        type="button"
                        @click="reset"
                    >{{ t('admin.brand_palette_reset') }}</button>
                </div>
            </Panel>

            <Panel :title="t('admin.brand_derived')">
                <p class="intro">{{ t('admin.brand_derived_hint') }}</p>

                <ul class="ramp">
                    <li v-for="[name, value] in ramp" :key="name" class="ramp__item">
                        <span class="ramp__chip" :style="{ background: value }" aria-hidden="true" />
                        <code class="ramp__name latin">{{ name }}</code>
                        <code class="ramp__hex latin">{{ value }}</code>
                    </li>
                </ul>
            </Panel>

            <Panel :title="t('admin.brand_contrast')">
                <p class="intro">{{ t('admin.brand_contrast_hint') }}</p>

                <ul class="checks">
                    <li v-for="row in contrast" :key="row.pair" class="check">
                        <span class="check__name">{{ t(`admin.contrast_${row.pair}`) }}</span>
                        <code class="check__ratio latin">{{ row.ratio }}:1</code>
                        <span class="check__min latin">{{ t('admin.contrast_minimum') }} {{ row.minimum }}:1</span>
                        <span class="chip" :class="row.passes ? 'chip--ok' : 'chip--warn'">
                            {{ row.passes ? t('admin.contrast_pass') : t('admin.contrast_fail') }}
                        </span>
                    </li>
                </ul>
            </Panel>
        </Workspace>
    </AdminLayout>
</template>

<style scoped>
.intro {
    color: var(--muted);
    font-size: var(--fs-sm);
    margin-block-end: var(--s-5);
    max-inline-size: 75ch;
    line-height: 1.8;
}

.slots {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
}

.slot {
    padding: var(--s-4);
    border-radius: var(--r-md);
    background: var(--paper-alt);
}

.slot__title {
    font-size: var(--fs-h3);
}

.slot__hint {
    margin-block: var(--s-1) var(--s-3);
    font-size: var(--fs-xs);
    color: var(--muted);
}

.slot__frame {
    display: flex;
    align-items: center;
    justify-content: center;
    min-block-size: 120px;
    padding: var(--s-4);
    border-radius: var(--r-sm);
    border: 1px solid var(--hairline);
    background: var(--paper);
}

.slot__frame--dark {
    background: var(--navy-900);
}

.slot__img {
    max-inline-size: 100%;
    max-block-size: 88px;
    object-fit: contain;
}

/* An icon is square and small; shown at 88px it is the size it is actually
   read at in a browser tab's neighbourhood, not a poster of itself. */
.slot__img--icon {
    inline-size: 64px;
    block-size: 64px;
    border-radius: var(--r-sm);
}

/*
 * The shipped mark, at a size worth judging.
 *
 * The component's own width is the §23 minimum — 96px, the smallest the logo
 * may ever be printed — which is a rule about the live site, not a useful
 * preview. Here there is a 120px-tall frame and one thing to look at.
 */
.slot__logo {
    inline-size: min(220px, 100%);
}

.slot__empty {
    font-size: var(--fs-xs);
    color: var(--muted);
    max-inline-size: 34ch;
    line-height: 1.7;
    text-align: center;
}

/* Says which of the two the frame is showing — the client's file, or ours. */
.slot__source {
    margin-block-start: var(--s-2);
    font-size: var(--fs-xs);
    color: var(--muted);
}

.slot__actions {
    display: flex;
    gap: var(--s-2);
    margin-block-start: var(--s-3);
}

.slot__upload {
    position: relative;
    cursor: pointer;
}

.slot__upload input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
}

.slot__remove {
    color: var(--action-600);
}

/*
 * One column until there is room for two.
 *
 * This was `repeat(2, 1fr)` at every width. `1fr` is `minmax(auto, 1fr)`, so
 * the tracks refuse to shrink below their contents — a 40px chip, a name and
 * a hex code come to about 195px — and two of those plus the gap do not fit
 * on a 360px phone. The page scrolled sideways as a result; measured at 437px
 * of document in a 360px viewport.
 */
.palette {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .palette {
        /* minmax(0, …), not 1fr: the same refusal to shrink would otherwise
           come back the moment a colour is given a longer name. */
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.swatch {
    display: flex;
    align-items: center;
    /* The badge drops below rather than squeezing the hex field, which is the
       one thing in the row that cannot lose characters and stay a colour. */
    flex-wrap: wrap;
    gap: var(--s-3);
    padding: var(--s-3);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
    min-inline-size: 0;
}

/*
 * The chip IS the picker.
 *
 * A swatch beside a button is two things to understand; a swatch you press is
 * one. The label carries the border and the corner so the native control can
 * be stripped back to its colour — admin.css does that stripping, beside the
 * rule that excludes a colour input from the text-field styling.
 */
.swatch__chip {
    display: block;
    inline-size: 44px;
    block-size: 44px;
    border-radius: var(--r-sm);
    box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.1);
    overflow: hidden;
    flex: 0 0 auto;
    cursor: pointer;
}

/*
 * NO NAME ON THE SWATCH, and that is the point.
 *
 * Each one carried its colour's name — «الكحلي», «البرتقالي». The moment a
 * client changes the dominant colour to red the label reads "navy" over a red
 * square, and a label that can be wrong is worse than none: the swatch and the
 * hex beside it say what the colour is, truthfully, at any value.
 *
 * The control still HAS a name, on `aria-label`, because a picker a screen
 * reader announces as "colour" four times is unusable. That name describes the
 * role — the dominant colour, the action colour — which is what the slot
 * actually is and cannot go stale.
 *
 * It also fixes the alignment this replaced: a name above and a field below
 * left the swatch centred against two rows and level with neither.
 */
.swatch__badge {
    flex: 0 0 auto;
}

.swatch__hex {
    /* Fills what the swatch and the badge leave, down to the seven
       characters it holds and no further. */
    flex: 1 1 11ch;
    min-inline-size: 8ch;
    /* The chip's own height exactly, so the two sit on one line and share a
       centre rather than being centred against each other. */
    min-block-size: 44px;
    padding-inline: var(--s-3);
    font-size: var(--t-meta);
    text-transform: uppercase;
}

@media (min-width: 900px) {
    .slots {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .palette {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

/*
 * THE DERIVED RAMP
 *
 * Fourteen shades read as a reference table, not as a grid of cards: one line
 * each, the swatch, what it is called in the stylesheets, and its value. The
 * name is the CSS custom property rather than a translated label because that
 * is the thing itself — a developer reading a handover, or the client sending
 * one a screenshot, needs the name the code uses.
 */
.ramp {
    display: grid;
    gap: 2px;
    grid-template-columns: 1fr;
    margin: 0;
    padding: 0;
    list-style: none;
}

.ramp__item {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-2) var(--s-3);
    border-radius: var(--r-sm);
    background: var(--wash-1);
}

.ramp__chip {
    inline-size: 28px;
    block-size: 28px;
    border-radius: var(--r-sm);
    box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.12);
    flex: 0 0 auto;
}

.ramp__name {
    font-size: var(--t-meta);
    min-inline-size: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ramp__hex {
    margin-inline-start: auto;
    font-size: var(--t-meta);
    color: var(--muted);
    flex: 0 0 auto;
}

/*
 * THE LEGIBILITY CHECK
 *
 * The ratio is the point of the row, so it is the largest thing in it and the
 * verdict sits at the end where the eye lands last. A number without a verdict
 * is a number nobody acts on, and a verdict without the number is a judgement
 * the client cannot check.
 */
.checks {
    display: grid;
    gap: var(--s-2);
    margin: 0;
    padding: 0;
    list-style: none;
}

.check {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--s-2) var(--s-3);
    padding: var(--s-3);
    border-radius: var(--r-sm);
    background: var(--wash-1);
}

.check__name {
    font-size: var(--t-body);
    min-inline-size: 0;
    flex: 1 1 14ch;
}

.check__ratio {
    font-size: var(--t-title);
    font-weight: 700;
}

.check__min {
    font-size: var(--t-meta);
    color: var(--muted);
}

.check .chip {
    margin-inline-start: auto;
}

@media (min-width: 900px) {
    .ramp {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
