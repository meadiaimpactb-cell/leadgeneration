<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lead form builder (§6.1, §9.1).
 *
 * Lets Amad Craft turn a field on or off, reorder it, and rename it in both
 * languages — without a developer.
 *
 * Two rules are enforced here rather than trusted to the interface:
 *   · the contact field can never be disabled or made optional; without it
 *     there is no lead, and the lead count is the site's only metric (§1)
 *   · a field's `key` is never editable, because historic leads in `extra`
 *     are stored against it
 */
class LeadFieldController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return Inertia::render('Admin/LeadFields', [
            'fields' => LeadField::query()
                ->with('translations')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (LeadField $field): array => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'type' => $field->type,
                    'isEnabled' => $field->is_enabled,
                    'isRequired' => $field->is_required,
                    'isLocked' => $field->is_locked,
                    'maxLength' => $field->max_length,
                    'options' => $field->options ?? [],
                    'labels' => collect(array_keys(config('site.locales')))
                        ->mapWithKeys(fn (string $l): array => [$l => [
                            'label' => $field->t('label', $l),
                            'placeholder' => $field->t('placeholder', $l),
                            'help' => $field->t('help', $l),
                        ]]),
                ]),
            'locales' => array_keys(config('site.locales')),
            'types' => LeadField::TYPES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer', 'exists:lead_fields,id'],
            'fields.*.isEnabled' => ['boolean'],
            'fields.*.isRequired' => ['boolean'],
            'fields.*.maxLength' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'fields.*.labels' => ['required', 'array'],
            'fields.*.labels.*.label' => ['nullable', 'string', 'max:120'],
            'fields.*.labels.*.placeholder' => ['nullable', 'string', 'max:160'],
            'fields.*.labels.*.help' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['fields'] as $position => $row) {
                $field = LeadField::query()->find($row['id']);

                if ($field === null) {
                    continue;
                }

                $field->fill([
                    // A locked field stays on and stays required whatever the
                    // request says — the UI disables those controls, but the
                    // rule is enforced here (§9.2).
                    'is_enabled' => $field->is_locked ? true : (bool) ($row['isEnabled'] ?? false),
                    'is_required' => $field->is_locked ? true : (bool) ($row['isRequired'] ?? false),
                    'max_length' => $row['maxLength'] ?? $field->max_length,
                    'sort_order' => $position,
                ])->save();

                foreach ($row['labels'] as $locale => $values) {
                    if (blank($values['label'] ?? null)) {
                        // A field with no label in a locale cannot be shown
                        // there; the form omits it rather than rendering a
                        // blank one (§12).
                        $field->translations()->where('locale', $locale)->delete();

                        continue;
                    }

                    $field->translations()->updateOrCreate(['locale' => $locale], $values);
                }
            }
        });

        $this->flushCache();

        return back()->with('success', __('admin.saved'));
    }

    private function flushCache(): void
    {
        LeadField::flushCache();
    }
}
