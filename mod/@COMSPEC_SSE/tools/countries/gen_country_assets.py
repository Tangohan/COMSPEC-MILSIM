#!/usr/bin/env python3
"""Gabarits des papiers de PAYS RÉELS (registre countries.json), dessinés avec l'API de tools/gen_id_assets.py.

Papiers de JEU, pas des faux : aucun modèle officiel, aucune armoirie, aucun emblème d'administration, aucun motif de
sécurité réel. Un dessin original par pays « full » (couleurs du pays, drapeau en simples bandes, filigrane des deux lettres
ISO), des gabarits neutres « bare » pour tous les autres. Chaque gabarit porte un bandeau SPÉCIMEN · SIMULATION dans la
langue du pays et en anglais, en pied de document (hors des champs). Aucun texte de champ n'est peint : nom du pays,
titre du papier et étiquettes sont posés en jeu (fn_idCountryChrome), dans la langue du pays et en anglais.

  python -I tools/countries/gen_country_assets.py                # écrit addons/identity/data/*.paa
  python -I tools/countries/gen_country_assets.py --png DIR      # garde aussi les PNG (aperçus)
  python -I tools/countries/gen_country_assets.py --only FRA,USA # quelques pays (et les gabarits neutres)

Fichiers : tpl_card_<pays>_recto_co, tpl_passport_<pays>_co (pays « full »), tpl_license_usa_recto_co (permis d'État) ;
tpl_card_bare_recto_co, tpl_card_bare_verso_co, tpl_passport_bare_co, tpl_license_bare_recto_co,
tpl_military_bare_recto_co, tpl_vreg_bare_co ; icon_residence_ca, icon_vreg_ca. 1024 x 512 (icônes 256), DXT.
Conversion : ImageToPAA (Arma 3 Tools) s'il est installé, sinon celle de gen_id_assets (HEMTT, paa_dxt).
"""
import json
import os
import shutil
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(HERE))
import gen_id_assets as G  # noqa: E402
from PIL import Image, ImageDraw  # noqa: E402

sys.stdout.reconfigure(encoding="utf-8")
REG = json.load(open(os.path.join(HERE, "countries.json"), encoding="utf-8"))
IMAGE_TO_PAA = r"F:\SteamLibrary\steamapps\common\Arma 3 Tools\ImageToPAA\ImageToPAA.exe"

SPECIMEN = {"fr": "SPÉCIMEN · SIMULATION", "de": "MUSTER · SIMULATION", "nl": "SPECIMEN · SIMULATIE", "it": "FACSIMILE · SIMULAZIONE",
            "es": "ESPÉCIMEN · SIMULACIÓN", "pl": "WZÓR · SYMULACJA", "en": "SPECIMEN · SIMULATION"}
BAND = {1.585: 0.155, 1.42: 0.105, 1.414: 0.105}
PHOTO_CARD = G.PHOTO_CARD
PHOTO_PASSPORT = G.PHOTO_PASSPORT
CHIP_CARD = G.CHIP_CARD
NEUTRAL = (118, 128, 142)


def rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def specimen_line(langs):
    parts = []
    for l in list(langs) + ["en"]:
        t = SPECIMEN.get(l)
        if t and t not in parts:
            parts.append(t)
    return "  ·  ".join(parts) + "  ·  DOCUMENT DE JEU, SANS VALEUR · GAME DOCUMENT, NO VALUE"


def specimen_strip(doc, y0, text, col=(176, 40, 44)):
    """Bandeau SPÉCIMEN en pied : fond pâle, filets, texte centré (lisible, hors des champs)."""
    d = doc.d
    d.rectangle((0, doc.Y(y0), doc.W, doc.H), fill=G.tint(col, 0.9) + (255,))
    d.line((0, doc.Y(y0), doc.W, doc.Y(y0)), fill=G.tint(col, 0.45) + (255,), width=doc.ss)
    h = 1.0 - y0
    f = G.font("bahnschrift.ttf", doc.px(h * 0.52), 600, 84)
    while G.text_w(f, text) > doc.W * 0.94 and f.size > 6:
        f = G.font("bahnschrift.ttf", f.size - 1, 600, 84)
    d.text((doc.X(0.5), doc.Y(y0 + h * 0.52)), text, fill=col + (255,), font=f, anchor="mm")


def flag(doc, x, y, w, h, kind, cols):
    """Drapeau simple (bandes, croix, canton) : jamais d'armoiries, d'étoiles ni d'inscriptions."""
    d = doc.d
    X0, Y0, X1, Y1 = doc.box(x, y, w, h)
    W, H = X1 - X0, Y1 - Y0
    c = [rgb(v) + (255,) for v in cols] or [(255, 255, 255, 255)]
    white = (255, 255, 255, 255)
    pad = doc.ss * 3
    d.rectangle((X0 - pad, Y0 - pad, X1 + pad, Y1 + pad), fill=(255, 255, 255, 235))
    n = len(c)
    if kind == "v":
        for i, col in enumerate(c):
            d.rectangle((X0 + W * i / n, Y0, X0 + W * (i + 1) / n, Y1), fill=col)
    elif kind == "canton":
        for i in range(7):
            d.rectangle((X0, Y0 + H * i / 7, X1, Y0 + H * (i + 1) / 7), fill=c[i % 2] if n > 1 else c[0])
        d.rectangle((X0, Y0, X0 + W * 0.42, Y0 + H * 4 / 7), fill=c[2] if n > 2 else c[0])
    elif kind == "cross":
        red = c[1] if n > 1 else (200, 16, 46, 255)
        d.rectangle((X0, Y0, X1, Y1), fill=c[0])
        d.rectangle((X0, Y0 + H * 0.36, X1, Y0 + H * 0.64), fill=white)
        d.rectangle((X0 + W * 0.42, Y0, X0 + W * 0.58, Y1), fill=white)
        d.rectangle((X0, Y0 + H * 0.41, X1, Y0 + H * 0.59), fill=red)
        d.rectangle((X0 + W * 0.45, Y0, X0 + W * 0.55, Y1), fill=red)
    elif kind == "nordic":
        d.rectangle((X0, Y0, X1, Y1), fill=c[0])
        cc = c[1] if n > 1 else white
        d.rectangle((X0, Y0 + H * 0.38, X1, Y0 + H * 0.62), fill=cc)
        d.rectangle((X0 + W * 0.28, Y0, X0 + W * 0.44, Y1), fill=cc)
        if n > 2:
            d.rectangle((X0, Y0 + H * 0.44, X1, Y0 + H * 0.56), fill=c[2])
            d.rectangle((X0 + W * 0.32, Y0, X0 + W * 0.40, Y1), fill=c[2])
    elif kind == "swiss":
        d.rectangle((X0, Y0, X1, Y1), fill=c[0])
        cc = c[1] if n > 1 else white
        d.rectangle((X0 + W * 0.40, Y0 + H * 0.18, X0 + W * 0.60, Y0 + H * 0.82), fill=cc)
        d.rectangle((X0 + W * 0.27, Y0 + H * 0.40, X0 + W * 0.73, Y0 + H * 0.60), fill=cc)
    else:
        for i, col in enumerate(c):
            d.rectangle((X0, Y0 + H * i / n, X1, Y0 + H * (i + 1) / n), fill=col)


def flag_box(ratio, band):
    """Même place que fn_idCountryChrome : x 0,025, y = 19 % du bandeau, hauteur 62 %, drapeau 3:2."""
    fh = band * 0.62
    return 0.025, band * 0.19, 1.5 * fh / ratio, fh


def ribbon(doc, cols, alpha=0.10):
    """Ruban diagonal aux couleurs du drapeau, très pâle, dans le coin bas droit (marque de pays, pas un motif officiel)."""
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    n = len(cols)
    W, H = doc.W, doc.H
    wdt = W * 0.05
    for i, col in enumerate(cols):
        off = i * wdt
        d.polygon([(W * 0.62 + off, H), (W * 0.62 + off + wdt, H), (W + off + wdt, H * 0.32), (W + off, H * 0.32)], fill=rgb(col) + (255,))
    doc.blend(lay, alpha)


def iso_watermark(doc, text, col, cx=0.80, cy=0.56, size=0.42, alpha=0.07):
    lay = doc.layer()
    d = ImageDraw.Draw(lay)
    f = G.font("bahnschrift.ttf", doc.px(size), 700, 80)
    d.text((doc.X(cx), doc.Y(cy)), text, fill=tuple(col) + (255,), font=f, anchor="mm")
    doc.blend(lay, alpha)


def band_header(doc, col, band):
    G.header(doc, col, "", "", band=band, emblem_at=None, pill_text=None)


# ----------------------------------------------------------------------------------------------- dessins par pays
def country_card(code, c, kind="card"):
    """Recto de carte (ID-1) : carte d'identité, titre de séjour ; « licence » : permis d'État (États-Unis)."""
    ratio = 1.585
    band = BAND[ratio]
    main, accent = rgb(c["colors"]["main"]), rgb(c["colors"]["accent"])
    bg = G.tint(main, 0.935 if kind == "card" else 0.955)
    doc = G.Doc(ratio, bg)
    seed = sum(map(ord, code)) + (11 if kind == "licence" else 0)
    G.guilloche(doc, main, seed=seed, alpha=0.22, accent=accent, rosettes=[(0.93, 0.60, 0.26), (0.18, 0.52, 0.30)])
    ribbon(doc, c["flag"]["colors"])
    iso_watermark(doc, c["iso2"], main)
    band_header(doc, main, band)
    flag(doc, *flag_box(ratio, band), c["flag"]["kind"], c["flag"]["colors"])
    G.microtext(doc, band + 0.014, main)
    G.photo_frame(doc, PHOTO_CARD, main)
    if kind == "card":
        G.chip(doc, CHIP_CARD)
    G.signature(doc, 0.34, 0.60, 0.885, G.label_colour(main))
    specimen_strip(doc, 0.935, specimen_line(c["languages"]))
    return doc.finish()


def country_passport(code, c):
    ratio = 1.42
    band = BAND[ratio]
    main, accent = rgb(c["colors"]["main"]), rgb(c["colors"]["accent"])
    doc = G.Doc(ratio, G.tint(main, 0.94))
    G.guilloche(doc, main, seed=sum(map(ord, code)) + 3, alpha=0.2, accent=accent, rosettes=[(0.72, 0.42, 0.30), (0.18, 0.44, 0.28)], y_range=(0.12, 0.74))
    ribbon(doc, c["flag"]["colors"], 0.08)
    iso_watermark(doc, c["iso2"], main, cx=0.78, cy=0.45, size=0.38)
    band_header(doc, main, band)
    flag(doc, *flag_box(ratio, band), c["flag"]["kind"], c["flag"]["colors"])
    G.edoc_symbol(doc, 0.95, band * 0.5, band * 0.24)
    G.microtext(doc, band + 0.014, main)
    G.photo_frame(doc, PHOTO_PASSPORT, main)
    G.mrz_zone(doc, 0.785, 0.965)
    specimen_strip(doc, 0.968, specimen_line(c["languages"]))
    return doc.finish()


# ----------------------------------------------------------------------------------------------- gabarits neutres
GENERIC = specimen_line(["fr"])


def bare_card_recto():
    doc = G.Doc(1.585, (240, 242, 245))
    G.guilloche(doc, NEUTRAL, seed=21, alpha=0.18, accent=(150, 132, 104), rosettes=[(0.93, 0.60, 0.26), (0.18, 0.52, 0.30)])
    band_header(doc, (214, 218, 224), BAND[1.585])
    G.microtext(doc, BAND[1.585] + 0.014, NEUTRAL)
    G.photo_frame(doc, PHOTO_CARD, NEUTRAL)
    G.chip(doc, CHIP_CARD)
    G.signature(doc, 0.34, 0.60, 0.885, G.label_colour(NEUTRAL))
    specimen_strip(doc, 0.935, GENERIC)
    return doc.finish()


def bare_card_verso():
    doc = G.Doc(1.585, (240, 242, 245))
    G.guilloche(doc, NEUTRAL, seed=22, alpha=0.16, y_range=(0.05, 0.55), bands=2)
    G.mrz_zone(doc, 0.575, 0.96)
    specimen_strip(doc, 0.962, GENERIC)
    return doc.finish()


def bare_passport():
    doc = G.Doc(1.42, (241, 241, 238))
    G.guilloche(doc, NEUTRAL, seed=23, alpha=0.17, rosettes=[(0.72, 0.42, 0.30), (0.18, 0.44, 0.28)], y_range=(0.12, 0.74))
    band_header(doc, (214, 218, 224), BAND[1.42])
    G.edoc_symbol(doc, 0.95, BAND[1.42] * 0.5, BAND[1.42] * 0.24, (120, 128, 140))
    G.microtext(doc, BAND[1.42] + 0.014, NEUTRAL)
    G.photo_frame(doc, PHOTO_PASSPORT, NEUTRAL)
    G.mrz_zone(doc, 0.785, 0.965)
    specimen_strip(doc, 0.968, GENERIC)
    return doc.finish()


def bare_license():
    main, accent = G.LICENCE
    doc = G.Doc(1.585, (251, 233, 237))
    G.guilloche(doc, main, seed=24, alpha=0.2, accent=accent, rosettes=[(0.92, 0.50, 0.24), (0.18, 0.52, 0.30)])
    band_header(doc, (214, 218, 224), BAND[1.585])
    G.microtext(doc, BAND[1.585] + 0.014, main)
    G.photo_frame(doc, PHOTO_CARD, main)
    G.signature(doc, 0.34, 0.60, 0.885, G.label_colour(main))
    specimen_strip(doc, 0.935, GENERIC)
    return doc.finish()


def bare_military():
    main, accent = G.MILITARY
    doc = G.Doc(1.585, (236, 237, 226))
    G.guilloche(doc, main, seed=25, alpha=0.2, accent=accent, rosettes=[(0.93, 0.60, 0.26), (0.18, 0.52, 0.30)])
    band_header(doc, (214, 218, 224), BAND[1.585])
    G.microtext(doc, BAND[1.585] + 0.014, main)
    G.photo_frame(doc, PHOTO_CARD, main)
    G.chip(doc, CHIP_CARD)
    G.signature(doc, 0.34, 0.60, 0.885, G.label_colour(main))
    specimen_strip(doc, 0.935, GENERIC)
    return doc.finish()


def bare_vreg():
    """Certificat d'immatriculation : feuillet crème A6, rangées des champs (CfgComspecDocuments vehicle_registration)."""
    doc = G.Doc(1.414, (246, 241, 228))
    G.paper(doc, (246, 241, 228))
    G.guilloche(doc, (120, 112, 92), seed=26, alpha=0.12, lattice=False, bands=2, y_range=(0.2, 0.9))
    band_header(doc, (214, 218, 224), BAND[1.414])
    d = doc.d
    rule = (196, 186, 160, 255)
    for y in (0.17, 0.31, 0.45, 0.59, 0.73, 0.865):
        d.line((doc.X(0.04), doc.Y(y), doc.X(0.96), doc.Y(y)), fill=rule, width=doc.ss)
    specimen_strip(doc, 0.968, GENERIC)
    return doc.finish()


# ----------------------------------------------------------------------------------------------- sortie
def to_paa(png, paa):
    if os.path.exists(IMAGE_TO_PAA):
        if os.path.exists(paa):
            os.remove(paa)
        r = subprocess.run([IMAGE_TO_PAA, png, paa], capture_output=True, text=True)
        if r.returncode == 0 and os.path.exists(paa):
            return
        print(r.stdout, r.stderr)
    G.to_paa(png, paa)


def main():
    keep = sys.argv[sys.argv.index("--png") + 1] if "--png" in sys.argv else None
    only = set(sys.argv[sys.argv.index("--only") + 1].split(",")) if "--only" in sys.argv else None
    jobs = {
        "tpl_card_bare_recto_co": bare_card_recto(), "tpl_card_bare_verso_co": bare_card_verso(), "tpl_passport_bare_co": bare_passport(),
        "tpl_license_bare_recto_co": bare_license(), "tpl_military_bare_recto_co": bare_military(), "tpl_vreg_bare_co": bare_vreg(),
        "icon_residence_ca": G.icon_card((30, 110, 120), G.tint((30, 110, 120), 0.9), code="RP"),
        "icon_vreg_ca": G.icon_sheet((96, 84, 60), title="REG"),
    }
    for code, c in REG.items():
        if c["tier"] != "full" or (only and code not in only):
            continue
        lc = code.lower()
        jobs[f"tpl_card_{lc}_recto_co"] = country_card(code, c)
        jobs[f"tpl_passport_{lc}_co"] = country_passport(code, c)
        if code == "USA":
            jobs["tpl_license_usa_recto_co"] = country_card(code, c, "licence")
    os.makedirs(G.OUT, exist_ok=True)
    if keep:
        os.makedirs(keep, exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="comspec_pays_")
    for name, img in jobs.items():
        if name.endswith("_co"):
            img = img.convert("RGB").convert("RGBA")
        png = os.path.join(tmp, name + ".png")
        img.save(png)
        if keep:
            shutil.copy(png, os.path.join(keep, name + ".png"))
        if "--no-paa" not in sys.argv:
            to_paa(png, os.path.join(G.OUT, name + ".paa"))
        print("  +", name)
    shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    main()
