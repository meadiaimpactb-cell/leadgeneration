<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Color;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic the palette is built on.
 *
 * The client can now pick any four colours and the whole theme is derived from
 * them, which means these few functions decide whether the site stays readable
 * — including for the choices nobody thought to try. So they are tested
 * against known answers rather than against themselves.
 */
class ColourMathsTest extends TestCase
{
    /**
     * WCAG's own worked figures, so a mistake in the luminance curve shows up
     * as a wrong number rather than as a plausible one.
     *
     * @return array<string, array{string, string, float}>
     */
    public static function knownRatios(): array
    {
        return [
            'black on white' => ['#000000', '#FFFFFF', 21.0],
            'white on white' => ['#FFFFFF', '#FFFFFF', 1.0],
            'the identity navy on white' => ['#002546', '#FFFFFF', 15.52],
            'white on the action colour' => ['#FFFFFF', '#B4522C', 5.02],
            // The pair §10.2 names as the reason --action-600 exists at all.
            'white on the raw burnt orange' => ['#FFFFFF', '#D7653B', 3.61],
            // And the pair it names as the reason gold is never text on paper.
            'gold on warm paper' => ['#DCAD75', '#FBF8F3', 1.93],
        ];
    }

    #[Test]
    #[DataProvider('knownRatios')]
    public function it_measures_contrast_the_way_wcag_does(string $a, string $b, float $expected): void
    {
        $this->assertEqualsWithDelta(
            $expected,
            Color::fromHex($a)->contrast(Color::fromHex($b)),
            0.01,
            "The ratio for {$a} on {$b} is not what WCAG's formula gives.",
        );
    }

    #[Test]
    public function contrast_does_not_depend_on_which_colour_is_asked(): void
    {
        $navy = Color::fromHex('#002546');
        $white = Color::fromHex('#FFFFFF');

        $this->assertSame($navy->contrast($white), $white->contrast($navy));
    }

    /**
     * The guarantee the whole feature rests on.
     *
     * Whatever colour a client picks, the shade that carries white text has to
     * clear 4.5:1 — including for colours that are nowhere near able to on
     * their own, like a pale yellow.
     */
    #[Test]
    #[DataProvider('awkwardColours')]
    public function it_finds_a_shade_that_is_legible_under_white(string $hex): void
    {
        $white = Color::fromHex('#FFFFFF');
        $shade = Color::fromHex($hex)->legibleOn($white, 4.5);

        $this->assertGreaterThanOrEqual(
            4.5,
            round($shade->contrast($white), 2),
            "White is not legible on the shade derived from {$hex} ({$shade->toHex()}).",
        );
    }

    #[Test]
    #[DataProvider('awkwardColours')]
    public function it_finds_a_shade_that_is_legible_on_paper(string $hex): void
    {
        $paper = Color::fromHex('#FBF8F3');
        $shade = Color::fromHex($hex)->legibleOn($paper, 4.5);

        $this->assertGreaterThanOrEqual(
            4.5,
            round($shade->contrast($paper), 2),
            "The shade derived from {$hex} ({$shade->toHex()}) is not legible on paper.",
        );
    }

    /** @return array<string, array{string}> */
    public static function awkwardColours(): array
    {
        return [
            'the identity lavender' => ['#8685D8'],
            'the identity orange' => ['#D7653B'],
            'a pale yellow' => ['#FFF176'],
            'near white' => ['#FAFAFA'],
            'pure white' => ['#FFFFFF'],
            'pure black' => ['#000000'],
            'a saturated cyan' => ['#00E5FF'],
            'a mid grey' => ['#808080'],
            'a hot pink' => ['#FF4FD8'],
        ];
    }

    /**
     * A colour that is already legible is handed back untouched.
     *
     * The client picked it; if it works there is no reason to move it, and
     * nudging it anyway would mean the swatch on the panel and the colour on
     * the page were different colours.
     */
    #[Test]
    public function a_colour_that_already_passes_is_left_alone(): void
    {
        $navy = Color::fromHex('#002546');

        $this->assertSame(
            '#002546',
            $navy->legibleOn(Color::fromHex('#FFFFFF'), 4.5)->toHex(),
        );
    }

    /**
     * A pinned shade lands at the lightness asked for whatever it started at.
     *
     * This is what stops the page ground following a dark premium colour down
     * into a dark page — the bug that made a deep amber turn the whole site
     * mid-tone before these were pinned.
     */
    #[Test]
    #[DataProvider('awkwardColours')]
    public function a_pinned_shade_lands_where_it_was_told_to(string $hex): void
    {
        $pinned = Color::fromHex($hex)->at(0.98, 0.081);

        [$lightness] = $pinned->toOklch();

        $this->assertEqualsWithDelta(
            0.98,
            $lightness,
            0.02,
            "A ground pinned at 0.98 came out at {$lightness} from {$hex}.",
        );
    }

    #[Test]
    public function a_shade_keeps_the_hue_it_was_derived_from(): void
    {
        $gold = Color::fromHex('#DCAD75');

        [, , $hue] = $gold->toOklch();
        [, , $shadeHue] = $gold->shade(0.17, 0.12)->toOklch();

        $this->assertEqualsWithDelta($hue, $shadeHue, 0.2, 'A derived shade drifted off its hue.');
    }

    #[Test]
    public function it_round_trips_through_oklch(): void
    {
        foreach (['#002546', '#8685D8', '#D7653B', '#DCAD75', '#FFFFFF', '#000000'] as $hex) {
            [$lightness, $chroma, $hue] = Color::fromHex($hex)->toOklch();

            $this->assertSame(
                $hex,
                Color::fromOklch($lightness, $chroma, $hue)->toHex(),
                "{$hex} did not survive a round trip through OKLCh.",
            );
        }
    }

    #[Test]
    public function it_reads_the_shorthand_and_a_missing_hash(): void
    {
        $this->assertSame('#AABBCC', Color::fromHex('#abc')->toHex());
        $this->assertSame('#002546', Color::fromHex('002546')->toHex());
        $this->assertSame('#002546', Color::fromHex('  #002546  ')->toHex());
    }

    #[Test]
    public function it_refuses_something_that_is_not_a_colour(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::fromHex('rebeccapurple');
    }

    #[Test]
    public function it_publishes_the_channels_the_stylesheets_tint_with(): void
    {
        $this->assertSame('0 37 70', Color::fromHex('#002546')->channels());
    }
}
