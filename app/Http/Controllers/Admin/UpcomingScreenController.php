<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The screens whose place in the panel is agreed but whose function is not
 * built yet.
 *
 * They exist as real routes rather than dead sidebar links for two reasons:
 * the shape of the finished panel is what the client is approving, and a link
 * that 404s teaches an operator to distrust the menu. Each one states what it
 * will do and which phase builds it.
 *
 * Every entry here is temporary by definition. As a phase lands, its screen
 * moves to its own controller and drops out of this list — when the list is
 * empty, this file goes with it.
 */
class UpcomingScreenController extends Controller
{
    /**
     * Screen key => the phase that builds it.
     *
     * The key is also the translation key for the title and the description,
     * so a screen cannot be added here without saying what it is for.
     */
    private const SCREENS = [
        'notifications' => 2,
        'spam' => 2,
        'activity' => 4,
        'backups' => 4,
    ];

    public function show(Request $request, string $screen): Response
    {
        abort_unless(array_key_exists($screen, self::SCREENS), 404);
        abort_unless($request->user()->can('settings.manage') || $request->user()->can('pages.view'), 403);

        return Inertia::render('Admin/Upcoming', [
            'screen' => $screen,
            'phase' => self::SCREENS[$screen],
        ]);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::SCREENS);
    }
}
