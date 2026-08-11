<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';
import Button from '@/Components/ui/Button.vue';

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

const showMessage = ref(false);
const processing = ref(false);
const submitted = ref(false);
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
    };
}

function submit() {
    if (processing.value) return;

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

                // The conversion event GA4 and Meta listen for (§14.1).
                if (typeof window !== 'undefined') {
                    window.dataLayer = window.dataLayer ?? [];
                    window.dataLayer.push({
                        event: 'lead_submitted',
                        lead_locale: page.props.locale,
                        lead_campaign: props.campaign ?? null,
                        lead_sector: props.sectorHint ?? null,
                    });
                }
            },
            onError: (received) => {
                errors.value = received;
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
    <div class="lead" :class="`lead--${layout}`">
        <!-- Confirmation replaces the form in place, with the Sadu thread
             weaving itself underneath (§10.6). -->
        <div v-if="submitted" class="lead__done" role="status" aria-live="polite">
            <p class="lead__done-text">{{ t('leads.success') }}</p>
            <div class="lead-success-thread" aria-hidden="true" />
        </div>

        <form v-else novalidate @submit.prevent="submit">
            <p v-if="heading" class="lead__heading">{{ heading }}</p>
            <p v-if="reassurance" class="lead__reassurance">{{ reassurance }}</p>

            <!-- Fields the client enabled beyond the brief's two. Absent in
                 the shipped configuration. -->
            <div v-if="extraFields.length" class="lead__extras">
                <div v-for="field in extraFields" :key="field.key" class="lead__extra">
                    <label class="lead__label" :for="fieldId(field.key)">
                        {{ field.label }}
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
                        :dir="field.type === 'email' || field.type === 'tel' ? 'ltr' : 'auto'"
                        :class="{ 'lead-input--mono': field.type === 'email' || field.type === 'tel' }"
                        :required="field.required || undefined"
                        :aria-required="field.required ? 'true' : undefined"
                        :aria-invalid="errors[field.key] ? 'true' : undefined"
                        :aria-describedby="errors[field.key] ? errorId(field.key) : undefined"
                    />

                    <p v-if="errors[field.key]" :id="errorId(field.key)" class="lead-error">
                        {{ errors[field.key] }}
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

                <Button
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
                <!-- Collapsed by default to keep cognitive load minimal (§10.6). -->
                <button
                    v-if="!showMessage"
                    type="button"
                    class="lead__toggle link-weave"
                    @click="showMessage = true"
                >
                    {{ t('leads.add_message') }}
                </button>

                <div v-else class="lead__message">
                    <label class="visually-hidden" :for="fieldId(MESSAGE)">
                        {{ messageField.label }}
                    </label>
                    <textarea
                        :id="fieldId(MESSAGE)"
                        v-model="values[MESSAGE]"
                        class="lead-input lead-textarea"
                        :name="MESSAGE"
                        rows="1"
                        dir="auto"
                        :placeholder="messageField.placeholder ?? ''"
                        :maxlength="messageField.maxLength ?? undefined"
                    />
                    <p v-if="errors[MESSAGE]" class="lead-error">{{ errors[MESSAGE] }}</p>
                </div>
            </template>

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

    .lead__extras {
        grid-template-columns: repeat(2, 1fr);
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

.lead__done-text {
    font-size: var(--fs-body-lg);
    font-weight: 600;
    margin-block-end: var(--s-3);
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
