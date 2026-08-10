<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Read access to the `settings` table.
 *
 * Everything the client must be able to change without a deploy lives here:
 * contact details, social links, tracking IDs, SEO defaults, feature switches
 * (§14.1, §22.9). Values are cached until a Setting is written.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * @return array<string, mixed> keyed "group.key"
     */
    public function all(): array
    {
        return $this->cache ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()
                ->get()
                ->mapWithKeys(fn (Setting $s): array => ["{$s->group}.{$s->key}" => $s->value])
                ->all()
        );
    }

    /**
     * @param  string  $key  "group.key" — a literal array key, not a path.
     *                       data_get() must not be used here: it would read
     *                       "site.english_enabled" as $all['site']['english_enabled'].
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    /**
     * Only rows flagged is_public are exposed to the browser. Anything else —
     * API keys, internal webhook targets — stays server-side.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY.'.public',
            fn (): array => Setting::query()
                ->where('is_public', true)
                ->get()
                ->mapWithKeys(fn (Setting $s): array => ["{$s->group}.{$s->key}" => $s->value])
                ->all()
        );
    }

    public function forget(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.public');
    }
}
