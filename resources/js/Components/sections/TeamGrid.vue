<script setup>
import { usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/TeamGrid — the people, if Amad Craft decides to publish them.
 *
 * Built and switched OFF. The section row exists in the builder with
 * `is_active = false` and no members, so turning it on is a toggle in the
 * panel and nothing more. It ships empty on purpose: a team grid filled with
 * invented names and stock portraits does not read as a placeholder, it reads
 * as a claim about who works here (§22.1).
 *
 * A member with no photograph renders their initial on the warm placeholder
 * ground rather than a grey silhouette — a company that has not photographed
 * its team yet should look like that, not like a database with holes.
 */
defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    /** `[{ name, name_en, role, role_en, image: { url, alt } }]`. */
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
const page = usePage();

/** Both languages travel on each item — `settings` is not per-locale (§12). */
function text(member, field) {
    return page.props.locale === 'en' ? (member[`${field}_en`] ?? null) : (member[field] ?? null);
}

const initial = (member) => (text(member, 'name') ?? '').trim().charAt(0);
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="section team"
        :aria-labelledby="heading ? 'team-heading' : undefined"
    >
        <Container>
            <h2 v-if="heading" id="team-heading" class="h2 reveal">{{ heading }}</h2>
            <p v-if="subheading" class="team__sub reveal">{{ subheading }}</p>

            <ul class="team__grid">
                <li v-for="(member, i) in items" :key="i" class="team__card reveal">
                    <div class="team__frame cut">
                        <img
                            v-if="member.image"
                            class="team__img"
                            :src="member.image.webp ?? member.image.url"
                            :alt="member.image.alt ?? text(member, 'name') ?? ''"
                            loading="lazy"
                            decoding="async"
                        />
                        <span v-else class="team__initial" aria-hidden="true">{{ initial(member) }}</span>
                    </div>

                    <p v-if="text(member, 'name')" class="team__name">{{ text(member, 'name') }}</p>
                    <p v-if="text(member, 'role')" class="team__role">{{ text(member, 'role') }}</p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.team__sub {
    margin-block-start: var(--s-3);
    max-inline-size: 60ch;
    color: var(--text-muted);
}

.team__grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--gutter);
    margin-block-start: var(--s-7);
    list-style: none;
}

.team__frame {
    display: grid;
    place-items: center;
    aspect-ratio: 4 / 5;
    background: var(--placeholder-warm);
    overflow: hidden;
}

.team__img {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
}

.team__initial {
    font-family: var(--font-display);
    font-size: 2.5rem;
    color: var(--navy-900);
    opacity: 0.35;
}

.team__name {
    margin-block-start: var(--s-4);
    font-weight: 600;
    color: var(--navy-900);
}

.team__role {
    margin-block-start: var(--s-1);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 900px) {
    .team__grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
