<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One CRUD screen for every content entity that shares the same shape
 * (§9.1) — solutions, sectors, products, stories, reports, programmes,
 * partners, impact numbers.
 *
 * The alternative was nine near-identical controllers and nine near-identical
 * Vue forms. Everything that actually differs between them lives in
 * ContentRegistry, which is also what the bilingual editor reads to know
 * which fields to draw.
 */
class ResourceController extends Controller
{
    public function index(Request $request, string $entity): Response
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        $model = $config['model'];
        $flag = $config['flag'];

        $records = $model::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'title' => $this->titleOf($record, $config),
                'slug' => $record->slug ?? null,
                'active' => (bool) $record->{$flag},
                'sortOrder' => $record->sort_order,
                // The "translation missing" indicator (§9.1).
                'locales' => $record->translatedLocales(),
            ]);

        return Inertia::render('Admin/Content/Index', [
            'entity' => $entity,
            'records' => $records,
            'locales' => array_keys(config('site.locales')),
            'meta' => $this->meta($config),
        ]);
    }

    public function create(Request $request, string $entity): Response
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        if (! $config['creatable']) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Admin/Content/Edit', [
            'entity' => $entity,
            'record' => null,
            'meta' => $this->meta($config),
            'options' => $this->relationOptions($config),
            'taxonomyOptions' => $this->taxonomyOptions($config),
            'taxonomyValues' => $this->taxonomyValues(null, $config),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function edit(Request $request, string $entity, int $id): Response
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        $record = $config['model']::query()->with('translations')->findOrFail($id);

        return Inertia::render('Admin/Content/Edit', [
            'entity' => $entity,
            'record' => $this->payload($record, $config),
            'meta' => $this->meta($config),
            'options' => $this->relationOptions($config),
            'taxonomyOptions' => $this->taxonomyOptions($config),
            'taxonomyValues' => $this->taxonomyValues($record, $config),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        if (! $config['creatable']) {
            throw new NotFoundHttpException;
        }

        $record = new $config['model'];
        $this->persist($request, $record, $config);

        return redirect()
            ->route('admin.content.edit', [$entity, $record->getKey()])
            ->with('success', __('admin.saved'));
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        $record = $config['model']::query()->findOrFail($id);
        $this->persist($request, $record, $config);

        return back()->with('success', __('admin.saved'));
    }

    public function destroy(Request $request, string $entity, int $id): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        if (! $config['deletable']) {
            throw new NotFoundHttpException;
        }

        $config['model']::query()->findOrFail($id)->delete();

        return redirect()
            ->route('admin.content.index', $entity)
            ->with('success', __('admin.deleted'));
    }

    /**
     * Drag-and-drop ordering (§9.1).
     */
    public function reorder(Request $request, string $entity): RedirectResponse
    {
        $config = $this->config($entity);
        $this->authorizeEntity($request, $config);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data, $config): void {
            foreach ($data['order'] as $position => $id) {
                $config['model']::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return back()->with('success', __('admin.saved'));
    }

    // ---------------------------------------------------------------- //

    /**
     * Validate and write both the base row and every locale's translation.
     */
    private function persist(Request $request, Model $record, array $config): void
    {
        $rules = ['active' => ['boolean']];

        foreach ($config['attributes'] as $name => $type) {
            $rules["attributes.{$name}"] = $this->rulesFor($type);
        }

        // Many-to-many pickers, e.g. the audience segments a solution serves.
        foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
            $rules["taxonomies.{$name}"] = ['array'];
            $rules["taxonomies.{$name}.*"] = ['integer', 'exists:'.$taxonomy['table'].',id'];
        }

        foreach (array_keys(config('site.locales')) as $locale) {
            foreach ($config['fields'] as $field => $type) {
                $rules["translations.{$locale}.{$field}"] = array_merge(
                    ['nullable'],
                    $this->presenceRulesFor($config, $locale, $field),
                    ['string', $type === 'text' ? 'max:255' : 'max:20000'],
                );
            }
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($record, $config, $data): void {
            $attributes = $data['attributes'] ?? [];

            if (isset($attributes['slug'])) {
                $attributes['slug'] = Str::slug($attributes['slug'], '-', null);
            }

            $record->fill($attributes);
            $record->{$config['flag']} = (bool) ($data['active'] ?? false);
            $record->save();

            foreach ($data['translations'] ?? [] as $locale => $values) {
                // A locale left entirely blank is not written at all, so the
                // record stays honestly untranslated rather than gaining an
                // empty row that would let it leak into that locale's site
                // and sitemap (§12).
                if (collect($values)->filter(fn ($v) => filled($v))->isEmpty()) {
                    $record->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $record->translations()->updateOrCreate(['locale' => $locale], $values);
            }

            /*
             * `sync` with the order carried in the pivot, so the client's
             * chosen order survives. An absent key means "not submitted",
             * which is left alone; an empty array means "cleared", which is
             * honoured — the two are different and must not be conflated.
             */
            foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
                if (! array_key_exists($name, $data['taxonomies'] ?? [])) {
                    continue;
                }

                $ids = collect($data['taxonomies'][$name])
                    ->values()
                    ->mapWithKeys(fn (int $id, int $i): array => [$id => ['sort_order' => $i]])
                    ->all();

                $record->{$taxonomy['relation']}()->sync($ids);
            }
        });
    }

    /**
     * What makes one translated field compulsory, if anything does.
     *
     * Every field stays `nullable`, because a record that exists in one
     * language and not the other is normal and §12 wants it stored that way —
     * and because empty strings arrive here as null, so dropping `nullable`
     * makes `string` fail on every blank box.
     *
     * An entity may name fields it cannot be described without — the outcome
     * line on a training track — and those become required *for a locale being
     * written*, never for a locale left alone. `required_with` is an implicit
     * rule, so it still fires alongside `nullable`, and it fires only once
     * something else in that same column has been typed: a blank English side
     * still means "not translated" and still deletes its row.
     *
     * @return list<string>
     */
    private function presenceRulesFor(array $config, string $locale, string $field): array
    {
        if (! in_array($field, $config['required'] ?? [], true)) {
            return [];
        }

        $siblings = array_map(
            fn (string $other): string => "translations.{$locale}.{$other}",
            array_values(array_diff(array_keys($config['fields']), [$field])),
        );

        return ['required_with:'.implode(',', $siblings)];
    }

    /** @return list<string> */
    private function rulesFor(string $type): array
    {
        return match (true) {
            $type === 'number' => ['nullable', 'numeric'],
            $type === 'url' => ['nullable', 'url', 'max:512'],
            $type === 'slug' => ['required', 'string', 'max:191'],
            str_starts_with($type, 'relation:') => ['nullable', 'integer'],
            str_starts_with($type, 'enum:') => ['required', 'string',
                'in:'.substr($type, strlen('enum:'))],
            default => ['nullable', 'string', 'max:255'],
        };
    }

    /** @return array<string, mixed> */
    private function payload(Model $record, array $config): array
    {
        $translations = [];

        foreach (array_keys(config('site.locales')) as $locale) {
            $row = $record->translationFor($locale);

            foreach (array_keys($config['fields']) as $field) {
                $translations[$locale][$field] = $row?->getAttribute($field);
            }
        }

        $attributes = [];

        foreach (array_keys($config['attributes']) as $name) {
            $attributes[$name] = $record->getAttribute($name);
        }

        $media = [];

        foreach ($config['media'] as $collection) {
            $media[$collection] = $record->getMedia($collection)
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    // getUrl(), not getFullUrl(): the latter prefixes APP_URL,
                    // so every thumbnail in the panel breaks the moment the
                    // site is opened on a host APP_URL does not name — the dev
                    // port, staging, or after the §16 domain move. Same reason
                    // MediaResource and SectionController use it. An <img>
                    // never needs the host.
                    'url' => $item->getUrl(),
                    'name' => $item->file_name,
                ])->values();
        }

        return [
            'id' => $record->getKey(),
            'active' => (bool) $record->{$config['flag']},
            'attributes' => $attributes,
            'translations' => $translations,
            'media' => $media,
        ];
    }

    /**
     * Options for every `relation:` attribute, so the form can render a
     * select rather than asking for a raw id.
     *
     * @return array<string, list<array{value: int, label: string}>>
     */
    private function relationOptions(array $config): array
    {
        $options = [];

        foreach ($config['attributes'] as $name => $type) {
            if (! str_starts_with($type, 'relation:')) {
                continue;
            }

            $target = ContentRegistry::get(substr($type, strlen('relation:')));

            $options[$name] = $target['model']::query()
                ->with('translations')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Model $r): array => [
                    'value' => $r->getKey(),
                    'label' => (string) $this->titleOf($r, $target),
                ])
                ->all();
        }

        return $options;
    }

    /**
     * Choices for every many-to-many picker on this entity.
     *
     * Labelled from the target's own first translated field, exactly as the
     * single-relation selects are, so a segment renamed in its own editor
     * renames itself here too.
     *
     * @return array<string, list<array{value: int, label: string}>>
     */
    private function taxonomyOptions(array $config): array
    {
        $options = [];

        foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
            $target = ContentRegistry::get($taxonomy['entity']);

            $options[$name] = $target['model']::query()
                ->with('translations')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Model $r): array => [
                    'value' => $r->getKey(),
                    'label' => (string) $this->titleOf($r, $target),
                ])
                ->all();
        }

        return $options;
    }

    /**
     * The ids currently selected for each picker, in their pivot order.
     *
     * @return array<string, list<int>>
     */
    private function taxonomyValues(?Model $record, array $config): array
    {
        $selected = [];

        foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
            // modelKeys() rather than pluck('<table>.id'): the relation knows
            // its own key, and naming the table here would silently return
            // nothing the day a second taxonomy is added.
            $selected[$name] = $record === null
                ? []
                : $record->{$taxonomy['relation']}()->get()->modelKeys();
        }

        return $selected;
    }

    /**
     * The first translated field is the record's human label.
     */
    private function titleOf(Model $record, array $config): ?string
    {
        $first = array_key_first($config['fields']);

        return $record->t($first) ?? $record->slug ?? $record->name ?? "#{$record->getKey()}";
    }

    /** @return array<string, mixed> */
    private function meta(array $config): array
    {
        return [
            'fields' => $config['fields'],
            // So the editor marks what it is going to refuse to save without,
            // rather than teaching it through an error after the fact.
            'required' => $config['required'] ?? [],
            'attributes' => $config['attributes'],
            'media' => $config['media'],
            'creatable' => $config['creatable'],
            'deletable' => $config['deletable'],
            'hasSections' => $config['hasSections'],
            // Label + entity per picker, so the form can title the field
            // without knowing what a taxonomy is.
            'taxonomies' => $config['taxonomies'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function config(string $entity): array
    {
        if (! ContentRegistry::exists($entity)) {
            throw new NotFoundHttpException;
        }

        return ContentRegistry::get($entity);
    }

    /**
     * §9.2: authorisation is enforced here, never by hiding a button.
     */
    private function authorizeEntity(Request $request, array $config): void
    {
        abort_unless($request->user()->can($config['permission']), 403);
    }
}
