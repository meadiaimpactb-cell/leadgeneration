<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * A managed 301 (§13, §22.7).
 *
 * Existing search rankings are protected by never letting a URL simply
 * disappear: every path change is recorded here so the old address keeps
 * resolving. This is also what carries the legacy store links through a
 * domain move (§16).
 */
class Redirect extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_code' => 'integer',
        ];
    }

    /**
     * Find the redirect matching a request path, if any.
     * Paths are compared without a leading slash and without the query string.
     */
    public static function match(string $path): ?self
    {
        $normalised = '/'.trim(parse_url($path, PHP_URL_PATH) ?: '', '/');

        return static::query()
            ->where('is_active', true)
            ->whereIn('from_path', [$normalised, ltrim($normalised, '/')])
            ->first();
    }

    public function recordHit(): void
    {
        $this->newQuery()->whereKey($this->getKey())->increment('hits');
    }
}
