/**
 * Normalises the partner logos dropped into public/images/partner/ into a set
 * the site can serve directly.
 *
 * Why this exists rather than pointing the seeder at the raw files:
 *
 *   · The Partner `logo` collection registers no conversions, so whatever is
 *     attached is what the browser downloads. wadi-alathar.png is 4500×3883 —
 *     a 200 KB download for a logo the strip renders 48 px tall, which alone
 *     would eat a third of the §15.1 page-weight budget.
 *   · alinma.avif is HEIF. GD in this PHP build cannot open it, so the media
 *     library would store a file it can never re-process.
 *   · The logos arrive with wildly different padding around the mark. Trimming
 *     the uniform border makes them optically the same size in the strip
 *     instead of one floating small inside its own whitespace.
 *
 * Originals are left untouched; this only writes new files.
 *
 * Run: node tools/prepare-partner-logos.mjs
 */
import sharp from 'sharp';
import { statSync } from 'node:fs';

const DIR = 'public/images/partner';

/* 48 px in the strip, so 160 px covers a 3× display without enlarging any
   source. Every input is already taller than this except the AMAD lockup,
   which is left at its native height. */
const TARGET_HEIGHT = 160;

const LOGOS = [
    ['alinma.avif', 'alinma.webp'],
    ['alahsa.png', 'al-ahsa-chamber.webp'],
    ['hayyat-alturath.jpg', 'heritage-commission.webp'],
    ['wadi-alathar.png', 'impact-valley.webp'],
    ['alianama-amid-alharaf.png', 'amad-program.webp'],
];

for (const [from, to] of LOGOS) {
    const out = await sharp(`${DIR}/${from}`)
        // threshold 12 so JPEG ringing around a white background still trims.
        .trim({ threshold: 12 })
        .resize({ height: TARGET_HEIGHT, fit: 'inside', withoutEnlargement: true })
        .webp({ quality: 92, effort: 6 })
        .toFile(`${DIR}/${to}`);

    const before = Math.round(statSync(`${DIR}/${from}`).size / 1024);
    const after = Math.round(out.size / 1024);

    console.log(`${from} → ${to}  ${out.width}×${out.height}  ${before}KB → ${after}KB`);
}
