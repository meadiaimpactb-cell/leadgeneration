#!/usr/bin/env python3
"""
Flatten the client's .ico into a plain PNG the icon generator can read.

Run once whenever public/images/amadcraft.ico is replaced:

    python3 scripts/ico-to-png.py public/images/amadcraft.ico public/brand/icon-source.png

Why a step at all: the supplied file is a single 256x256 image in an
uncompressed DIB, and neither sharp nor libvips reads ICO. Pillow does, so the
conversion happens here and the Node generator stays a resizer. Keeping the
intermediate in the repo means a deploy never needs Python.
"""

import sys
from PIL import Image

src, dst = sys.argv[1], sys.argv[2]

im = Image.open(src)
im = im.convert('RGBA')

# Flattened onto the artwork's own navy: an .ico may carry transparency, and a
# transparent tab icon on a dark browser theme disappears entirely.
ground = Image.new('RGBA', im.size, (0, 37, 70, 255))
ground.alpha_composite(im)

ground.convert('RGB').save(dst, 'PNG', optimize=True)
print(f'{src}  {im.size[0]}x{im.size[1]}  ->  {dst}')
