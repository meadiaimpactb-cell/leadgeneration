<script setup>
import { computed } from 'vue';
import Field from '@/Components/admin/Field.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The side-by-side bilingual editor (§9.1).
 *
 * Both languages are on screen at once rather than behind tabs, because the
 * job is translating — seeing the Arabic while writing the English is the
 * whole point. Each column carries its own `dir`, so Arabic types RTL and
 * English types LTR in the same form.
 *
 * A column left entirely blank means "this record does not exist in that
 * language", which the server honours by deleting the translation row rather
 * than storing empties (§12).
 */
const props = defineProps({
    // { ar: {field: value}, en: {...} }
    modelValue: { type: Object, required: true },
    locales: { type: Array, required: true },
    // { fieldName: 'text' | 'textarea' | 'richtext' }
    fields: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
    /**
     * Field names this record cannot be described without, in whichever
     * language is being written. A wholly blank column still means "not
     * translated" — the marker says what is needed once you start, not that
     * the language is compulsory.
     */
    required: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useTranslation();

const DIRS = { ar: 'rtl', en: 'ltr' };

const entries = computed(() => Object.entries(props.fields));

function update(locale, field, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [locale]: { ...(props.modelValue[locale] ?? {}), [field]: value },
    });
}

function isMissing(locale) {
    const values = props.modelValue[locale] ?? {};
    return !Object.values(values).some((v) => v !== null && v !== undefined && String(v).trim() !== '');
}

/**
 * A field's name in the editor's language.
 *
 * Resolved HERE rather than passed in by each screen. Every caller could pass
 * `:labels` and none of them did, so all four bilingual screens rendered raw
 * column names — `heading`, `cta url`, `meta description` — to an Arabic
 * editor, in breach of §22.5. A prop that must be remembered in four places
 * gets forgotten in four places; a lookup that happens once cannot be.
 *
 * `admin.f.<field>` is the convention. `translate()` returns the key itself
 * when a string is missing, which is the signal to fall back — so an
 * unnamed field degrades to its readable column name instead of printing
 * "admin.f.whatever" at an editor.
 *
 * `labels` still wins where a screen needs to override one word in context.
 */
function label(field) {
    if (props.labels[field]) {
        return props.labels[field];
    }

    const key = `admin.f.${field}`;
    const translated = t(key);

    return translated === key ? field.replace(/_/g, ' ') : translated;
}

/**
 * The hint inside the box: what to type here, and in what form.
 *
 * Same convention and same fallback as the label — `admin.ph.<field>`, and a
 * missing string yields no placeholder rather than the key. It says how to
 * fill the field, never what to say in it: the words on the site are the
 * client's (§22.1), and an example sentence in a placeholder is a sentence
 * somebody eventually ships.
 */
function placeholder(field) {
    const key = `admin.ph.${field}`;
    const translated = t(key);

    return translated === key ? undefined : translated;
}
</script>

<template>
    <div class="bi">
        <div v-for="locale in locales" :key="locale" class="bi__col" :dir="DIRS[locale] ?? 'auto'">
            <header class="bi__head">
                <span class="bi__locale">{{ locale.toUpperCase() }}</span>
                <span v-if="isMissing(locale)" class="bi__missing">
                    {{ t('admin.translation_missing') }}
                </span>
            </header>

            <div class="bi__fields">
                <Field
                    v-for="[field, type] in entries"
                    :key="`${locale}-${field}`"
                    :label="label(field)"
                    :type="type"
                    :placeholder="placeholder(field)"
                    :required="required.includes(field)"
                    :dir="DIRS[locale] ?? 'auto'"
                    :model-value="modelValue[locale]?.[field] ?? ''"
                    :error="errors[`translations.${locale}.${field}`]"
                    @update:model-value="(v) => update(locale, field, v)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.bi {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
}

.bi__col {
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.bi__head {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    margin-block-end: var(--s-4);
}

.bi__locale {
    font-size: var(--fs-xs);
    font-weight: 700;
    color: var(--navy-900);
    padding: var(--s-1) var(--s-2);
    background: var(--navy-100);
    border-radius: var(--r-sm);
}

.bi__missing {
    font-size: var(--fs-xs);
    font-weight: 600;
    color: var(--action-600);
}

.bi__fields {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}

@media (min-width: 1024px) {
    .bi {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
