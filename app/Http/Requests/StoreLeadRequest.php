<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LeadField;
use App\Rules\InternationalPhone;
use App\Support\ContactValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Validation for the one form on the site (§6.1).
 *
 * The rules are built from whatever fields are enabled in the admin panel, so
 * switching a field on or off never needs a code change. The site ships with
 * exactly the two fields §6.1 defines.
 *
 * Everything else on the request is attribution the browser sends, not
 * something the visitor typed.
 */
class StoreLeadRequest extends FormRequest
{
    public ?ContactValue $contact = null;

    /** @var Collection<int, LeadField>|null */
    private ?Collection $fields = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return Collection<int, LeadField> */
    public function enabledFields(): Collection
    {
        return $this->fields ??= LeadField::query()->enabled()->with('translations')->get();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            /*
             * The contact rule is unconditional, NOT derived from lead_fields.
             *
             * A lead with no contact value is not a lead — there is no way to
             * reach the person, and the count of leads is the only thing this
             * site is measured on (§1). Deriving this rule from the table
             * meant an empty or mis-edited table silently turned the endpoint
             * into one that accepted anything.
             *
             * The table governs this field's LABEL and ORDER. Its existence
             * and its being required are guaranteed here.
             */
            LeadField::KEY_CONTACT => ['required', 'string', 'max:191'],

            // Honeypot: a real browser leaves this empty because it is hidden.
            config('site.leads.honeypot_field') => ['nullable', 'prohibited'],

            // Round-trip time guard — a bot fills the form instantly.
            'started_at' => ['nullable', 'integer'],

            // Attribution. Never trusted for anything but reporting, so the
            // rules are about size, not shape.
            'page_url' => ['nullable', 'string', 'max:512'],
            'referrer' => ['nullable', 'string', 'max:512'],
            'utm_source' => ['nullable', 'string', 'max:128'],
            'utm_medium' => ['nullable', 'string', 'max:128'],
            'utm_campaign' => ['nullable', 'string', 'max:128'],
            'utm_term' => ['nullable', 'string', 'max:128'],
            'utm_content' => ['nullable', 'string', 'max:128'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'fbclid' => ['nullable', 'string', 'max:255'],
            'campaign' => ['nullable', 'string', 'max:191'],
            'sector_hint' => ['nullable', 'string', 'max:64'],
            /*
             * Which of a page's audiences pressed the button — «ارعوا مساراً»
             * or «التحقوا بمسار» on /training. Bounded by length only, like
             * `sector_hint` above and for the same reason: the value comes
             * from a section setting the client edits, and a whitelist here
             * would turn a typo in the panel into a rejected lead.
             */
            'interest' => ['nullable', 'string', 'max:32'],
        ];

        foreach ($this->enabledFields() as $field) {
            // Never let a table row weaken the contact rule set above.
            if ($field->key === LeadField::KEY_CONTACT) {
                continue;
            }

            $rules[$field->key] = $field->rules();
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->string(LeadField::KEY_CONTACT)->toString();

            if ($raw === '') {
                return;
            }

            $this->contact = ContactValue::parse($raw);

            if ($this->contact === null) {
                // One message covering both accepted forms — the visitor was
                // never asked to choose a type, so the error must not imply
                // they picked the wrong one (§10.6).
                $validator->errors()->add(LeadField::KEY_CONTACT, __('leads.contact_invalid'));
            }
        });
    }

    /**
     * Answers to fields beyond the two backed by real columns.
     *
     * @return array<string, mixed>
     */
    public function extraAnswers(): array
    {
        $extra = [];

        foreach ($this->enabledFields() as $field) {
            if ($field->isReserved()) {
                continue;
            }

            $value = $this->input($field->key);

            if (filled($value) || $field->type === 'checkbox') {
                $extra[$field->key] = match ($field->type) {
                    'checkbox' => $this->boolean($field->key),
                    /*
                     * Stored as it is dialled, not as it was typed.
                     *
                     * «0512345678» is how a Saudi writes their own number and
                     * means nothing to a WhatsApp link or a CRM. The browser
                     * already sends E.164 when its JavaScript ran; this makes
                     * it true when it did not.
                     */
                    'tel' => InternationalPhone::e164((string) $value),
                    // Nobody means « A@B.Com ».
                    'email' => mb_strtolower(trim((string) $value)),
                    default => $value,
                };
            }
        }

        return $extra;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            LeadField::KEY_CONTACT.'.required' => __('leads.contact_required'),
            LeadField::KEY_MESSAGE.'.max' => __('leads.message_too_long'),
        ];
    }

    /**
     * Use the client's own field labels in error messages, so a renamed field
     * does not produce an error naming a key the visitor never saw.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->enabledFields()
            ->mapWithKeys(fn (LeadField $f): array => [$f->key => (string) $f->t('label')])
            ->all();
    }
}
