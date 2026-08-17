<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * The page as it will appear in a Google result, while it is being written.
 *
 * WHY THIS EARNS ITS SPACE
 *
 * The title and description fields are two boxes with no context, and what
 * they produce is the only thing a buyer sees before deciding whether to
 * click. Editors write them blind, discover the truncation months later, and
 * cannot picture the "— أمد الحرف" the site appends. Showing the result while
 * they type turns two abstract fields into one concrete thing.
 *
 * It is a preview, not a promise: Google rewrites titles and descriptions when
 * it judges its own version more useful, and the widths below are typical
 * rather than guaranteed. The panel says so rather than implying precision it
 * does not have.
 *
 * Purely presentational — it reads the same fallbacks MetaBuilder applies on
 * the server (an empty meta title falls back to the page title) so that what
 * is drawn here and what is served are the same rule, not two copies of it.
 */
const props = defineProps({
    locale: { type: String, required: true },
    /** The page's own title, used when no meta title is written. */
    title: { type: String, default: '' },
    metaTitle: { type: String, default: '' },
    metaDescription: { type: String, default: '' },
    slug: { type: String, default: '' },
    siteName: { type: String, default: '' },
    baseUrl: { type: String, default: '' },
    indexable: { type: Boolean, default: true },
});

const { t } = useTranslation();
const { number } = useFormat();

/** Typical truncation points. Not rules — see the note in the template. */
const TITLE_LIMIT = 60;
const DESCRIPTION_LIMIT = 155;

/** True when the title shown comes from the page title, not the meta field. */
const borrowedTitle = computed(() => !props.metaTitle?.trim() && Boolean(props.title?.trim()));

const headline = computed(() => {
    const own = (props.metaTitle?.trim() || props.title?.trim() || '');

    if (!own) return '';

    // Exactly what MetaBuilder builds: "Page — Site".
    return props.siteName ? `${own} — ${props.siteName}` : own;
});

const url = computed(() => {
    const slug = (props.slug || '').replace(/^\/+|\/+$/g, '');

    return [props.baseUrl, props.locale, slug === 'home' ? '' : slug]
        .filter(Boolean)
        .join('/');
});

const description = computed(() => props.metaDescription?.trim() ?? '');

function state(length, limit) {
    if (length === 0) return 'empty';
    if (length > limit) return 'over';
    // Very short is not an error, but it is a wasted opportunity worth naming.
    if (length < limit * 0.5) return 'short';

    return 'good';
}

const titleState = computed(() => state(headline.value.length, TITLE_LIMIT));
const descriptionState = computed(() => state(description.value.length, DESCRIPTION_LIMIT));
</script>

<template>
    <div class="preview">
        <p class="preview__label">{{ t('admin.snippet.label', { locale }) }}</p>

        <!-- Deliberately not styled to imitate Google's exact chrome: it is a
             sketch of the result, and a pixel-perfect copy would suggest a
             precision that does not exist. -->
        <div class="card" :dir="locale === 'en' ? 'ltr' : 'rtl'">
            <span class="card__url latin" dir="ltr">{{ url }}</span>

            <p v-if="headline" class="card__title">{{ headline }}</p>
            <p v-else class="card__title card__title--empty">{{ t('admin.snippet.no_title') }}</p>

            <p v-if="description" class="card__desc">{{ description }}</p>
            <p v-else class="card__desc card__desc--empty">{{ t('admin.snippet.no_description') }}</p>
        </div>

        <ul class="notes">
            <li v-if="!indexable" class="note note--warn">
                {{ t('admin.snippet.not_indexable') }}
            </li>

            <li v-if="borrowedTitle" class="note">
                {{ t('admin.snippet.borrowed_title') }}
            </li>

            <li class="note" :class="`is-${titleState}`">
                {{ t('admin.snippet.title_count', {
                    count: number(headline.length),
                    limit: number(TITLE_LIMIT),
                }) }}
                <span class="note__advice">{{ t(`admin.snippet.title_${titleState}`) }}</span>
            </li>

            <li class="note" :class="`is-${descriptionState}`">
                {{ t('admin.snippet.description_count', {
                    count: number(description.length),
                    limit: number(DESCRIPTION_LIMIT),
                }) }}
                <span class="note__advice">{{ t(`admin.snippet.description_${descriptionState}`) }}</span>
            </li>
        </ul>

        <p class="disclaimer">{{ t('admin.snippet.disclaimer') }}</p>
    </div>
</template>

<style scoped>
.preview {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
}

.preview__label {
    font-weight: 700;
    font-size: var(--t-meta);
    color: var(--navy-900);
}

.card {
    padding: var(--s-4);
    background: #fff;
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.card__url {
    display: block;
    margin-block-end: var(--s-1);
    font-size: var(--t-meta);
    color: var(--muted);
    overflow-wrap: anywhere;
}

/* The blue is Google's link colour, not a brand colour — this box is a
   simulation of somewhere else, and the identity tokens do not apply. */
.card__title {
    margin: 0 0 var(--s-1);
    font-size: 1.15rem;
    line-height: 1.35;
    color: #1a0dab;
    overflow-wrap: anywhere;
}

.card__desc {
    margin: 0;
    font-size: var(--t-meta);
    line-height: 1.6;
    color: #4d5156;
    overflow-wrap: anywhere;
}

.card__title--empty,
.card__desc--empty {
    color: var(--muted);
    font-style: italic;
}

.notes {
    display: grid;
    gap: var(--s-2);
    margin: 0;
    padding: 0;
    list-style: none;
}

.note {
    font-size: var(--t-meta);
    line-height: var(--lh-body);
    color: var(--muted);
}

.note__advice {
    display: block;
}

.note.is-over { color: #C0392B; }
.note.is-good { color: #1E7A4B; }
.note.is-empty,
.note.is-short { color: #B8860B; }

.note--warn {
    padding: var(--s-2) var(--s-3);
    background: #FBF0DA;
    color: #7A5B12;
}

.disclaimer {
    font-size: var(--t-meta);
    color: var(--muted);
    line-height: var(--lh-body);
}
</style>
