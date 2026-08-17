<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import Container from '@/Components/ui/Container.vue';
import LeadField from '@/Components/forms/LeadField.vue';
import SocialLinks from '@/Components/ui/SocialLinks.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The contact page (§5).
 *
 * The shortest page on the site, and deliberately so: whoever arrives here has
 * already decided to make contact, so the page's whole job is to remove what
 * stands between that decision and a sent message. No hero, no proof, no
 * testimonials — persuasion here would be an argument with someone who has
 * already agreed.
 *
 * It carries no location section. It used to, and the footer's site-wide
 * showroom block was suppressed to make room — one address, one set of hours
 * and one map, stated once. The client's instruction reversed which of the two
 * survives: the footer block appears on every page and must appear on this one
 * as well, so the page-level section is the one that goes.
 *
 * Every value on it — the channels, the WhatsApp opener, the visit prompt — is
 * read from the contact settings the client owns, the same source the footer
 * reads. Nothing about Amad Craft is written in this file.
 */
const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

const { t } = useTranslation();
const inertia = usePage();

const settings = computed(() => inertia.props.settings ?? {});
const locale = computed(() => inertia.props.locale);

const formCopy = computed(() => props.sections.find((s) => s.type === 'contact_block') ?? {});

const email = computed(() => settings.value['contact.email'] ?? null);
const phone = computed(() => settings.value['contact.phone'] ?? null);
const whatsapp = computed(() => settings.value['contact.whatsapp'] ?? null);
const social = computed(() => settings.value['contact.social'] ?? []);

/*
 * The address, the hours and the map settings are read by `SiteFooter`, not
 * here. They were read in both places while this page carried its own location
 * section; now there is one reader, which is what "one source" is supposed to
 * mean in practice.
 */
const visitLabel = computed(() => settings.value[`contact.visit_cta.${locale.value}`] ?? null);
const visitPrompt = computed(() => settings.value[`contact.visit_prompt.${locale.value}`] ?? null);

/**
 * The reply-time commitment.
 *
 * Two conditions, not one: the client must have switched it on AND written
 * what it says. A promise about how fast Amad Craft answers is theirs to make
 * — the template must never carry a default (§22.1).
 */
const responsePromise = computed(() =>
    settings.value['contact.response_promise_enabled'] === true
        ? (settings.value[`contact.response_promise.${locale.value}`] || null)
        : null
);

/**
 * WhatsApp, opened on a sentence rather than on an empty box.
 *
 * `wa.me` wants digits with no punctuation, and the opener is URL-encoded
 * because it is Arabic prose with an ellipsis in it.
 */
const whatsappHref = computed(() => {
    if (!whatsapp.value) return null;

    const digits = String(whatsapp.value).replace(/\D/g, '');
    const opener = settings.value[`contact.whatsapp_message.${locale.value}`] ?? null;

    return opener
        ? `https://wa.me/${digits}?text=${encodeURIComponent(opener)}`
        : `https://wa.me/${digits}`;
});

/**
 * The two channels that stay written values: one to copy, one to dial.
 *
 * Each carries its own glyph. A label alone made the card a list of words on a
 * pale rectangle; the icon is what lets someone find the phone number without
 * reading, which is the whole reason this card sits beside the form.
 */
const MAIL_MARK = 'M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3.5 7.5l8.5 6 8.5-6';
const PHONE_MARK = 'M7 3.5h3l1.5 4-2 1.5a12 12 0 005.5 5.5l1.5-2 4 1.5v3a2 2 0 01-2.2 2A17 17 0 015 5.7 2 2 0 017 3.5z';

const channels = computed(() =>
    [
        email.value && {
            label: t('contact.email'),
            value: email.value,
            href: `mailto:${email.value}`,
            mark: MAIL_MARK,
        },
        phone.value && {
            label: t('contact.phone'),
            value: phone.value,
            href: `tel:${phone.value}`,
            mark: PHONE_MARK,
        },
    ].filter(Boolean)
);

/**
 * A WhatsApp tap is a lead by another route, so it is measured like one.
 *
 * §14.1 puts every conversion through the data layer rather than through a
 * vendor's own snippet, so the client can point GA4 and Meta at it from the
 * panel without a deploy. The link is a real anchor and navigates whether or
 * not this fires.
 */
function trackWhatsapp() {
    if (typeof window === 'undefined') return;

    window.dataLayer = window.dataLayer ?? [];
    window.dataLayer.push({
        event: 'whatsapp_click',
        lead_locale: locale.value,
        lead_source: 'contact_page',
    });
}

/**
 * The visit request lands in the one form, at the field that can carry it.
 *
 * The three required fields cannot say "we would like to come on the 14th",
 * so the button opens the optional message with that sentence as its example.
 * It is a placeholder, never a value — nothing is submitted that the visitor
 * did not type.
 */
const leadForm = ref(null);

function requestVisit() {
    leadForm.value?.openMessage(visitPrompt.value);
}
</script>

<template>
    <!--
        The footer's showroom block stays on this page too.

        It was suppressed here, on the reasoning that a contact page should
        state its address once. The client's instruction is the opposite and it
        governs: the block is the site's single answer to "where are you", and
        a visitor who learns to look for it at the foot of every page must find
        it at the foot of this one. Uniformity beats local tidiness.

        So the duplication is resolved from the other end — this page no longer
        carries a location section of its own. See below.
    -->
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <section class="section intro">
            <Container>
                <h1 v-if="page?.title">{{ page.title }}</h1>
                <p v-if="page?.subtitle" class="intro__sub">{{ page.subtitle }}</p>

                <div class="grid">
                    <!-- The one form (§6.1). Its fields come from the admin
                         panel, so this block never needs editing to change. -->
                    <div class="grid__form">
                        <LeadField
                            ref="leadForm"
                            layout="stacked"
                            :heading="formCopy.heading"
                            :reassurance="formCopy.subheading"
                            :submit-label="formCopy.ctaLabel"
                        />
                    </div>

                    <!--
                        Navy, not paper.

                        This card sat on `--paper-alt` beside a form on paper —
                        two near-whites a few shades apart, which reads as an
                        unfinished box rather than as a second option. Navy is
                        the identity's dominant ground (§10.2) and it is what
                        the footer and the CTA band already use for exactly this
                        job: the alternative to the form, weighted to look like
                        a real alternative.
                    -->
                    <aside
                        v-if="channels.length || whatsappHref || social.length"
                        class="grid__aside on-dark"
                    >
                        <p class="mono-label mono-label--gold">{{ t('contact.direct_eyebrow') }}</p>
                        <h2 class="aside__title">{{ t('contact.direct_channels') }}</h2>

                        <ul v-if="channels.length" class="channels">
                            <li v-for="channel in channels" :key="channel.label" class="channel">
                                <span class="channel__icon" aria-hidden="true">
                                    <svg
                                        viewBox="0 0 24 24"
                                        width="17"
                                        height="17"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path :d="channel.mark" />
                                    </svg>
                                </span>

                                <span class="channel__text">
                                    <span class="channel__label">{{ channel.label }}</span>
                                    <!-- `.latin` isolates the run and sets its
                                         direction: an address and a country
                                         code are LTR whatever the page is. -->
                                    <a class="channel__value latin" :href="channel.href">
                                        {{ channel.value }}
                                    </a>
                                </span>
                            </li>
                        </ul>

                        <!--
                            WhatsApp is an action, not a number to copy down.
                            In this market it is the channel an institutional
                            buyer actually gets answered on, so it is the one
                            control in this card that looks like a button.
                        -->
                        <a
                            v-if="whatsappHref"
                            class="btn btn--cta whatsapp"
                            :href="whatsappHref"
                            rel="noopener noreferrer"
                            target="_blank"
                            @click="trackWhatsapp"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="18"
                                height="18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M3.5 20.5l1.3-4.4A8.2 8.2 0 1120.5 12a8.4 8.4 0 01-12.4 7.2l-4.6 1.3z" />
                                <path d="M9 9.5c0 3 2.5 5.5 5.5 5.5" stroke-linecap="round" />
                            </svg>
                            {{ t('contact.whatsapp') }}
                            <span class="visually-hidden">{{ t('common.external_link') }}</span>
                        </a>

                        <!--
                            The appointment request, moved here from the
                            location section this page no longer carries. It is
                            a channel like the others — "come and see us" beside
                            "write to us" and "call us" — and it is the one that
                            needs the form, which is now next to it rather than
                            a screen away.
                        -->
                        <button
                            v-if="visitLabel"
                            type="button"
                            class="btn btn--secondary visit"
                            @click="requestVisit"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="18"
                                height="18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                                <path d="M3.5 9.5h17M8 3.5v3M16 3.5v3" stroke-linecap="round" />
                            </svg>
                            {{ visitLabel }}
                        </button>

                        <!-- Only when the client has switched it on AND said
                             what it promises. -->
                        <p v-if="responsePromise" class="promise">
                            <svg
                                viewBox="0 0 24 24"
                                width="16"
                                height="16"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3.5 2" />
                            </svg>
                            {{ responsePromise }}
                        </p>

                        <template v-if="social.length">
                            <h2 class="aside__title aside__title--spaced">{{ t('contact.social') }}</h2>
                            <!--
                                Marks, not words. This printed "Snapchat
                                Facebook X Instagram" as four text links while
                                the footer three sections below showed the same
                                four accounts as icons — the same component now
                                draws both.
                            -->
                            <SocialLinks :items="social" tone="dark" :label="t('contact.social')" />
                        </template>
                    </aside>
                </div>
            </Container>
        </section>

        <!--
            No «موقعنا» section here.

            This page used to carry one — heading, address, opening hours and a
            map — directly above the footer's showroom block, which carries the
            same address, the same hours and a second map. Two of everything on
            a page whose whole virtue is being short.

            The footer block is the one that stays, because it is the one that
            appears on every other page. What this section owned and the footer
            does not is the appointment request, and that has moved into the
            contact card beside the form, which is where someone who has
            already decided to get in touch is looking.
        -->
        <SectionRenderer :sections="sections" :skip="['contact_block', 'map']" />
    </PublicLayout>
</template>

<style scoped>
.intro__sub {
    margin-block-start: var(--s-4);
    font-size: var(--fs-body-lg);
    color: var(--text-muted);
    max-inline-size: 56ch;
}

.grid {
    display: grid;
    gap: var(--s-8);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-8);
}

.grid__form {
    max-inline-size: 620px;
}

/*
 * The card is navy with a gold hairline, matching the CTA band and the footer.
 * No radius: this identity's cards are cut, not rounded (DESIGN_SYSTEM), and
 * the 4px the old card carried was the only rounded corner on the page.
 */
.grid__aside {
    padding: var(--s-7) var(--s-6);
    background: var(--navy-900);
    box-shadow: inset 0 0 0 1px rgba(220, 173, 117, 0.28);
    align-self: start;
}

.aside__title {
    margin-block-start: var(--s-3);
    font-family: var(--font-display);
    font-size: 1.375rem;
    color: #fff;
}

.aside__title--spaced {
    margin-block-start: var(--s-7);
    padding-block-start: var(--s-5);
    border-block-start: 1px solid rgba(255, 255, 255, 0.12);
}

.channels {
    list-style: none;
    margin: var(--s-5) 0 0;
    padding: 0;
}

.channel {
    display: flex;
    align-items: center;
    gap: var(--s-4);
    padding-block: var(--s-3);
}

.channel + .channel {
    border-block-start: 1px solid rgba(255, 255, 255, 0.1);
}

/* A gold disc, the same treatment the cards grid gives its glyphs. */
.channel__icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    inline-size: 38px;
    block-size: 38px;
    border-radius: 50%;
    background: rgba(220, 173, 117, 0.14);
    color: var(--gold-400);
}

.channel__text {
    display: flex;
    flex-direction: column;
    min-inline-size: 0;
}

.channel__label {
    font-family: var(--font-mono);
    font-size: 0.625rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.55);
}

/* The link is this element, not the 62px row around it — the icon and the
   label are not part of the anchor. On its line box alone it was about 26px
   of target for the two numbers a buyer is most likely to tap. */
.channel__value {
    display: inline-flex;
    align-items: center;
    min-block-size: 44px;
    color: #fff;
    font-size: 1.0625rem;
    font-weight: 600;
    overflow-wrap: anywhere;
    transition: color var(--dur-micro) var(--ease);
}

.channel__value:hover,
.channel__value:focus-visible {
    color: var(--gold-400);
}

/*
 * WhatsApp is the one control here that carries the action colour: in this
 * market it is the channel an institutional buyer actually gets answered on.
 * The appointment sits under it as an outline, so the pair reads as a primary
 * and an alternative rather than as two equal buttons.
 */
.whatsapp,
.visit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--s-2);
    inline-size: 100%;
    margin-block-start: var(--s-5);
    min-block-size: 48px;
}

.visit {
    margin-block-start: var(--s-3);
}

.promise {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    margin-block-start: var(--s-5);
    color: rgba(255, 255, 255, 0.7);
    font-size: var(--fs-sm);
}

.promise svg {
    flex-shrink: 0;
    color: var(--gold-400);
}

@media (min-width: 1024px) {
    .grid {
        /* Both tracks bounded: the form's inputs and the card's email address
           are each wider than their share when a bare fr is used. */
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.65fr);
        gap: var(--s-10);
    }
}
</style>
