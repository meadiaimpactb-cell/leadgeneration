<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CRM integration  (PROJECT_BRIEF.md §6.3)
|--------------------------------------------------------------------------
| The site must NEVER be coupled to a single CRM provider. Every lead goes
| out through the CrmDriver contract; the active provider is picked here and
| is swappable via CRM_DRIVER with no code change.
|
| Odoo is the system in use today. Zid is the likely destination. Both are
| implemented, plus a generic webhook fallback and a null driver for local
| development.
*/

return [

    /*
    | Active driver: null | webhook | odoo | zid
    |
    | The `?:` is load-bearing, not defensive noise. Laravel's env() maps the
    | literal string "null" onto PHP null, so `CRM_DRIVER=null` in .env would
    | otherwise resolve to no driver at all and every lead would fail to sync.
    */
    'driver' => env('CRM_DRIVER') ?: 'null',

    /*
    | Queued push retry policy. Exponential backoff, in seconds.
    | A lead that exhausts these is marked crm_status = failed and raises
    | an alert — it is never silently dropped.
    */
    'retry' => [
        'attempts' => (int) env('CRM_RETRY_ATTEMPTS', 5),
        'backoff' => [30, 120, 600, 1800, 7200],
    ],

    /*
    | Truncate request/response bodies written to crm_sync_logs so a chatty
    | provider cannot bloat the table.
    */
    'log_body_limit' => 8000,

    'drivers' => [

        'null' => [
            // Records the attempt in crm_sync_logs and reports success.
            // Local/dev only — never set CRM_DRIVER=null in production.
        ],

        'webhook' => [
            'url' => env('CRM_WEBHOOK_URL'),
            'secret' => env('CRM_WEBHOOK_SECRET'),
            'timeout' => 10,
        ],

        'odoo' => [
            'url' => env('ODOO_URL'),
            'database' => env('ODOO_DB'),
            'username' => env('ODOO_USERNAME'),
            'api_key' => env('ODOO_API_KEY'),
            'timeout' => 15,

            // Odoo model the lead is written to.
            'model' => 'crm.lead',

            // Source label written onto the Odoo record so the sales team
            // can tell corporate-site leads from every other channel.
            'source' => 'amadcraft-b2b-site',
        ],

        'zid' => [
            'base_url' => env('ZID_API_BASE', 'https://api.zid.sa'),

            // The store's manager token, from the Zid dashboard's API screen.
            'access_token' => env('ZID_ACCESS_TOKEN'),

            // The OAuth token of an application registered with Zid. Without
            // it Zid answers 401 — see App\Support\CrmSettings::FIELDS.
            'oauth_token' => env('ZID_OAUTH_TOKEN'),

            'store_id' => env('ZID_STORE_ID'),
            'timeout' => 15,
        ],
    ],
];
