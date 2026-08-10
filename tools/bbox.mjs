import sharp from 'sharp';
for (const f of ['logo-horizontal','logo-stacked']) {
  const buf = await sharp(`public/brand/${f}.svg`, { density: 300 }).png().toBuffer();
  const meta = await sharp(buf).metadata();
  const { info } = await sharp(buf).trim({ threshold: 1 }).toBuffer({ resolveWithObject: true });
  const sx = 1080/meta.width, sy = 1080/meta.height;
  console.log(f, 'canvas', meta.width+'x'+meta.height, '| trimmed', info.width+'x'+info.height,
    '| aspect', (info.width/info.height).toFixed(3),
    '| viewBox approx:', (info.trimOffsetLeft!==undefined?(-info.trimOffsetLeft*sx).toFixed(1):'?'),
    (info.trimOffsetTop!==undefined?(-info.trimOffsetTop*sy).toFixed(1):'?'),
    (info.width*sx).toFixed(1), (info.height*sy).toFixed(1));
}
