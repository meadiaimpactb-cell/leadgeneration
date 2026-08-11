/**
 * Prepare the client's own photographs for the web.
 *
 * `public/images/amadcraft (1..11).jpg` arrive straight from a camera: 16 MB
 * across eleven files, several of them over 4000px wide. Nothing on the page
 * renders wider than about 1200px, so every one of those pixels is download
 * the visitor pays for and never sees.
 *
 * This writes a 1200px-wide WebP beside each original into
 * `public/images/craft/` and leaves the originals untouched, so the run is
 * repeatable and reversible.
 *
 * It also lifts the first frame of the hero GIF into a WebP poster. The GIF is
 * 20.7 MB and is the largest contentful paint on the home page; the poster is
 * ~40 KB and paints immediately, so the pane is never an empty box while the
 * animation downloads. Converting the GIF itself to MP4/WebM needs ffmpeg,
 * which is not installed here — see the note in the handover.
 *
 *   node tools/prepare-craft-photos.mjs
 */
import { mkdir, readdir, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const IMAGES = path.resolve('public/images');
const OUT = path.join(IMAGES, 'craft');
const GIF = path.resolve('public/videos/amadcraft.gif');

const MAX_EDGE = 1200;
const QUALITY = 78;

const kb = (n) => `${(n / 1024).toFixed(0)} KB`;

async function convert(source, target, maxEdge = MAX_EDGE) {
    const before = (await stat(source)).size;

    const buffer = await sharp(source, { animated: false })
        .rotate() // honour the EXIF orientation before it is stripped
        .resize({ width: maxEdge, height: maxEdge, fit: 'inside', withoutEnlargement: true })
        .webp({ quality: QUALITY })
        .toBuffer();

    await writeFile(target, buffer);

    return { before, after: buffer.length };
}

await mkdir(OUT, { recursive: true });

const sources = (await readdir(IMAGES)).filter((f) => /^amadcraft \(\d+\)\.jpg$/i.test(f)).sort();

let before = 0;
let after = 0;

for (const file of sources) {
    const n = file.match(/\((\d+)\)/)[1];
    const target = path.join(OUT, `craft-${n.padStart(2, '0')}.webp`);

    const size = await convert(path.join(IMAGES, file), target);

    before += size.before;
    after += size.after;

    console.log(`${file}  ${kb(size.before)} -> ${path.basename(target)}  ${kb(size.after)}`);
}

console.log(`\n${sources.length} photographs: ${kb(before)} -> ${kb(after)}`);

// The hero poster, from the GIF's first frame.
try {
    const poster = await convert(GIF, path.join(OUT, 'hero-poster.webp'), 1600);
    console.log(`hero poster: ${kb(poster.before)} -> ${kb(poster.after)}`);
} catch (error) {
    console.warn(`hero poster skipped: ${error.message}`);
}
