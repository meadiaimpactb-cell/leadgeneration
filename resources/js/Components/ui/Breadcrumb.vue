<script setup>
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * ui/Breadcrumb (§10.5). The matching BreadcrumbList structured data is
 * emitted by the page (§13).
 */
defineProps({
    items: { type: Array, default: () => [] },
});

const { t } = useTranslation();
</script>

<template>
    <nav v-if="items.length > 1" class="crumbs" :aria-label="t('common.breadcrumb')">
        <Container>
            <ol class="crumbs__list">
                <li v-for="(item, i) in items" :key="i" class="crumbs__item">
                    <Link v-if="item.url" :href="item.url" class="crumbs__link link-weave">
                        {{ item.label }}
                    </Link>
                    <span v-else aria-current="page">{{ item.label }}</span>

                    <!-- A slash, not a chevron: no directional glyph to mirror. -->
                    <span v-if="i < items.length - 1" class="crumbs__sep" aria-hidden="true">/</span>
                </li>
            </ol>
        </Container>
    </nav>
</template>

<style scoped>
.crumbs {
    padding-block: var(--s-5);
    border-block-end: 1px solid var(--hairline);
}

.crumbs__list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-2);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

.crumbs__item {
    display: flex;
    align-items: center;
    gap: var(--s-2);
}

.crumbs__link {
    color: var(--link);
}

.crumbs__sep {
    color: var(--hairline);
}
</style>
