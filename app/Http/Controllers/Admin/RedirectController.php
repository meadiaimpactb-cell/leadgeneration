<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApplyRedirects;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managed 301s (§13).
 *
 * "A `redirects` table to manage 301s from the admin panel — essential to
 * protect indexing of legacy URLs." Every path change goes through here rather
 * than being left to break (§22.7), and this is also how the legacy store
 * links survive a domain move (§16).
 *
 * The hit counter shows which old URLs still receive traffic, so rows can be
 * retired on evidence rather than on a guess.
 */
class RedirectController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('redirects.manage'), 403);

        return Inertia::render('Admin/Redirects', [
            'redirects' => Redirect::query()
                ->orderByDesc('hits')
                ->orderBy('from_path')
                ->get()
                ->map(fn (Redirect $r): array => [
                    'id' => $r->id,
                    'from' => $r->from_path,
                    'to' => $r->to_path,
                    'status' => $r->status_code,
                    'hits' => $r->hits,
                    'isActive' => $r->is_active,
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('redirects.manage'), 403);

        $data = $request->validate([
            'redirects' => ['present', 'array'],
            'redirects.*.id' => ['nullable', 'integer'],
            'redirects.*.from' => ['required', 'string', 'max:255'],
            'redirects.*.to' => ['required', 'string', 'max:512'],
            'redirects.*.status' => ['required', 'integer', 'in:301,302,307,308'],
            'redirects.*.isActive' => ['boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            $kept = [];

            foreach ($data['redirects'] as $row) {
                $from = '/'.trim($row['from'], '/');

                // A redirect pointing at itself would loop forever.
                if ($from === '/'.trim($row['to'], '/')) {
                    continue;
                }

                $redirect = Redirect::query()->updateOrCreate(
                    ['from_path' => $from],
                    [
                        'to_path' => $row['to'],
                        'status_code' => $row['status'],
                        'is_active' => (bool) ($row['isActive'] ?? true),
                    ]
                );

                $kept[] = $redirect->id;
            }

            Redirect::query()->whereNotIn('id', $kept ?: [0])->delete();
        });

        ApplyRedirects::flush();

        return back()->with('success', __('admin.saved'));
    }
}
