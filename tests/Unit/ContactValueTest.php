<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ContactValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The single contact field auto-detects email vs phone with no type selector
 * (§6.1). Getting this wrong loses the site's only success metric, so the
 * cases below cover how a Saudi visitor actually types.
 */
class ContactValueTest extends TestCase
{
    #[Test]
    #[DataProvider('emails')]
    public function it_recognises_emails(string $input, string $expected): void
    {
        $parsed = ContactValue::parse($input);

        $this->assertNotNull($parsed, "Expected [{$input}] to parse as an email.");
        $this->assertSame('email', $parsed->type);
        $this->assertSame($expected, $parsed->value);
    }

    /** @return array<string, array{string, string}> */
    public static function emails(): array
    {
        return [
            'plain' => ['name@company.sa', 'name@company.sa'],
            'uppercase' => ['Name@Company.SA', 'name@company.sa'],
            'surrounding spaces' => ['  name@company.sa  ', 'name@company.sa'],
            'inner spaces' => ['name @ company.sa', 'name@company.sa'],
            'full-width at sign' => ['name＠company.sa', 'name@company.sa'],
            'plus addressing' => ['a.b+tag@sub.company.com', 'a.b+tag@sub.company.com'],
        ];
    }

    #[Test]
    #[DataProvider('phones')]
    public function it_normalises_saudi_mobiles_to_e164(string $input): void
    {
        $parsed = ContactValue::parse($input);

        $this->assertNotNull($parsed, "Expected [{$input}] to parse as a phone.");
        $this->assertSame('phone', $parsed->type);
        $this->assertSame('+966512345678', $parsed->value);
    }

    /** @return array<string, array{string}> */
    public static function phones(): array
    {
        return [
            'local with zero' => ['0512345678'],
            'local without zero' => ['512345678'],
            'international plus' => ['+966512345678'],
            'international double zero' => ['00966512345678'],
            'spaced' => ['05 1234 5678'],
            'dashed' => ['05-1234-5678'],
            'arabic indic digits' => ['٠٥١٢٣٤٥٦٧٨'],
            'spaced international' => ['+966 51 234 5678'],
        ];
    }

    #[Test]
    public function it_keeps_plausible_international_numbers(): void
    {
        $parsed = ContactValue::parse('+442071838750');

        $this->assertNotNull($parsed);
        $this->assertSame('phone', $parsed->type);
        $this->assertSame('+442071838750', $parsed->value);
    }

    #[Test]
    #[DataProvider('rejections')]
    public function it_rejects_what_it_cannot_read(string $input): void
    {
        $this->assertNull(ContactValue::parse($input));
    }

    /** @return array<string, array{string}> */
    public static function rejections(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['   '],
            'prose' => ['اتصلوا بي من فضلكم'],
            'malformed email' => ['name@'],
            'email without tld' => ['name@company'],
            'too few digits' => ['12345'],
            'landline not mobile' => ['0112345678'],
            'saudi wrong prefix' => ['0412345678'],
        ];
    }
}
