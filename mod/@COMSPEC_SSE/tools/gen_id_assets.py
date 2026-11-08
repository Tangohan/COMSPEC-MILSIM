#!/usr/bin/env python3
"""Visuels du contrôle d'identité (addon identity) : gabarits de documents FICTIFS, icônes, silhouette, app ATAK.

Tous les documents portent la mention « SIMULATION » et des pays imaginaires (codes hors ISO : ALT, TNA, LVN, MLD).
Pays réels stylisés (univers réaliste-simulation) : trois gabarits GÉNÉRIQUES par zone de style (EU bordeaux, AM bleu,
OT vert), sans nom de pays (posé en jeu), sans armoiries ni motif officiel, emblème « SIM » inventé, mention
« SIMULATION — DOCUMENT DE JEU » dans le bandeau et en pied de page.
Les champs (nom, dates, MRZ…) ne sont PAS peints : ils sont posés en jeu par dialogs/idViewer.hpp
(fn_idViewerOnLoad, mêmes coordonnées que LAYOUT ci-dessous, en fraction du document).

  python tools/gen_id_assets.py            # écrit addons/identity/data/*.paa (HEMTT, sinon paa_dxt.py de l'ATAK)
  python tools/gen_id_assets.py --png DIR  # garde aussi les PNG sources dans DIR
  python tools/gen_id_assets.py --zones    # seulement les gabarits des zones de style (EU, AM, OT)

Côtés en puissances de 2 : gabarits 1024 x 512 (étirés à l'affichage au format du document), icônes 256, app 128.
Dépendances : Pillow (numpy pour le repli paa_dxt).
"""
import math
import os
import shutil
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "addons", "identity", "data")
FONTS = os.path.join(os.environ.get("WINDIR", "C:\\Windows"), "Fonts")

COUNTRIES = {
    # code : (nom FR, nom EN, couleur principale, fond)
    "ALT": ("RÉPUBLIQUE D'ALTIS", "REPUBLIC OF ALTIS", (43, 92, 168), (223, 233, 247)),
    "TNA": ("FÉDÉRATION DE TANOA", "FEDERATION OF TANOA", (36, 120, 80), (222, 242, 230)),
    "LVN": ("RÉPUBLIQUE DE LIVONIE", "REPUBLIC OF LIVONIA", (128, 40, 56), (245, 225, 228)),
    "MLD": ("RÉPUBLIQUE DE MALDEN", "REPUBLIC OF MALDEN", (176, 106, 28), (247, 236, 217)),
    # Zones de style des pays réels stylisés : aucun nom peint, gabarit commun à tous les pays de la zone.
    "EU": ("", "", (109, 26, 43), (240, 228, 230)),
    "AM": ("", "", (27, 42, 92), (226, 231, 244)),
    "OT": ("", "", (31, 77, 58), (224, 238, 230)),
}
ZONES = ("EU", "AM", "OT")
GAME_MENTION = "SIMULATION — DOCUMENT DE JEU"
GAME_FOOTER = "SIMULATION — DOCUMENT DE JEU — SANS VALEUR · GAME DOCUMENT — NO VALUE"

# Libellés peints : (x, y) en fraction du document ; la valeur est posée en jeu juste dessous (y + 0.035).
LAYOUT = {
    "card_recto": [
        ("NOM / SURNAME", 0.35, 0.20), ("PRÉNOMS / GIVEN NAMES", 0.35, 0.32),
        ("SEXE / SEX", 0.35, 0.44), ("NATIONALITÉ / NAT.", 0.47, 0.44), ("NÉ(E) LE / BIRTH", 0.72, 0.44),
        ("LIEU DE NAISSANCE / PLACE OF BIRTH", 0.35, 0.56),
        ("N° DU DOCUMENT / DOC. No", 0.35, 0.68), ("EXPIRE LE / EXPIRY", 0.72, 0.68),
    ],
    "card_verso": [
        ("ADRESSE / ADDRESS", 0.05, 0.06), ("AUTORITÉ / AUTHORITY", 0.05, 0.22),
        ("DATE DE DÉLIVRANCE / DATE OF ISSUE", 0.05, 0.38), ("PROFESSION / OCCUPATION", 0.55, 0.38),
    ],
    "passport": [
        ("TYPE", 0.36, 0.13), ("CODE", 0.46, 0.13), ("N° PASSEPORT / PASSPORT No", 0.62, 0.13),
        ("NOM / SURNAME", 0.36, 0.24), ("PRÉNOMS / GIVEN NAMES", 0.36, 0.35),
        ("NATIONALITÉ / NAT.", 0.36, 0.46), ("NÉ(E) LE / BIRTH", 0.66, 0.46),
        ("SEXE / SEX", 0.36, 0.57), ("LIEU DE NAISSANCE / PLACE OF BIRTH", 0.48, 0.57),
        ("DÉLIVRÉ / ISSUE", 0.36, 0.68), ("EXPIRE / EXPIRY", 0.53, 0.68), ("AUTORITÉ / AUTHORITY", 0.70, 0.68),
    ],
    "military_recto": [
        ("NOM / SURNAME", 0.35, 0.20), ("PRÉNOMS / GIVEN NAMES", 0.35, 0.32),
        ("GRADE / RANK", 0.35, 0.44), ("NÉ(E) LE / BIRTH", 0.65, 0.44),
        ("FORCE / SERVICE", 0.35, 0.56),
        ("N° DE CARTE / CARD No", 0.35, 0.68), ("EXPIRE LE / EXPIRY", 0.72, 0.68),
    ],
    "license_recto": [
        ("1. NOM / SURNAME", 0.35, 0.20), ("2. PRÉNOMS / GIVEN NAMES", 0.35, 0.31),
        ("3. DATE ET LIEU DE NAISSANCE / DATE AND PLACE OF BIRTH", 0.35, 0.42),
        ("4a. DÉLIVRANCE / ISSUE", 0.35, 0.53), ("4b. EXPIRATION / EXPIRY", 0.62, 0.53),
        ("4c. AUTORITÉ / AUTHORITY", 0.35, 0.64), ("5. N° / No", 0.35, 0.75), ("9. CATÉGORIES", 0.70, 0.75),
    ],
}
# Proportions réelles (largeur / hauteur) : carte ID-1, page de données TD3.
RATIO = {"card_recto": 1.585, "card_verso": 1.585, "passport": 1.42, "license_recto": 1.585, "license_verso": 1.585, "military_recto": 1.585}


def font(name, size):
    for n in (name, "arial.ttf"):
        p = os.path.join(FONTS, n)
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def canvas(kind):
    w = 1024
    return w, int(round(w / RATIO[kind]))


def guilloche(d, w, h, col, seed):
    c = tuple(min(255, int(v + (255 - v) * 0.55)) for v in col)
    for k in range(18):
        pts = []
        amp = 10 + (k * 7 + seed * 3) % 22
        per = 90 + (k * 13 + seed * 11) % 80
        off = k * (h / 18.0)
        for x in range(0, w + 8, 8):
            pts.append((x, off + amp * math.sin((x + k * 37 + seed * 50) / per * 2 * math.pi)))
        d.line(pts, fill=c, width=1)


def emblem(d, cx, cy, r, col, code):
    d.ellipse((cx - r, cy - r, cx + r, cy + r), outline=col, width=4)
    pts = []
    for i in range(10):
        a = -math.pi / 2 + i * math.pi / 5
        rr = r * (0.62 if i % 2 == 0 else 0.28)
        pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    d.polygon(pts, fill=col)
    f = font("bahnschrift.ttf", int(r * 0.42))
    d.text((cx, cy + r * 0.78), code, fill=col, font=f, anchor="mm")


def watermark(img, text="SIMULATION", alpha=38, color=(200, 30, 30)):
    w, h = img.size
    layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    f = font("bahnschrift.ttf", int(h * 0.22))
    ld.text((w / 2, h / 2), text, fill=color + (alpha,), font=f, anchor="mm")
    layer = layer.rotate(18, resample=Image.BICUBIC, center=(w / 2, h / 2))
    img.alpha_composite(layer)


def footer(d, w, h, text="SIMULATION — DOCUMENT FICTIF SANS VALEUR — FICTITIOUS DOCUMENT, NO VALUE"):
    d.rectangle((0, int(h * 0.94), w, h), fill=(170, 24, 24))
    d.text((w / 2, int(h * 0.97)), text, fill=(255, 255, 255), font=font("bahnschrift.ttf", int(h * 0.035)), anchor="mm")


def labels(d, w, h, kind, col):
    f = font("arialbd.ttf", max(11, int(h * 0.022)))
    for txt, x, y in LAYOUT[kind]:
        d.text((x * w, y * h), txt, fill=col, font=f)


def photo_frame(d, w, h, box, col):
    x, y, bw, bh = box
    d.rectangle((x * w - 3, y * h - 3, (x + bw) * w + 3, (y + bh) * h + 3), outline=col, width=2)


def chip_logo(d, cx, cy, s, col):
    # Symbole « document électronique » (rectangle + cercle), dessiné à la main.
    d.rectangle((cx - s, cy - s * 0.62, cx + s, cy + s * 0.62), outline=col, width=3)
    d.ellipse((cx - s * 0.32, cy - s * 0.32, cx + s * 0.32, cy + s * 0.32), outline=col, width=3)
    d.line((cx - s, cy, cx - s * 0.32, cy), fill=col, width=3)
    d.line((cx + s * 0.32, cy, cx + s, cy), fill=col, width=3)


def foot(code):
    return GAME_FOOTER if code in ZONES else "SIMULATION — DOCUMENT FICTIF SANS VALEUR — FICTITIOUS DOCUMENT, NO VALUE"


def header(d, w, h, code, title_fr, title_en, hb=0.17):
    name_fr, name_en, col, _ = COUNTRIES[code]
    if code in ZONES:
        name_en = GAME_MENTION
    d.rectangle((0, 0, w, int(h * hb)), fill=col)
    d.text((int(w * 0.13), int(h * hb * 0.30)), name_fr, fill=(255, 255, 255), font=font("bahnschrift.ttf", int(h * hb * 0.30)), anchor="lm")
    d.text((int(w * 0.13), int(h * hb * 0.72)), f"{title_fr}  ·  {title_en}  ·  {name_en}", fill=(235, 240, 250),
           font=font("arial.ttf", int(h * hb * 0.20)), anchor="lm")
    emblem(d, int(w * 0.065), int(h * hb * 0.5), int(h * hb * 0.40), (255, 255, 255), "SIM" if code in ZONES else code)


def base(kind, code, seed):
    w, h = canvas(kind)
    _, _, col, bg = COUNTRIES[code]
    if kind.startswith("license"):
        bg = (246, 222, 230)
    img = Image.new("RGBA", (w, h), bg + (255,))
    d = ImageDraw.Draw(img)
    guilloche(d, w, h, col, seed)
    return img, d, w, h, col


def card_recto(code):
    img, d, w, h, col = base("card_recto", code, 1)
    header(d, w, h, code, "CARTE NATIONALE D'IDENTITÉ", "IDENTITY CARD")
    photo_frame(d, w, h, (0.04, 0.21, 0.27, 0.62), col)
    labels(d, w, h, "card_recto", col)
    chip_logo(d, int(w * 0.93), int(h * 0.27), int(h * 0.035), col)
    watermark(img)
    footer(d, w, h, foot(code))
    return img


def card_verso(code):
    img, d, w, h, col = base("card_verso", code, 2)
    labels(d, w, h, "card_verso", col)
    emblem(d, int(w * 0.86), int(h * 0.24), int(h * 0.15), col, "SIM" if code in ZONES else code)
    # Zone MRZ (3 lignes posées en jeu).
    d.rectangle((0, int(h * 0.58), w, int(h * 0.94)), fill=(250, 250, 246, 255))
    watermark(img, alpha=26)
    footer(d, w, h, foot(code))
    return img


def passport(code):
    img, d, w, h, col = base("passport", code, 3)
    header(d, w, h, code, "PASSEPORT", "PASSPORT", hb=0.11)
    photo_frame(d, w, h, (0.04, 0.15, 0.28, 0.55), col)
    labels(d, w, h, "passport", col)
    chip_logo(d, int(w * 0.93), int(h * 0.055), int(h * 0.03), (255, 255, 255))
    d.rectangle((0, int(h * 0.78), w, int(h * 0.94)), fill=(250, 250, 246, 255))
    watermark(img)
    footer(d, w, h, foot(code))
    return img


def military_recto(code):
    img, d, w, h, col = base("military_recto", code, 6)
    header(d, w, h, code, "CARTE MILITAIRE", "MILITARY ID")
    photo_frame(d, w, h, (0.04, 0.21, 0.27, 0.62), col)
    labels(d, w, h, "military_recto", col)
    chip_logo(d, int(w * 0.93), int(h * 0.27), int(h * 0.035), col)
    watermark(img)
    footer(d, w, h, foot(code))
    return img


def license_recto(code):
    img, d, w, h, col = base("license_recto", code, 4)
    header(d, w, h, code, "PERMIS DE CONDUIRE", "DRIVING LICENCE")
    photo_frame(d, w, h, (0.04, 0.21, 0.27, 0.62), col)
    labels(d, w, h, "license_recto", col)
    watermark(img)
    footer(d, w, h, foot(code))
    return img


def license_verso():
    w, h = canvas("license_verso")
    img = Image.new("RGBA", (w, h), (246, 222, 230, 255))
    d = ImageDraw.Draw(img)
    guilloche(d, w, h, (150, 60, 100), 5)
    f = font("arialbd.ttf", int(h * 0.04))
    fs = font("arial.ttf", int(h * 0.034))
    cols = [0.04, 0.22, 0.42, 0.62, 0.82]
    heads = ["9. CAT.", "10. DÉLIVRÉ", "11. EXPIRE", "12. MENTIONS", ""]
    for x, t in zip(cols, heads):
        d.text((x * w, h * 0.06), t, fill=(120, 40, 70), font=f)
    for i, cat in enumerate(["AM", "A1", "A", "B", "C1", "C", "D", "BE"]):
        y = h * (0.13 + i * 0.058)
        d.line((w * 0.03, y, w * 0.97, y), fill=(190, 140, 160), width=1)
        d.text((w * 0.05, y + h * 0.010), cat, fill=(90, 30, 55), font=fs)
    # Zone MRZ (gabarit TD1, code « D< », comme le site) posée en jeu.
    d.rectangle((0, int(h * 0.62), w, int(h * 0.94)), fill=(250, 250, 246, 255))
    watermark(img, alpha=30)
    footer(d, w, h)
    return img


def silhouette():
    s = 256
    img = Image.new("RGBA", (s, s), (205, 214, 224, 255))
    d = ImageDraw.Draw(img)
    for y in range(s):
        v = int(214 - 30 * (y / s))
        d.line((0, y, s, y), fill=(v - 8, v, v + 8, 255))
    # Étirée à l'affichage en 35 x 45 : dessin pré-compressé en largeur.
    sil = (92, 98, 108, 255)
    d.ellipse((s * 0.30, s * 0.14, s * 0.70, s * 0.56), fill=sil)
    d.rectangle((s * 0.42, s * 0.50, s * 0.58, s * 0.66), fill=sil)
    d.ellipse((s * 0.04, s * 0.60, s * 0.96, s * 1.30), fill=sil)
    img = img.filter(ImageFilter.GaussianBlur(1.2))
    ImageDraw.Draw(img).text((s * 0.5, s * 0.93), "SIMULATION", fill=(230, 60, 60, 255), font=font("bahnschrift.ttf", 22), anchor="mm")
    return img


def hologram():
    s = 256
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    for i in range(36):
        a0 = i * 10
        hue = i / 36.0
        r = int(127 + 127 * math.sin(2 * math.pi * hue))
        g = int(127 + 127 * math.sin(2 * math.pi * (hue + 1 / 3)))
        b = int(127 + 127 * math.sin(2 * math.pi * (hue + 2 / 3)))
        d.arc((12, 12, s - 12, s - 12), a0, a0 + 10, fill=(r, g, b, 120), width=26)
    d.ellipse((70, 70, s - 70, s - 70), outline=(220, 230, 255, 110), width=6)
    return img.filter(ImageFilter.GaussianBlur(1.5))


def icon(kind):
    s = 256
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if kind == "military":
        col = (64, 82, 48, 255)
        d.rounded_rectangle((14, 58, 242, 202), 14, fill=(226, 230, 212, 255), outline=col, width=5)
        d.rectangle((14, 58, 242, 86), fill=col)
        d.rectangle((30, 98, 92, 182), fill=(200, 208, 190, 255), outline=col, width=3)
        d.ellipse((46, 108, 76, 140), fill=(96, 106, 84, 255))
        d.ellipse((36, 138, 86, 196), fill=(96, 106, 84, 255))
        d.rectangle((30, 182, 92, 186), fill=col)
        for i in range(3):
            d.line((116, 150 + i * 14, 150, 128 + i * 14, 184, 150 + i * 14), fill=col, width=6)
        d.rectangle((106, 104, 226, 112), fill=col)
    elif kind == "passport":
        d.rounded_rectangle((52, 20, 204, 236), 14, fill=(40, 52, 96, 255), outline=(20, 26, 50, 255), width=4)
        emblem(d, 128, 112, 46, (214, 180, 90), "ALT")
        d.text((128, 196), "PASSEPORT", fill=(214, 180, 90), font=font("bahnschrift.ttf", 20), anchor="mm")
    else:
        bg = (246, 222, 230, 255) if kind == "license" else (223, 233, 247, 255)
        col = (150, 60, 100, 255) if kind == "license" else (43, 92, 168, 255)
        d.rounded_rectangle((14, 58, 242, 202), 14, fill=bg, outline=col, width=5)
        d.rectangle((14, 58, 242, 86), fill=col)
        d.rectangle((30, 98, 92, 182), fill=(200, 208, 218, 255), outline=col, width=3)
        d.ellipse((46, 108, 76, 140), fill=(110, 116, 126, 255))
        d.ellipse((36, 138, 86, 196), fill=(110, 116, 126, 255))
        d.rectangle((30, 182, 92, 186), fill=col)
        for i, ww in enumerate((120, 96, 128, 80)):
            d.rectangle((106, 104 + i * 20, 106 + ww, 112 + i * 20), fill=col)
        if kind == "license":
            d.text((210, 72), "B", fill=(255, 255, 255), font=font("arialbd.ttf", 22), anchor="mm")
    return img


def app_icon():
    s = 128
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    white = (255, 255, 255, 255)
    d.rounded_rectangle((10, 26, 118, 98), 9, outline=white, width=7)
    d.ellipse((24, 40, 50, 66), fill=white)
    d.pieslice((18, 62, 56, 102), 180, 360, fill=white)
    for i, ww in enumerate((40, 30, 36)):
        d.rectangle((62, 42 + i * 13, 62 + ww, 48 + i * 13), fill=white)
    # Coche de contrôle.
    d.line((80, 104, 92, 116, 120, 84), fill=white, width=9, joint="curve")
    return img


def to_paa(png, paa):
    hemtt = shutil.which("hemtt")
    if hemtt:
        r = subprocess.run([hemtt, "utils", "paa", "convert", png, paa], capture_output=True, text=True)
        if r.returncode == 0 and os.path.exists(paa):
            return
        print(r.stdout, r.stderr)
    sys.path.insert(0, os.path.join(os.path.dirname(ROOT), "COMSPEC_ATAK_Native", "tools"))
    from paa_dxt import save_paa  # noqa: E402
    save_paa(Image.open(png).convert("RGBA"), paa)


def main():
    keep = None
    if "--png" in sys.argv:
        keep = sys.argv[sys.argv.index("--png") + 1]
        os.makedirs(keep, exist_ok=True)
    os.makedirs(OUT, exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="comspec_id_")
    jobs = {}
    zones_only = "--zones" in sys.argv
    military_only = "--military" in sys.argv
    for code in COUNTRIES:
        if zones_only and code not in ZONES:
            continue
        lc = code.lower()
        jobs[f"tpl_card_{lc}_recto_co"] = (card_recto(code), (1024, 512))
        jobs[f"tpl_card_{lc}_verso_co"] = (card_verso(code), (1024, 512))
        jobs[f"tpl_passport_{lc}_co"] = (passport(code), (1024, 512))
        jobs[f"tpl_license_{lc}_recto_co"] = (license_recto(code), (1024, 512))
        jobs[f"tpl_military_{lc}_recto_co"] = (military_recto(code), (1024, 512))
    jobs["icon_military_ca"] = (icon("military"), (256, 256))
    if military_only:
        jobs = {k: v for k, v in jobs.items() if "military" in k}
    elif zones_only:
        jobs = {k: v for k, v in jobs.items()}
    else:
        jobs["tpl_license_verso_co"] = (license_verso(), (1024, 512))
    if not zones_only and not military_only:
        jobs["silhouette_co"] = (silhouette(), (256, 256))
        jobs["hologram_ca"] = (hologram(), (256, 256))
        for k in ("card", "passport", "license"):
            jobs[f"icon_{k}_ca"] = (icon(k), (256, 256))
        jobs["app_id"] = (app_icon(), (128, 128))
    for name, (img, size) in jobs.items():
        img = img.resize(size, Image.LANCZOS)
        if name.endswith("_co"):
            img = img.convert("RGB").convert("RGBA")
        png = os.path.join(tmp, name + ".png")
        img.save(png)
        if keep:
            shutil.copy(png, os.path.join(keep, name + ".png"))
        to_paa(png, os.path.join(OUT, name + ".paa"))
        print("  +", name + ".paa")
    shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
