<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The CRM connection, as the client can edit it.
 *
 * `config/crm.php` reads `.env`, which is right for a deployment set up by a
 * developer and wrong for a client who must be able to point the site at a
 * different CRM without one. §22.9 permits either store — "settings or .env" —
 * so this layers one over the other rather than replacing it:
 *
 *   a value saved in the panel wins;
 *   otherwise the .env value stands.
 *
 * That ordering matters. Reversing it would mean a deployment with ODOO_URL
 * in .env could never be re-pointed from the panel, and dropping .env support
 * would break every environment already configured that way on the first
 * deploy.
 *
 * Applied into the runtime config, so the four drivers keep reading
 * `config('crm.drivers.*')` and know nothing about where the value came from.
 */
class CrmSettings
{
    /** Providers the panel offers. `null` and `webhook` stay .env-only. */
    public const PROVIDERS = ['zid', 'odoo'];

    /**
     * Credential fields per provider, and what kind of value each holds.
     *
     *   text   — an ordinary value, shown as typed.
     *   secret — hidden on the way back to the browser, one line.
     *   token  — hidden too, but too long for a line.
     *
     * `token` exists because Zid's access token is a JWT: it arrives from the
     * dashboard well past a thousand characters. Behind a single-line password
     * box it was a field nobody could read back or check, and behind the 512
     * limit the save simply refused — which is what was blocking the Zid
     * connection from being entered at all.
     *
     * Secrets are never sent back to the browser — see `redacted()`. A panel
     * that re-displays an API key puts it in every screenshot, every screen
     * share and every browser cache the client's team ever makes.
     *
     * @var array<string, array<string, string>>
     */
    public const FIELDS = [
        /*
         * Zid needs two tokens, not one, and that is the whole reason a
         * connection built from the dashboard pair alone answers 401.
         *
         *   access_token — «رمز الوصول» from the store dashboard's API screen.
         *                  Zid calls this the manager token; it names the store.
         *   oauth_token  — the token Zid returns when a store owner installs an
         *                  application registered on the Zid partner portal. It
         *                  names the application making the call.
         *
         * That screen says so itself: only an application Zid has authorised
         * may use these credentials. The manager token says which store; the
         * OAuth token says who is asking. With only the first, nobody is
         * asking, and Zid refuses the request exactly as it refuses a random
         * string. Left empty, the manager token is sent for both — which is
         * Zid's documented shortcut, and worth keeping for the day it applies.
         */
        'zid' => [
            'base_url' => 'text',
            'store_id' => 'text',
            'access_token' => 'token',
            'oauth_token' => 'token',
        ],
        'odoo' => [
            'url' => 'text',
            'database' => 'text',
            'username' => 'text',
            'api_key' => 'secret',
        ],
    ];

    /**
     * Accepted length per kind. Generous for a token and still bounded — the
     * settings row is a JSON column, so the ceiling is a sanity check on a
     * pasted value, not a storage limit.
     */
    public const MAX_LENGTH = [
        'text' => 512,
        'secret' => 512,
        'token' => 4096,
    ];

    /** Whether a value of this kind must be kept out of the browser. */
    public static function isSecret(string $type): bool
    {
        return $type !== 'text';
    }

    public function __construct(private readonly Settings $settings) {}

    /** The active provider: the panel's choice, else the .env one. */
    public function driver(): string
    {
        $chosen = $this->settings->get('crm.driver');

        return is_string($chosen) && $chosen !== ''
            ? $chosen
            : (config('crm.driver') ?: 'null');
    }

    /**
     * Push every saved credential into the runtime config.
     *
     * Called before a driver is resolved. Cheap — the settings store is
     * already loaded once per request — and idempotent.
     */
    public function apply(): void
    {
        config()->set('crm.driver', $this->driver());

        foreach (self::FIELDS as $provider => $fields) {
            foreach (array_keys($fields) as $field) {
                $value = $this->settings->get("crm.{$provider}.{$field}");

                if (is_string($value) && $value !== '') {
                    config()->set("crm.drivers.{$provider}.{$field}", $value);
                }
            }
        }
    }

    /**
     * The credentials as the panel should show them: plain values for the
     * ordinary fields, a placeholder for a secret that is set, and an empty
     * string for one that is not.
     *
     * @return array<string, array<string, string>>
     */
    public function redacted(): array
    {
        $out = [];

        foreach (self::FIELDS as $provider => $fields) {
            foreach ($fields as $field => $type) {
                $saved = $this->settings->get("crm.{$provider}.{$field}");
                $fromEnv = config("crm.drivers.{$provider}.{$field}");
                $value = is_string($saved) && $saved !== '' ? $saved : $fromEnv;

                $out[$provider][$field] = match (true) {
                    ! is_string($value) || $value === '' => '',
                    self::isSecret($type) => '••••••••',
                    default => $value,
                };
            }
        }

        return $out;
    }

    /** Where each value currently comes from, so the panel can say so. */
    public function sources(): array
    {
        $out = [];

        foreach (self::FIELDS as $provider => $fields) {
            foreach (array_keys($fields) as $field) {
                $saved = $this->settings->get("crm.{$provider}.{$field}");

                $out[$provider][$field] = is_string($saved) && $saved !== ''
                    ? 'panel'
                    : (filled(config("crm.drivers.{$provider}.{$field}")) ? 'env' : 'unset');
            }
        }

        return $out;
    }
}
