<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The lead form builder (§6.1, §9.1).
 *
 * The warning at the top is not decoration. §6.1 sets the form at one field
 * plus an optional line precisely because every extra field costs leads, and
 * leads are the only thing this site is measured on (§1). The screen makes the
 * trade-off visible at the moment someone is about to make it.
 */
const props = defineProps({
    fields: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const DIRS = { ar: 'rtl', en: 'ltr' };

const state = reactive(
    props.fields.map((f) => ({
        id: f.id,
        key: f.key,
        type: f.type,
        isEnabled: f.isEnabled,
        isRequired: f.isRequired,
        isLocked: f.isLocked,
        maxLength: f.maxLength,
        labels: JSON.parse(JSON.stringify(f.labels)),
    }))
);

function move(index, delta) {
    const next = index + delta;
    if (next < 0 || next >= state.length) return;
    [state[index], state[next]] = [state[next], state[index]];
}

function save() {
    router.put('/admin/lead-fields', { fields: state }, { preserveScroll: true });
}

const enabledCount = () => state.filter((f) => f.isEnabled).length;
</script>

<template>
    <AdminLayout :title="t('admin.lead_fields')">
        <p class="notice" role="note">{{ t('admin.lead_fields_warning') }}</p>

        <Panel :title="t('admin.lead_fields')" :hint="t('admin.order_hint')">
            <template #actions>
                <span class="count">{{ t('admin.lead_fields_enabled', { count: enabledCount() }) }}</span>
                <button class="btn btn--cta" type="button" @click="save">{{ t('admin.save') }}</button>
            </template>

            <ol class="fields">
                <li v-for="(field, index) in state" :key="field.id" class="field-row">
                    <header class="field-row__head">
                        <span class="field-row__key latin">{{ field.key }}</span>
                        <span class="field-row__type latin">{{ field.type }}</span>

                        <span v-if="field.isLocked" class="chip chip--lock">
                            {{ t('admin.field_locked') }}
                        </span>

                        <div class="field-row__tools">
                            <button
                                class="btn btn--ghost"
                                type="button"
                                :aria-label="t('admin.move_up')"
                                :disabled="index === 0"
                                @click="move(index, -1)"
                            >↑</button>
                            <button
                                class="btn btn--ghost"
                                type="button"
                                :aria-label="t('admin.move_down')"
                                :disabled="index === state.length - 1"
                                @click="move(index, 1)"
                            >↓</button>
                        </div>
                    </header>

                    <div class="field-row__switches">
                        <Field
                            v-model="field.isEnabled"
                            :label="t('admin.field_enabled')"
                            type="checkbox"
                            :disabled="field.isLocked"
                        />
                        <Field
                            v-model="field.isRequired"
                            :label="t('admin.field_required')"
                            type="checkbox"
                            :disabled="field.isLocked"
                        />
                        <Field
                            v-model="field.maxLength"
                            :label="t('admin.field_max_length')"
                            type="number"
                        />
                    </div>

                    <div class="field-row__locales">
                        <div
                            v-for="locale in locales"
                            :key="locale"
                            class="locale"
                            :dir="DIRS[locale] ?? 'auto'"
                        >
                            <span class="locale__tag">{{ locale.toUpperCase() }}</span>
                            <Field
                                v-model="field.labels[locale].label"
                                :label="t('admin.field_label')"
                                :dir="DIRS[locale]"
                            />
                            <Field
                                v-model="field.labels[locale].placeholder"
                                :label="t('admin.field_placeholder')"
                                :dir="DIRS[locale]"
                            />
                            <Field
                                v-model="field.labels[locale].help"
                                :label="t('admin.field_help')"
                                :dir="DIRS[locale]"
                            />
                        </div>
                    </div>
                </li>
            </ol>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.notice {
    margin-block-end: var(--s-5);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--action-600);
    font-weight: 600;
    max-inline-size: 80ch;
}

.count {
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

.fields {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}

.field-row {
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.field-row__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
    margin-block-end: var(--s-4);
}

.field-row__key {
    font-weight: 700;
    color: var(--navy-900);
}

.field-row__type {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.field-row__tools {
    display: flex;
    gap: var(--s-1);
    margin-inline-start: auto;
}

.field-row__tools .btn {
    min-inline-size: 44px;
    min-block-size: 44px;
    padding-inline: var(--s-2);
}

.field-row__switches {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
    margin-block-end: var(--s-4);
}

.field-row__locales {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

.locale {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
    padding: var(--s-3);
    border-radius: var(--r-sm);
    background: var(--paper);
}

.locale__tag {
    align-self: flex-start;
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    background: var(--navy-100);
    font-size: var(--fs-xs);
    font-weight: 700;
}

.chip {
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    font-size: var(--fs-xs);
    font-weight: 600;
    background: var(--navy-100);
    color: var(--navy-900);
}

.chip--lock {
    background: var(--gold-100);
    color: var(--action-600);
}

@media (min-width: 640px) {
    .field-row__switches {
        grid-template-columns: repeat(3, 1fr);
        align-items: end;
    }
}

@media (min-width: 1024px) {
    .field-row__locales {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
