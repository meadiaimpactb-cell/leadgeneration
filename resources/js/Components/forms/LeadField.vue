<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import Button from '@/Components/ui/Button.vue';
import PhoneField from '@/Components/forms/PhoneField.vue';
import ResultDialog from '@/Components/ui/ResultDialog.vue';

/**
 * forms/LeadField — the single most important control on the site (§10.6).
 *
 * The site ships in the §6.1 configuration: one required field accepting an
 * email OR a mobile number, auto-detected with no type selector, plus one
 * optional collapsed message. No name.
 *
 * The fields themselves come from the server, so Amad Craft can add, reorder
 * or rename one from the admin panel without a developer. This component only
 * knows how to render whatever it is given — it hard-codes no field.
 *
 * Heading and reassurance are props fed from the database; this component
 * never contains marketing copy (§0.1).
 */
const props = defineProps({
    heading: { type: String, default: null },
    reassurance: { type: String, default: null },
    submitLabel: { type: String, default: null },
    // Which sector page this was submitted from — a hint for sales, not a claim.
    sectorHint: { type: String, default: null },
    campaign: { type: String, default: null },
    /**
     * Which of a page's audiences this visitor is.
     *
     * /training addresses two people who want opposite things — an artisan
     * asking to join a track and an institution asking to sponsor one — and
     * this records which button they pressed. §6.1 fixes the form at three
     * controls, so the alternative was a fourth field asking the visitor to
     * classify themselves. This is the same answer, obtained for free.
     */
    interest: { type: String, default: null },
    /**
     * A page-specific example for the optional message.
     *
     * The placeholder only, exactly as `firstFieldLabel` is the label only:
     * same field, same column, same optionality.
     */
    messagePlaceholder: { type: String, default: null },
    /**
     * A page-specific label for the first field.
     *
     * The label only — same field, same column, same required flag, same
     * endpoint. "اسم الشركة" is right for a procurement officer and wrong for
     * an individual artisan, and asking someone to type their craft under a
     * heading that says "company" is how a form starts feeling like it was
     * written for somebody else.
     *
     * §6.1 fixes the SHAPE of this form, not the words on it.
     */
    firstFieldLabel: { type: String, default: null },
    // `inline` sits inside a CTA band; `stacked` is the standalone block.
    layout: {
        type: String,
        default: 'stacked',
        validator: (v) => ['stacked', 'inline'].includes(v),
    },
});

const { t } = useTranslation();
const page = usePage();

const CONTACT = 'contact';
const MESSAGE = 'message';

const fields = computed(() => page.props.leadFields ?? []);

/**
 * The always-present contact field.
 *
 * Falls back to the translated defaults if the admin table has no row for it.
 * The server enforces this field unconditionally, so the form must never fail
 * to render the input the server is going to require.
 */
const contactField = computed(
    () =>
        fields.value.find((f) => f.key === CONTACT) ?? {
            key: CONTACT,
            type: 'text',
            required: true,
            label: t('leads.placeholder'),
            placeholder: t('leads.placeholder'),
        }
);

/** Optional single-line message, collapsed until asked for (§10.6). */
const messageField = computed(() => fields.value.find((f) => f.key === MESSAGE) ?? null);

/** Anything the client switched on beyond the two above. */
const extraFields = computed(() => fields.value.filter((f) => f.key !== CONTACT && f.key !== MESSAGE));

const values = reactive({
    [CONTACT]: '',
    ...Object.fromEntries(fields.value.map((f) => [f.key, f.type === 'checkbox' ? false : ''])),
});

const showMessage = ref(true);

/*
 * Direction for the text fields.
 *
 * dir="auto" decides from the FIRST strong character typed — so an empty
 * field has nothing to judge and falls back to LTR. On the Arabic site that
 * put the caret and the placeholder on the wrong side of every box until the
 * visitor had typed a letter. Binding to the page locale is what uto was
 * standing in for.
 *
 * Email and phone stay ltr regardless: both are Latin in every locale.
 */
const textDir = computed(() => (page.props.locale === 'en' ? 'ltr' : 'rtl'));

// A placeholder handed over at the moment the box is opened — see openMessage.
const messageHint = ref(null);
const messageBox = ref(null);

/**
 * Complaints raised while typing, kept apart from the server's.
 *
 * `errors` belongs to the server and is cleared on every submit. These are
 * the browser's, and they appear only once a field has been left — §10.6 says
 * validation runs on submit, never while typing, and the reason holds: an
 * error under a half-typed email is scolding someone mid-word. Leaving the
 * field is a different moment. They are answered the instant the value
 * becomes valid, so nobody is told off for something they have already fixed.
 */
const liveErrors = reactive({});

/**
 * A real check, not `type="email"`.
 *
 * The browser accepts `a@b` as a valid email; a buyer who typos their address
 * is a lead that cannot be answered — the one outcome this site is measured
 * on (§1). Public domains are welcome: plenty of small institutions run on
 * Gmail, and refusing them would refuse real work.
 */
const EMAIL = /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/;

function onPhoneValidity(key, state) {
    if (state.empty || state.valid) {
        delete liveErrors[key];

        return;
    }

    liveErrors[key] = state.message;
}

/** Trim and lower-case as it leaves the field — nobody means «  A@B.Com ». */
function normaliseEmail(key) {
    const value = String(values[key] ?? '').trim().toLowerCase().replace(/\s+/g, '');

    values[key] = value;

    if (value === '') {
        delete liveErrors[key];

        return;
    }

    if (EMAIL.test(value)) {
        delete liveErrors[key];

        return;
    }

    liveErrors[key] = t('leads.email_invalid');
}

/** Answered as soon as it is right, so nobody argues with a fixed field. */
function clearLive(key) {
    if (liveErrors[key] && EMAIL.test(String(values[key] ?? '').trim().toLowerCase())) {
        delete liveErrors[key];
    }
}
const processing = ref(false);
const submitted = ref(false);

/**
 * `null` while nothing has happened, then 'success' or 'error'.
 *
 * The form no longer disappears on success — it stays where it is and this
 * decides what the dialog over it says. Half a band going empty read as a
 * broken page rather than a completed errand.
 */
const outcome = ref(null);
const errors = ref({});

// Round-trip timer: a human cannot complete this in under two seconds.
const startedAt = ref(Date.now());

// Honeypot. Hidden from sight AND from assistive tech, so only a bot fills it.
const honeypotName = 'company_website';
const honeypot = ref('');

const uid = Math.random().toString(36).slice(2, 8);
const fieldId = (key) => `lead-${uid}-${key}`;
const errorId = (key) => `${fieldId(key)}-error`;

function attribution() {
    const params =
        typeof window === 'undefined'
            ? new URLSearchParams()
            : new URLSearchParams(window.location.search);

    return {
        page_url: typeof window === 'undefined' ? null : window.location.href,
        referrer: typeof document === 'undefined' ? null : document.referrer || null,
        utm_source: params.get('utm_source'),
        utm_medium: params.get('utm_medium'),
        utm_campaign: params.get('utm_campaign'),
        utm_term: params.get('utm_term'),
        utm_content: params.get('utm_content'),
        gclid: params.get('gclid'),
        fbclid: params.get('fbclid'),
        campaign: props.campaign,
        sector_hint: props.sectorHint,
        interest: props.interest,
    };
}

/**
 * Choosing an audience opens the message box.
 *
 * It stays collapsed on arrival — §10.6 is explicit that the form starts at
 * one field — but a visitor who has just pressed «التحقوا بمسار» has said
 * something about themselves, and the example in the placeholder is only
 * useful if it is on screen when they do. A watcher fires on change only, so
 * a page that merely renders with an interest already set is unaffected — the
 * box opens because somebody pressed something, never on arrival.
 */
watch(
    () => props.interest,
    (chosen) => {
        if (chosen) showMessage.value = true;
    }
);

/**
 * The root node, so a caller can bring the form onto the screen.
 *
 * `.lead` is also what `ContactDock` watches with its IntersectionObserver, so
 * this is a reference to a node that already exists rather than a new wrapper.
 */
const root = ref(null);

/**
 * Open the form at the message box, with an example of what to write.
 *
 * Exposed for a page that has a second entry point into the one form — the
 * showroom section's «احجزوا زيارة بموعد مسبق» is a request the three fixed
 * fields cannot express, so the button hands the visitor the field that can
 * and shows them what belongs in it. Still one form, one endpoint (§6.1);
 * this is a scroll and a focus, not a second route in.
 *
 * Returns false when the client has switched the message field off, so the
 * caller can decide what to do instead of assuming it worked.
 */
function openMessage(placeholder = null) {
    const smooth =
        typeof window !== 'undefined' &&
        !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    root.value?.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'center' });

    if (!messageField.value) {
        nextTick(() => document.getElementById(fieldId(CONTACT))?.focus({ preventScroll: true }));

        return false;
    }

    messageHint.value = placeholder;
    showMessage.value = true;

    nextTick(() => document.getElementById(fieldId(MESSAGE))?.focus({ preventScroll: true }));

    return true;
}

defineExpose({ openMessage });

/**
 * Closing the confirmation empties the form for the next request.
 *
 * Only after a success: a failure keeps every value, because asking someone
 * to retype what they just typed is how a retry becomes an abandonment.
 */
/**
 * The message box grows with what is written in it.
 *
 * It was a fixed three rows with an inner scrollbar, so anyone writing more
 * than two lines lost sight of their own sentence — on a field whose whole
 * purpose is the one line that qualifies a lead («عندي مؤتمر بتاريخ كذا»).
 *
 * Height is reset before it is measured: `scrollHeight` only ever grows while
 * the element is taller than its content, so without the reset the box can
 * expand and never shrink back.
 */
const MESSAGE_MAX_ROWS = 10;

function grow(event) {
    const el = event?.target ?? messageBox.value;
    if (!el) return;

    const styles = window.getComputedStyle(el);
    const line = parseFloat(styles.lineHeight) || 24;
    const chrome =
        parseFloat(styles.paddingBlockStart) +
        parseFloat(styles.paddingBlockEnd) +
        parseFloat(styles.borderBlockStartWidth) +
        parseFloat(styles.borderBlockEndWidth);

    const max = line * MESSAGE_MAX_ROWS + chrome;

    el.style.height = 'auto';

    const next = Math.min(el.scrollHeight, max);

    el.style.height = `${next}px`;
    // Past the ceiling it scrolls rather than pushing the button off-screen.
    el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
}

function dismiss() {
    if (outcome.value === 'success') {
        fields.value.forEach((field) => {
            values[field.key] = '';
        });

        submitted.value = false;
        errors.value = {};

        // The box shrinks with the text it no longer holds.
        nextTick(() => {
            if (messageBox.value) {
                messageBox.value.style.height = 'auto';
                messageBox.value.style.overflowY = 'hidden';
            }
        });
    }

    outcome.value = null;
}

function submit() {
    if (processing.value) return;

    /*
     * The browser's own complaints are settled first.
     *
     * Not a replacement for the server's check — that one is authoritative
     * and runs regardless (§7.4) — but a round trip to be told an address is
     * malformed is a round trip the visitor did not need to make.
     */
    fields.value
        .filter((field) => field.type === 'email')
        .forEach((field) => normaliseEmail(field.key));

    /*
     * Phone fields are read off the page, not trusted to have arrived.
     *
     * The number is displayed by intl-tel-input, which formats as you type by
     * assigning to the element — and a programmatic assignment fires no
     * `input` event, so a keystroke can reach the box without ever reaching
     * the model. Twice now a visitor has filled this form, seen their number
     * sitting in the field, and been told the field was required.
     *
     * At this instant the element holds what the person believes they typed,
     * which is the only version worth submitting. The value is used only when
     * the model has nothing: an emitted E.164 number is better than the
     * formatted one on screen, and the server normalises either.
     */
    fields.value
        .filter((field) => field.type === 'tel')
        .forEach((field) => {
            const shown = document.getElementById(fieldId(field.key))?.value?.trim() ?? '';

            if (shown !== '' && String(values[field.key] ?? '').trim() === '') {
                values[field.key] = shown;
            }
        });

    if (Object.keys(liveErrors).length > 0) return;

    processing.value = true;
    errors.value = {};

    router.post(
        '/leads',
        {
            ...values,
            started_at: startedAt.value,
            [honeypotName]: honeypot.value,
            ...attribution(),
        },
        {
            preserveScroll: true,
            // Success resolves in place — no modal, no redirect (§10.6).
            onSuccess: () => {
                submitted.value = true;
                outcome.value = 'success';

                // The conversion event GA4 and Meta listen for (§14.1).
                if (typeof window !== 'undefined') {
                    window.dataLayer = window.dataLayer ?? [];
                    window.dataLayer.push({
                        event: 'lead_submitted',
                        lead_locale: page.props.locale,
                        lead_campaign: props.campaign ?? null,
                        lead_sector: props.sectorHint ?? null,
                        lead_interest: props.interest ?? null,
                    });
                }
            },
            onError: (received) => {
                errors.value = received;

                /*
                 * A field-level complaint is answered under the field; only a
                 * failure with nothing to point at gets the dialog. Otherwise
                 * a mistyped email would raise a modal that says nothing the
                 * message beneath the input has not already said.
                 */
                if (Object.keys(received ?? {}).length === 0) {
                    outcome.value = 'error';
                }
            },
            onFinish: () => {
                processing.value = false;
                // A new timer, so a corrected resubmission is not read as a bot.
                startedAt.value = Date.now();
            },
        }
    );
}
</script>

<template>
    <div ref="root" class="lead" :class="`lead--${layout}`">
        <!--
            Announced, not shown.

            The confirmation used to replace the whole form, which left half
            the band empty and read as a page that had broken rather than an
            errand that had finished. The visible answer is now the dialog at
            the end of this file; this line stays because `aria-live` is how a
            screen reader learns anything happened at all (§10.6).
        -->
        <p v-if="submitted" class="visually-hidden" role="status" aria-live="polite">
            {{ t('leads.success') }}
        </p>

        <form novalidate @submit.prevent="submit">
            <p v-if="heading" class="lead__heading">{{ heading }}</p>
            <p v-if="reassurance" class="lead__reassurance">{{ reassurance }}</p>

            <!-- Fields the client enabled beyond the brief's two. Absent in
                 the shipped configuration. -->
            <div v-if="extraFields.length" class="lead__extras">
                <div v-for="(field, i) in extraFields" :key="field.key" class="lead__extra">
                    <label class="lead__label" :for="fieldId(field.key)">
                        {{ (i === 0 && firstFieldLabel) ? firstFieldLabel : field.label }}
                        <!-- The marker and the input's own `required` come from
                             the same flag, so what the visitor is told and what
                             the form enforces cannot drift apart. -->
                        <span v-if="field.required" class="lead__required" aria-hidden="true">*</span>
                        <span v-else class="lead__optional">
                            {{ t('common.optional') }}
                        </span>
                    </label>

                    <select
                        v-if="field.type === 'select'"
                        :id="fieldId(field.key)"
                        v-model="values[field.key]"
                        class="lead-input"
                        :aria-invalid="errors[field.key] ? 'true' : undefined"
                        :aria-describedby="errors[field.key] ? errorId(field.key) : undefined"
                    >
                        <option value="">—</option>
                        <option v-for="opt in field.options" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>

                    <label v-else-if="field.type === 'checkbox'" class="lead__check">
                        <input
                            :id="fieldId(field.key)"
                            v-model="values[field.key]"
                            type="checkbox"
                        />
                        <span>{{ field.help ?? field.label }}</span>
                    </label>

                    <!--
                        A phone number gets the country list, the flag and the
                        per-country check. Keyed by type rather than by name,
                        so a second `tel` field the client adds in the panel
                        behaves the same without a code change.
                    -->
                    <PhoneField
                        v-else-if="field.type === 'tel'"
                        :id="fieldId(field.key)"
                        v-model="values[field.key]"
                        :name="field.key"
                        :required="field.required"
                        :invalid="Boolean(errors[field.key] || liveErrors[field.key])"
                        :described-by="(errors[field.key] || liveErrors[field.key]) ? errorId(field.key) : undefined"
                        @validity="(state) => onPhoneValidity(field.key, state)"
                    />

                    <textarea
                        v-else-if="field.type === 'textarea'"
                        :id="fieldId(field.key)"
                        v-model="values[field.key]"
                        class="lead-input lead-textarea"
                        rows="2"
                        dir="auto"
                        :placeholder="field.placeholder ?? ''"
                        :maxlength="field.maxLength ?? undefined"
                        :aria-invalid="errors[field.key] ? 'true' : undefined"
                    />

                    <input
                        v-else
                        :id="fieldId(field.key)"
                        v-model="values[field.key]"
                        class="lead-input"
                        :type="field.type === 'email' ? 'email' : field.type === 'tel' ? 'tel' : 'text'"
                        :placeholder="field.placeholder ?? ''"
                        :maxlength="field.maxLength ?? undefined"
                        :dir="field.type === 'email' || field.type === 'tel' ? 'ltr' : textDir"
                        :class="{ 'lead-input--mono': field.type === 'email' || field.type === 'tel' }"
                        :required="field.required || undefined"
                        :aria-required="field.required ? 'true' : undefined"
                        :aria-invalid="(errors[field.key] || liveErrors[field.key]) ? 'true' : undefined"
                        :aria-describedby="(errors[field.key] || liveErrors[field.key]) ? errorId(field.key) : undefined"
                        @blur="field.type === 'email' ? normaliseEmail(field.key) : null"
                        @input="field.type === 'email' ? clearLive(field.key) : null"
                    />

                    <p
                        v-if="errors[field.key] || liveErrors[field.key]"
                        :id="errorId(field.key)"
                        class="lead-error"
                    >
                        {{ errors[field.key] ?? liveErrors[field.key] }}
                    </p>
                </div>
            </div>

            <!-- The contact field and the submit button, side by side. -->
            <div v-if="contactField" class="lead__row">
                <div class="lead__field">
                    <!--
                        Visible in the CTA band, hidden in the stacked layout.
                        Stacked, the field is one control under its own heading
                        and a label would repeat it; in the band it sits in a
                        column of three labelled fields, and the one without a
                        label is the one people skip.
                    -->
                    <label
                        :class="layout === 'inline' ? 'lead__label' : 'visually-hidden'"
                        :for="fieldId(CONTACT)"
                    >
                        {{ contactField.label }}
                        <span v-if="layout === 'inline'" class="lead__required" aria-hidden="true">
                            *
                        </span>
                    </label>
                    <input
                        :id="fieldId(CONTACT)"
                        v-model="values[CONTACT]"
                        class="lead-input"
                        type="text"
                        :name="CONTACT"
                        inputmode="text"
                        autocomplete="email tel"
                        dir="auto"
                        :placeholder="contactField.placeholder ?? contactField.label"
                        :aria-invalid="errors[CONTACT] ? 'true' : undefined"
                        :aria-describedby="errors[CONTACT] ? errorId(CONTACT) : undefined"
                        required
                    />
                </div>

                <!--
                    The button leaves this row when there is a message box,
                    because the box belongs between the last field and the
                    button — a visitor writes their line and then presses
                    send. With no message field the row is unchanged: field
                    and button side by side, exactly as before.
                -->
                <Button
                    v-if="!messageField"
                    type="submit"
                    :variant="layout === 'inline' ? 'cta-lg' : 'cta'"
                    :loading="processing"
                    class="lead__submit"
                >
                    {{ processing ? t('leads.submitting') : (submitLabel ?? t('leads.submit')) }}
                </Button>
            </div>

            <!-- Validation runs on submit only, never while typing (§10.6). -->
            <p v-if="errors[CONTACT]" :id="errorId(CONTACT)" class="lead-error" aria-live="polite">
                {{ errors[CONTACT] }}
            </p>

            <template v-if="messageField">
                <!--
                    The box is open from the start.

                    It was collapsed behind a link to keep the form to three
                    controls (§10.6), and the reasoning holds for the required
                    fields — but the message is where a buyer writes «عندي
                    مؤتمر بتاريخ كذا», which is the single most useful line the
                    sales team can receive. A field nobody sees is a field
                    nobody fills; the cost of one visible optional box is far
                    smaller than the qualification it buys.
                -->
                <div class="lead__message">
                    <!--
                        A visible label, not a screen-reader-only one.

                        «(اختياري)» comes from the language file rather than
                        from the label in the database: it describes the
                        field's validation, not its content, and an editor who
                        renames the label must not be able to make the form
                        claim something the server does not enforce.
                    -->
                    <label class="lead__label" :for="fieldId(MESSAGE)">
                        {{ messageField.label }}
                        <span class="lead__optional">{{ t('leads.optional') }}</span>
                    </label>
                    <textarea
                        :id="fieldId(MESSAGE)"
                        ref="messageBox"
                        v-model="values[MESSAGE]"
                        class="lead-input lead-textarea"
                        :name="MESSAGE"
                        rows="3"
                        :dir="textDir"
                        :placeholder="messageHint ?? messagePlaceholder ?? messageField.placeholder ?? ''"
                        :maxlength="messageField.maxLength ?? undefined"
                        @input="grow"
                    />
                    <p v-if="errors[MESSAGE]" class="lead-error">{{ errors[MESSAGE] }}</p>
                </div>
            </template>

            <div v-if="messageField" class="lead__actions">
                <Button
                    type="submit"
                    :variant="layout === 'inline' ? 'cta-lg' : 'cta'"
                    :loading="processing"
                    class="lead__submit"
                >
                    {{ processing ? t('leads.submitting') : (submitLabel ?? t('leads.submit')) }}
                </Button>
            </div>

            <!-- Honeypot: aria-hidden and tabindex -1 so no real user reaches it. -->
            <div class="lead__trap" aria-hidden="true">
                <label :for="`${fieldId('hp')}`">Company website</label>
                <input
                    :id="`${fieldId('hp')}`"
                    v-model="honeypot"
                    :name="honeypotName"
                    type="text"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>
        </form>

        <!-- The visible answer. The form behind it keeps its place. -->
        <ResultDialog
            :open="outcome !== null"
            :tone="outcome ?? 'success'"
            :title="outcome === 'error' ? t('leads.error_title') : t('leads.success_title')"
            :message="outcome === 'error' ? t('leads.error_body') : t('leads.success_body')"
            :auto-close-ms="outcome === 'error' ? 0 : 5000"
            @close="dismiss"
        />
    </div>
</template>

<style scoped>
.lead__heading {
    font-family: var(--font-display);
    font-size: var(--fs-h3);
    font-weight: 700;
    margin-block-end: var(--s-2);
}

.lead__reassurance {
    color: var(--text-muted);
    font-size: var(--fs-sm);
    margin-block-end: var(--s-4);
}

.on-dark .lead__reassurance {
    color: rgba(255, 255, 255, 0.72);
}

.lead__extras {
    display: grid;
    gap: var(--s-4);
    margin-block-end: var(--s-4);
}

.lead__label {
    display: block;
    margin-block-end: var(--s-2);
    font-size: var(--fs-sm);
    font-weight: 600;
}

.lead__optional {
    font-weight: 400;
    color: var(--text-muted);
}

/* Gold, so the marker reads as part of the identity rather than as an error
   colour sitting next to every required field. */
.lead__required {
    color: var(--gold-400);
    margin-inline-start: var(--s-1);
}

.on-dark .lead__optional {
    color: rgba(255, 255, 255, 0.6);
}

.lead__check {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
}

.lead__row {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
}

.lead__field {
    flex: 1 1 auto;
    min-inline-size: 0;
}

@media (min-width: 640px) {
    .lead__row {
        flex-direction: row;
        align-items: stretch;
    }

    .lead__submit {
        flex: 0 0 auto;
    }

    /*
     * `minmax(0, 1fr)`, not a bare `1fr`.
     *
     * A bare fr track is sized to its content's minimum before free space is
     * shared out, and an <input>'s intrinsic minimum is wide — so two inputs
     * in two bare tracks demand more than the row has and push the form past
     * the viewport instead of shrinking. This is the same defect that
     * collapsed the CtaBand heading to one word per line; the form carries it
     * on every page, so it is the one worth fixing first.
     */
    .lead__extras {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.lead-error {
    margin-block-start: var(--s-2);
}

.lead__toggle {
    display: inline-block;
    margin-block-start: var(--s-4);
    color: var(--link);
    font-size: var(--fs-sm);
    font-weight: 600;
    /* 44px target without a 44px-looking control. */
    padding-block: var(--s-3);
}

.on-dark .lead__toggle {
    color: var(--gold-400);
}

.lead__message {
    margin-block-start: var(--s-3);
}

/* The button's own line, once the message box sits above it. Same gap as
   between the fields, so nothing about the section's rhythm changes. */
.lead__actions {
    display: flex;
    margin-block-start: var(--s-4);
}


/* Off-canvas rather than display:none — some bots skip hidden inputs. */
.lead__trap {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}
</style>
