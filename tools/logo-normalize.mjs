import sharp from 'sharp';
import { readFile, writeFile } from 'node:fs/promises';

const PAD = 6; // px of breathing room inside the tight box

for (const f of ['logo-horizontal', 'logo-stacked']) {
  const src = `public/brand/${f}.svg`;
  const raw = await readFile(src, 'utf8');

  const png = await sharp(src, { density: 300 }).png().toBuffer();
  const meta = await sharp(png).metadata();
  const { info } = await sharp(png).trim({ threshold: 1 }).toBuffer({ resolveWithObject: true });

  const sx = 1080 / meta.width;
  const sy = 1080 / meta.height;
  const x = (-info.trimOffsetLeft * sx) - PAD;
  const y = (-info.trimOffsetTop * sy) - PAD;
  const w = info.width * sx + PAD * 2;
  const h = info.height * sy + PAD * 2;
  const vb = [x, y, w, h].map((n) => Math.round(n * 10) / 10).join(' ');

  const out = raw
    .replace(/viewBox="[^"]*"/, `viewBox="${vb}"`)
    .replace(/fill:\s*#002546/gi, 'fill: currentColor')
    .replace(/fill="#002546"/gi, 'fill="currentColor"')
    .replace(/<svg /, '<svg role="img" ');

  await writeFile(src, out, 'utf8');
  console.log(`${f}: viewBox="${vb}"  aspect ${(w / h).toFixed(3)}`);
}
