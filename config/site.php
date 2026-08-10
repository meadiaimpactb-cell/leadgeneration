<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Site configuration  (PROJECT_BRIEF.md §12, §15.3)
|--------------------------------------------------------------------------
| Structural configuration only. Anything the client should be able to
| change without a deploy — tracking IDs, contact details, SEO defaults,
| copy — belongs in the `settings` table, NOT here (§14.1, §22.9).
*/

return [

    /*
    | Supported locales. Arabic is the default and the fallback.
    | Adding a locale here is a code + content decision, not a setting.
    */
    'locales' => [
        'ar' => [
            'name' => 'العربية',
            'native' => 'العربية',
            'english' => 'Arabic',
            'dir' => 'rtl',
            'html_lang' => 'ar',
            'hreflang' => 'ar',
        ],
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'english' => 'English',
            'dir' => 'ltr',
            'html_lang' => 'en',
            'hreflang' => 'en',
        ],
    ],

    'default_locale' => 'ar',

    /*
    | Which locale answers to `x-default` in hreflang.
    */
    'x_default_locale' => 'ar',

    /*
    | How long the visitor's locale choice is remembered, in minutes.
    */
    'locale_cookie' => 'amadcraft_locale',
    'locale_cookie_days' => 365,

    /*
    | Lead capture guards (§6.1, §15.3).
    */
    'leads' => [
        // Who is alerted when a lead arrives, and when one fails to reach the
        // CRM. §20 decision 4 names the real recipients.
        'notify_to' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('LEADS_NOTIFY_TO', '')))
        )),

        // Salt for hashing visitor IPs. The raw address is never stored (§15.3).
        'ip_hash_salt' => env('IP_HASH_SALT', ''),

        // POST /leads throttle — attempts per minute, per IP.
        'rate_limit' => 5,

        // Honeypot field name. Any value submitted in it = bot.
        'honeypot_field' => 'company_website',

        // A human cannot complete the form faster than this, in seconds.
        'min_fill_seconds' => 2,

        // Max length of the optional single-line message (§6.1).
        'message_max' => 500,
    ],

    /*
    | Public page cache TTL in seconds (§7.1). Zero disables it.
    */
    'page_cache_ttl' => (int) env('PAGE_CACHE_TTL', 300),
];
