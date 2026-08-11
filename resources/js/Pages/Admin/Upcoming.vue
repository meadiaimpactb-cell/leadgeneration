<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * A screen that is agreed but not yet built.
 *
 * It says three things and nothing else: what this screen will do, which
 * phase builds it, and that nothing is missing — because the alternative,
 * an empty page or a dead link, reads as a fault in the panel rather than as
 * work still ahead.
 */
const props = defineProps({
    screen: { type: String, required: true },
    phase: { type: Number, required: true },
});

const { t } = useTranslation();
const { number } = useFormat();

const title = computed(() => t(`admin.upcoming.${props.screen}_title`));
const body = computed(() => t(`admin.upcoming.${props.screen}_body`));
</script>

<template>
    <AdminLayout :title="title">
        <Panel :title="title">
            <template #actions>
                <span class="phase">{{ t('admin.upcoming.phase', { n: number(phase) }) }}</span>
            </template>

            <p class="body">{{ body }}</p>

            <p class="note">{{ t('admin.upcoming.note') }}</p>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.phase {
    display: inline-flex;
    align-items: center;
    padding: var(--s-1) var(--s-3);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: #7a4a00;
    font-size: var(--fs-xs);
    font-weight: 700;
    white-space: nowrap;
}

.body {
    font-size: var(--fs-body-lg);
    line-height: var(--lh-body);
    color: var(--navy-900);
    max-inline-size: 62ch;
}

.note {
    margin-block-start: var(--s-5);
    padding-block-start: var(--s-4);
    border-block-start: var(--border-hairline);
    font-size: var(--fs-sm);
    color: var(--text-muted);
    max-inline-size: 62ch;
}
</style>
