<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Browser icons, drawn from the identity rather than exported by hand
|--------------------------------------------------------------------------
| Run: php scripts/make-favicons.php
|
| WHAT IS DRAWN, AND WHY IT IS NOT THE LOGO
|
| The Amad Craft lockup is two lines of type — «أمد الحرف» over «Amad Craft».
| At 16×16 that is four illegible smudges. What survives at that size is the
| Sadu weave: two crossing hairline zigzags, which is the identity's signature
| element (§10.1) and already the shape in public/favicon.svg.
|
| So the icon IS the mark, not a shrunken logo, and it matches what the tab
| already showed anyone whose browser took the SVG.
|
| WHY GD AND NOT AN EXPORT TOOL
|
| The shape is three primitives on a rounded square. Imagick is not installed
| here, and adding a rasteriser to draw five rectangles would be a dependency
| for a job PHP can already do. Everything is drawn at 8× and downsampled,
| which is what gives the diagonals their smooth edge.
|
| The .ico wraps PNGs rather than BMPs: every browser that matters has read
| PNG-in-ICO for over a decade, and it avoids hand-rolling BMP masks.
*/

const NAVY = [0x00, 0x25, 0x46];
const GOLD = [0xDC, 0xAD, 0x75];
const ORANGE = [0xD7, 0x65, 0x3B];

/** Supersampling factor — the whole reason the diagonals do not stair-step. */
const SS = 8;

/**
 * The mark at one size.
 *
 * @param  bool  $rounded  square for Apple and Android, which apply their own
 *                         mask; rounded for the browser tab, which does not.
 */
function mark(int $size, bool $rounded = true): GdImage
{
    $big = $size * SS;

    $canvas = imagecreatetruecolor($big, $big);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);

    $navy = imagecolorallocate($canvas, ...NAVY);

    if ($rounded) {
        // 4/32 of the width, the radius the SVG uses.
        imagefilledrectangle($canvas, 0, 0, $big - 1, $big - 1, $navy);
        roundCorners($canvas, $big, (int) round($big * 4 / 32));
    } else {
        imagefilledrectangle($canvas, 0, 0, $big - 1, $big - 1, $navy);
    }

    // The weave, in the SVG's own 32-unit coordinates.
    $unit = $big / 32;

    /*
     * Fewer peaks when the icon is small.
     *
     * The full mark crosses four times across 32 units. At 16px that is four
     * peaks in sixteen pixels: the two threads collide into a smudge and the
     * weave stops reading as a weave. Two peaks at the same amplitude keeps
     * the crossing legible, which is the whole point of the shape. Above 32px
     * there is room for the mark as drawn.
     */
    $coarse = $size <= 32;

    $gold = imagecolorallocate($canvas, ...GOLD);
    $orange = imagecolorallocatealpha($canvas, ORANGE[0], ORANGE[1], ORANGE[2], 32);

    if ($coarse) {
        stroke($canvas, [[5, 21], [16, 11], [27, 21]], $unit, $gold, 3 * $unit);
        stroke($canvas, [[5, 11], [16, 21], [27, 11]], $unit, $orange, 2 * $unit);
    } else {
        stroke($canvas, [[4, 20], [10, 12], [16, 20], [22, 12], [28, 20]], $unit, $gold, 2.2 * $unit);

        // The counter-thread is lighter and thinner — it reads as depth, not
        // as a second line, which is what makes two zigzags a weave.
        stroke($canvas, [[4, 12], [10, 20], [16, 12], [22, 20], [28, 12]], $unit, $orange, 1.4 * $unit);
    }

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $canvas, 0, 0, 0, 0, $size, $size, $big, $big);
    imagedestroy($canvas);

    return $out;
}

/** A polyline with round joins, drawn as overlapping discs. */
function stroke(GdImage $im, array $points, float $unit, int $colour, float $width): void
{
    $r = $width / 2;

    for ($i = 0; $i < count($points) - 1; $i++) {
        [$x1, $y1] = $points[$i];
        [$x2, $y2] = $points[$i + 1];

        $ax = $x1 * $unit;
        $ay = $y1 * $unit;
        $bx = $x2 * $unit;
        $by = $y2 * $unit;

        $steps = (int) max(abs($bx - $ax), abs($by - $ay));

        for ($s = 0; $s <= $steps; $s++) {
            $t = $steps === 0 ? 0 : $s / $steps;
            $x = $ax + ($bx - $ax) * $t;
            $y = $ay + ($by - $ay) * $t;

            imagefilledellipse($im, (int) round($x), (int) round($y), (int) round($r * 2), (int) round($r * 2), $colour);
        }
    }
}

/** Punch the corners back to transparent. */
function roundCorners(GdImage $im, int $size, int $radius): void
{
    $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagealphablending($im, false);

    foreach ([[0, 0, 1, 1], [$size - 1, 0, -1, 1], [0, $size - 1, 1, -1], [$size - 1, $size - 1, -1, -1]] as [$ox, $oy, $sx, $sy]) {
        for ($x = 0; $x < $radius; $x++) {
            for ($y = 0; $y < $radius; $y++) {
                $dx = $radius - $x;
                $dy = $radius - $y;

                if (sqrt($dx * $dx + $dy * $dy) > $radius) {
                    imagesetpixel($im, $ox + $x * $sx, $oy + $y * $sy, $clear);
                }
            }
        }
    }

    imagealphablending($im, true);
}

function pngBytes(GdImage $im): string
{
    ob_start();
    imagepng($im, null, 9);

    return (string) ob_get_clean();
}

/** An .ico carrying one PNG per size. */
function ico(array $sizes): string
{
    $count = count($sizes);
    $header = pack('vvv', 0, 1, $count);
    $entries = '';
    $data = '';
    $offset = 6 + $count * 16;

    foreach ($sizes as $size => $png) {
        $entries .= pack(
            'CCCCvvVV',
            $size >= 256 ? 0 : $size,   // 0 means 256 in the ICO format
            $size >= 256 ? 0 : $size,
            0,                           // palette
            0,                           // reserved
            1,                           // colour planes
            32,                          // bits per pixel
            strlen($png),
            $offset
        );

        $data .= $png;
        $offset += strlen($png);
    }

    return $header.$entries.$data;
}

// ---------------------------------------------------------------------

$public = dirname(__DIR__).'/public';

$written = [];

foreach ([16 => true, 32 => true, 48 => true] as $size => $rounded) {
    $icoParts[$size] = pngBytes(mark($size, $rounded));
}

file_put_contents($public.'/favicon.ico', ico($icoParts));
$written[] = 'favicon.ico (16, 32, 48)';

foreach ([
    'favicon-32x32.png' => [32, true],
    'favicon-16x16.png' => [16, true],
    // Apple and Android mask the corners themselves, so these ship square.
    'apple-touch-icon.png' => [180, false],
    'android-chrome-192x192.png' => [192, false],
    'android-chrome-512x512.png' => [512, false],
] as $name => [$size, $rounded]) {
    file_put_contents($public.'/'.$name, pngBytes(mark($size, $rounded)));
    $written[] = "{$name} ({$size}×{$size})";
}

foreach ($written as $line) {
    echo "wrote  {$line}\n";
}
