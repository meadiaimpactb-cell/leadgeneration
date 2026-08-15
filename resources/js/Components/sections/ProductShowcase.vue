<script setup>
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * sections/ProductShowcase (§11.1) — informational only.
 *
 * There is no price, no quantity, no "add to cart" and no "buy" button here,
 * and there must never be (§2.2). The store link, where present, is a plain
 * outbound link for anyone who wants to go there — the site does not steer
 * visitors toward a direct purchase (§4).
 *
 * The category filter and the pager are real links, not click handlers. Three
 * reasons, in order of weight:
 *
 *   · A crawler can follow them, so each category is indexed as its own page
 *     rather than hidden behind a filter it cannot operate (§13).
 *   · They survive a page reload and can be shared — a buyer sending a
 *     colleague "the gifts we liked" sends a URL that opens on gifts.
 *   · They work before the JavaScript bundle has parsed.
 *
 * Inertia intercepts them on the client, so navigation is still a partial
 * update rather than a full page load.
 */
defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    items: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    activeCategory: { type: String, default: null },
    allUrl: { type: String, default: null },
    totalCount: { type: Number, default: 0 },
    pagination: { type: Object, default: null },
    storeLabel: { type: String, default: null },
});

const { t } = useTranslation();
const { number } = useFormat();
const { root } = useReveal();
</script>

<template>
    <section ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>
            <p v-if="subheading" class="showcase__sub reveal">{{ subheading }}</p>

            <nav
                v-if="categories.length"
                class="filters reveal"
                :aria-label="t('products.filter_by_category')"
            >
                <Link
                    v-if="allUrl"
                    class="chip"
                    :class="{ 'is-active': activeCategory === null }"
                    :href="allUrl"
                    :aria-current="activeCategory === null ? 'page' : undefined"
                    preserve-scroll
                >
                    {{ t('common.view_all') }}
                    <span class="chip__count">{{ number(totalCount) }}</span>
                </Link>

                <Link
                    v-for="cat in categories"
                    :key="cat.id"
                    class="chip"
                    :class="{ 'is-active': activeCategory === cat.slug }"
                    :href="cat.url"
                    :aria-current="activeCategory === cat.slug ? 'page' : undefined"
                    preserve-scroll
                >
                    {{ cat.name }}
                    <span class="chip__count">{{ number(cat.count) }}</span>
                </Link>
            </nav>

            <p v-if="!items.length" class="empty">{{ t('products.empty') }}</p>

            <ul v-else class="products">
                <li v-for="product in items" :key="product.id" class="product reveal">
                    <div class="product__frame">
                        <img
                            v-if="product.image"
                            class="product__image"
                            :src="product.image.webp ?? product.image.url"
                                :srcset="product.image.srcset ?? undefined"
                                sizes="(min-width: 900px) 25vw, 50vw"
                            :alt="product.image.alt ?? product.name"
                            :width="product.image.width ?? undefined"
                            :height="product.image.height ?? undefined"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>

                    <p v-if="product.categoryName" class="product__category">
                        {{ product.categoryName }}
                    </p>
                    <h3 class="product__name">{{ product.name }}</h3>
                    <p v-if="product.craftTechnique" class="product__craft">
                        {{ product.craftTechnique }}
                    </p>
                    <p v-if="product.description" class="product__desc">{{ product.description }}</p>

                    <a
                        v-if="product.storeUrl && storeLabel"
                        class="product__store link-weave"
                        :href="product.storeUrl"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        {{ storeLabel }}
                        <span class="visually-hidden">{{ t('common.external_link') }}</span>
                    </a>
                </li>
            </ul>

            <nav v-if="pagination && pagination.last > 1" class="pager" :aria-label="t('products.pagination')">
                <Link
                    v-if="pagination.prev"
                    class="pager__step"
                    :href="pagination.prev"
                    rel="prev"
                    preserve-scroll
                >{{ t('common.previous') }}</Link>
                <span v-else class="pager__step is-disabled" aria-hidden="true">
                    {{ t('common.previous') }}
                </span>

                <span class="pager__position">
                    {{ t('products.page_of', { current: number(pagination.current), last: number(pagination.last) }) }}
                </span>

                <Link
                    v-if="pagination.next"
                    class="pager__step"
                    :href="pagination.next"
                    rel="next"
                    preserve-scroll
                >{{ t('common.next') }}</Link>
                <span v-else class="pager__step is-disabled" aria-hidden="true">
                    {{ t('common.next') }}
                </span>
            </nav>
        </Container>
    </section>
</template>

<style scoped>
.showcase__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-block-start: var(--s-6);
}

/* --r-pill is permitted for filters only (§10.4). */
.chip {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    padding: var(--s-2) var(--s-4);
    min-block-size: 44px;
    border-radius: var(--r-pill);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--navy-900);
    box-shadow: inset 0 0 0 1px var(--hairline);
    transition: background-color var(--dur-micro) var(--ease);
}

.chip:hover {
    background: var(--navy-100);
}

.chip__count {
    font-size: var(--fs-xs);
    font-weight: 500;
    color: var(--text-muted);
    font-variant-numeric: tabular-nums;
}

.chip.is-active {
    background: var(--navy-900);
    color: #fff;
    box-shadow: none;
}

.chip.is-active .chip__count {
    color: rgb(255 255 255 / 0.72);
}

.empty {
    margin-block-start: var(--s-7);
    color: var(--text-muted);
}

.products {
    display: grid;
    gap: var(--s-7) var(--gutter);
    grid-template-columns: repeat(2, 1fr);
    margin-block-start: var(--s-7);
}

/*
 * The frame, rather than object-fit on the image itself. The catalogue mixes
 * cut-outs on white with photographs shot in a room; `cover` cropped the
 * cut-outs into their own empty margin and left some cards showing a corner
 * of a product. `contain` inside a fixed square shows every piece whole and
 * keeps the grid on one baseline.
 */
.product__frame {
    display: flex;
    align-items: center;
    justify-content: center;
    aspect-ratio: 1;
    padding: var(--s-3);
    border-radius: var(--r-md);
    background: var(--paper-alt);
    overflow: hidden;
}

.product__image {
    max-inline-size: 100%;
    max-block-size: 100%;
    inline-size: auto;
    block-size: auto;
    object-fit: contain;
    transition: transform var(--dur-el) var(--ease);
}

.product:hover .product__image {
    transform: scale(1.04);
}

.product__category {
    margin-block-start: var(--s-4);
    font-size: var(--fs-xs);
    font-weight: 600;
    letter-spacing: 0.02em;
    color: var(--text-muted);
}

.product__name {
    margin-block-start: var(--s-1);
    font-size: var(--fs-body);
    font-weight: 700;
    line-height: var(--lh-heading);
}

.product__craft {
    margin-block-start: var(--s-1);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--action-600);
}

.product__desc {
    margin-block-start: var(--s-2);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

.product__store {
    display: inline-block;
    margin-block-start: var(--s-3);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--link);
}

.pager {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--s-4);
    margin-block-start: var(--s-8);
}

.pager__step {
    display: inline-flex;
    align-items: center;
    min-block-size: 44px;
    padding-inline: var(--s-4);
    border-radius: var(--r-sm);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--navy-900);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.pager__step:hover {
    background: var(--navy-100);
}

.pager__step.is-disabled {
    color: var(--text-muted);
    opacity: 0.5;
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.pager__position {
    font-size: var(--fs-sm);
    color: var(--text-muted);
    font-variant-numeric: tabular-nums;
}

@media (min-width: 640px) {
    .products {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .products {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
