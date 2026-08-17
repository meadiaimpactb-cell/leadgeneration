<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nothing is destroyed on one click (§9.1).
 *
 * The panel asked "are you sure?" with the browser's `confirm()` on eight
 * screens and did not ask at all on nine more — a trash icon that removed a
 * brand asset, a notification recipient, a keyword, a redirect, a gallery
 * image or a card the moment it was pressed. The client found this by pressing
 * one.
 *
 * The guard is a scan rather than a click-through because the failure mode is
 * a NEW delete button added later without one. A person reviewing that diff
 * sees a button; this sees a missing question.
 */
class DestructiveActionsAreConfirmedTest extends TestCase
{
    /** @return list<string> */
    private function adminSources(): array
    {
        $roots = [
            base_path('resources/js/Pages/Admin'),
            base_path('resources/js/Components/admin'),
        ];

        $files = [];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'vue') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $path);
    }

    /** A component with its comments removed, so prose is not read as code. */
    private function code(string $source): string
    {
        return (string) preg_replace(
            ['#/\*.*?\*/#s', '#<!--.*?-->#s', '#(^|\s)//[^\n]*#'],
            ' ',
            $source
        );
    }

    #[Test]
    public function every_delete_request_is_asked_about_first(): void
    {
        $unguarded = [];

        foreach ($this->adminSources() as $path) {
            $lines = explode("\n", (string) file_get_contents($path));

            foreach ($lines as $number => $line) {
                if (! str_contains($line, 'router.delete')) {
                    continue;
                }

                // The question is asked in the handler that issues the request,
                // so look back over the enclosing function rather than the line.
                $preceding = implode("\n", array_slice($lines, max(0, $number - 12), min(12, $number)));

                if (! str_contains($preceding, 'confirmDialog')) {
                    $unguarded[] = $this->relative($path).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $unguarded,
            'These delete requests fire without asking. Guard them with '
            .'`if (!(await confirmDialog({ message: t(\'admin.confirm_delete\') }))) return;`');
    }

    #[Test]
    public function the_panel_never_falls_back_to_the_browsers_own_dialog(): void
    {
        /*
         * `confirm()` cannot be styled, names its buttons in the browser's
         * language rather than the editor's, lays them out to the browser's
         * direction rather than the document's, and blocks the main thread.
         * The panel has its own; nothing should reach for the native one again.
         */
        $offenders = [];

        foreach ($this->adminSources() as $path) {
            // Comments are stripped first. ConfirmDialog.vue explains at length
            // what it replaces and why, and a scan that reads prose as code
            // reports the cure as the disease.
            $source = $this->code((string) file_get_contents($path));

            // `confirmDialog(` must not count as a match for `confirm(`.
            if (preg_match('/(?<![\w.])(confirm|alert)\s*\(/', $source) === 1) {
                $offenders[] = $this->relative($path);
            }
        }

        $this->assertSame([], $offenders,
            'These screens use the browser dialog. Use admin/confirm.js instead.');
    }

    #[Test]
    public function the_dialog_is_mounted_once_for_the_whole_panel(): void
    {
        // A screen that renders its own copy would stack dialogs; the promise
        // API only works because there is exactly one host.
        $layout = (string) file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue'));

        $this->assertStringContainsString('<ConfirmDialog />', $layout);

        $mounts = 0;

        foreach ($this->adminSources() as $path) {
            $mounts += substr_count((string) file_get_contents($path), '<ConfirmDialog');
        }

        $this->assertSame(0, $mounts,
            'ConfirmDialog belongs in AdminLayout only — a per-screen copy stacks dialogs.');
    }

    #[Test]
    public function the_dialog_keeps_its_wording_in_both_languages(): void
    {
        foreach (['confirm_title', 'confirm_yes', 'confirm_delete', 'confirm_delete_redirect'] as $key) {
            foreach (['ar', 'en'] as $locale) {
                $line = __("admin.{$key}", locale: $locale);

                $this->assertNotSame("admin.{$key}", $line,
                    "admin.{$key} is missing from the {$locale} panel strings.");
            }
        }
    }

    #[Test]
    public function the_dialog_carries_no_physical_direction(): void
    {
        // §22.6. The close control sits opposite the title by `space-between`
        // on a row that inherits the document's direction — never by testing
        // the locale and picking a side.
        $source = (string) file_get_contents(base_path('resources/js/Components/admin/ConfirmDialog.vue'));

        $this->assertSame(0, preg_match('/(?<!-)(left|right)\s*:/', $source),
            'ConfirmDialog uses a physical direction. Logical properties only.');
    }
}
