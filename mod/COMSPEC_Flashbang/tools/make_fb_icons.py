"""
Icônes, logo et aperçu Steam du mod COMSPEC Flashbang (Pillow), puis conversion PNG -> PAA (ImageToPAA).

Entrées (tools/render/, écrites par model_flashbang.py dans Blender) : icon_raw_<V>.png (512 px), steam_raw.png.
Sorties :
  render/fb_icon_<V>_ca.png      icône d'inventaire 256 x 256 (grenade + badge du chiffre)   -> ui/fb_icon_<V>_ca.paa
  render/fb_logo_ca.png          logo du mod 256 x 256                                          -> ui/fb_logo_ca.paa et ../logo.paa
  render/steam_preview.jpg       aperçu Steam 1024 x 576
  render/fb_*.png des textures et de l'interface (gen_fb_textures.py)                           -> data/*.paa, ui/*.paa
Les rendus bruts (icon_raw_*, steam_raw) sont effacés une fois composés.

    python make_fb_icons.py            (ARMA3TOOLS = dossier Arma 3 Tools si ImageToPAA n'est pas trouvé)
"""
import math
import os
import subprocess
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
RENDER = os.path.join(HERE, "render")
ADDON = os.path.join(HERE, "..", "Sources", "addons", "main")
BASES = [str(i) for i in range(1, 10)] + ["drill", "50"]     # 50 : GANG BANG BF
# Variantes à délai (mèche fixe 3 s / 8 s) : <base>_d3, <base>_d8.
# BANG spéciaux : badge de la couleur du thème, nombre de flashs dedans (bord, fond, encre).
THEMES = {"japan": (3, (200, 22, 30, 255), (245, 245, 240, 245), (200, 22, 30, 255)),
          "women": (3, (245, 245, 240, 255), (150, 70, 200, 245), (245, 245, 240, 255)),
          "surprise": ("?", (245, 245, 240, 255), (20, 20, 20, 245), (255, 220, 40, 255)),
          "german": (9, (20, 20, 20, 255), (240, 194, 26, 245), (20, 20, 20, 255))}
VARIANTS = BASES + [f"{b}_d{d}" for b in BASES for d in (3, 8)] + list(THEMES)
# Variantes « R » (rafale hyper rapide) : icône de la grenade standard + pastille éclair « R ».
FAST = ["2", "3", "4", "5", "6", "7", "8", "9", "50"]
DELAY_FILL = {3: (242, 194, 20, 245), 8: (209, 26, 20, 245)}
DELAY_TEXT = {3: (15, 15, 15, 255), 8: (245, 245, 240, 255)}

# PNG source -> PAA dans l'addon (chemin relatif à Sources/addons/main).
TEXTURES = {f"fb_body_{v}_co": f"data/fb_body_{v}_co" for v in VARIANTS}
TEXTURES.update({k: "data/" + k for k in ("fb_body_nohq", "fb_body_smdi", "fb_metal_co", "fb_metal_nohq", "fb_metal_smdi")})
TEXTURES.update({k: "ui/" + k for k in ("fb_afterimage_ca", "fb_flare_ca", "fb_module_ca", "fb_logo_ca")})
TEXTURES.update({f"fb_icon_{v}_ca": f"ui/fb_icon_{v}_ca" for v in VARIANTS})
TEXTURES.update({f"fb_icon_{n}_r_ca": f"ui/fb_icon_{n}_r_ca" for n in FAST})


def font(size):
    for p in (r"C:\Windows\Fonts\bahnschrift.ttf", r"C:\Windows\Fonts\arialbd.ttf",
              "/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf"):
        if os.path.exists(p):
            f = ImageFont.truetype(p, size)
            if p.endswith("bahnschrift.ttf"):
                try:
                    f.set_variation_by_name("Bold")
                except (OSError, ValueError):
                    pass
            return f
    return ImageFont.load_default()


def icon(v):
    """Grenade réduite à 256 px, badge rond en bas à droite : chiffre blanc sur gris foncé (D blanc sur bleu) ;
    variantes à délai : en plus, pastille horloge + « 3s » / « 8s » en haut à droite (jaune 3 s, rouge 8 s)."""
    base, delay = (v.split("_d")[0], int(v.split("_d")[1])) if "_d" in v else (v, 0)
    raw = Image.open(os.path.join(RENDER, f"icon_raw_{v}.png")).convert("RGBA").resize((256, 256), Image.LANCZOS)
    S = 4 * 256
    badge = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(badge)
    cx, cy, r = 4 * 204, 4 * 206, 4 * 40
    fill = (24, 70, 170, 240) if base == "drill" else (28, 28, 26, 235)
    d.ellipse([cx - r - 8, cy - r - 8, cx + r + 8, cy + r + 8], fill=(0, 0, 0, 110))
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=fill, outline=(240, 240, 235, 255), width=4 * 4)
    fill = (150, 20, 15, 240) if base == "50" else fill
    if base == "50":
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=fill, outline=(240, 240, 235, 255), width=4 * 4)
    if base in THEMES:
        label, edge, fill, ink = THEMES[base]
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=fill, outline=edge, width=4 * 5)
        d.text((cx, cy + 6), str(label), font=font(4 * 54), fill=ink, anchor="mm")
    else:
        d.text((cx, cy + 6), "D" if base == "drill" else base, font=font(4 * (40 if len(base) > 1 else 54)), fill=(245, 245, 240, 255), anchor="mm")
    if delay:
        # Pastille de délai : horloge + « 3s » / « 8s », même famille de couleurs que la bande sur le corps.
        x0, y0, x1, y1 = 4 * 150, 4 * 10, 4 * 248, 4 * 62
        d.rounded_rectangle([x0 - 8, y0 - 8, x1 + 8, y1 + 8], radius=4 * 28, fill=(0, 0, 0, 110))
        d.rounded_rectangle([x0, y0, x1, y1], radius=4 * 26, fill=DELAY_FILL[delay], outline=(240, 240, 235, 255), width=4 * 3)
        ccx, ccy, cr = x0 + 4 * 25, (y0 + y1) // 2, 4 * 16
        ink = DELAY_TEXT[delay]
        d.ellipse([ccx - cr, ccy - cr, ccx + cr, ccy + cr], outline=ink, width=4 * 4)
        d.line([ccx, ccy, ccx, ccy - cr + 4 * 5], fill=ink, width=4 * 4)
        d.line([ccx, ccy, ccx + cr - 4 * 6, ccy], fill=ink, width=4 * 4)
        d.text(((ccx + cr + x1) // 2 + 4 * 2, ccy + 4 * 2), f"{delay}s", font=font(4 * 40), fill=ink, anchor="mm")
    badge = badge.resize((256, 256), Image.LANCZOS)
    out = Image.alpha_composite(raw, badge)
    out.save(os.path.join(RENDER, f"fb_icon_{v}_ca.png"))


def icon_fast(n):
    """Icône « R » (rafale hyper rapide) : icône de la grenade standard + pastille orange éclair « R » en haut à droite."""
    base = Image.open(os.path.join(RENDER, f"fb_icon_{n}_ca.png")).convert("RGBA")
    S = 4 * 256
    badge = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(badge)
    x0, y0, x1, y1 = 4 * 166, 4 * 10, 4 * 248, 4 * 62
    d.rounded_rectangle([x0 - 8, y0 - 8, x1 + 8, y1 + 8], radius=4 * 28, fill=(0, 0, 0, 110))
    d.rounded_rectangle([x0, y0, x1, y1], radius=4 * 26, fill=(245, 140, 20, 245), outline=(240, 240, 235, 255), width=4 * 3)
    cx, cy = x0 + 4 * 24, (y0 + y1) // 2
    bolt = [(cx + 4 * 4, cy - 4 * 20), (cx - 4 * 10, cy + 4 * 3), (cx + 0, cy + 4 * 3), (cx - 4 * 5, cy + 4 * 20),
            (cx + 4 * 12, cy - 4 * 4), (cx + 4 * 2, cy - 4 * 4)]
    d.polygon(bolt, fill=(20, 20, 20, 255))
    d.text(((cx + 4 * 14 + x1) // 2, cy + 4 * 2), "R", font=font(4 * 40), fill=(20, 20, 20, 255), anchor="mm")
    badge = badge.resize((256, 256), Image.LANCZOS)
    Image.alpha_composite(base, badge).save(os.path.join(RENDER, f"fb_icon_{n}_r_ca.png"))


def logo():
    """Logo 256 px : éclat blanc-jaune derrière la grenade 9 coups, mot FLASHBANG en pied."""
    S = 1024
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    burst = Image.new("L", (S, S), 0)
    d = ImageDraw.Draw(burst)
    c = (S / 2, S * 0.44)
    for k in range(18):
        a = 2 * math.pi * k / 18 + 0.08
        r1 = S * (0.47 if k % 2 == 0 else 0.36)
        w = 0.09
        d.polygon([c, (c[0] + r1 * math.cos(a - w), c[1] + r1 * math.sin(a - w)),
                   (c[0] + r1 * math.cos(a + w), c[1] + r1 * math.sin(a + w))], fill=200)
    d.ellipse([c[0] - S * 0.2, c[1] - S * 0.2, c[0] + S * 0.2, c[1] + S * 0.2], fill=255)
    burst = burst.filter(ImageFilter.GaussianBlur(10))
    glow = Image.new("RGBA", (S, S), (255, 236, 160, 0))
    glow.putalpha(burst)
    img = Image.alpha_composite(img, glow)
    g = Image.open(os.path.join(RENDER, "icon_raw_9.png")).convert("RGBA").resize((int(S * 0.84), int(S * 0.84)), Image.LANCZOS)
    img.alpha_composite(g, (int(S * 0.08), int(S * 0.0)))
    d = ImageDraw.Draw(img)
    d.rounded_rectangle([S * 0.08, S * 0.80, S * 0.92, S * 0.97], 40, fill=(20, 22, 20, 235))
    d.text((S / 2, S * 0.885), "FLASHBANG", font=font(int(S * 0.115)), fill=(245, 240, 220, 255), anchor="mm")
    img.resize((256, 256), Image.LANCZOS).save(os.path.join(RENDER, "fb_logo_ca.png"))


def steam():
    img = Image.open(os.path.join(RENDER, "steam_raw.png")).convert("RGB")
    d = ImageDraw.Draw(img, "RGBA")
    d.rectangle([0, 0, 1024, 118], fill=(0, 0, 0, 120))
    d.text((40, 22), "COMSPEC FLASHBANG", font=font(56), fill=(245, 242, 230))
    d.text((42, 84), "Grenades assourdissantes de 1 à 9 détonations · grenade d'exercice", font=font(24), fill=(210, 205, 190))
    img.save(os.path.join(RENDER, "steam_preview.jpg"), quality=92)


def image_to_paa():
    cands = [os.environ.get("ARMA3TOOLS", "")] + [f"{d}:\\SteamLibrary\\steamapps\\common\\Arma 3 Tools" for d in "CDEFGH"] + \
            [r"C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools"]
    for c in cands:
        exe = os.path.join(c, "ImageToPAA", "ImageToPAA.exe")
        if c and os.path.exists(exe):
            return exe
    return None


def convert(delayed_only=False, only=None):
    exe = image_to_paa()
    if not exe:
        sys.exit("ImageToPAA introuvable : définir ARMA3TOOLS")
    jobs = list(TEXTURES.items()) + [("fb_logo_ca", "../../../logo")]
    if delayed_only:
        jobs = [(k, v) for k, v in jobs if "_d3_" in k or "_d8_" in k]
    if only:
        jobs = [(k, v) for k, v in jobs if any(m in k for m in only)]
    for src, dst in jobs:
        png = os.path.join(RENDER, src + ".png")
        paa = os.path.normpath(os.path.join(ADDON, dst + ".paa"))
        os.makedirs(os.path.dirname(paa), exist_ok=True)
        if os.path.exists(paa):
            os.remove(paa)          # ImageToPAA ne remplace pas toujours un PAA existant
        subprocess.run([exe, png, paa], check=True, stdout=subprocess.DEVNULL)
        if not os.path.exists(paa):
            sys.exit("conversion échouée : " + png)
        print("paa", os.path.relpath(paa, os.path.join(HERE, "..")))


def main():
    # Compose les icônes dont le rendu brut existe (toutes, ou seulement les variantes à délai après
    # DELAYED_ONLY=1 dans Blender) ; logo et aperçu Steam seulement si leurs rendus bruts sont là.
    done = [v for v in VARIANTS if os.path.exists(os.path.join(RENDER, f"icon_raw_{v}.png"))]
    for v in done:
        icon(v)
    if "9" in done:
        logo()
    if os.path.exists(os.path.join(RENDER, "steam_raw.png")):
        steam()
    for f in os.listdir(RENDER):
        if f.startswith("icon_raw_") or f == "steam_raw.png":
            os.remove(os.path.join(RENDER, f))
    for n in FAST:
        if os.path.exists(os.path.join(RENDER, f"fb_icon_{n}_ca.png")):
            icon_fast(n)
    if "--no-paa" not in sys.argv:
        # --delai : ne convertit que les textures et icônes des variantes à délai ; --seulement=motif1,motif2 : ne
        # convertit que les fichiers dont le nom contient un des motifs (les autres PAA restent tels quels).
        only = next((a.split("=", 1)[1].split(",") for a in sys.argv if a.startswith("--seulement=")), None)
        convert(delayed_only="--delai" in sys.argv, only=only)


if __name__ == "__main__":
    main()
