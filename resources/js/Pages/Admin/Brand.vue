<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Workspace from '@/Components/admin/Workspace.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The brand assets the client owns (§10, §19).
 *
 * The palette below the uploads is shown read-only on purpose, and the screen
 * says why. Every contrast guarantee in §10.8 is calculated against those four
 * exact values, so a colour picker here would let one save turn a compliant
 * site into an inaccessible one with nothing to warn the person who did it.
 */
defineProps({
    assets: { type: Object, default: () => ({}) },
    palette: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const busy = ref(null);

const SLOTS = ['logo_light', 'logo_dark', 'favicon', 'og_image'];

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

function remove(asset) {
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
                            <span v-else class="slot__empty">{{ t('admin.brand_default') }}</span>
                        </div>

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
                            >
                                {{ t('admin.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Panel>

            <Panel :title="t('admin.brand_palette')">
                <p class="intro">{{ t('admin.brand_palette_hint') }}</p>

                <ul class="palette">
                    <li v-for="colour in palette" :key="colour.hex" class="swatch">
                        <span class="swatch__chip" :style="{ background: colour.hex }" aria-hidden="true" />
                        <span class="swatch__name">{{ t(`admin.colour_${colour.name}`) }}</span>
                        <code class="swatch__hex latin">{{ colour.hex }}</code>
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

.slot__empty {
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

.palette {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: repeat(2, 1fr);
}

.swatch {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-3);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.swatch__chip {
    inline-size: 40px;
    block-size: 40px;
    border-radius: var(--r-sm);
    box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.1);
    flex: 0 0 auto;
}

.swatch__name {
    font-weight: 600;
    font-size: var(--fs-sm);
}

.swatch__hex {
    margin-inline-start: auto;
    font-size: var(--fs-xs);
    color: var(--muted);
}

@media (min-width: 900px) {
    .slots {
        grid-template-columns: repeat(2, 1fr);
    }

    .palette {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
