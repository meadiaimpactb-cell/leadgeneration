<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Brand;
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
 * Not replaceable from here: the four brand colours. §10.2 fixes them from the
 * approved identity, and every contrast guarantee in §10.8 is calculated
 * against those exact values — `--action-600` exists because #D7653B on white
 * fails AA below 18px/700. A colour picker would let one save turn a compliant
 * site into an inaccessible one with no warning. Changing the palette is an
 * identity decision, and it is applied deliberately in the token layer.
 */
class BrandController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $brand = app(Brand::class);

        return Inertia::render('Admin/Brand', [
            'assets' => collect(Brand::COLLECTIONS)
                ->mapWithKeys(fn (string $collection): array => [
                    $collection => $brand->asset($collection),
                ])->all(),
            'palette' => Brand::PALETTE,
        ]);
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
