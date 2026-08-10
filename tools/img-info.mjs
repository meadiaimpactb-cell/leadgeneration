import sharp from 'sharp';
import { readdir } from 'node:fs/promises';
const files = await readdir('public/images');
for (const f of files.sort()) {
  try {
    const m = await sharp(`public/images/${f}`).metadata();
    console.log(`${f.padEnd(12)} ${m.width}x${m.height}  ${m.format}  ratio ${(m.width/m.height).toFixed(2)}`);
  } catch (e) { console.log(`${f}: ${e.message.slice(0,50)}`); }
}
