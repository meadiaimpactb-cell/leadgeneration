<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Setting;
use App\Support\Brand;
use App\Support\Palette;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The brand assets the client owns and can replace (§10, §23).
 *
 * Which assets are here, and which are not, is a deliberate line.
 *
 * Replaceable: the logo (light and dark lockups), the browser icon, and the
 * default share image. Those are Amad Craft's property, they change when the
 * identity is refreshed, and waiting on a developer to swap a PNG is exactly
 * the dependence §19 says the handover must remove.
 *
 * The four brand colours are replaceable too, as of the client's request of
 * 8 September 2026. They were not, and the reason they were not still stands:
 * every contrast guarantee in §10.8 was calculated against those exact values,
 * and `--action-600` exists because white on #D7653B fails AA below 18px/700.
 * A plain colour picker would let one save turn a compliant site into an
 * inaccessible one with nothing to warn the person who did it.
 *
 * So the picker is not plain. App\Support\Palette derives the whole ramp from
 * the four choices, and derives the two shades that carry a guarantee by
 * measuring the guarantee rather than by a fixed step — link text still clears
 * 4.5:1 on paper and white still clears 4.5:1 on the action colour whatever is
 * chosen. What cannot be guaranteed is measured and shown on this screen at
 * the moment the choice is made. An identity decision stays the client's; what
 * it costs is no longer invisible.
 */
class BrandController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $brand = app(Brand::class);
        $palette = app(Palette::class);

        return Inertia::render('Admin/Brand', [
            'assets' => collect(Brand::COLLECTIONS)
                ->mapWithKeys(fn (string $collection): array => [
                    $collection => $brand->asset($collection),
                ])->all(),

            // What the client has chosen, keyed by the four family names.
            'palette' => $palette->colours(),
            // What §23 approved, so the screen can offer a way back to it and
            // say which of the four have been changed.
            'identity' => Palette::IDENTITY,
            // Every shade derived from the choices, so the screen shows the
            // real theme rather than four swatches and a promise.
            'derived' => $palette->tokens(),
            'contrast' => $palette->report(),
        ]);
    }

    /**
     * Replace the four identity colours.
     *
     * All four every time, so the stored palette is always a complete one. A
     * per-colour endpoint would allow a half-applied identity to exist between
     * two requests, and this value is read by every page on the site.
     */
    public function palette(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $families = collect(Palette::IDENTITY)->keys();

        $data = $request->validate(
            // Six digits, with the hash: the one spelling the token layer, the
            // derivation and the <style> block all agree on. A shorthand or a
            // colour name would be valid CSS and unparseable here.
            $families->mapWithKeys(fn (string $name): array => [
                $name => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ])->all(),

            // Named once and used for all four. The stock message for `regex`
            // says a format is invalid without saying which format — which,
            // for a client typing a colour, is no help at all.
            $families->mapWithKeys(fn (string $name): array => [
                "{$name}.regex" => __('admin.brand_colour_format'),
            ])->all(),
        );

        foreach ($data as $name => $hex) {
            $hex = strtoupper($hex);
            $row = ['group' => Palette::GROUP, 'key' => "colour.{$name}"];

            /*
             * A colour back at its approved value has its row removed, not
             * rewritten to the same thing.
             *
             * The site is the identity because nothing says otherwise, which
             * is what makes "reset" genuinely a reset rather than a fifth copy
             * of §23 living in the database — and what keeps one answer to
             * "which of these has the client actually changed".
             */
            if ($hex === Palette::IDENTITY[$name]) {
                Setting::query()->where($row)->delete();

                continue;
            }

            Setting::query()->updateOrCreate($row, ['value' => $hex, 'is_public' => false]);
        }

        // The palette is read through the settings cache, and it is read on
        // every request by app.blade.php. Not clearing it here would serve the
        // old colours until the cache happened to be dropped for another
        // reason — which looks exactly like a save that did not work.
        app(Settings::class)->forget();

        return back()->with('success', __('admin.saved'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'collection' => ['required', 'string', 'in:'.implode(',', Brand::COLLECTIONS)],
            // `mimes` checks the real type, not the filename (§9.2).
            'file' => ['required', 'file', 'mimes:svg,png,webp,jpg,jpeg,avif,ico', 'max:2048'],
        ]);

        app(Brand::class)->replace($data['collection'], $request->file('file'));

        return back()->with('success', __('settings.saved'));
    }

    public function destroy(Request $request, Media $medium): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        // Removing an uploaded logo falls back to the identity file shipped
        // with the build, so the site is never left without a mark.
        $medium->delete();

        return back()->with('success', __('admin.deleted'));
    }
}
