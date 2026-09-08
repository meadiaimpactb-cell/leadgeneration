<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * An sRGB colour, and the two kinds of arithmetic the palette needs.
 *
 * WCAG relative luminance, for measuring whether one colour is legible on
 * another (§10.8), and OKLab, for making a colour lighter or darker without
 * changing what colour it is.
 *
 * OKLab rather than HSL, and the difference is not academic. HSL's "lightness"
 * is a channel average: at L=50% a blue is nearly black and a yellow is nearly
 * white, so darkening a palette by a fixed HSL step gives four shades with
 * four different apparent weights, and the pale end of the scale turns muddy.
 * OKLab's L is perceptual, so one step means one step whatever the hue — which
 * is what lets a client pick any colour and get a set of shades that hangs
 * together the way the approved one does.
 *
 * Immutable: every operation returns a new colour, so a token cannot be
 * mutated halfway through being derived.
 */
final class Color
{
    private function __construct(
        public readonly int $r,
        public readonly int $g,
        public readonly int $b,
    ) {}

    /**
     * @param  string  $hex  `#RGB` or `#RRGGBB`, with or without the hash.
     */
    public static function fromHex(string $hex): self
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            // #abc means #aabbcc — expanded here so callers never have to care.
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new InvalidArgumentException("Not a colour: {$hex}");
        }

        return new self(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    /** Uppercase, always six digits — one spelling, so values compare as strings. */
    public function toHex(): string
    {
        return sprintf('#%02X%02X%02X', $this->r, $this->g, $this->b);
    }

    /**
     * The channels as CSS wants them inside `rgb(… / alpha)`.
     *
     * This is what makes a tint follow its base colour. The stylesheets hold
     * about a hundred low-alpha washes of the four identity colours, and every
     * one of them used to be a literal `rgba(0, 37, 70, 0.08)` — a navy that
     * did not move when navy did.
     */
    public function channels(): string
    {
        return "{$this->r} {$this->g} {$this->b}";
    }

    /** WCAG 2.1 relative luminance. */
    public function luminance(): float
    {
        $linear = static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linear($this->r)
             + 0.7152 * $linear($this->g)
             + 0.0722 * $linear($this->b);
    }

    /** WCAG contrast ratio, from 1.0 (identical) to 21.0 (black on white). */
    public function contrast(self $other): float
    {
        $a = $this->luminance();
        $b = $other->luminance();

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Lightness, chroma and hue in OKLCh.
     *
     * @return array{0: float, 1: float, 2: float} L (0–1), C, H (radians)
     */
    public function toOklch(): array
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, [$this->r, $this->g, $this->b]);

        $cbrt = static fn (float $v): float => $v < 0 ? -((-$v) ** (1 / 3)) : $v ** (1 / 3);

        $l = $cbrt(0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b);
        $m = $cbrt(0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b);
        $s = $cbrt(0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return [$lightness, sqrt($a * $a + $bb * $bb), atan2($bb, $a)];
    }

    /**
     * The colour at that lightness, chroma and hue.
     *
     * Out-of-gamut results are clipped per channel. A very light, very
     * saturated request has no sRGB answer, and clipping returns the closest
     * one a screen can actually show rather than failing — which is what a
     * colour picker needs, since a client can ask for anything.
     */
    public static function fromOklch(float $lightness, float $chroma, float $hue): self
    {
        $a = $chroma * cos($hue);
        $b = $chroma * sin($hue);

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $linear = [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];

        $channels = array_map(static function (float $v): int {
            $v = max(0.0, min(1.0, $v));
            $srgb = $v <= 0.0031308 ? $v * 12.92 : 1.055 * ($v ** (1 / 2.4)) - 0.055;

            return (int) max(0, min(255, (int) round($srgb * 255)));
        }, $linear);

        return new self($channels[0], $channels[1], $channels[2]);
    }

    /**
     * A shade of this colour: lightness moved by `$byLightness`, chroma scaled
     * by `$chromaFactor`, hue untouched.
     *
     * Hue is what makes a colour that colour, so it is the one thing a derived
     * shade never changes. Chroma is scaled rather than shifted because the
     * pale end of a ramp needs proportionally less of it — a 4% tint at full
     * chroma reads as a stain, not a tint.
     */
    public function shade(float $byLightness, float $chromaFactor = 1.0): self
    {
        [$lightness, $chroma, $hue] = $this->toOklch();

        return self::fromOklch(
            max(0.0, min(1.0, $lightness + $byLightness)),
            max(0.0, $chroma * $chromaFactor),
            $hue,
        );
    }

    /**
     * This colour's hue and chroma, at a lightness of your choosing.
     *
     * The counterpart to `shade()`: that one moves a colour a step from where
     * it is, this one puts it where it has to be. A page ground is pale
     * because it is a page ground, not because the colour it is tinted with
     * happens to be light.
     */
    public function at(float $lightness, float $chromaFactor = 1.0): self
    {
        [, $chroma, $hue] = $this->toOklch();

        return self::fromOklch(
            max(0.0, min(1.0, $lightness)),
            max(0.0, $chroma * $chromaFactor),
            $hue,
        );
    }

    /**
     * The nearest shade of this colour that is legible against `$against`.
     *
     * Returns this colour untouched when it already clears the ratio. When it
     * does not, lightness steps away from `$against` until it does, or until
     * there is nowhere left to go — black and white are the ends of the scale,
     * and past them there is no darker or lighter answer to give.
     *
     * This is what makes the palette safe to hand to a client. `--action-600`
     * exists because white text on the identity's burnt orange fails AA below
     * 18px/700; deriving it by a fixed step would reproduce that failure for
     * any lighter orange somebody picks. Deriving it by contrast cannot.
     */
    public function legibleOn(self $against, float $ratio): self
    {
        if ($this->contrast($against) >= $ratio) {
            return $this;
        }

        // Away from the other colour: darker if it is light, lighter if it is dark.
        $step = $against->luminance() > 0.18 ? -0.01 : 0.01;
        $candidate = $this;

        // 100 steps covers the whole 0–1 lightness range, so the loop always
        // ends: at a passing shade, or at the end of the scale.
        for ($i = 0; $i < 100; $i++) {
            $candidate = $candidate->shade($step);

            if ($candidate->contrast($against) >= $ratio) {
                return $candidate;
            }
        }

        return $candidate;
    }
}
