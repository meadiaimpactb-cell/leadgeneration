<script setup>
/**
 * A titled card in the admin panel. Purely presentational.
 */
defineProps({
    title: { type: String, default: null },
    hint: { type: String, default: null },
});
</script>

<template>
    <section class="panel">
        <header v-if="title || $slots.actions" class="panel__head">
            <div>
                <h2 v-if="title" class="panel__title">{{ title }}</h2>
                <p v-if="hint" class="panel__hint">{{ hint }}</p>
            </div>
            <div v-if="$slots.actions" class="panel__actions">
                <slot name="actions" />
            </div>
        </header>

        <div class="panel__body">
            <slot />
        </div>
    </section>
</template>

<style scoped>
.panel {
    background: var(--paper);
    border-radius: var(--r-md);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.panel + .panel {
    margin-block-start: var(--s-5);
}

.panel__head {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    align-items: center;
    justify-content: space-between;
    padding: var(--s-4) var(--s-5);
    border-block-end: 1px solid var(--hairline);
}

.panel__title {
    font-size: var(--fs-h3);
}

.panel__hint {
    margin-block-start: var(--s-1);
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.panel__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-inline-start: auto;
}

.panel__body {
    padding: var(--s-5);
}
</style>
