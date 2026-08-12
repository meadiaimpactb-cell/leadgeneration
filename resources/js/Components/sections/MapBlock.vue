<script setup>
import { computed, ref } from 'vue';
import Container from '@/Components/ui/Container.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/MapBlock — where Amad Craft is.
 *
 * The map is NOT embedded on page load. Google's iframe pulls ~600KB and sets
 * third-party cookies before the visitor has done anything, which would blow
 * the §15.1 page-weight budget on a page whose real job is one form, and would
 * place a tracker on the page ahead of any consent. It loads on click, behind
 * a static, styled placeholder.
 *
 * Everything here is client-managed (§14.1). With no address configured the
 * section renders nothing rather than an empty grey box.
 */
const props = defineProps({
    heading: { type: String, default: null },
    address: { type: String, default: null },
    hours: { type: String, default: null },
    // A full Google Maps embed URL, if the client pasted one.
    embedUrl: { type: String, default: null },
    // Otherwise a plain search query, e.g. "أمد الحرف، حي العقيق، الرياض".
    query: { type: String, default: null },
    // Where "open in Maps" goes.
    linkUrl: { type: String, default: null },
    /**
     * The visit request, if the page wants one.
     *
     * A showroom that receives institutional visitors by appointment needs a
     * way to ask for the appointment; without it the section ends at a map,
     * and a buyer who wanted to come has nothing to press. Empty label means
     * no button — this component never invents a call to action (§22.1).
     */
    visitLabel: { type: String, default: null },
});

const emit = defineEmits(['visit']);

const { t } = useTranslation();
const loaded = ref(false);

const src = computed(() => {
    if (props.embedUrl) return props.embedUrl;
    if (!props.query) return null;

    // The keyless embed endpoint. `hl` keeps the map's own labels in the
    // reader's language.
    return `https://www.google.com/maps?q=${encodeURIComponent(props.query)}&output=embed`;
});

const externalUrl = computed(
    () =>
        props.linkUrl ??
        (props.query ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.query)}` : null)
);

const hasContent = computed(() => Boolean(props.address || src.value));
</script>

<template>
    <section v-if="hasContent" class="section map">
        <Container>
            <div class="map__grid">
                <div class="map__info">
                    <h2 v-if="heading">{{ heading }}</h2>

                    <address v-if="address" class="map__address">{{ address }}</address>

                    <p v-if="hours" class="map__hours">{{ hours }}</p>

                    <!-- The appointment first, the directions second: the
                         visitor who wants to come is worth more to §1 than the
                         one who wants to look at a map. -->
                    <div v-if="visitLabel || externalUrl" class="map__actions">
                        <button
                            v-if="visitLabel"
                            type="button"
                            class="btn btn--cta"
                            @click="emit('visit')"
                        >
                            {{ visitLabel }}
                        </button>

                        <a
                            v-if="externalUrl"
                            class="btn btn--secondary"
                            :href="externalUrl"
                            rel="noopener noreferrer"
                            target="_blank"
                        >
                            {{ t('contact.open_in_maps') }}
                            <span class="visually-hidden">{{ t('common.external_link') }}</span>
                        </a>
                    </div>
                </div>

                <div v-if="src" class="map__frame">
                    <iframe
                        v-if="loaded"
                        class="map__iframe"
                        :src="src"
                        :title="heading ?? t('contact.map')"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    />

                    <!-- Placeholder until the visitor asks for the map. -->
                    <button v-else type="button" class="map__placeholder" @click="loaded = true">
                        <span class="map__pin" aria-hidden="true" />
                        <span class="map__cta">{{ t('contact.load_map') }}</span>
                        <span class="map__note">{{ t('contact.map_privacy') }}</span>
                    </button>
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.map__grid {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
    align-items: center;
}

.map__address {
    margin-block-start: var(--s-4);
    font-style: normal;
    font-size: var(--fs-body-lg);
    color: var(--text);
    max-inline-size: 40ch;
    white-space: pre-line;
}

.map__hours {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.map__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

.map__frame {
    position: relative;
    inline-size: 100%;
    aspect-ratio: 16 / 10;
    border-radius: var(--r-md);
    overflow: hidden;
    background: var(--navy-100);
}

.map__iframe {
    inline-size: 100%;
    block-size: 100%;
    border: 0;
    display: block;
}

.map__placeholder {
    inline-size: 100%;
    block-size: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--s-2);
    padding: var(--s-5);
    text-align: center;
    /* The faint Sadu ground reused as map texture would be a fourth use of
       the signature (§10.1), so this stays a plain tinted panel. */
    background: var(--navy-100);
    transition: background-color var(--dur-micro) var(--ease);
}

.map__placeholder:hover {
    background: #d7e2ec;
}

.map__pin {
    inline-size: 20px;
    block-size: 20px;
    border-radius: 50% 50% 50% 0;
    background: var(--action-600);
    transform: rotate(-45deg);
}

.map__cta {
    font-weight: 600;
    color: var(--navy-900);
}

.map__note {
    font-size: var(--fs-xs);
    color: var(--muted);
    max-inline-size: 34ch;
}

@media (min-width: 1024px) {
    .map__grid {
        grid-template-columns: 0.8fr 1.2fr;
        gap: var(--s-10);
    }
}
</style>
