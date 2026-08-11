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
     * Credential fields per provider, and whether each is a secret.
     *
     * Secrets are never sent back to the browser — see `redacted()`. A panel
     * that re-displays an API key puts it in every screenshot, every screen
     * share and every browser cache the client's team ever makes.
     *
     * @var array<string, array<string, bool>>
     */
    public const FIELDS = [
        'zid' => [
            'base_url' => false,
            'store_id' => false,
            'access_token' => true,
        ],
        'odoo' => [
            'url' => false,
            'database' => false,
            'username' => false,
            'api_key' => true,
        ],
    ];

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
            foreach ($fields as $field => $isSecret) {
                $saved = $this->settings->get("crm.{$provider}.{$field}");
                $fromEnv = config("crm.drivers.{$provider}.{$field}");
                $value = is_string($saved) && $saved !== '' ? $saved : $fromEnv;

                $out[$provider][$field] = match (true) {
                    ! is_string($value) || $value === '' => '',
                    $isSecret => '••••••••',
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
