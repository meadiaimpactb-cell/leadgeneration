<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * forms/PhoneField — an international number, stored the way a phone is dialled.
 *
 * Built on `intl-tel-input` rather than by hand, and the reason is the part
 * that cannot be hand-rolled: deciding whether +966 51 234 5678 is a real
 * Saudi mobile means carrying Google's libphonenumber metadata for every
 * country, and that data changes. A country list and a flag sprite are easy;
 * knowing that a Kuwaiti mobile is eight digits and a German one is not is
 * not. The library is loaded WITH its utils bundle so the check is the real
 * one, not a length guess.
 *
 * What it emits is E.164 — `+966512345678`, no spaces, no local `0`. A number
 * kept as typed is a number the sales team has to retype before they can dial
 * it, and «0512345678» means nothing to a WhatsApp link.
 *
 * Client-side only: it touches `document` on init, and this site renders on
 * the server first (§7.2). Until it mounts, the plain input underneath is a
 * working phone field — so a visitor with JavaScript off still reaches us,
 * and the server validates the value either way.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, required: true },
    name: { type: String, default: 'phone' },
    placeholder: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    describedBy: { type: String, default: null },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'validity']);

const { t } = useTranslation();

const input = ref(null);
const instance = ref(null);
const page = usePage();

/**
 * Where to start the country list.
 *
 * Saudi Arabia unless the browser says otherwise, because most visitors to
 * this site are local (§3) and a default that is right most of the time beats
 * a lookup that costs a request. `ipinfo`-style geo services are deliberately
 * not used: a third-party call on every page load to save one tap is a
 * privacy cost the brief's §6 posture does not justify.
 */
function initialCountry() {
    const locales = [page.props.locale, ...(navigator?.languages ?? [navigator?.language])].filter(Boolean);

    for (const tag of locales) {
        // "en-GB" → GB, "ar-SA" → SA. A bare "ar" or "en" says nothing about
        // country, so it is skipped rather than guessed at.
        const region = String(tag).split('-')[1];

        if (region && /^[A-Za-z]{2}$/.test(region)) return region.toLowerCase();
    }

    return 'sa';
}

onMounted(async () => {
    /*
     * Both loaded on mount, and only here.
     *
     * The library and its stylesheet are ~100KB with the validation metadata
     * — real weight against §15.1's 180KB initial-JS budget, and pages
     * without a phone field must not pay it. A dynamic import puts them in
     * their own chunk that arrives when a form does, and keeps the whole
     * thing out of the server render, which has no `document` to attach to.
     */
    const [{ default: intlTelInput }] = await Promise.all([
        import('intl-tel-input/intlTelInputWithUtils'),
        import('intl-tel-input/styles'),
    ]);

    instance.value = intlTelInput(input.value, {
        initialCountry: initialCountry(),
        // Saudi first, then the countries this site is actually written for
        // (§3), then everyone else.
        countryOrder: ['sa', 'ae', 'kw', 'qa', 'bh', 'om', 'eg', 'jo', 'gb', 'us'],
        countrySearch: true,
        formatAsYouType: true,
        // The list is drawn inside the form rather than pinned to <body>, so
        // it inherits the page's direction and cannot be left behind when the
        // section scrolls.
        dropdownContainer: null,
        i18n: page.props.locale === 'ar' ? await arabicLabels() : undefined,
        nationalMode: false,
        strictMode: true,
    });

    if (props.modelValue) instance.value.setNumber(props.modelValue);

    input.value.addEventListener('input', publish);
    input.value.addEventListener('countrychange', publish);
});

onBeforeUnmount(() => {
    input.value?.removeEventListener('input', publish);
    input.value?.removeEventListener('countrychange', publish);
    instance.value?.destroy();
});

/** The country names in Arabic, from the library's own locale files. */
async function arabicLabels() {
    try {
        const { default: ar } = await import('intl-tel-input/locale/ar');

        return ar;
    } catch {
        // English names are a worse experience than Arabic ones and a far
        // better one than an empty list.
        return undefined;
    }
}

/**
 * Publish E.164 upward, and say whether it is a real number.
 *
 * The parent stores what this emits — never what is on screen. The two differ
 * on purpose: the field shows «051 234 5678» because that is how a Saudi
 * reads their own number, and the database gets `+966512345678` because that
 * is what a dialler needs.
 */
function publish() {
    if (!instance.value) return;

    const typed = input.value.value.trim();

    if (typed === '') {
        emit('update:modelValue', '');
        emit('validity', { empty: true, valid: !props.required, message: null });

        return;
    }

    const valid = instance.value.isValidNumber();

    emit('update:modelValue', valid ? instance.value.getNumber() : typed);
    emit('validity', {
        empty: false,
        valid,
        message: valid ? null : t('leads.phone_invalid'),
    });
}

// Kept in step when the parent clears the form after a successful send.
watch(
    () => props.modelValue,
    (value) => {
        if (!instance.value) return;
        if (value === input.value.value) return;

        if (value === '') {
            input.value.value = '';

            return;
        }

        nextTick(() => instance.value.setNumber(value));
    }
);
</script>

<template>
    <input
        :id="id"
        ref="input"
        type="tel"
        class="lead-input"
        :name="name"
        dir="ltr"
        inputmode="tel"
        autocomplete="tel"
        :placeholder="placeholder ?? ''"
        :aria-invalid="invalid ? 'true' : undefined"
        :aria-describedby="describedBy"
        :required="required"
        :value="modelValue"
    />
</template>

<style>
/*
 * NOT scoped: the library builds its country list outside this component's
 * tree, so a scoped rule would never reach it.
 *
 * Everything below exists to make a third-party widget stop looking like one.
 * The dial-code button borrows the form's own field styling; the dropdown
 * borrows the panel's paper, hairline and radius. Nothing here invents a
 * colour — §10.2's tokens only.
 */
.iti {
    display: block;
    inline-size: 100%;
}

/* The number itself stays left-to-right in an RTL page — a phone number is
   not a sentence — while the flag button sits on the reading edge. */
.iti__tel-input {
    inline-size: 100%;
}

.iti--show-flags .iti__tel-input {
    padding-inline-start: 92px;
}

.iti__country-container {
    inset-inline-start: 0;
    inset-inline-end: auto;
}

.iti__selected-country {
    border-start-start-radius: var(--r-sm);
    border-end-start-radius: var(--r-sm);
    background: transparent;
}

.iti__selected-country:hover,
.iti__selected-country-primary:hover {
    background: rgba(255, 255, 255, 0.06);
}

.iti__dropdown-content {
    border: 1px solid var(--hairline);
    border-radius: var(--r-md);
    background: var(--paper);
    color: var(--ink);
    box-shadow:
        0 1px 2px rgba(0, 37, 70, 0.06),
        0 8px 24px rgba(0, 37, 70, 0.06);
}

.iti__search-input {
    inline-size: 100%;
    padding: var(--s-3);
    border: 0;
    border-block-end: 1px solid var(--hairline);
    border-radius: 0;
    background: var(--paper);
    color: var(--ink);
    font: inherit;
}

.iti__country-list {
    max-block-size: 260px;
}

.iti__country {
    padding: var(--s-2) var(--s-3);
    font-size: var(--fs-sm);
}

.iti__country.iti__highlight,
.iti__country:hover {
    background: var(--gold-100);
}

.iti__dial-code {
    color: var(--muted);
    /* Dial codes are Latin digits and must read as such inside an RTL list. */
    direction: ltr;
    unicode-bidi: isolate;
}

/* On the navy CTA band the list keeps its own light ground — a dropdown that
   inherited the band would be white text on white. */
.on-dark .iti__selected-country {
    color: #fff;
}
</style>
