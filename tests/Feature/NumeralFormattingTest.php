<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ContactValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Digits are Latin (0–9) everywhere the platform displays them.
 *
 * Arabic-Indic digits (١٨٬٥٠٠) read as a different number to anyone scanning a
 * dashboard, cannot be pasted into a spreadsheet or a CRM, and have no tabular
 * variant in the body face — so columns of figures fail to align despite
 * `font-variant-numeric: tabular-nums`. §10.3 puts numerals in IBM Plex Sans
 * with tabular-nums, which assumes Latin digits.
 *
 * This is a DISPLAY rule only. Visitors may still TYPE Arabic-Indic digits
 * into the contact field, and that must keep working — see the last test.
 */
class NumeralFormattingTest extends TestCase
{
    private const ARABIC_INDIC = '/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u';

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $roots = [
            base_path('resources/lang'),
            base_path('resources/js'),
            base_path('database/seeders'),
        ];

        $files = [];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'vue'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    #[Test]
    public function no_displayed_string_contains_arabic_indic_digits(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $path) {
            $contents = (string) file_get_contents($path);

            // useFormat documents the rule and quotes an example of what it
            // prevents; that comment is the one legitimate occurrence.
            if (str_ends_with(str_replace('\\', '/', $path), 'Composables/useFormat.js')) {
                continue;
            }

            if (preg_match(self::ARABIC_INDIC, $contents) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $offenders,
            'These files display Arabic-Indic digits. Numerals are Latin across the platform; '
            .'format numbers and dates through resources/js/Composables/useFormat.js.');
    }

    #[Test]
    public function the_shared_formatter_pins_latin_numerals_and_the_gregorian_calendar(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/Composables/useFormat.js'));

        // `ar-SA` alone yields Arabic-Indic digits AND the Hijri calendar — a
        // lead timestamp would then not match the same lead in the CRM.
        $this->assertStringContainsString('ar-SA-u-ca-gregory-nu-latn', $source);
        $this->assertStringContainsString("NumberFormat('en-US')", $source);
    }

    #[Test]
    public function no_component_formats_numbers_or_dates_on_its_own(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $path) {
            if (! str_ends_with($path, '.vue')) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            // One formatter, one rule. A component reaching for toLocaleString
            // directly is how the inconsistency crept in the first time.
            if (preg_match('/toLocale(String|DateString|TimeString)/', $contents) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $offenders,
            'Use useFormat() rather than toLocaleString(), so numerals and the calendar stay consistent.');
    }

    #[Test]
    public function visitors_may_still_type_arabic_indic_digits(): void
    {
        // Display is Latin; input is not restricted. A visitor typing their
        // number on an Arabic keyboard must still reach the sales team (§6.1).
        $parsed = ContactValue::parse('٠٥١٢٣٤٥٦٧٨');

        $this->assertNotNull($parsed);
        $this->assertSame('+966512345678', $parsed->value);
    }
}
