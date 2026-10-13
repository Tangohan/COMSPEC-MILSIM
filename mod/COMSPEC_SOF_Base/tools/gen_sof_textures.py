"""
Textures du mod COMSPEC Base SOF (PNG dans tools/render/, à convertir en .paa) et rvmats (shader Super).

  sof_mesh_<couleur>_ca   grillage soudé 50 mm, fond transparent (cages, casiers) : blue, black
  sof_plywood_co          contreplaqué (alvéoles, râtelier)
  sof_steel_co            acier peint sombre, légèrement usé (établi, étagère, casiers)
  sof_galva_co            tôle galvanisée (tablettes d'étagère)
  sof_pegboard_co         panneau perforé (établi)
  sof_red_co              tôle laquée rouge (servante)
  sof_sign_co             pancarte vierge (à personnaliser dans Eden)
  sof_flag_co             drapeau vierge (à personnaliser dans Eden)

    python3 gen_sof_textures.py
    puis  for f in render/*.png; do hemtt utils paa convert $f ../Sources/addons/main/data/$(basename ${f%.png}).paa; done
"""
import os
import random

from PIL import Image, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
RENDER = os.path.join(HERE, "render")
DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
TEX = "\\z\\comspec_sof\\addons\\main\\data\\"


def noise(size, scale, amp, seed):
    """Bruit basse fréquence raccordable (grille aléatoire agrandie en bicubique, bords recopiés)."""
    r = random.Random(seed)
    n = max(2, size // scale)
    small = Image.new("L", (n, n))
    small.putdata([128 + r.randint(-amp, amp) for _ in range(n * n)])
    tiled = Image.new("L", (n * 3, n * 3))
    for i in range(3):
        for j in range(3):
            tiled.paste(small, (i * n, j * n))
    big = tiled.resize((size * 3, size * 3), Image.BICUBIC)
    return big.crop((size, size, size * 2, size * 2))


def shade(base, size, layers, seed):
    im = Image.new("RGB", (size, size), base)
    px = im.load()
    ns = [noise(size, sc, amp, seed + k).load() for k, (sc, amp) in enumerate(layers)]
    for y in range(size):
        for x in range(size):
            d = sum(n[x, y] - 128 for n in ns)
            px[x, y] = tuple(max(0, min(255, c + d)) for c in base)
    return im


def mesh(color, path, size=512, cells=10, wire=3):
    im = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    step = size // cells
    hi = tuple(min(255, c + 40) for c in color)
    for i in range(cells):
        p = i * step
        d.rectangle([p, 0, p + wire, size], fill=(*color, 255))
        d.rectangle([0, p, size, p + wire], fill=(*color, 255))
        d.line([p + 1, 0, p + 1, size], fill=(*hi, 255))
    im.save(path)


def plywood(path, size=512):
    r = random.Random(3)
    im = Image.new("RGB", (size, size), (196, 152, 98))
    d = ImageDraw.Draw(im)
    for k in range(180):
        y = r.uniform(0, size)
        a = r.uniform(4, 22)
        f = r.uniform(1, 3) * 2 * 3.14159 / size
        ph = r.uniform(0, 6.28)
        c = r.randint(-34, 18)
        col = (196 + c, 152 + c, 98 + int(c * 0.7))
        pts = [(x, (y + a * __import__("math").sin(x * f + ph)) % size) for x in range(0, size + 8, 8)]
        d.line(pts, fill=col, width=r.randint(1, 3))
    im = im.filter(ImageFilter.GaussianBlur(1.2))
    n = noise(size, 64, 14, 9).load()
    px = im.load()
    for y in range(size):
        for x in range(size):
            dd = n[x, y] - 128
            px[x, y] = tuple(max(0, min(255, c + dd)) for c in px[x, y])
    im.save(path)


def pegboard(path, size=512):
    im = shade((205, 205, 200), size, [(64, 6), (8, 3)], 21)
    d = ImageDraw.Draw(im)
    step = size // 16
    for i in range(16):
        for j in range(16):
            cx, cy = i * step + step // 2, j * step + step // 2
            d.ellipse([cx - 5, cy - 5, cx + 5, cy + 5], fill=(18, 18, 18))
    im.save(path)


def sign(path, size=(1024, 512)):
    im = shade((232, 230, 224), 1024, [(128, 5), (8, 3)], 31).crop((0, 0, *size))
    d = ImageDraw.Draw(im)
    d.rectangle([6, 6, size[0] - 7, size[1] - 7], outline=(70, 70, 70), width=10)
    im.save(path)


def flag(path, size=(1024, 512)):
    im = shade((214, 212, 204), 1024, [(256, 8), (4, 4)], 41).crop((0, 0, *size))
    im.save(path)


RVMATS = {
    # nom : (specular, specularPower, smdi (spec, gloss) procédural)
    "sof_frame": ((0.5, 0.5, 0.5), 40, (0.6, 0.5)),
    "sof_mesh": ((0.6, 0.6, 0.6), 50, (0.7, 0.6)),
    "sof_steel": ((0.35, 0.35, 0.35), 30, (0.4, 0.35)),
    "sof_galva": ((0.8, 0.8, 0.8), 60, (0.8, 0.6)),
    "sof_wood": ((0.08, 0.08, 0.08), 8, (0.08, 0.15)),
    "sof_paint": ((0.6, 0.6, 0.6), 70, (0.7, 0.75)),
    "sof_matte": ((0.05, 0.05, 0.05), 5, (0.05, 0.1)),
}

UVT = "class uvTransform { aside[]={1,0,0}; up[]={0,1,0}; dir[]={0,0,0}; pos[]={0,0,0}; };"


def rvmat(name, spec, power, smdi):
    s = f"""// COMSPEC Base SOF (tools/gen_sof_textures.py) : shader Super, relief neutre, reflets procéduraux.
ambient[]={{1,1,1,1}};
diffuse[]={{1,1,1,1}};
forcedDiffuse[]={{0,0,0,0}};
emmisive[]={{0,0,0,1}};
specular[]={{{spec[0]},{spec[1]},{spec[2]},1}};
specularPower={power};
PixelShaderID="Super";
VertexShaderID="Super";
class Stage1 {{ texture="#(argb,8,8,3)color(0.5,0.5,1,1,NOHQ)"; uvSource="tex"; {UVT} }};
class Stage2 {{ texture="#(argb,8,8,3)color(0.5,0.5,0.5,1,DT)"; uvSource="tex"; {UVT} }};
class Stage3 {{ texture="#(argb,8,8,3)color(0,0,0,0,MC)"; uvSource="tex"; {UVT} }};
class Stage4 {{ texture="#(argb,8,8,3)color(1,1,1,1,AS)"; uvSource="tex"; {UVT} }};
class Stage5 {{ texture="#(argb,8,8,3)color(1,{smdi[0]},{smdi[1]},1,SMDI)"; uvSource="tex"; {UVT} }};
class Stage6 {{ texture="#(ai,64,64,1)fresnel(1.5,0.6)"; uvSource="none"; }};
class Stage7 {{ texture="a3\\data_f\\env_land_co.paa"; useWorldEnvMap="true"; uvSource="tex"; {UVT} }};
"""
    with open(os.path.join(DATA, name + ".rvmat"), "w", encoding="utf-8", newline="\n") as fh:
        fh.write(s)


def main():
    os.makedirs(RENDER, exist_ok=True)
    os.makedirs(DATA, exist_ok=True)
    mesh((28, 64, 170), os.path.join(RENDER, "sof_mesh_blue_ca.png"))
    mesh((34, 36, 34), os.path.join(RENDER, "sof_mesh_black_ca.png"))
    plywood(os.path.join(RENDER, "sof_plywood_co.png"))
    shade((44, 47, 50), 512, [(128, 8), (16, 5), (2, 3)], 11).save(os.path.join(RENDER, "sof_steel_co.png"))
    shade((150, 154, 156), 512, [(64, 14), (4, 4)], 12).save(os.path.join(RENDER, "sof_galva_co.png"))
    shade((150, 18, 14), 256, [(128, 6), (4, 2)], 13).save(os.path.join(RENDER, "sof_red_co.png"))
    pegboard(os.path.join(RENDER, "sof_pegboard_co.png"))
    sign(os.path.join(RENDER, "sof_sign_co.png"))
    flag(os.path.join(RENDER, "sof_flag_co.png"))
    for k, v in RVMATS.items():
        rvmat(k, *v)
    print("ok")


if __name__ == "__main__":
    main()
