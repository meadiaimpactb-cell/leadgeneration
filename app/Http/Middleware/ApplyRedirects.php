<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the managed 301s (§13, §22.7).
 *
 * The `redirects` table existed from the first migration but nothing ever
 * consulted it, so every row an administrator added did nothing. This is what
 * makes it real: any request that would 404 is checked against the table
 * before the 404 is returned.
 *
 * Checking only on a 404 — rather than on every request — means a live URL is
 * never shadowed by a stale redirect row, and the lookup costs nothing on the
 * pages people actually visit.
 *
 * This is what carries the legacy store links through a domain move (§16).
 */
class ApplyRedirects
{
    /** Rows change rarely and are read on every 404, so the set is cached. */
    private const CACHE_KEY = 'redirects.active';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethodSafe()) {
            return $response;
        }

        $redirect = $this->match($request->path());

        if ($redirect === null) {
            return $response;
        }

        // Counted so the client can see which legacy URLs still receive
        // traffic, and retire the rows that no longer do.
        Redirect::query()->whereKey($redirect['id'])->increment('hits');

        return redirect()->to($redirect['to'], $redirect['status']);
    }

    /**
     * @return array{id: int, to: string, status: int}|null
     */
    private function match(string $path): ?array
    {
        $needle = '/'.trim($path, '/');

        foreach ($this->rules() as $rule) {
            if ($rule['from'] === $needle) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return list<array{id: int, from: string, to: string, status: int}>
     */
    private function rules(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): array => Redirect::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (Redirect $r): array => [
                'id' => $r->id,
                'from' => '/'.trim($r->from_path, '/'),
                'to' => $r->to_path,
                'status' => $r->status_code,
            ])
            ->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
