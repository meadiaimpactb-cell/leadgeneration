<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises and classifies the single contact field (§6.1).
 *
 * The visitor types an email OR a mobile number into one box and is never
 * asked which. This class is the auto-detection; getting it wrong means
 * losing the site's only measurable outcome, so it is deliberately strict
 * about what it accepts and forgiving about how it is typed.
 */
final class ContactValue
{
    private function __construct(
        public readonly string $value,
        public readonly string $type,
    ) {}

    public static function parse(string $raw): ?self
    {
        // Full-width punctuation must be folded BEFORE deciding which branch
        // to take: an Arabic keyboard can produce "name＠company.sa", which
        // contains no ASCII "@" and would otherwise be read as a phone number.
        $trimmed = self::foldFullWidth(trim($raw));

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '@')) {
            return self::asEmail($trimmed);
        }

        return self::asPhone($trimmed);
    }

    private static function asEmail(string $raw): ?self
    {
        $normalised = strtolower(self::latinizeDigits($raw));
        $normalised = preg_replace('/\s+/u', '', $normalised) ?? '';

        if (! filter_var($normalised, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return new self($normalised, 'email');
    }

    /** Fold the full-width forms an Arabic/CJK keyboard can emit. */
    private static function foldFullWidth(string $value): string
    {
        return strtr($value, [
            '＠' => '@',
            '．' => '.',
            '＋' => '+',
            '－' => '-',
        ]);
    }

    /**
     * Saudi mobile numbers, however the visitor writes them:
     * 0512345678 · 512345678 · +966512345678 · 00966 51 234 5678 · ٠٥١٢٣٤٥٦٧٨
     *
     * Stored in E.164 so the CRM and any dialler receive one canonical form.
     */
    private static function asPhone(string $raw): ?self
    {
        $digits = self::latinizeDigits($raw);

        $hasPlus = str_starts_with(trim($digits), '+');
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        if ($digits === '') {
            return null;
        }

        // 00966… → 966…
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        // Local forms → +966
        if (str_starts_with($digits, '966')) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '05') && strlen($digits) === 10) {
            $national = substr($digits, 1);
        } elseif (str_starts_with($digits, '5') && strlen($digits) === 9) {
            $national = $digits;
        } else {
            // A non-Saudi number: keep it if it looks like a plausible
            // international one rather than rejecting a real prospect.
            return $hasPlus && strlen($digits) >= 8 && strlen($digits) <= 15
                ? new self('+'.$digits, 'phone')
                : null;
        }

        // Saudi mobiles are 9 national digits starting with 5.
        if (strlen($national) !== 9 || ! str_starts_with($national, '5')) {
            return null;
        }

        return new self('+966'.$national, 'phone');
    }

    /** Convert Arabic-Indic and Eastern Arabic-Indic digits to ASCII. */
    private static function latinizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
