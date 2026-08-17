<script setup>
import { Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The bar above a record's form: how you got here, and where you can go next.
 *
 * Every edit screen in the panel opened with a form and nothing else. There
 * was no way back to the list except the browser's own button or the sidebar,
 * no way to see the page you were editing, and — on a page or a segment — no
 * way to reach its sections without navigating the whole tree again.
 *
 * None of that was a missing feature so much as a missing signpost: the routes
 * all existed. An editor who is not a developer does not go looking for a URL,
 * so a route with no control pointing at it is a route that is not there.
 *
 * Three affordances, each optional, each an icon beside its word rather than a
 * word alone — §9.1 asks for a panel usable with no technical help, and the
 * sidebar already proved a glyph is found faster than an Arabic label read
 * letter by letter.
 */
defineProps({
    /** Where "back" goes. Omitted, no back control is drawn. */
    backHref: { type: String, default: null },
    /** Overrides the generic «رجوع» when the destination has a name worth saying. */
    backLabel: { type: String, default: null },
    /** The public URL of the thing being edited. Opens in a new tab. */
    previewHref: { type: String, default: null },
    /** The section builder for this record. */
    sectionsHref: { type: String, default: null },
});

const { t } = useTranslation();
</script>

<template>
    <nav v-if="backHref || previewHref || sectionsHref" class="screennav" :aria-label="t('admin.back')">
        <Link v-if="backHref" :href="backHref" class="screennav__back">
            <NavIcon name="back" :size="18" :muted="false" />
            <span>{{ backLabel ?? t('admin.back') }}</span>
        </Link>

        <div class="screennav__side">
            <!--
                Sections first: on a page or a segment it is the control the
                editor reaches for most, and it is the one that was hardest to
                find as a plain link in a table cell.
            -->
            <Link v-if="sectionsHref" :href="sectionsHref" class="btn btn--secondary screennav__act">
                <NavIcon name="layers" :size="18" :muted="false" />
                <span>{{ t('admin.sections') }}</span>
            </Link>

            <!--
                A real anchor, not a Link: this leaves the panel for the live
                site, and `target="_blank"` keeps the half-finished form the
                editor is standing in.
            -->
            <a
                v-if="previewHref"
                class="btn btn--ghost screennav__act"
                :href="previewHref"
                target="_blank"
                rel="noopener"
            >
                <NavIcon name="eye" :size="18" :muted="false" />
                <span>{{ t('admin.preview') }}</span>
            </a>
        </div>
    </nav>
</template>

<style scoped>
.screennav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
    margin-block-end: var(--s-5);
}

.screennav__back {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    /* 44px of target without a 44px-looking control — the same trade the
       public site's toggles make. */
    min-block-size: 44px;
    padding-inline: var(--s-3);
    border-radius: var(--r-sm);
    color: var(--navy-900);
    font-weight: 600;
    font-size: var(--fs-sm);
    transition: background-color var(--dur-micro) var(--ease);
}

.screennav__back:hover {
    background: var(--navy-100);
}

/* The glyph points at the reading edge in both directions. It is drawn for
   RTL, so the Latin document is the one that flips it. */
html[dir='ltr'] .screennav__back :deep(.ico) {
    transform: scaleX(-1);
}

.screennav__side {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-inline-start: auto;
}

.screennav__act {
    gap: var(--s-2);
    min-block-size: 44px;
}

@media (prefers-reduced-motion: reduce) {
    .screennav__back {
        transition: none;
    }
}
</style>
