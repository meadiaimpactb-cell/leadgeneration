<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages CRUD (§9.1) — "add, edit and delete pages with no technical help".
 *
 * A page's body is not edited here; it is composed in the section builder,
 * which is what makes any page shape possible without a developer.
 */
class PageController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Page::class);

        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::query()
                ->with('translations')
                ->withCount('sections')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Page $page): array => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $page->t('title'),
                    'status' => $page->status,
                    'publishedAt' => $page->published_at?->toIso8601String(),
                    'indexable' => $page->is_indexable,
                    'sections' => $page->sections_count,
                    'locales' => $page->translatedLocales(),
                    'previewUrl' => $page->previewUrl(),
                ]),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('Admin/Pages/Edit', [
            'page' => null,
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('Admin/Pages/Edit', [
            'page' => $this->payload($page),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Page::class);

        $page = new Page(['created_by' => $request->user()->id]);
        $this->persist($request, $page);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', __('admin.saved'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('update', $page);

        $this->persist($request, $page);

        return back()->with('success', __('admin.saved'));
    }

    /**
     * Publish or unpublish. Separate from update() because it is a separate
     * permission (§9.1) and a separate decision.
     */
    public function publish(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('publish', $page);

        $publish = $request->boolean('published');

        $page->forceFill([
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? ($page->published_at ?? now()) : null,
        ])->save();

        return back()->with('success', __('admin.saved'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $page->delete();

        return redirect()
            ->route('admin.pages.index')
            // The old URL keeps resolving only if a redirect is added (§22.7),
            // which the panel prompts for on the redirects screen.
            ->with('success', __('admin.deleted'));
    }

    private function persist(Request $request, Page $page): void
    {
        $rules = [
            'slug' => ['required', 'string', 'max:191'],
            'template' => ['nullable', 'string', 'max:64'],
            'is_indexable' => ['boolean'],
        ];

        foreach (array_keys(config('site.locales')) as $locale) {
            $rules["translations.{$locale}.title"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.subtitle"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.excerpt"] = ['nullable', 'string', 'max:2000'];
            $rules["translations.{$locale}.meta_title"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.meta_description"] = ['nullable', 'string', 'max:512'];
            // This page's own target phrases. The site-wide list and its
            // coverage report live on the keywords screen; this is the value
            // that reaches the page's own <meta name="keywords">.
            $rules["translations.{$locale}.keywords"] = ['nullable', 'string', 'max:512'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($page, $data): void {
            $page->fill([
                'slug' => Str::slug($data['slug'], '-', null),
                'template' => $data['template'] ?? 'default',
                'is_indexable' => (bool) ($data['is_indexable'] ?? true),
            ])->save();

            foreach ($data['translations'] ?? [] as $locale => $values) {
                if (blank($values['title'] ?? null)) {
                    // No title in this locale means the page does not exist
                    // there — it is hidden from that site and sitemap (§12).
                    $page->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $page->translations()->updateOrCreate(['locale' => $locale], $values);
            }
        });
    }

    /** @return array<string, mixed> */
    private function payload(Page $page): array
    {
        $translations = [];

        foreach (array_keys(config('site.locales')) as $locale) {
            $row = $page->translationFor($locale);

            $translations[$locale] = [
                'title' => $row?->title,
                'subtitle' => $row?->subtitle,
                'excerpt' => $row?->excerpt,
                'meta_title' => $row?->meta_title,
                'meta_description' => $row?->meta_description,
                'keywords' => $row?->keywords,
            ];
        }

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'template' => $page->template,
            'status' => $page->status,
            'is_indexable' => $page->is_indexable,
            'previewUrl' => $page->previewUrl(),
            'translations' => $translations,
        ];
    }
}
