#!/usr/bin/env python3
"""
Assemble les tuiles de la capture de carte du mod (fn_mapCapture : cmap_*.png dans Documents\\Arma 3\\Screenshots).

Pour chaque couche (carte, rendu, hauteur) :
  <sortie>/<carte>_<rendu>_h<hauteur>.jpg     mosaïque nord en haut (réduite à --max px de côté)
  <sortie>/<carte>_<rendu>_h<hauteur>.json    géoréférence : origine (m), mètres par pixel, taille, tuiles manquantes
  <sortie>/<carte>_<rendu>_h<hauteur>/z/x/y.jpg   tuiles web 256 px (Leaflet, CRS.Simple), avec --tiles

Le centre de chaque capture est au centre de sa case ; seul le carré central (le pas) est gardé, le reste sert de
recouvrement. La vue 3D oblique est assemblée de la même façon (aperçu ; les toits débordent un peu d'une case à l'autre).

  python3 tools/map_stitch.py "C:/Users/moi/Documents/Arma 3/Screenshots" sortie --tiles
"""
import argparse
import json
import math
import os
import re
import sys

try:
    from PIL import Image
except ImportError:  # pragma: no cover
    sys.exit("Pillow manquant : python -m pip install pillow")

NAME = re.compile(r"^cmap_(?P<world>.+?)_(?P<mode>sat|3d|plan)_h(?P<h>\d+)_s(?P<step>\d+)_w(?P<w>\d+)_o(?P<ox>-?\d+)-(?P<oy>-?\d+)_x(?P<ix>\d+)_y(?P<iy>\d+)\.(png|jpg)$", re.I)


def layers(folder):
    out = {}
    for f in sorted(os.listdir(folder)):
        m = NAME.match(f)
        if not m:
            continue
        g = m.groupdict()
        key = (g["world"], g["mode"].lower(), int(g["h"]))
        lay = out.setdefault(key, {"step": int(g["step"]), "w": int(g["w"]), "ox": int(g["ox"]), "oy": int(g["oy"]), "tiles": {}})
        lay["tiles"][(int(g["ix"]), int(g["iy"]))] = os.path.join(folder, f)
    return out


def stitch(key, lay, outdir, max_side, tiles):
    world, mode, h = key
    step, wm = lay["step"], lay["w"]
    nx = max(i for i, _ in lay["tiles"]) + 1
    ny = max(j for _, j in lay["tiles"]) + 1
    first = Image.open(next(iter(lay["tiles"].values())))
    ppm = first.width / float(wm)  # pixels par mètre d'une capture
    cell = max(1, int(round(step * ppm)))
    scale = min(1.0, max_side / float(max(nx, ny) * cell))
    cs = max(1, int(round(cell * scale)))
    mosaic = Image.new("RGB", (nx * cs, ny * cs), (0, 0, 0))
    missing = []
    for j in range(ny):
        for i in range(nx):
            p = lay["tiles"].get((i, j))
            if not p:
                missing.append([i, j])
                continue
            im = Image.open(p).convert("RGB")
            cx, cy = im.width / 2.0, im.height / 2.0
            box = (int(cx - cell / 2), int(cy - cell / 2), int(cx - cell / 2) + cell, int(cy - cell / 2) + cell)
            crop = im.crop(box)
            if cs != cell:
                crop = crop.resize((cs, cs), Image.LANCZOS)
            # Ligne 0 au sud : en bas de l'image.
            mosaic.paste(crop, (i * cs, (ny - 1 - j) * cs))
    base = os.path.join(outdir, "%s_%s_h%d" % (world, mode, h))
    mosaic.save(base + ".jpg", quality=90)
    meta = {
        "world": world, "mode": mode, "height_m": h, "origin_m": [lay["ox"], lay["oy"]],
        "meters_per_pixel": step / float(cs), "size_px": [mosaic.width, mosaic.height],
        "extent_m": [lay["ox"], lay["oy"], lay["ox"] + nx * step, lay["oy"] + ny * step],
        "grid": [nx, ny], "missing": missing,
    }
    with open(base + ".json", "w", encoding="utf-8") as fh:
        json.dump(meta, fh, indent=2)
    if tiles:
        web_tiles(mosaic, base)
    print("%s : %d x %d cases, %d x %d px, %d manquante(s)" % (os.path.basename(base), nx, ny, mosaic.width, mosaic.height, len(missing)))


def web_tiles(img, base, size=256):
    side = max(img.width, img.height)
    zmax = max(0, int(math.ceil(math.log(side / float(size), 2))))
    full = 2 ** zmax * size
    canvas = Image.new("RGB", (full, full), (0, 0, 0))
    canvas.paste(img, (0, full - img.height))  # coin sud-ouest de la carte en bas à gauche
    for z in range(zmax, -1, -1):
        n = 2 ** z
        level = canvas if z == zmax else canvas.resize((n * size, n * size), Image.LANCZOS)
        for x in range(n):
            for y in range(n):
                t = level.crop((x * size, y * size, (x + 1) * size, (y + 1) * size))
                if t.getbbox() is None:
                    continue
                d = os.path.join(base, str(z), str(x))
                os.makedirs(d, exist_ok=True)
                t.save(os.path.join(d, "%d.jpg" % y), quality=85)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("captures", help="dossier des captures (Screenshots d'Arma)")
    ap.add_argument("sortie", help="dossier de sortie")
    ap.add_argument("--max", type=int, default=16384, help="côté maximal de la mosaïque en pixels (16384)")
    ap.add_argument("--tiles", action="store_true", help="tuiles web z/x/y en plus")
    a = ap.parse_args()
    os.makedirs(a.sortie, exist_ok=True)
    found = layers(a.captures)
    if not found:
        sys.exit("Aucune capture cmap_* dans " + a.captures)
    for key, lay in sorted(found.items()):
        stitch(key, lay, a.sortie, a.max, a.tiles)


if __name__ == "__main__":
    main()
