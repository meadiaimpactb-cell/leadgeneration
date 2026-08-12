/**
 * Browser icons, generated from the icon the client supplied.
 *
 * Run: node scripts/make-favicons.mjs
 *
 * SOURCE OF TRUTH
 *
 * `public/images/amadcraft.ico` — the client's own file, flattened to
 * `public/brand/icon-source.png` by scripts/ico-to-png.py. It replaced two
 * earlier attempts, both mine: the Sadu weave, and a render of
 * logo-stacked.svg. Neither is used any more. To change the icon, replace the
 * .ico, rerun the Python step, then rerun this.
 *
 * WHY THIS RESIZES RATHER THAN JUST LINKING TO THE FILE
 *
 * Two reasons, and the second is the important one.
 *
 * The supplied file is a single 256×256 image in an uncompressed DIB: 264KB
 * fetched for a browser tab.
 *
 * And it is composed as an app icon, not as a favicon. Measured, the wordmark
 * occupies 69% of the width but only **20% of the height** — the rest is
 * empty navy. Scaled straight down, the mark would be about three pixels tall
 * at 16×16, which is not small: it is absent. The margin is trimmed here and
 * a consistent clear space added back, so the same artwork is as large as the
 * canvas allows at every size.
 *
 * WHAT THIS CANNOT FIX
 *
 * The lockup is roughly 3.5:1. Inside a square, a 16px icon gives it about
 * five pixels of height for two lines of type — it will read as the brand's
 * colour and silhouette, not as words. That is true of every wide wordmark in
 * a tab and no pipeline changes it; the honest alternative is a fragment of
 * the mark rather than the whole of it, which is a design decision and so the
 * client's to make. Retina tabs use the 32px icon, where it is markedly
 * better.
 */

import sharp from 'sharp';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const PUBLIC = join(ROOT, 'public');
const SOURCE = join(PUBLIC, 'brand/icon-source.png');

if (!existsSync(SOURCE)) {
    throw new Error(
        `${SOURCE} is missing. Rebuild it from the client's icon:\n` +
            '  python3 scripts/ico-to-png.py public/images/amadcraft.ico public/brand/icon-source.png'
    );
}

/** The artwork's own ground, so trimming and padding are invisible. */
const NAVY = { r: 0, g: 37, b: 70, alpha: 1 };

/*
 * Trimmed once, reused for every size.
 *
 * `trim` removes the uniform border by comparing against the top-left pixel —
 * which is the navy ground here — leaving just the wordmark.
 */
const artwork = await sharp(readFileSync(SOURCE))
    .trim({ threshold: 12 })
    .toBuffer();

const { width, height } = await sharp(artwork).metadata();
console.log(`source trimmed to ${width}×${height} (was 256×256)`);

/**
 * One square icon: the wordmark as large as it goes, with clear space.
 *
 * `contain` keeps the lockup's proportions — a wordmark stretched to fill a
 * square is a different logo — and the padding is the identity's own navy.
 */
async function icon(size) {
    /*
     * No margin at all in a tab.
     *
     * The lockup is 3.5:1, so inside a square it is already surrounded by
     * empty navy on two sides — adding more would shrink the only part of the
     * icon that carries meaning. Larger icons keep clear space (§23) because
     * they have it to spare.
     */
    const inset = size <= 32 ? 0 : 0.12;
    const inner = Math.max(1, Math.round(size * (1 - inset * 2)));

    return sharp(artwork)
        .resize(inner, inner, { fit: 'inside', kernel: 'lanczos3' })
        .extend({
            top: 0,
            bottom: 0,
            left: 0,
            right: 0,
            background: NAVY,
        })
        .resize(size, size, { fit: 'contain', background: NAVY })
        .png({ compressionLevel: 9 })
        .toBuffer();
}

/** An .ico carrying one PNG per size. */
function ico(entries) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(entries.length, 4);

    let offset = 6 + entries.length * 16;
    const dir = [];

    for (const { size, data } of entries) {
        const e = Buffer.alloc(16);
        e.writeUInt8(size >= 256 ? 0 : size, 0);
        e.writeUInt8(size >= 256 ? 0 : size, 1);
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

const icoEntries = [];

for (const size of [16, 32, 48]) {
    icoEntries.push({ size, data: await icon(size) });
}

writeFileSync(join(PUBLIC, 'favicon.ico'), ico(icoEntries));
console.log('wrote  favicon.ico (16, 32, 48)');

for (const [name, size] of [
    ['favicon-16x16.png', 16],
    ['favicon-32x32.png', 32],
    ['apple-touch-icon.png', 180],
    ['android-chrome-192x192.png', 192],
    ['android-chrome-512x512.png', 512],
]) {
    writeFileSync(join(PUBLIC, name), await icon(size));
    console.log(`wrote  ${name} (${size}×${size})`);
}

/*
 * The hand-drawn favicon.svg is gone.
 *
 * The client's artwork is a bitmap, and a vector drawn by hand sitting beside
 * it would be a second icon that drifts from the first the moment either
 * changes. One source, one set — so the <link> for it goes too.
 */
writeFileSync(join(PUBLIC, 'favicon-256x256.png'), await icon(256));
console.log('wrote  favicon-256x256.png');
