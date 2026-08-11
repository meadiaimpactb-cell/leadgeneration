<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * Admin/Languages (§12).
 *
 * The English site's on/off switch, and how much of it is written.
 *
 * The switch is deliberately not a quiet checkbox among other settings: it
 * decides whether a whole language is served, and the consequences — the
 * toggle disappears, /en stops resolving, the English sitemap 404s — are
 * spelled out beside it rather than left to be discovered.
 */
const props = defineProps({
    englishEnabled: { type: Boolean, required: true },
    secondary: { type: String, required: true },
    coverage: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { number } = useFormat();

const form = useForm({ englishEnabled: props.englishEnabled });

function save() {
    form.put('/admin/languages', { preserveScroll: true });
}

/** A content type nobody has created yet is not "0% translated". */
function tone(row) {
    if (row.percent === null) return 'empty';
    if (row.percent === 100) return 'done';
    if (row.percent === 0) return 'none';

    return 'partial';
}

const label = (key) => t(`admin.${key}`);

const overall = computed(() => {
    const counted = props.coverage.filter((r) => r.percent !== null);

    if (!counted.length) return null;

    const total = counted.reduce((sum, r) => sum + r.total, 0);
    const done = counted.reduce((sum, r) => sum + r.translated, 0);

    return total === 0 ? null : Math.round((done / total) * 100);
});
</script>

<template>
    <AdminLayout :title="t('admin.languages')">
        <Panel :title="t('admin.languages_switch')" :hint="t('admin.languages_switch_hint')">
            <label class="switch">
                <input v-model="form.englishEnabled" type="checkbox" @change="save" />
                <span class="switch__label">{{ t('admin.languages_enable_english') }}</span>
            </label>

            <ul class="effects">
                <li>{{ t('admin.languages_effect_toggle') }}</li>
                <li>{{ t('admin.languages_effect_routes') }}</li>
                <li>{{ t('admin.languages_effect_sitemap') }}</li>
            </ul>

            <p class="keep">{{ t('admin.languages_effect_keep') }}</p>
        </Panel>

        <Panel :title="t('admin.languages_coverage')" :hint="t('admin.languages_coverage_hint')">
            <p v-if="overall !== null" class="overall">
                {{ t('admin.languages_overall') }}
                <strong class="tabular">{{ number(overall) }}%</strong>
            </p>

            <ul class="rows">
                <li v-for="row in coverage" :key="row.key" class="row" :class="`row--${tone(row)}`">
                    <span class="row__name">{{ label(row.key) }}</span>

                    <span class="row__bar" aria-hidden="true">
                        <span class="row__fill" :style="{ inlineSize: `${row.percent ?? 0}%` }" />
                    </span>

                    <span class="row__count tabular">
                        {{ number(row.translated) }} / {{ number(row.total) }}
                    </span>

                    <span class="row__percent tabular">
                        {{ row.percent === null ? '—' : `${number(row.percent)}%` }}
                    </span>
                </li>
            </ul>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.switch {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    min-block-size: 44px;
    font-weight: 600;
    cursor: pointer;
}

.effects {
    margin-block-start: var(--s-4);
    padding-inline-start: var(--s-5);
    list-style: disc;
    color: var(--text-muted);
    font-size: var(--fs-sm);
    line-height: var(--lh-body);
}

.keep {
    margin-block-start: var(--s-4);
    padding-block-start: var(--s-4);
    border-block-start: var(--border-hairline);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.overall {
    margin-block-end: var(--s-5);
    font-size: var(--fs-body-lg);
}

.rows {
    display: grid;
    gap: var(--s-2);
    list-style: none;
}

.row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 3rem 4rem;
    align-items: center;
    gap: var(--s-3);
    padding-block: var(--s-2);
}

.row__name {
    font-weight: 600;
}

/* The bar drops below the label on a narrow panel rather than crushing the
   two counts, which are the part that has to stay readable. */
.row__bar {
    display: none;
    block-size: 6px;
    border-radius: var(--r-pill);
    background: var(--navy-100);
    overflow: hidden;
}

.row__fill {
    display: block;
    block-size: 100%;
    background: var(--navy-900);
}

.row--done .row__fill {
    background: var(--success);
}

.row--none .row__fill,
.row--partial .row__fill {
    background: var(--action-600);
}

.row__count,
.row__percent {
    color: var(--text-muted);
    font-size: var(--fs-sm);
    text-align: end;
}

.row--empty .row__name {
    color: var(--text-muted);
}

@media (min-width: 768px) {
    .row {
        grid-template-columns: minmax(0, 14rem) minmax(0, 1fr) 4.5rem 3.5rem;
    }

    .row__bar {
        display: block;
    }
}
</style>
