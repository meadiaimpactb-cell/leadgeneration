<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        $retired = Page::retiredSlugs();

        /*
         * Where each retired page now sends its visitors.
         *
         * Looked up once and handed to the screen, so a row can say «تُحوَّل
         * إلى ‎/ar#government» instead of leaving an editor to guess whether a
         * page still does anything. One query, not one per row.
         */
        $redirects = $retired === []
            ? collect()
            : Redirect::query()
                ->where('is_active', true)
                ->where('from_path', 'like', '/'.app()->getLocale().'/%')
                ->pluck('to_path', 'from_path');

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
                    /*
                     * The site itself. There is one page with a body since the
                     * landing-page decision, and it was sorting to the bottom
                     * of thirteen rows — the one thing the editor opens every
                     * day, last, under eleven that no longer answer.
                     */
                    'isSite' => $page->slug === 'landing',
                    'retired' => in_array($page->slug, $retired, true),
                    'redirectsTo' => $redirects->get('/'.app()->getLocale().'/'.$page->slug),
                ])
                ->sortBy(fn (array $page): array => [
                    $page['isSite'] ? 0 : 1,
                    $page['retired'] ? 1 : 0,
                ])
                ->values(),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('Admin/Pages/Edit', [
            'page' => null,
            'locales' => array_keys(config('site.locales')),
            // What the search-result preview needs to draw the same string the
            // public page will serve: "Page title — Site name", on the real URL.
            'siteNames' => $this->siteNames(),
            'baseUrl' => rtrim(url('/'), '/'),
        ]);
    }

    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('Admin/Pages/Edit', [
            'page' => $this->payload($page),
            'locales' => array_keys(config('site.locales')),
            // What the search-result preview needs to draw the same string the
            // public page will serve: "Page title — Site name", on the real URL.
            'siteNames' => $this->siteNames(),
            'baseUrl' => rtrim(url('/'), '/'),
        ]);
    }

    /**
     * The site name per language, as the public page appends it.
     *
     * Read from `settings` rather than config so the preview shows the name
     * the client actually set — the whole value of the preview is that it is
     * the same string the visitor will see, not an approximation of it.
     *
     * @return array<string, string|null>
     */
    private function siteNames(): array
    {
        $settings = app(Settings::class);

        return collect(array_keys(config('site.locales')))
            ->mapWithKeys(fn (string $locale): array => [
                $locale => $settings->get("site.name.{$locale}") ?? $settings->get('site.name'),
            ])
            ->all();
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
        /*
         * Normalise BEFORE validating, not after.
         *
         * The slug is stored as `Str::slug()` of what was typed, so validating
         * the raw input asks the wrong question: "Services" is unique against a
         * table holding "services" and passes, then collides on insert. The
         * editor gets a 500 for a mistake the form should have caught. Every
         * rule below therefore sees the value that will actually be written.
         */
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug'), '-', null),
        ]);

        $rules = [
            'slug' => [
                'required',
                'string',
                'max:191',
                /*
                 * `pages.slug` is UNIQUE and the model soft-deletes, so a
                 * deleted page keeps its slug reserved forever — invisibly,
                 * because nothing in the panel lists the bin. Without this
                 * rule the collision surfaced as an unhandled
                 * UniqueConstraintViolationException: a stack trace where a
                 * field error belonged. `withTrashed()` is the point rather
                 * than an oversight; the constraint counts those rows, so the
                 * message must too, and it says where the slug went.
                 */
                Rule::unique('pages', 'slug')->ignore($page->id),
            ],
            'template' => ['nullable', 'string', 'max:64'],
            'is_indexable' => ['boolean'],
        ];

        foreach (array_keys(config('site.locales')) as $locale) {
            /*
             * A title is what makes the page exist in this language.
             *
             * A wholly blank column still means "not translated" and deletes
             * the row, per §12 — that is deliberate. What was not deliberate is
             * what happened one field along: fill in a subtitle, or a meta
             * description, and leave the title empty, and the save reported
             * success while `persist()` below deleted the translation and
             * everything typed into it. No error, no warning, work gone.
             *
             * `required_with` draws the line exactly where the deletion branch
             * does: say nothing and the language is simply absent; say
             * anything at all and the page needs a name.
             */
            $siblings = array_map(
                fn (string $field): string => "translations.{$locale}.{$field}",
                ['subtitle', 'excerpt', 'meta_title', 'meta_description', 'keywords'],
            );

            $rules["translations.{$locale}.title"] = [
                'nullable',
                'required_with:'.implode(',', $siblings),
                'string',
                'max:255',
            ];
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
