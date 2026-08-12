<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * A phone number that a person could actually be reached on.
 *
 * The browser checks this too, through intl-tel-input — but the browser is
 * not where a lead becomes real. A crafted POST, a script, or JavaScript that
 * simply did not run all reach the same endpoint, and §7.4 puts a Form
 * Request in front of every input for exactly that reason.
 *
 * Both sides run the same metadata: Google's libphonenumber, which is what
 * knows that a Kuwaiti mobile is eight digits and a German one is not. The
 * `-lite` distribution is the same library without the geocoding and carrier
 * datasets, which this site has no use for and which are most of the weight.
 */
class InternationalPhone implements ValidationRule
{
    /**
     * The country assumed when a number arrives without a `+`.
     *
     * Saudi, because §3 makes local institutions the first audience and the
     * form's own default country is `sa`. It only applies to a value that
     * named no country at all — a number that says `+49` is read as German
     * whatever this says.
     */
    private const FALLBACK_REGION = 'SA';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // `nullable`/`required` decide emptiness, not this rule.
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(trim($value), self::FALLBACK_REGION);
        } catch (NumberParseException) {
            $fail(__('leads.phone_invalid'))->translate();

            return;
        }

        if (! $util->isValidNumber($number)) {
            $fail(__('leads.phone_invalid'))->translate();
        }
    }

    /**
     * The number as it should be stored: `+966512345678`.
     *
     * E.164 rather than what was typed, so the sales team can dial it, link
     * it to WhatsApp and match it in the CRM without cleaning it first. A
     * value that cannot be parsed is returned untouched — losing what someone
     * gave us because we could not tidy it would be worse than storing it
     * imperfectly.
     */
    public static function e164(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(trim($value), self::FALLBACK_REGION);
        } catch (NumberParseException) {
            return $value;
        }

        return $util->isValidNumber($number)
            ? $util->format($number, PhoneNumberFormat::E164)
            : $value;
    }

    /**
     * The same number written the way a person reads it: `+966 51 234 5678`.
     *
     * For the leads screen only. What is stored stays E.164 — this is the
     * presentation of it, and the two must never swap places.
     */
    public static function readable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(trim($value), self::FALLBACK_REGION);
        } catch (NumberParseException) {
            return $value;
        }

        return $util->isValidNumber($number)
            ? $util->format($number, PhoneNumberFormat::INTERNATIONAL)
            : $value;
    }
}
