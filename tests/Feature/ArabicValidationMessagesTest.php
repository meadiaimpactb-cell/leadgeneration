<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every validation rule this application uses must speak Arabic (§12, §22.3).
 *
 * `resources/lang/ar/validation.php` translates only the rules in use, which is
 * the right policy — a guessed Arabic sentence is worse than none. What made it
 * dangerous was the belief that an untranslated rule falls back to English. It
 * does not: APP_FALLBACK_LOCALE is `ar`, so a missing rule has nowhere to fall
 * back to and Laravel prints the key. An administrator whose campaign end date
 * preceded its start was told «validation.after_or_equal», and the same was
 * true of `required_with` and of every password rule guarding panel accounts.
 *
 * Two guards, because the failure is silent either way:
 *
 *   · the rules known to be in use render a real message
 *   · no parameterised rule appears in app/ without a translation to match
 *
 * The second is the one that matters over time. It is limited to the `rule:arg`
 * form on purpose: those are unambiguously validation rules, whereas a bare
 * 'file' or 'string' is as likely to be an ordinary word, and a guard that
 * cries wolf gets deleted.
 */
class ArabicValidationMessagesTest extends TestCase
{
    /** Where each rule is used, so a reader can check the list is still true. */
    private const IN_USE = [
        // CampaignController — a campaign that ends before it starts.
        'after_or_equal' => [['b' => '2020-01-01', 'a' => '2026-01-01'], 'after_or_equal:a'],
        // PageController, ResourceController — the bilingual editor's rule.
        'required_with' => [['b' => null, 'a' => 'x'], 'required_with:a'],
    ];

    #[Test]
    public function every_rule_in_use_answers_in_arabic(): void
    {
        $this->app->setLocale('ar');

        foreach (self::IN_USE as $rule => [$data, $spec]) {
            $message = Validator::make($data, ['b' => $spec])->errors()->first();

            $this->assertStringStartsNotWith('validation.', $message,
                "The `{$rule}` rule printed its own key instead of a message. "
                .'Add it to resources/lang/ar/validation.php — there is no English to fall back to.');
        }
    }

    /**
     * Password::min(12)->letters()->numbers()->symbols() guards every panel
     * account, and each link in that chain carries its own message.
     */
    #[Test]
    public function the_password_rules_answer_in_arabic(): void
    {
        $this->app->setLocale('ar');

        $chains = [
            'letters' => [Password::min(12)->letters(), '123456789012'],
            'numbers' => [Password::min(12)->numbers(), 'abcdefghijkl'],
            'symbols' => [Password::min(12)->symbols(), 'abcdefghij12'],
            'mixed' => [Password::min(12)->mixedCase(), 'abcdefghij12'],
        ];

        foreach ($chains as $name => [$rule, $value]) {
            $message = Validator::make(['password' => $value], ['password' => $rule])->errors()->first();

            $this->assertStringStartsNotWith('validation.', $message,
                "The password `{$name}` rule printed its own key while an administrator was choosing a password.");
        }
    }

    /**
     * The guard against the next one. Any `rule:argument` written in app/ must
     * have an Arabic message, or it will print its key the first time a real
     * person trips it.
     */
    #[Test]
    public function no_parameterised_rule_in_the_codebase_is_untranslated(): void
    {
        $vocabulary = array_keys(require base_path('resources/lang/en/validation.php'));
        $translated = require base_path('resources/lang/ar/validation.php');

        /*
         * Two names in this codebase read exactly like rules and are not.
         * Listing them here rather than loosening the pattern keeps the guard
         * sharp: if either ever becomes a real rule, delete its line and the
         * test will ask for the translation.
         *
         *   decimal — an Eloquent cast, `'value_numeric' => 'decimal:2'`
         *   enum    — a ContentRegistry field type; ResourceController rewrites
         *             `enum:a,b,c` into the `in:a,b,c` rule, and `in` is
         *             translated
         */
        $notRules = ['decimal', 'enum'];

        $untranslated = [];

        foreach ($this->phpFilesIn(app_path()) as $path) {
            $source = (string) file_get_contents($path);

            preg_match_all('/[\'"]([a-z_]+):[^\'"]+[\'"]/', $source, $matches);

            foreach ($matches[1] as $rule) {
                if (! in_array($rule, $vocabulary, true)
                    || in_array($rule, $notRules, true)
                    || isset($translated[$rule])) {
                    continue;
                }

                $untranslated[$rule][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $untranslated,
            'These validation rules are used but carry no Arabic message, so they will '
            .'render as their own key: '.implode(', ', array_keys($untranslated)));
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
