<?php

declare(strict_types=1);

namespace App\Services\Crm\Drivers;

use App\Models\Lead;
use App\Services\Crm\CrmDriver;
use App\Services\Crm\CrmResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Zid CRM driver (§6.3 — the likely destination once Odoo is retired).
 *
 * AUTHENTICATION. Zid's Merchant API reads two headers, and they answer two
 * different questions:
 *
 *   X-Manager-Token: <manager token>   which store
 *   Authorization:   Bearer <oauth>    who is asking
 *
 * The dashboard's «التكاملات مع واجهات البرمجة (API)» screen hands a merchant
 * the first only. The second comes from installing an application registered on
 * the Zid partner portal. This driver previously sent the manager token as
 * `Authorization` and omitted `X-Manager-Token` entirely, which answers neither
 * question.
 *
 * NOTE FOR HANDOVER — WHAT WAS MEASURED, 17 Aug 2026. With store 1200977's id
 * and a freshly generated access token from that dashboard screen,
 * `/managers/account/profile` answered 401 `Unauthenticated` under every
 * arrangement tried: both headers, each alone, with and without
 * `Store-Id`/`Role`, bare and `Bearer`-prefixed, and under the alternative
 * header names `Access-Token` and `X-Access-Token`. `/managers/store/customers`
 * behaved identically. Zid's reply to the real token was byte-for-byte its
 * reply to a deliberately invalid string — so it is not a permissions problem
 * or a wrong endpoint; standalone, that token is not a credential Zid accepts.
 *
 * Which is what the screen says in its own first note: only an application Zid
 * has authorised may use these credentials. The remaining step is Zid-side —
 * register the site on the partner portal, have store 1200977 install it, and
 * put the OAuth token Zid returns into the second field. No code change is
 * waiting on that; `headers()` already sends both the moment it is filled in.
 *
 * ALSO STILL OPEN: `verify()` is exercised against a real endpoint and is
 * trustworthy. `pushLead()` is not. Zid publishes no lead resource, so the path
 * it posts to does not exist and will 404; what a B2B enquiry should become on
 * the Zid side — a customer record, or nothing, with leads continuing to Odoo —
 * is a decision for Amad Craft, not one to be guessed at here (§22.2, §22.10).
 * Until both are settled, leave CRM_DRIVER on a provider that can receive a
 * lead, and use this connection only to prove the credentials.
 */
class ZidCrmDriver implements CrmDriver
{
    use LeadPayload;

    public function name(): string
    {
        return 'zid';
    }

    public function isConfigured(): bool
    {
        $c = config('crm.drivers.zid');

        return filled($c['base_url']) && filled($c['access_token']);
    }

    /**
     * Ask Zid who the token belongs to.
     *
     * `/managers/account/profile` is a plain read and returns the store behind
     * the token, which is the second half of the answer: a token that works but
     * belongs to someone else's store is not a working connection, and only the
     * store name coming back proves it is the right one.
     */
    public function verify(): CrmResult
    {
        $config = config('crm.drivers.zid');

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout((int) $config['timeout'])
                ->acceptJson()
                ->get($this->endpoint('/v1/managers/account/profile'));

            if (! $response->successful()) {
                return CrmResult::failure(
                    error: $this->explain($response->status(), $response),
                    httpStatus: $response->status(),
                    retryable: CrmResult::retryableForStatus($response->status()),
                    response: $response->json() ?? ['raw' => $response->body()],
                );
            }

            $store = $response->json('user.store');
            $store = is_array($store) ? $store : [];

            /*
             * The token authenticated, but possibly against a different store
             * than the one entered beside it. Saying "connected" here would
             * hide exactly the mix-up this screen exists to catch — two Zid
             * stores under one account, the wrong id pasted from the dashboard.
             */
            $expected = (string) ($config['store_id'] ?? '');
            $actual = (string) ($store['id'] ?? '');

            if ($expected !== '' && $actual !== '' && $expected !== $actual) {
                return CrmResult::failure(
                    error: "The token belongs to Zid store {$actual}, not {$expected}.",
                    httpStatus: $response->status(),
                    retryable: false,
                    response: ['store' => $store],
                );
            }

            // Whatever names the store to a human, in the order a human would
            // recognise it. Blank-but-present fields are skipped, not shown.
            $named = collect([$store['title'] ?? null, $store['username'] ?? null, $actual])
                ->first(fn ($value) => filled($value));

            return CrmResult::success(
                externalId: $named !== null ? (string) $named : null,
                httpStatus: $response->status(),
                response: ['store' => $store],
            );
        } catch (Throwable $e) {
            return CrmResult::fromException($e);
        }
    }

    public function pushLead(Lead $lead): CrmResult
    {
        $config = config('crm.drivers.zid');
        $payload = $this->toZidLead($lead);

        try {
            $response = Http::withHeaders($this->headers($lead->locale))
                ->timeout((int) $config['timeout'])
                ->asJson()
                ->post($this->endpoint('/v1/managers/store/leads'), $payload);

            if ($response->successful()) {
                return CrmResult::success(
                    externalId: (string) ($response->json('id') ?? $response->json('data.id') ?? $lead->uuid),
                    httpStatus: $response->status(),
                    request: $payload,
                    response: $response->json() ?? [],
                );
            }

            return CrmResult::failure(
                error: $this->explain($response->status(), $response),
                httpStatus: $response->status(),
                retryable: CrmResult::retryableForStatus($response->status()),
                request: $payload,
                response: $response->json() ?? ['raw' => $response->body()],
            );
        } catch (Throwable $e) {
            return CrmResult::fromException($e, $payload);
        }
    }

    /**
     * The headers every Zid call carries. See the class docblock for why the
     * access token appears twice.
     *
     * @return array<string, string>
     */
    private function headers(?string $locale = null): array
    {
        $config = config('crm.drivers.zid');

        // The manager token names the store; the OAuth token names the
        // application asking. Zid wants both, and falls back to the documented
        // single-token shortcut when no application has been registered yet.
        $manager = (string) $config['access_token'];
        $asking = filled($config['oauth_token'] ?? null) ? (string) $config['oauth_token'] : $manager;

        $headers = [
            'Authorization' => 'Bearer '.$asking,
            'X-Manager-Token' => $manager,
            'Accept' => 'application/json',
        ];

        // Zid rejects an empty Store-Id outright, so an unset one is omitted
        // rather than sent blank.
        if (filled($config['store_id'] ?? null)) {
            $headers['Store-Id'] = (string) $config['store_id'];
            $headers['Role'] = 'Manager';
        }

        if ($locale !== null) {
            $headers['Accept-Language'] = $locale;
        }

        return $headers;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('crm.drivers.zid.base_url'), '/').$path;
    }

    /**
     * Turn a failed response into something the person on the connection screen
     * can act on. "Zid returned HTTP 401" tells an operator nothing they can
     * fix; "the token was rejected" points at the field to correct.
     */
    private function explain(int $status, Response $response): string
    {
        $reason = match (true) {
            $status === 401 || $status === 403 => 'Zid rejected the access token — it is wrong, expired, or lacks permission for this store.',
            $status === 404 => 'Zid has no resource at that path.',
            $status === 422 => 'Zid rejected the request body.',
            $status === 429 => 'Zid is rate-limiting this store; the request was not processed.',
            $status >= 500 => 'Zid is unavailable right now.',
            default => "Zid returned HTTP {$status}.",
        };

        $said = $this->whatZidSaid($response);

        return $said === null ? $reason : "{$reason} Zid said: {$said}";
    }

    /**
     * Zid's own words, whatever shape it chose to put them in.
     *
     * `message` is a string on some endpoints and a bag of per-field arrays on
     * others. Typing this parameter as `?string` was a crash, and a crash here
     * replaced the provider's actual complaint with a PHP TypeError — losing
     * exactly the sentence that says what is wrong with the credentials.
     */
    private function whatZidSaid(Response $response): ?string
    {
        $body = $response->json();

        if (! is_array($body)) {
            // A non-JSON body is usually an HTML error page; a few hundred
            // characters of markup helps nobody.
            return Str::limit(trim($response->body()), 200) ?: null;
        }

        $words = [];

        // Bound to a variable first: `array_walk_recursive` takes its array by
        // reference, and handing it a function's return value raises a notice
        // that Laravel promotes to an exception under test — which swallowed
        // the provider's message all over again.
        $reported = Arr::only($body, ['message', 'description', 'error', 'errors', 'status']);

        // Flattened so a nested `{"message": {"store_id": ["..."]}}` still
        // reaches the screen as a readable line.
        array_walk_recursive($reported, function ($value) use (&$words): void {
            if (is_scalar($value) && filled($value)) {
                $words[] = (string) $value;
            }
        });

        return $words === [] ? null : Str::limit(implode(' · ', array_unique($words)), 300);
    }

    /** @return array<string, mixed> */
    private function toZidLead(Lead $lead): array
    {
        $payload = $this->payload($lead);
        $payload['title'] = $this->subject($lead);

        return $payload;
    }
}
