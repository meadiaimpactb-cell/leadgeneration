<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Page;
use App\Models\Section;
use App\Models\Sector;
use App\Models\Solution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Which library images a section uses, and in which order.
 *
 * Separate from MediaController because these are two different verbs on two
 * different things: that one owns the files, this one owns the arrangement.
 * Nothing here creates or destroys an image — the worst it can do is leave a
 * section showing nothing, which one click puts back.
 */
class SectionMediaController extends Controller
{
    /** Slots a section has. Mirrors Section::registerMediaCollections. */
    private const COLLECTIONS = ['image', 'gallery'];

    /**
     * Set a slot's contents to exactly this list, in this order.
     *
     * One endpoint for insert, replace, remove and reorder, because from the
     * database's point of view they are the same operation — the panel sends
     * the arrangement it wants and gets it. Four endpoints would be four
     * chances for the order to disagree with what is on screen.
     */
    public function sync(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($request, $section);

        $data = $request->validate([
            'collection' => ['required', 'string', Rule::in(self::COLLECTIONS)],
            'media' => ['present', 'array'],
            'media.*' => ['integer', 'exists:media,id'],
        ]);

        $ids = array_map('intval', $data['media']);

        // `image` holds one. Enforced here rather than trusted from the panel:
        // the single-image components read the first entry and would silently
        // ignore the rest, which looks like the upload failed.
        if ($data['collection'] === 'image') {
            $ids = array_slice($ids, 0, 1);
        }

        $section->syncAttachedMedia($data['collection'], $ids);

        return back()->with('success', __('admin.saved'));
    }

    /**
     * Take one image out of a section.
     *
     * The image itself is untouched: it stays in the library and stays in
     * every other section using it. That is the whole distinction this screen
     * has to teach, so the two actions are deliberately different words in
     * different places — "remove" here, "delete" only in the media library.
     */
    public function detach(Request $request, Section $section, Media $medium): RedirectResponse
    {
        $this->authorizeSection($request, $section);

        $data = $request->validate([
            'collection' => ['required', 'string', Rule::in(self::COLLECTIONS)],
        ]);

        $section->detachMedia($medium->id, $data['collection']);

        return back()->with('success', __('admin.saved'));
    }

    /**
     * A section inherits the permission of whatever owns it — editing a page's
     * section image needs the right to edit that page.
     */
    private function authorizeSection(Request $request, Section $section): void
    {
        $allowed = match ($section->sectionable_type) {
            Page::class => $request->user()->can('pages.update'),
            Solution::class => $request->user()->can('solutions.manage'),
            Sector::class => $request->user()->can('sectors.manage'),
            Campaign::class => $request->user()->can('campaigns.update'),
            default => false,
        };

        abort_unless($allowed, 403);
    }
}
