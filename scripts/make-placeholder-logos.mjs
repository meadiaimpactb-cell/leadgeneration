/**
 * Neutral stand-in logos for the segment client strip.
 *
 * Run: node scripts/make-placeholder-logos.mjs
 *
 * WHY THESE ARE BLANK MARKS AND NOT NAMES
 *
 * The strip under a segment hero answers "who else has bought". Filling it
 * with the five real organisations already on the site would file them under
 * "client", and DemoContentSeeder::partners() has already ruled on that: the
 * official site lists all five as partners with no client group, so calling
 * any of them a client asserts a relationship that organisation has not
 * published. That is a claim about someone else, and not ours to make.
 *
 * So these carry no wordmark and no initials — nothing that could be read as
 * an organisation. They exist to show the strip's rhythm: how five marks of
 * different widths sit on the baseline, how the marquee moves, how much air
 * the section needs. They are replaced from the panel the day real client
 * logos arrive.
 *
 * Deliberately varied in width, because logos are: a strip tested with five
 * identical rectangles hides exactly the alignment problem it should catch.
 */

import sharp from 'sharp';
import { writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'public/images/placeholder');

mkdirSync(OUT, { recursive: true });

/** Mid grey: reads as "a mark goes here" on both the paper and cream grounds. */
const INK = '#A8AFB6';

/*
 * Five silhouettes, no letters. Width varies; height is constant, because the
 * strip aligns on height and that is the constraint being tested.
 */
const MARKS = [
    { w: 260, shape: `<circle cx="46" cy="60" r="26"/><rect x="88" y="46" width="150" height="12" rx="6"/><rect x="88" y="70" width="104" height="12" rx="6"/>` },
    { w: 200, shape: `<rect x="24" y="34" width="52" height="52" rx="10"/><rect x="92" y="46" width="84" height="12" rx="6"/><rect x="92" y="70" width="60" height="12" rx="6"/>` },
    { w: 300, shape: `<path d="M46 32 66 60 46 88 26 60Z"/><rect x="82" y="46" width="192" height="12" rx="6"/><rect x="82" y="70" width="128" height="12" rx="6"/>` },
    { w: 230, shape: `<rect x="24" y="40" width="18" height="40" rx="6"/><rect x="50" y="30" width="18" height="60" rx="6"/><rect x="76" y="48" width="18" height="24" rx="6"/><rect x="110" y="54" width="96" height="12" rx="6"/>` },
    { w: 270, shape: `<circle cx="44" cy="46" r="16"/><circle cx="44" cy="82" r="16"/><rect x="76" y="40" width="170" height="12" rx="6"/><rect x="76" y="76" width="118" height="12" rx="6"/>` },
];

for (const [i, { w, shape }] of MARKS.entries()) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="120" viewBox="0 0 ${w} 120"><g fill="${INK}">${shape}</g></svg>`;

    const file = join(OUT, `segment-client-${i + 1}.webp`);

    writeFileSync(file, await sharp(Buffer.from(svg)).webp({ quality: 90 }).toBuffer());

    console.log(`wrote  images/placeholder/segment-client-${i + 1}.webp (${w}×120)`);
}
