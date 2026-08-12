/**
 * Browser icons, rendered from the real Amad Craft lockup.
 *
 * Run: node scripts/make-favicons.mjs
 *
 * WHAT CHANGED, AND WHY
 *
 * These were drawn as the Sadu weave — the identity's signature mark — on the
 * reasoning that two lines of type cannot survive 16×16. The client asked for
 * the actual logo instead, so the actual logo is what is rendered: the STACKED
 * lockup, because at 516×470 it is very nearly square and therefore the one
 * that fills an icon canvas without being letterboxed into a sliver.
 *
 * The trade is real and worth stating plainly: at 16px the wordmark reads as
 * the brand's silhouette rather than as words. That is how most typographic
 * logos behave in a tab, and it is what was asked for. At 32px and above —
 * bookmarks, the Android launcher, the iOS home screen — it is legible.
 *
 * WHY sharp AND NOT GD
 *
 * The lockup is seventeen Bézier paths of Arabic calligraphy. GD draws
 * primitives and cannot rasterise a path, so the previous generator could only
 * ever produce geometry it drew itself. sharp is already in this project as a
 * Vite dependency, renders SVG through librsvg, and adds nothing to install.
 */

import sharp from 'sharp';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const PUBLIC = join(ROOT, 'public');

const NAVY = '#002546';

/**
 * The lockup's own paths, lifted out of the identity file.
 *
 * It ships as two groups, and which is which matters here: the first holds the
 * eight paths of the Arabic wordmark, the second the nine of "Amad Craft".
 */
const source = readFileSync(join(PUBLIC, 'brand/logo-stacked.svg'), 'utf8');
const [vx, vy, vw, vh] = source.match(/viewBox="([^"]+)"/)[1].split(/\s+/).map(Number);

const pathsIn = (svg) => [...svg.matchAll(/<path[^>]*\sd="([^"]+)"/g)].map((m) => m[1]);
const groups = [...source.matchAll(/<g>([\s\S]*?)<\/g>/g)].map((m) => m[1]);

const ARABIC = pathsIn(groups[0] ?? '');
const FULL = pathsIn(source);

if (ARABIC.length === 0 || FULL.length === 0) {
    throw new Error('logo-stacked.svg did not parse - check its group structure');
}

/**
 * The band the Arabic wordmark occupies, in the file's own coordinates.
 *
 * Measured from the paths rather than assumed: the Latin line sits from y=700
 * downward, and cropping to this band is what lets the wordmark fill the
 * canvas instead of sharing it.
 */
const ARABIC_BAND = { top: 300, bottom: 650 };

/**
 * One icon.
 *
 * The lockup is scaled to a share of the canvas and centred, so the mark keeps
 * clear space on every side — §23 asks for the height of the «ا» glyph, and a
 * logo pressed against the edge of a 16px square is the one way to make it
 * less legible than it already is.
 *
 * @param {number} size
 * @param {boolean} rounded  Apple and Android apply their own mask; the tab
 *                           does not, so only the tab icon gets a radius.
 */
function iconSvg(size, rounded) {
    /*
     * Small icons carry the Arabic wordmark alone.
     *
     * The whole lockup at 16x16 puts "Amad Craft" in about four pixels of
     * height, and the result is a grey smear under the Arabic - it makes the
     * icon look damaged rather than small. Dropping to the Arabic wordmark
     * roughly doubles the glyph height and the mark is recognisable again.
     *
     * Above 32px - bookmarks, the iOS home screen, the Android launcher -
     * there is room for both lines, so both are drawn.
     */
    const small = size <= 32;
    const paths = small ? ARABIC : FULL;
    const top = small ? ARABIC_BAND.top : vy;
    const height = small ? ARABIC_BAND.bottom - ARABIC_BAND.top : vh;

    // Clear space around the mark. Tighter when small: at sixteen pixels a
    // wide margin costs more than it protects.
    const inset = small ? 0.1 : 0.16;
    const box = size * (1 - inset * 2);
    const scale = Math.min(box / vw, box / height);
    const w = vw * scale;
    const h = height * scale;
    const dx = (size - w) / 2;
    const dy = (size - h) / 2;
    const radius = rounded ? size * (4 / 32) : 0;

    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
  <rect width="${size}" height="${size}" rx="${radius}" fill="${NAVY}"/>
  <g transform="translate(${dx} ${dy}) scale(${scale}) translate(${-vx} ${-top})" fill="#FFFFFF">
    ${paths.map((d) => `<path d="${d}"/>`).join('')}
  </g>
</svg>`);
}

const png = (size, rounded = true) =>
    sharp(iconSvg(size, rounded), { density: 384 }).png({ compressionLevel: 9 }).toBuffer();

/** An .ico carrying one PNG per size. */
function ico(entries) {
    const count = entries.length;
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(count, 4);

    let offset = 6 + count * 16;
    const dir = [];

    for (const { size, data } of entries) {
        const e = Buffer.alloc(16);
        e.writeUInt8(size >= 256 ? 0 : size, 0);
        e.writeUInt8(size >= 256 ? 0 : size, 1);
        e.writeUInt8(0, 2);
        e.writeUInt8(0, 3);
        e.writeUInt16LE(1, 4);
        e.writeUInt16LE(32, 6);
        e.writeUInt32LE(data.length, 8);
        e.writeUInt32LE(offset, 12);
        dir.push(e);
        offset += data.length;
    }

    return Buffer.concat([header, ...dir, ...entries.map((e) => e.data)]);
}

// ---------------------------------------------------------------------

const icoSizes = [16, 32, 48];
const icoEntries = [];

for (const size of icoSizes) {
    icoEntries.push({ size, data: await png(size, true) });
}

writeFileSync(join(PUBLIC, 'favicon.ico'), ico(icoEntries));
console.log('wrote  favicon.ico (16, 32, 48)');

const files = [
    ['favicon-16x16.png', 16, true],
    ['favicon-32x32.png', 32, true],
    ['apple-touch-icon.png', 180, false],
    ['android-chrome-192x192.png', 192, false],
    ['android-chrome-512x512.png', 512, false],
];

for (const [name, size, rounded] of files) {
    writeFileSync(join(PUBLIC, name), await png(size, rounded));
    console.log(`wrote  ${name} (${size}×${size})`);
}

/*
 * The SVG the tab prefers, written from the same source so the two can never
 * drift. Browsers that take it get the lockup at whatever size they render.
 */
writeFileSync(join(PUBLIC, 'favicon.svg'), iconSvg(32, true));
console.log('wrote  favicon.svg');
