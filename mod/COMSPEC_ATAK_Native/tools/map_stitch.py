#!/usr/bin/env python3
"""
Assemble les tuiles de la capture de carte du mod (fn_mapCapture : cmap_*.jpg corrigées et compressées par la DLL dans
Documents\\COMSPEC_MapCapture\\<carte>, ou cmap_*.png laissées dans Documents\\Arma 3\\Screenshots sans la DLL ;
sous-dossiers compris, le JPEG passe devant le PNG d'origine gardé à côté).

1. Mosaïque par couche (carte, rendu, hauteur), comme avant :
  <sortie>/<carte>_<rendu>_h<hauteur>.jpg     mosaïque nord en haut (réduite à --max px de côté)
  <sortie>/<carte>_<rendu>_h<hauteur>.json    géoréférence : origine (m), mètres par pixel, taille, tuiles manquantes
  <sortie>/<carte>_<rendu>_h<hauteur>/z/x/y.jpg   tuiles web 256 px (Leaflet, CRS.Simple), avec --tiles

Le centre de chaque capture est au centre de sa case ; seul le carré central (le pas) est gardé, le reste sert de
recouvrement. La vue 3D oblique est assemblée de la même façon (aperçu ; les toits débordent un peu d'une case à l'autre).

2. Fond à niveaux de détail (--pyramid), vues satellite et plan :
  <sortie>/<carte>_<rendu>/meta.json et z/x/y.webp
Toutes les hauteurs d'une carte forment une seule pyramide en mètres Arma (comme Plans ou Google Maps : on
zoome, l'image s'affine). Chaque hauteur remplit les niveaux qui correspondent à sa finesse ; la plus détaillée
passe devant là où elle existe (une ville à 300 m, le reste de l'île à 1200 m). Niveau z : la carte (côté
« extent » = puissance de 2 au-dessus de la taille de la carte) tient dans 2^z × 2^z tuiles de 256 px, tuile
y = 0 au nord. Avec le journal Arma (--rpt, trouvé tout seul sous Windows), chaque image satellite est redressée sur
le relief (orthophoto) : le cadrage mesuré en jeu et les altitudes notées sous l'image (lignes CMAPL / CMAPS).

  --upload https://athena-comspec.eu/public --key <X_COMSPEC_KEY>   envoie le fond au site (calque « Photo COMSPEC ») :
                      tuiles WebP (site) et leur copie JPEG z/x/y.jpg (fond PHOTO du téléphone, téléchargé en jeu par la
                      DLL : Arma ne lit pas le WebP) ; --upload-format pour n'envoyer que l'un des deux. Clé lue aussi
                      dans la variable d'environnement COMSPEC_KEY (sans --key : la clé n'apparaît pas dans l'historique)
  Fond déjà fabriqué (dossier <sortie>/<carte>_<rendu> avec meta.json) donné à la place des captures : envoyé tel quel,
  sans refaire la pyramide :
    python3 tools/map_stitch.py sortie/Altis_sat sortie --upload https://athena-comspec.eu/public
  --addon <dossier>   fabrique le pack secondaire <dossier>/@COMSPEC_ATAK_Photo (une PBO par carte, toutes les cartes
                      déjà assemblées gardées), le fond PHOTO du téléphone : mod optionnel chargé en plus de
                      @COMSPEC_ATAK_Native, qui retombe sur la carte d'Arma sans lui (PAA par HEMTT 1.15 ou plus, sinon JPG)
  --pack-only         refait seulement le pack depuis les fonds déjà assemblés dans <sortie> (sans les captures)

3. Regroupement des captures (« auto » à la place du dossier) : tous les dossiers Screenshots d'Arma (profil par
défaut, « Arma 3 - Other Profiles\\<profil> », Documents sous OneDrive) sont lus ensemble. --collect <bibliothèque>
les range dans un seul dossier (<carte>/<rendu>_h<hauteur>/), en WebP sans perte (2 à 3 fois plus léger que le PNG
d'Arma, pixels identiques vérifiés), avec les lignes CMAP des journaux Arma (cmap_journal.txt) : la bibliothèque se
suffit à elle-même, même quand Arma a effacé ses vieux journaux. --move vide ensuite les dossiers Screenshots (Arma y
plafonne la place).

Rendu (satellite) : raccords fondus entre images voisines, exposition égalisée d'une image à l'autre (Arma règle
la lumière à chaque prise), voile de l'altitude retiré, couleurs ravivées et netteté ; --no-enhance pour l'image
brute. Compression : WebP méthode 6 pour le site, DXT1 (deux fois plus léger que DXT5) pour les tuiles pleines du pack.

  python3 tools/map_stitch.py auto sortie --collect bibliotheque --move --pyramid --no-mosaic --addon mods
  python3 tools/map_stitch.py bibliotheque sortie --pyramid --no-mosaic --addon mods     (plus tard, sans recapturer)
  python3 tools/map_stitch.py - sortie --pack-only --addon mods --addon-max-mb 600
  python3 tools/map_stitch.py "C:/Users/moi/Documents/Arma 3/Screenshots" sortie --tiles

Dépendances : Pillow, numpy (python -m pip install pillow numpy).
"""
import argparse
import glob
import io
import json
import math
import os
import re
import shutil
import subprocess
import sys
import time
import urllib.request
import zipfile
from collections import OrderedDict
from concurrent.futures import ThreadPoolExecutor

try:
    from PIL import Image, ImageChops, ImageEnhance, ImageFilter
except ImportError:  # pragma: no cover
    sys.exit("Pillow manquant : python -m pip install pillow")

NAME = re.compile(r"^cmap_(?P<world>.+?)_(?P<mode>sat|3d|plan)_h(?P<h>\d+)_s(?P<step>\d+)_w(?P<w>\d+)_o(?P<ox>-?\d+)-(?P<oy>-?\d+)_x(?P<ix>\d+)_y(?P<iy>\d+)\.(png|jpg|webp)$", re.I)
TILE = 256
HERE = os.path.dirname(os.path.abspath(__file__))
JOURNAL = "cmap_journal.txt"


# --------------------------------------------------------------------------------------------------------------
# Regroupement des captures
# --------------------------------------------------------------------------------------------------------------

def arma_screenshot_dirs():
    """Tous les dossiers Screenshots d'Arma de ce PC : profil par défaut et autres profils, Documents local ou OneDrive
    (fn_mapCapture écrit dans celui du profil en cours)."""
    docs = [os.path.join(os.path.expanduser("~"), "Documents")]
    for var in ("OneDrive", "OneDriveConsumer", "OneDriveCommercial"):
        if os.environ.get(var):
            docs.append(os.path.join(os.environ[var], "Documents"))
    docs.append(os.path.join(os.path.expanduser("~"), "OneDrive", "Documents"))
    out, seen = [], set()
    for d in docs:
        for p in [os.path.join(d, "Arma 3", "Screenshots")] + glob.glob(os.path.join(d, "Arma 3 - Other Profiles", "*", "Screenshots")):
            r = os.path.normcase(os.path.realpath(p))
            if os.path.isdir(p) and r not in seen:
                seen.add(r)
                out.append(p)
    return out


def capture_files(folders):
    """cmap_* de tous les dossiers (sous-dossiers compris) ; un même nom vu deux fois (Screenshots et bibliothèque)
    garde le plus récent."""
    best = {}
    for folder in folders:
        for root, _, names in os.walk(folder):
            for f in names:
                m = NAME.match(f)
                if not m:
                    continue
                p = os.path.join(root, f)
                stem = os.path.splitext(f)[0].lower()
                t = os.path.getmtime(p)
                if stem not in best or t > best[stem][0]:
                    best[stem] = (t, p, m)
    return best


def collect(folders, lib, rpts, move, fmt):
    """Range toutes les captures dans <lib>/<carte>/<rendu>_h<hauteur>/ (WebP sans perte par défaut, pixels vérifiés)
    et recopie les lignes CMAP des journaux Arma dans <lib>/cmap_journal.txt."""
    os.makedirs(lib, exist_ok=True)
    libr = os.path.normcase(os.path.realpath(lib))
    files = capture_files(folders)
    jobs = []
    for stem, (t, p, m) in files.items():
        if os.path.normcase(os.path.realpath(p)).startswith(libr + os.sep):
            continue
        g = m.groupdict()
        ext = ".webp" if fmt == "webp" else os.path.splitext(p)[1].lower()
        name = os.path.splitext(os.path.basename(p))[0]  # casse d'origine : le nom de la carte en dépend
        dest = os.path.join(lib, g["world"], "%s_h%s" % (g["mode"].lower(), g["h"]), name + ext)
        jobs.append((t, p, dest))
    before = after = 0
    done = 0

    def one(job):
        t, p, dest = job
        if os.path.exists(dest) and os.path.getmtime(dest) >= t - 1:
            return os.path.getsize(p), os.path.getsize(dest), True
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        tmp = dest + ".tmp"
        with Image.open(p) as im:
            src = im.convert("RGB")
        if dest.endswith(".webp"):
            src.save(tmp, "WEBP", lossless=True, quality=80, method=4)
            with Image.open(tmp) as chk:
                if ImageChops.difference(src, chk.convert("RGB")).getbbox() is not None:
                    os.remove(tmp)
                    shutil.copy2(p, os.path.splitext(dest)[0] + os.path.splitext(p)[1])
                    return os.path.getsize(p), os.path.getsize(p), True
        else:
            shutil.copyfile(p, tmp)
        os.replace(tmp, dest)
        os.utime(dest, (t, t))
        return os.path.getsize(p), os.path.getsize(dest), True

    with ThreadPoolExecutor(max_workers=os.cpu_count() or 4) as ex:
        for (t, p, dest), (a, b, ok) in zip(jobs, ex.map(one, jobs)):
            before += a
            after += b
            done += 1
            if move and ok:
                os.remove(p)
            if done % 25 == 0 or done == len(jobs):
                print("  regroupement : %d / %d images" % (done, len(jobs)), end="\r")
    if jobs:
        print()
    # Journal : lignes CMAP de tous les RPT, sans doublon, ajoutées à celles déjà gardées.
    jpath = os.path.join(lib, JOURNAL)
    lines, seen = [], set()
    if os.path.exists(jpath):
        with open(jpath, encoding="utf-8", errors="replace") as fh:
            for line in fh:
                line = line.rstrip("\n")
                if line and line not in seen:
                    seen.add(line)
                    lines.append(line)
    added = 0
    for f in rpt_files(rpts):
        with open(f, encoding="utf-8", errors="replace") as fh:
            for line in fh:
                i = line.find("CMAP")
                if i < 0:
                    continue
                line = line[i:].strip().strip('"')
                if line not in seen:
                    seen.add(line)
                    lines.append(line)
                    added += 1
    with open(jpath, "w", encoding="utf-8") as fh:
        fh.write("\n".join(lines) + ("\n" if lines else ""))
    print("Bibliothèque %s : %d image(s) rangée(s) (%.0f Mo -> %.0f Mo)%s, %d ligne(s) de journal ajoutée(s)"
          % (lib, len(jobs), before / 1048576.0, after / 1048576.0, ", originaux retirés de Screenshots" if move else "", added))


def layers(folders):
    """Une couche par (carte, rendu, hauteur). Plusieurs captures d'une même hauteur (deux villes, ou une zone
    reprise) sont réunies : leur grille est calée sur des multiples du pas, les cases sont renumérotées depuis
    l'origine la plus au sud-ouest ; une case prise deux fois garde l'image la plus récente."""
    found = {}
    for _, (_, p, m) in capture_files(folders).items():
        g = m.groupdict()
        key = (g["world"], g["mode"].lower(), int(g["h"]))
        found.setdefault(key, []).append((os.path.getmtime(p), int(g["step"]), int(g["w"]), int(g["ox"]), int(g["oy"]),
                                          int(g["ix"]), int(g["iy"]), p))
    out = {}
    for key, items in found.items():
        steps = {}
        for it in items:
            steps[it[1]] = steps.get(it[1], 0) + 1
        step = max(steps, key=lambda s: steps[s])
        if len(steps) > 1:
            print("%s_%s_h%d : pas différents %s, seul le pas %d m est gardé (refaire la capture des autres)"
                  % (key[0], key[1], key[2], sorted(steps), step))
        items = sorted((it for it in items if it[1] == step), key=lambda it: it[0])
        ox = min(it[3] for it in items)
        oy = min(it[4] for it in items)
        tiles = {}
        for _, _, _, iox, ioy, ix, iy, p in items:
            tiles[(ix + int(round((iox - ox) / float(step))), iy + int(round((ioy - oy) / float(step))))] = p
        out[key] = {"step": step, "w": max(it[2] for it in items), "ox": ox, "oy": oy, "tiles": tiles}
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


# --------------------------------------------------------------------------------------------------------------
# Fond à niveaux de détail (--pyramid)
# --------------------------------------------------------------------------------------------------------------

def rpt_files(paths, extra=()):
    """Journaux Arma à lire : ceux donnés, sinon %LOCALAPPDATA%\\Arma 3\\*.rpt (Windows) ; plus les journaux gardés
    dans une bibliothèque (extra, lus en premier : un RPT plus récent l'emporte)."""
    if not paths:
        local = os.environ.get("LOCALAPPDATA")
        paths = [os.path.join(local, "Arma 3")] if local else []
    out = []
    for p in paths:
        if os.path.isdir(p):
            out += glob.glob(os.path.join(p, "*.rpt")) + glob.glob(os.path.join(p, "*.RPT"))
        elif os.path.isfile(p):
            out.append(p)
    extra = [p for p in extra if os.path.isfile(p)]
    return extra + [p for p in sorted(set(out), key=os.path.getmtime) if p not in extra]


def journals(folders):
    return [os.path.join(f, JOURNAL) for f in folders if os.path.isfile(os.path.join(f, JOURNAL))]


def read_manifest(paths, extra=()):
    """Lignes CMAPL (cadrage d'une couche) et CMAPS (une image) du journal ; la plus récente gagne."""
    heads, shots = {}, {}
    for f in rpt_files(paths, extra):
        with open(f, encoding="utf-8", errors="replace") as fh:
            for line in fh:
                i = line.find("CMAP")
                if i < 0:
                    continue
                p = line[i:].strip().strip('"').split("|")
                try:
                    if p[0] == "CMAPL" and len(p) >= 20:
                        world, mode, h = p[1], p[2].lower(), int(float(p[3]))
                        # v : taille carte, altitude, fov, ax, ay, u0, v0, pas, largeur, hauteur, x0, y0, nx, ny, grille, portée
                        v = [float(x) for x in p[4:20]]
                        # Même cadrage pour toutes les captures d'une hauteur : l'origine n'entre pas dans la clé.
                        heads[(world.lower(), mode, h, int(round(v[7])))] = {
                            "world_size": v[0], "alt": v[1], "fov": v[2], "ax": v[3], "ay": v[4], "u0": v[5], "v0": v[6],
                            "step": v[7], "fw": v[8], "fh": v[9], "gn": int(v[14]), "span": v[15],
                        }
                    elif p[0] == "CMAPS" and len(p) >= 6:
                        # Clé sans extension : la DLL range l'image en .jpg, le journal la nomme .png.
                        shots[os.path.splitext(p[1].lower())[0]] = {"x": float(p[2]), "y": float(p[3]), "cz": float(p[4]),
                                               "hs": [float(x) for x in p[5].split(",") if x != ""]}
                except (ValueError, IndexError):
                    continue
    return heads, shots


class Shot:
    def __init__(self, path, cx, cy, fw, calib=None, cz=None, grid=None, span=None):
        self.path, self.cx, self.cy, self.fw = path, cx, cy, fw
        self.calib, self.cz, self.grid, self.span = calib, cz, grid, span
        self.gain = None  # gain par canal (égalisation de l'exposition), harmonize()

    def project(self, X, Y, W, H):
        """Sol (X, Y en m) -> pixel de l'image (colonne, ligne)."""
        import numpy as np
        if self.calib is not None and self.cz is not None:
            ax, ay, u0, v0 = self.calib
            Z = self.height(X, Y)
            d = np.maximum(self.cz - Z, 1.0)
            u = u0 + ax * (X - self.cx) / d
            v = v0 - ay * (Y - self.cy) / d
        else:
            fh = self.fw * H / float(W)
            u = 0.5 + (X - self.cx) / self.fw
            v = 0.5 - (Y - self.cy) / fh
        return u * W - 0.5, v * H - 0.5

    def height(self, X, Y):
        import numpy as np
        g = self.grid
        n = g.shape[0]
        fx = np.clip(((X - self.cx) / self.span + 0.5) * (n - 1), 0, n - 1.0001)
        fy = np.clip(((Y - self.cy) / self.span + 0.5) * (n - 1), 0, n - 1.0001)
        i0 = fx.astype(int)
        j0 = fy.astype(int)
        tx = fx - i0
        ty = fy - j0
        a = g[j0, i0] * (1 - tx) + g[j0, i0 + 1] * tx
        b = g[j0 + 1, i0] * (1 - tx) + g[j0 + 1, i0 + 1] * tx
        return a * (1 - ty) + b * ty


class Layer:
    def __init__(self, key, lay, heads, shots):
        import numpy as np
        self.world, self.mode, self.h = key
        self.step, self.x0, self.y0 = float(lay["step"]), float(lay["ox"]), float(lay["oy"])
        first = next(iter(lay["tiles"].values()))
        with Image.open(first) as im:
            self.W, self.H = im.size
        head = heads.get((self.world.lower(), self.mode, self.h, lay["step"]))
        self.world_size = head["world_size"] if head else None
        fw = head["fw"] if head else float(lay["w"])
        fh = head["fh"] if head and head.get("fh") else fw * self.H / float(self.W)
        self.res = fw / float(self.W)  # mètres par pixel d'une capture
        # Recouvrement au-delà de la case, de chaque côté : là où deux images voisines voient le même sol.
        self.mx = max(0.0, (fw - self.step) / 2.0)
        self.my = max(0.0, (fh - self.step) / 2.0)
        self.levels = None  # (noir par canal, facteur) : voile retiré, harmonize()
        self.shots = {}
        self.calibrated = 0
        for (ix, iy), path in lay["tiles"].items():
            cx = self.x0 + (ix + 0.5) * self.step
            cy = self.y0 + (iy + 0.5) * self.step
            s = Shot(path, cx, cy, fw)
            m = shots.get(os.path.splitext(os.path.basename(path).lower())[0])
            if head and m and len(m["hs"]) == head["gn"] ** 2:
                s.calib = (head["ax"], head["ay"], head["u0"], head["v0"])
                s.cz = m["cz"]
                s.grid = np.array(m["hs"], dtype=np.float64).reshape(head["gn"], head["gn"])
                s.span = head["span"]
                self.calibrated += 1
            self.shots[(ix, iy)] = s
        self.nx = max(i for i, _ in self.shots) + 1
        self.ny = max(j for _, j in self.shots) + 1
        self.bbox = (self.x0, self.y0, self.x0 + self.nx * self.step, self.y0 + self.ny * self.step)

    def native_zoom(self, extent):
        # Niveau dont la finesse est la plus proche de la capture (jusqu'à 1,27 fois agrandie).
        return max(0, int(math.floor(math.log(extent / (TILE * self.res), 2) + 0.35)))


class ImageCache:
    def __init__(self, size, layer=None):
        self.size = max(4, size)
        self.layer = layer
        self.items = OrderedDict()

    def get(self, shot):
        import numpy as np
        path = shot.path
        if path in self.items:
            self.items.move_to_end(path)
            return self.items[path]
        with Image.open(path) as im:
            arr = np.asarray(im.convert("RGB"), dtype=np.float32)
        arr = correct(arr, shot, self.layer)
        self.items[path] = arr
        if len(self.items) > self.size:
            self.items.popitem(last=False)
        return arr


def sample(img, px, py):
    """Bilinéaire ; renvoie (couleurs N×3, masque des points dans l'image)."""
    import numpy as np
    H, W = img.shape[0], img.shape[1]
    ok = (px >= 0) & (py >= 0) & (px <= W - 1) & (py <= H - 1)
    x = np.clip(px, 0, W - 1.001)
    y = np.clip(py, 0, H - 1.001)
    x0 = x.astype(int)
    y0 = y.astype(int)
    tx = (x - x0)[:, None]
    ty = (y - y0)[:, None]
    a = img[y0, x0] * (1 - tx) + img[y0, x0 + 1] * tx
    b = img[y0 + 1, x0] * (1 - tx) + img[y0 + 1, x0 + 1] * tx
    return a * (1 - ty) + b * ty, ok


def render_tile(layer, cache, extent, z, tx, ty, feather=True):
    """Tuile RGBA d'une couche à son niveau natif : chaque point du sol pris dans l'image de sa case (le moins de
    perspective), redressée sur le relief quand le cadrage est connu. Près du bord de la case, fondu avec l'image
    voisine sur la bande qu'elles voient toutes deux (plus de raccord net entre deux prises)."""
    import numpy as np
    T = extent / (2 ** z)
    k = (np.arange(TILE) + 0.5) * (T / TILE)
    X = np.tile(tx * T + k, TILE)
    Y = np.repeat(extent - ty * T - k, TILE)
    fx = (X - layer.x0) / layer.step
    fy = (Y - layer.y0) / layer.step
    ix = np.floor(fx).astype(int)
    iy = np.floor(fy).astype(int)
    ux = fx - ix
    uy = fy - iy
    # Poids de l'image de la case : 1 au centre, 0,5 sur le bord ; le reste à la voisine du bord le plus proche.
    def weight(u, margin):
        if not feather or margin <= 0:
            return np.ones_like(u)
        d = np.minimum(u, 1.0 - u) * layer.step
        return 0.5 + 0.5 * np.clip(d / (0.8 * margin), 0.0, 1.0)
    wx = weight(ux, layer.mx)
    wy = weight(uy, layer.my)
    sx = np.where(ux < 0.5, -1, 1)
    sy = np.where(uy < 0.5, -1, 1)
    combos = [(ix, iy, wx * wy), (ix + sx, iy, (1 - wx) * wy), (ix, iy + sy, wx * (1 - wy)), (ix + sx, iy + sy, (1 - wx) * (1 - wy))]
    acc = np.zeros((TILE * TILE, 3), dtype=np.float64)
    wsum = np.zeros(TILE * TILE, dtype=np.float64)
    for ci, cj, w in combos:
        idx = np.nonzero(w > 1e-3)[0]
        if idx.size == 0:
            continue
        cells = np.unique(np.stack([ci[idx], cj[idx]], axis=1), axis=0)
        for i, j in cells:
            shot = layer.shots.get((int(i), int(j)))
            if shot is None:
                continue
            sel = idx[(ci[idx] == i) & (cj[idx] == j)]
            img = cache.get(shot)
            px, py = shot.project(X[sel], Y[sel], layer.W, layer.H)
            rgb, ok = sample(img, px, py)
            sel = sel[ok]
            ww = w[sel]
            acc[sel] += rgb[ok] * ww[:, None]
            wsum[sel] += ww
    have = wsum > 0
    if not have.any():
        return None
    out = np.zeros((TILE * TILE, 4), dtype=np.uint8)
    out[have, :3] = np.clip(acc[have] / wsum[have, None] + 0.5, 0, 255).astype(np.uint8)
    out[have, 3] = 255
    return Image.fromarray(out.reshape(TILE, TILE, 4), "RGBA")


# --------------------------------------------------------------------------------------------------------------
# Rendu : exposition égalisée, voile retiré, couleurs et netteté
# --------------------------------------------------------------------------------------------------------------

def correct(arr, shot, layer):
    """Image d'une capture corrigée : gain de son exposition puis voile de l'altitude retiré (niveaux de la couche)."""
    if layer is None:
        return arr
    if shot.gain is not None:
        arr = arr * shot.gain
    if layer.levels is not None:
        lo, k = layer.levels
        arr = (arr - lo) * k
    return arr


def harmonize(layer, factor=8):
    """Mesure, sur des copies réduites, l'écart de lumière entre images voisines (même sol vu par les deux) et en tire
    un gain par image et par canal ; puis le voile commun à la couche (noir relevé et bleui par l'altitude)."""
    import numpy as np
    W, H = layer.W, layer.H

    def load(item):
        key, shot = item
        with Image.open(shot.path) as im:
            im = im.convert("RGB")
            return key, np.asarray(im.reduce(factor), dtype=np.float32)

    with ThreadPoolExecutor(max_workers=os.cpu_count() or 4) as ex:
        small = dict(ex.map(load, list(layer.shots.items())))
    if not small:
        return

    def look(shot, X, Y):
        img = small[shot]
        px, py = layer.shots[shot].project(X, Y, W, H)
        return sample(img, (px + 0.5) / factor - 0.5, (py + 0.5) / factor - 0.5)

    n = 24
    lin = (np.arange(n) + 0.5) / n
    pairs = []
    for (i, j), shot in layer.shots.items():
        cx = layer.x0 + (i + 0.5) * layer.step
        cy = layer.y0 + (j + 0.5) * layer.step
        for di, dj, margin in ((1, 0, layer.mx), (0, 1, layer.my)):
            other = (i + di, j + dj)
            if other not in layer.shots or margin <= 0:
                continue
            half = 0.7 * margin
            if di:
                bx = cx + layer.step / 2
                X = np.tile(bx - half + 2 * half * lin, n)
                Y = np.repeat(cy - layer.step / 2 + layer.step * lin, n)
            else:
                by = cy + layer.step / 2
                X = np.tile(cx - layer.step / 2 + layer.step * lin, n)
                Y = np.repeat(by - half + 2 * half * lin, n)
            a, oka = look((i, j), X, Y)
            b, okb = look(other, X, Y)
            ok = oka & okb
            if ok.sum() < n * 4:
                continue
            ma = a[ok].mean(axis=0)
            mb = b[ok].mean(axis=0)
            if min(ma.min(), mb.min()) < 6:
                continue  # trop sombre (mer de nuit, ombre) : rapport peu fiable
            pairs.append(((i, j), other, np.log(mb) - np.log(ma)))
    # log gain(a) - log gain(b) = log mb - log ma, au sens des moindres carrés, rappelé doucement vers 0.
    lg = {k: np.zeros(3) for k in layer.shots}
    nb = {k: [] for k in layer.shots}
    for a, b, d in pairs:
        nb[a].append((b, d))
        nb[b].append((a, -d))
    for _ in range(200):
        for key, links in nb.items():
            if links:
                lg[key] = sum(lg[o] + d for o, d in links) / (len(links) + 0.05)
    if pairs:
        mean = sum(lg.values()) / len(lg)
        for key, shot in layer.shots.items():
            shot.gain = np.exp(np.clip(lg[key] - mean, math.log(0.7), math.log(1.4))).astype(np.float32)
    # Voile : noir de chaque canal (0,5 %) retiré, puis blanc (99,7 %) ramené vers 250, sans assombrir.
    rng = np.random.default_rng(1)
    keys = list(small)
    pick = [keys[i] for i in rng.choice(len(keys), min(len(keys), 200), replace=False)]
    px = []
    for key in pick:
        arr = small[key].reshape(-1, 3)
        g = layer.shots[key].gain
        if g is not None:
            arr = arr * g
        px.append(arr[rng.choice(arr.shape[0], min(arr.shape[0], 4000), replace=False)])
    px = np.concatenate(px)
    px = px[px.max(axis=1) > 3]  # bords noirs hors carte
    if px.shape[0] > 1000:
        lo = np.minimum(np.percentile(px, 0.5, axis=0), 70.0).astype(np.float32)
        hi = float(np.percentile((px - lo).max(axis=1), 99.7))
        k = float(np.clip(250.0 / max(hi, 1.0), 1.0, 1.8))
        layer.levels = (lo, np.float32(k))
    spread = [float(np.abs(np.log(s.gain)).max()) for s in layer.shots.values() if s.gain is not None]
    print("  h%d : exposition égalisée sur %d raccords (écart moyen %.0f %%), voile %s"
          % (layer.h, len(pairs), 100 * (sum(spread) / len(spread) if spread else 0),
             "noir %s x%.2f" % ([int(v) for v in layer.levels[0]], layer.levels[1]) if layer.levels else "non mesuré"))


def finish(im, saturation, sharpen):
    """Tuile finale : couleurs ravivées et netteté (masque flou léger), la transparence des bords gardée."""
    if saturation == 1 and sharpen <= 0:
        return im
    alpha = im.split()[3] if im.mode == "RGBA" else None
    rgb = im.convert("RGB")
    if saturation != 1:
        rgb = ImageEnhance.Color(rgb).enhance(saturation)
    if sharpen > 0:
        rgb = rgb.filter(ImageFilter.UnsharpMask(radius=1.2, percent=int(100 * sharpen), threshold=2))
    if alpha is None:
        return rgb
    rgb.putalpha(alpha)
    return rgb


def tile_range(bbox, extent, z):
    T = extent / (2 ** z)
    n = 2 ** z
    x0 = max(0, int(math.floor(bbox[0] / T)))
    x1 = min(n - 1, int(math.floor((bbox[2] - 1e-6) / T)))
    y0 = max(0, int(math.floor((extent - bbox[3]) / T)))
    y1 = min(n - 1, int(math.floor((extent - bbox[1] - 1e-6) / T)))
    return x0, x1, y0, y1


def downsample(children):
    """Quatre enfants RGBA (None = vide) -> parent 256 px, couleurs pondérées par l'opacité."""
    canvas = Image.new("RGBA", (TILE * 2, TILE * 2), (0, 0, 0, 0))
    any_ = False
    for (dx, dy), im in children.items():
        if im is not None:
            canvas.paste(im, (dx * TILE, dy * TILE))
            any_ = True
    if not any_:
        return None
    return canvas.convert("RGBa").resize((TILE, TILE), Image.LANCZOS).convert("RGBA")


def build_pyramid(world, mode, lays, outdir, quality, cache_size, enhance=None):
    extent_from = [l.world_size for l in lays if l.world_size]
    size = max(extent_from) if extent_from else max(max(l.bbox[2], l.bbox[3]) for l in lays)
    extent = float(2 ** int(math.ceil(math.log(max(size, TILE), 2))))
    lays = sorted(lays, key=lambda l: -l.res)  # de la plus grossière à la plus fine
    work = os.path.join(outdir, "_work_%s_%s" % (world, mode))
    final = os.path.join(outdir, "%s_%s" % (world, mode))
    shutil.rmtree(work, ignore_errors=True)
    shutil.rmtree(final, ignore_errors=True)
    per_layer = []
    zmax = 0
    for li, lay in enumerate(lays):
        zl = lay.native_zoom(extent)
        zmax = max(zmax, zl)
        tiles = {}
        x0, x1, y0, y1 = tile_range(lay.bbox, extent, zl)
        if enhance and mode == "sat":
            harmonize(lay)
        cache = ImageCache(cache_size, lay)
        # Par bandes de colonnes (quelques images de large) : les images restent dans le cache.
        band = max(1, int(lay.step / (extent / 2 ** zl)) * 3)
        t0 = time.time()
        total = (x1 - x0 + 1) * (y1 - y0 + 1)
        done = 0
        for bx in range(x0, x1 + 1, band):
            for ty in range(y0, y1 + 1):
                for tx in range(bx, min(x1, bx + band - 1) + 1):
                    im = render_tile(lay, cache, extent, zl, tx, ty, feather=bool(enhance))
                    done += 1
                    if im is not None:
                        p = os.path.join(work, str(li), str(zl), str(tx), "%d.png" % ty)
                        os.makedirs(os.path.dirname(p), exist_ok=True)
                        im.save(p)
                        tiles[(zl, tx, ty)] = p
            print("  h%d : %d / %d tuiles (%ds)" % (lay.h, done, total, time.time() - t0), end="\r")
        print()
        # Niveaux inférieurs de cette couche, par réduction.
        for z in range(zl - 1, -1, -1):
            parents = {}
            for (cz, tx, ty) in [k for k in tiles if k[0] == z + 1]:
                parents.setdefault((tx // 2, ty // 2), True)
            for (px, py) in parents:
                kids = {}
                for dx in (0, 1):
                    for dy in (0, 1):
                        cp = tiles.get((z + 1, px * 2 + dx, py * 2 + dy))
                        kids[(dx, dy)] = Image.open(cp).convert("RGBA") if cp else None
                im = downsample(kids)
                if im is None:
                    continue
                p = os.path.join(work, str(li), str(z), str(px), "%d.png" % py)
                os.makedirs(os.path.dirname(p), exist_ok=True)
                im.save(p)
                tiles[(z, px, py)] = p
        per_layer.append((lay, zl, tiles))
        print("couche h%d : %.2f m/px, niveau %d, %d tuiles, %d/%d images redressées sur le relief"
              % (lay.h, lay.res, zl, len(tiles), lay.calibrated, len(lay.shots)))
    # Assemblage : la couche la plus fine devant.
    keys = sorted({k for _, _, t in per_layer for k in t})
    count = 0
    for k in keys:
        out = None
        for _, _, t in per_layer:
            p = t.get(k)
            if p is None:
                continue
            im = Image.open(p).convert("RGBA")
            out = im if out is None else Image.alpha_composite(out, im)
        if out is None:
            continue
        z, tx, ty = k
        p = os.path.join(final, str(z), str(tx), "%d.webp" % ty)
        os.makedirs(os.path.dirname(p), exist_ok=True)
        if enhance and mode == "sat":
            out = finish(out, enhance["saturation"], enhance["sharpen"])
        # Méthode 6 : la plus serrée de WebP (plus lente à écrire, rien de plus à lire).
        if out.getextrema()[3][0] == 255:
            out.convert("RGB").save(p, "WEBP", quality=quality, method=6)
        else:
            out.save(p, "WEBP", quality=quality, method=6)
        count += 1
    shutil.rmtree(work, ignore_errors=True)
    meta = {
        "world": world, "mode": mode, "extent": extent, "tileSize": TILE, "minZoom": 0, "maxZoom": zmax,
        "format": "webp", "tiles": count, "created": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "enhanced": bool(enhance and mode == "sat"),
        "layers": [{"height_m": l.h, "meters_per_pixel": round(l.res, 3), "zoom": zl, "bbox_m": [round(v) for v in l.bbox],
                    "images": len(l.shots), "orthorectified": l.calibrated} for l, zl, _ in per_layer],
    }
    with open(os.path.join(final, "meta.json"), "w", encoding="utf-8") as fh:
        json.dump(meta, fh, indent=2)
    print("%s : %d tuiles, niveaux 0 à %d, côté %d m (%.2f m/px au plus fin)" % (final, count, zmax, extent, extent / (TILE * 2 ** zmax)))
    return final, meta


def pyramid_tiles(folder, ext=".webp"):
    out = []
    for root, _, names in os.walk(folder):
        for n in names:
            if n.endswith(ext):
                rel = os.path.relpath(os.path.join(root, n), folder).replace("\\", "/")
                parts = rel.split("/")
                if len(parts) == 3:
                    out.append((int(parts[0]), int(parts[1]), int(parts[2].split(".")[0]), os.path.join(root, n)))
    return sorted(out)


def jpg_tiles(folder, quality=85):
    """Copie JPEG de chaque tuile WebP (z/x/y.jpg à côté), pour le téléphone ATAK : Arma ne lit pas le WebP. Le
    transparent (bord d'une zone capturée) devient noir, comme dans le mod @COMSPEC_ATAK_Photo en JPG. Une copie plus
    récente que sa tuile est gardée."""
    def conv(t):
        *_, p = t
        dst = p[:-5] + ".jpg"
        if os.path.exists(dst) and os.path.getmtime(dst) >= os.path.getmtime(p):
            return
        im = Image.open(p).convert("RGBA")
        flat = Image.new("RGB", im.size, (0, 0, 0))
        flat.paste(im, mask=im.split()[3])
        flat.save(dst + ".part", "JPEG", quality=quality, optimize=True)
        os.replace(dst + ".part", dst)

    tiles = pyramid_tiles(folder)
    with ThreadPoolExecutor(max_workers=os.cpu_count() or 4) as ex:
        list(ex.map(conv, tiles))
    print("%d tuiles JPEG pour le téléphone (%.0f Mo)" % (len(tiles), sum(os.path.getsize(t[3][:-5] + ".jpg") for t in tiles) / 1048576.0))


def upload(folder, meta, base, key, chunk_mb=6.0, fmt="both"):
    """Envoie le fond au site par paquets ZIP (le premier remplace l'ancien fond de cette carte)."""
    url = base.rstrip("/") + "/api/atak/map-photo/upload?world=%s&mode=%s" % (meta["world"], meta["mode"])
    exts = {"both": [".webp", ".jpg"], "webp": [".webp"], "jpg": [".jpg"]}[fmt]
    if ".jpg" in exts:
        jpg_tiles(folder)
    files = [(os.path.relpath(p, folder).replace("\\", "/"), p) for e in exts for *_, p in pyramid_tiles(folder, e)]
    files.append(("meta.json", os.path.join(folder, "meta.json")))  # en dernier : le fond n'apparaît qu'une fois complet
    limit = int(chunk_mb * 1024 * 1024)
    part, size, n = [], 0, 0

    def send(entries, first, last):
        buf = io.BytesIO()
        with zipfile.ZipFile(buf, "w", zipfile.ZIP_STORED) as zf:
            for rel, p in entries:
                zf.write(p, rel)
        q = url + ("&reset=1" if first else "") + ("&done=1" if last else "")
        req = urllib.request.Request(q, data=buf.getvalue(), method="POST",
                                     headers={"Content-Type": "application/zip", "X-Comspec-Key": key})
        for attempt in range(4):
            try:
                with urllib.request.urlopen(req, timeout=120) as r:
                    return json.loads(r.read().decode("utf-8") or "{}")
            except Exception as e:  # réseau : on réessaie
                if attempt == 3:
                    raise SystemExit("Envoi refusé : %s" % e)
                time.sleep(2 ** (attempt + 1))

    for i, (rel, p) in enumerate(files):
        s = os.path.getsize(p)
        if part and size + s > limit:
            send(part, n == 0, False)
            n += 1
            print("  envoi : paquet %d (%d / %d fichiers)" % (n, i, len(files)), end="\r")
            part, size = [], 0
        part.append((rel, p))
        size += s
    r = send(part, n == 0, True)
    print("\nEnvoyé : %d fichiers en %d paquet(s). Réponse : %s" % (len(files), n + 1, r))


def find_hemtt():
    for c in [os.environ.get("HEMTT", ""), shutil.which("hemtt") or "", shutil.which("hemtt.exe") or ""]:
        if c and os.path.exists(c):
            return c
    return None


PACK = "@COMSPEC_ATAK_Photo"
PACK_OVERVIEW = ("Optional layer for COMSPEC ATAK (native): photo map backgrounds captured from the game (PHOTO and SAT "
                 "map styles of the phone), several zoom levels per map. Load it together with @COMSPEC_ATAK_Native; "
                 "without it the phone keeps the regular Arma map.")


def build_addon(folder, meta, outroot, max_tiles, fmt, max_mb=0):
    """Pack secondaire @COMSPEC_ATAK_Photo : une PBO par carte (les autres cartes du pack sont gardées), tuiles PAA (ou
    JPG) lues par fn_photoMap. Tuiles pleines en DXT1 (sans transparence, deux fois plus légères), DXT5 seulement pour
    les tuiles de bord. Les niveaux les plus fins sont retirés tant que le pack de la carte dépasse --addon-max-mb."""
    world = meta["world"]
    cls = re.sub(r"[^A-Za-z0-9_]", "_", world)
    tiles = pyramid_tiles(folder)
    zmax = 0
    for z in range(meta["maxZoom"] + 1):
        if sum(1 for t in tiles if t[0] <= z) <= max_tiles:
            zmax = z
    tiles = [t for t in tiles if t[0] <= zmax]
    hemtt = find_hemtt() if fmt == "paa" else None
    if hemtt:
        # HEMTT avant 1.15 ne sait que lire les PAA : essai sur une image avant de lancer la conversion.
        probe = os.path.join(outroot, "_probe_comspec.png")
        os.makedirs(outroot, exist_ok=True)
        Image.new("RGB", (TILE, TILE)).save(probe)
        ok = subprocess.run([hemtt, "utils", "paa", "convert", probe, probe[:-4] + ".paa"],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0
        for f in (probe, probe[:-4] + ".paa"):
            if os.path.exists(f):
                os.remove(f)
        if not ok:
            hemtt = None
    if fmt == "paa" and not hemtt:
        print("HEMTT 1.15 ou plus introuvable (variable HEMTT ou PATH) : tuiles en JPG.")
        fmt = "jpg"
    src = os.path.join(outroot, "_src_comspec_atak_photo_%s" % cls.lower())
    shutil.rmtree(src, ignore_errors=True)
    prefix = "z\\comspec_atak_photo\\addons\\%s" % cls.lower()
    os.makedirs(src)
    with open(os.path.join(src, "$PBOPREFIX$"), "w", encoding="utf-8") as fh:
        fh.write(prefix)

    def conv(t):
        z, x, y, p = t
        d = os.path.join(src, "t", str(z), str(x))
        os.makedirs(d, exist_ok=True)
        im = Image.open(p).convert("RGBA")
        opaque = im.getextrema()[3][0] == 255
        if fmt == "jpg":
            flat = Image.new("RGB", im.size, (0, 0, 0))
            flat.paste(im, mask=im.split()[3])
            out = os.path.join(d, "%d.jpg" % y)
            flat.save(out, quality=88, optimize=True, progressive=False)
        else:
            png = os.path.join(d, "%d.png" % y)
            # Sans transparence, HEMTT écrit du DXT1 (4 bits par pixel) au lieu du DXT5 (8 bits).
            (im.convert("RGB") if opaque else im).save(png)
            out = os.path.join(d, "%d.paa" % y)
            subprocess.run([hemtt, "utils", "paa", "convert", png, out], check=True,
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            os.remove(png)
        return z, os.path.getsize(out), opaque

    with ThreadPoolExecutor(max_workers=os.cpu_count() or 4) as ex:
        done = list(ex.map(conv, tiles))
    per_z = {}
    for z, size, _ in done:
        per_z[z] = per_z.get(z, 0) + size
    if max_mb > 0:
        while zmax > 0 and sum(per_z.get(z, 0) for z in range(zmax + 1)) > max_mb * 1048576:
            shutil.rmtree(os.path.join(src, "t", str(zmax)), ignore_errors=True)
            print("  %s : niveau %d retiré du pack (au-delà de %d Mo)" % (world, zmax, max_mb))
            zmax -= 1
    kept = [d for d in done if d[0] <= zmax]
    with open(os.path.join(src, "config.cpp"), "w", encoding="utf-8") as fh:
        fh.write("""// Généré par tools/map_stitch.py --addon (capture de la carte COMSPEC ATAK).
class CfgPatches {
    class comspec_atak_photo_%(lc)s {
        name = "COMSPEC ATAK Photo %(world)s";
        author = "COMSPEC";
        units[] = {};
        weapons[] = {};
        requiredVersion = 2.10;
        requiredAddons[] = {};
    };
};
class COMSPEC_ATAK_PhotoMaps {
    class %(cls)s {
        world = "%(world)s";
        extent = %(extent)d;
        tileSize = %(ts)d;
        minZoom = 0;
        maxZoom = %(zmax)d;
        path = "\\%(prefix)s\\t\\";
        ext = ".%(fmt)s";
        created = "%(created)s";
    };
};
""" % {"lc": cls.lower(), "cls": cls, "world": world, "extent": meta["extent"], "ts": meta["tileSize"], "zmax": zmax,
           "prefix": prefix, "fmt": fmt, "created": meta["created"]})
    mod = os.path.join(outroot, PACK)
    os.makedirs(os.path.join(mod, "addons"), exist_ok=True)
    with open(os.path.join(mod, "mod.cpp"), "w", encoding="utf-8") as fh:
        fh.write('name = "COMSPEC ATAK - Photo maps";\nauthor = "COMSPEC";\npicture = "logo.paa";\nlogo = "logo.paa";\n'
                 'logoOver = "logo.paa";\ntooltip = "COMSPEC ATAK photo maps";\noverview = "%s";\n' % PACK_OVERVIEW)
    logo = os.path.join(os.path.dirname(HERE), "@COMSPEC_ATAK_Native", "logo.paa")
    if os.path.exists(logo):
        shutil.copyfile(logo, os.path.join(mod, "logo.paa"))
    pbo = os.path.join(mod, "addons", "comspec_atak_photo_%s.pbo" % cls.lower())
    subprocess.run([sys.executable, os.path.join(HERE, "pack_pbo.py"), src, pbo, prefix], check=True)
    shutil.rmtree(src, ignore_errors=True)
    full = sum(1 for d in kept if d[2])
    print("%s : %d tuiles (niveaux 0 à %d, %s, %d pleines en DXT1), %.0f Mo"
          % (pbo, len(kept), zmax, fmt.upper(), full if fmt == "paa" else 0, os.path.getsize(pbo) / 1048576.0))
    pack_index(mod)


def pack_index(mod):
    """cartes.txt du pack : chaque carte, sa taille ; total à publier."""
    rows, total = [], 0
    for f in sorted(glob.glob(os.path.join(mod, "addons", "comspec_atak_photo_*.pbo"))):
        size = os.path.getsize(f)
        total += size
        rows.append("%-40s %8.0f Mo" % (os.path.basename(f), size / 1048576.0))
    with open(os.path.join(mod, "cartes.txt"), "w", encoding="utf-8") as fh:
        fh.write("COMSPEC ATAK - Photo maps (pack secondaire de @COMSPEC_ATAK_Native)\n\n" + "\n".join(rows) +
                 "\n\nTotal : %.0f Mo\n" % (total / 1048576.0))
    print("Pack %s : %d carte(s), %.0f Mo au total" % (mod, len(rows), total / 1048576.0))


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("captures", help="dossier des captures (Documents/COMSPEC_MapCapture/<carte>, ou Screenshots d'Arma), "
                                     "« auto » pour tous les dossiers Screenshots d'Arma de ce PC (plusieurs dossiers séparés "
                                     "par « ; »), « - » avec --pack-only, ou un fond déjà fabriqué (<carte>_<rendu> avec meta.json)")
    ap.add_argument("sortie", help="dossier de sortie")
    ap.add_argument("--max", type=int, default=16384, help="côté maximal de la mosaïque en pixels (16384)")
    ap.add_argument("--tiles", action="store_true", help="tuiles web z/x/y en plus")
    ap.add_argument("--pyramid", action="store_true", help="fond à niveaux de détail (satellite et plan), toutes hauteurs réunies")
    ap.add_argument("--no-mosaic", action="store_true", help="avec --pyramid : pas de mosaïque par hauteur")
    ap.add_argument("--rpt", action="append", default=[], help="journal Arma (.rpt) ou son dossier ; défaut %%LOCALAPPDATA%%\\Arma 3")
    ap.add_argument("--world", help="seulement cette carte (nom Arma, ex. Altis)")
    ap.add_argument("--quality", type=int, default=82, help="qualité WebP du fond (82)")
    ap.add_argument("--cache", type=int, default=64, help="images gardées en mémoire (64, environ 6 Mo chacune)")
    ap.add_argument("--no-enhance", action="store_true", help="image brute : ni fondu des raccords, ni exposition égalisée, ni voile retiré")
    ap.add_argument("--saturation", type=float, default=1.08, help="couleurs du fond satellite (1.08 ; 1 = inchangé)")
    ap.add_argument("--sharpen", type=float, default=0.6, help="netteté du fond satellite (0.6 ; 0 = aucune)")
    ap.add_argument("--collect", metavar="BIBLIOTHEQUE", help="range d'abord les captures (et les lignes CMAP des journaux) dans ce dossier, puis travaille depuis lui")
    ap.add_argument("--collect-format", choices=["webp", "png"], default="webp", help="images de la bibliothèque : WebP sans perte (défaut) ou PNG d'origine")
    ap.add_argument("--move", action="store_true", help="avec --collect : retire les originaux des dossiers Screenshots une fois rangés")
    ap.add_argument("--collect-only", action="store_true", help="avec --collect : seulement ranger")
    ap.add_argument("--upload", metavar="URL", help="adresse du site (ex. https://athena-comspec.eu/public)")
    ap.add_argument("--key", default=os.environ.get("COMSPEC_KEY", ""),
                    help="clé plateforme X_COMSPEC_KEY du site (avec --upload) ; défaut : variable d'environnement COMSPEC_KEY")
    ap.add_argument("--upload-format", choices=["both", "webp", "jpg"], default="both",
                    help="tuiles envoyées : WebP (site) et JPEG (téléphone, défaut), ou l'un des deux")
    ap.add_argument("--addon", metavar="DOSSIER", help="fabrique le pack secondaire @COMSPEC_ATAK_Photo (fond PHOTO du téléphone) dans ce dossier")
    ap.add_argument("--pack-only", action="store_true", help="avec --addon : refait le pack depuis les fonds satellite déjà dans <sortie>")
    ap.add_argument("--addon-max-tiles", type=int, default=20000, help="tuiles au plus par carte dans le pack (20000)")
    ap.add_argument("--addon-max-mb", type=int, default=0, help="taille au plus par carte dans le pack, en Mo (0 = sans limite) : niveaux les plus fins retirés au-delà")
    ap.add_argument("--addon-format", choices=["paa", "jpg"], default="paa", help="format des tuiles du pack (paa)")
    a = ap.parse_args()
    os.makedirs(a.sortie, exist_ok=True)
    # Fond déjà fabriqué (meta.json) : envoi et pack seulement, sans refaire la pyramide.
    built = os.path.join(a.captures, "meta.json")
    if os.path.isfile(built):
        with open(built, encoding="utf-8") as fh:
            meta = json.load(fh)
        if a.upload:
            if not a.key:
                sys.exit("--upload demande la clé : variable d'environnement COMSPEC_KEY ou --key")
            upload(a.captures, meta, a.upload, a.key, fmt=a.upload_format)
        if a.addon and meta.get("mode") == "sat":
            build_addon(a.captures, meta, a.addon, a.addon_max_tiles, a.addon_format, a.addon_max_mb)
        if not a.upload and not a.addon:
            sys.exit("Fond déjà fabriqué : préciser --upload ou --addon")
        return
    if a.pack_only:
        if not a.addon:
            sys.exit("--pack-only demande --addon <dossier>")
        metas = sorted(glob.glob(os.path.join(a.sortie, "*_sat", "meta.json")))
        if a.world:
            metas = [m for m in metas if os.path.basename(os.path.dirname(m)).lower() == (a.world + "_sat").lower()]
        if not metas:
            sys.exit("Aucun fond satellite (<carte>_sat/meta.json) dans " + a.sortie)
        for m in metas:
            with open(m, encoding="utf-8") as fh:
                build_addon(os.path.dirname(m), json.load(fh), a.addon, a.addon_max_tiles, a.addon_format, a.addon_max_mb)
        return
    if a.captures.lower() == "auto":
        folders = arma_screenshot_dirs()
        print("Dossiers Screenshots d'Arma : " + (", ".join(folders) if folders else "aucun trouvé"))
    else:
        folders = [f for f in a.captures.split(";") if f]
    if a.collect:
        collect(folders, a.collect, a.rpt, a.move, a.collect_format)
        if a.collect_only:
            return
        folders = [a.collect]
    found = layers(folders)
    if a.world:
        found = {k: v for k, v in found.items() if k[0].lower() == a.world.lower()}
    if not found:
        sys.exit("Aucune capture cmap_* dans " + "; ".join(folders))
    if not (a.pyramid and a.no_mosaic):
        for key, lay in sorted(found.items()):
            stitch(key, lay, a.sortie, a.max, a.tiles)
    if not a.pyramid:
        return
    try:
        import numpy  # noqa: F401
    except ImportError:
        sys.exit("numpy manquant : python -m pip install numpy")
    heads, shots = read_manifest(a.rpt, journals(folders))
    if not heads:
        print("Journal Arma sans lignes CMAPL : images prises droites, sans redressement sur le relief.")
    enhance = None if a.no_enhance else {"saturation": a.saturation, "sharpen": a.sharpen}
    groups = {}
    for key, lay in found.items():
        if key[1] in ("sat", "plan"):
            groups.setdefault((key[0], key[1]), []).append(Layer(key, lay, heads, shots))
    if not groups:
        sys.exit("--pyramid : aucune capture satellite ou plan.")
    for (world, mode), lays in sorted(groups.items()):
        folder, meta = build_pyramid(world, mode, lays, a.sortie, a.quality, a.cache, enhance)
        if a.upload:
            if not a.key:
                sys.exit("--upload demande la clé : variable d'environnement COMSPEC_KEY ou --key (X_COMSPEC_KEY du .env du site)")
            upload(folder, meta, a.upload, a.key, fmt=a.upload_format)
        if a.addon and mode == "sat":
            build_addon(folder, meta, a.addon, a.addon_max_tiles, a.addon_format, a.addon_max_mb)


if __name__ == "__main__":
    main()
