#!/usr/bin/env python3
"""Écrit addons/editor/data/*.paa : icônes originales de l'éditeur SSE (types, remises, états) et logo Athena.

  python tools/gen_editor_assets.py

Icônes dessinées ici, en blanc sur fond transparent (teintées par ctrlSetTextColor). Logo : réduit depuis
public/assets/brand/athena-logo-blanc-1024.png. PAA écrits par mod/COMSPEC_ATAK_Native/tools/paa_dxt.py.
Dépendances : Pillow, numpy.
"""
import os
import sys

from PIL import Image, ImageDraw

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = os.path.dirname(os.path.dirname(ROOT))
OUT = os.path.join(ROOT, "addons", "editor", "data")
sys.path.insert(0, os.path.join(REPO, "mod", "COMSPEC_ATAK_Native", "tools"))
from paa_dxt import save_paa  # noqa: E402

S = 512
W = (255, 255, 255, 255)
T = 34


def canvas():
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    return img, ImageDraw.Draw(img)


def person(d, cx, cy, r):
    d.ellipse((cx - r, cy - r, cx + r, cy + r), outline=W, width=T)
    d.arc((cx - 2 * r, cy + r + 20, cx + 2 * r, cy + 5 * r), 180, 360, fill=W, width=T)


def intel():
    img, d = canvas()
    d.polygon(((120, 56), (320, 56), (392, 128), (392, 456), (120, 456)), outline=W, width=T)
    d.line((320, 56, 320, 128, 392, 128), fill=W, width=T)
    for y in (216, 288, 360):
        d.line((176, y, 336, y), fill=W, width=T)
    return img


def identity():
    img, d = canvas()
    person(d, 256, 170, 92)
    return img


def paper():
    img, d = canvas()
    d.rounded_rectangle((40, 112, 472, 400), 32, outline=W, width=T)
    d.ellipse((104, 176, 200, 272), fill=W)
    d.pieslice((88, 272, 216, 392), 180, 360, fill=W)
    for y, x2 in ((192, 416), (256, 416), (320, 352)):
        d.line((264, y, x2, y), fill=W, width=T)
    return img


def case():
    img, d = canvas()
    d.polygon(((48, 136), (200, 136), (248, 192), (464, 192), (464, 416), (48, 416)), outline=W, width=T)
    d.line((48, 256, 464, 256), fill=W, width=T)
    return img


def kit():
    img, d = canvas()
    d.rounded_rectangle((56, 176, 456, 432), 28, outline=W, width=T)
    d.rounded_rectangle((184, 88, 328, 192), 20, outline=W, width=T)
    d.line((56, 288, 456, 288), fill=W, width=T)
    d.rectangle((224, 264, 288, 320), fill=W)
    return img


def pickup():
    img, d = canvas()
    d.ellipse((136, 48, 376, 288), outline=W, width=T)
    d.ellipse((224, 136, 288, 200), fill=W)
    d.line((156, 248, 256, 464, 356, 248), fill=W, width=T, joint="curve")
    return img


def search():
    img, d = canvas()
    d.ellipse((64, 64, 336, 336), outline=W, width=T)
    d.line((304, 304, 448, 448), fill=W, width=T + 18)
    return img


def send():
    img, d = canvas()
    d.rounded_rectangle((144, 32, 368, 480), 36, outline=W, width=T)
    d.line((224, 88, 288, 88), fill=W, width=18)
    d.line((256, 368, 256, 192), fill=W, width=T)
    d.line((192, 256, 256, 184, 320, 256), fill=W, width=T, joint="curve")
    return img


def draft():
    img, d = canvas()
    d.line((96, 416, 112, 320, 352, 80, 432, 160, 192, 400, 96, 416), fill=W, width=T, joint="curve")
    d.line((304, 128, 384, 208), fill=W, width=T)
    return img


def placed():
    img, d = canvas()
    d.ellipse((48, 48, 464, 464), outline=W, width=T)
    d.line((144, 264, 232, 352, 376, 176), fill=W, width=T + 6, joint="curve")
    return img


def template():
    img, d = canvas()
    d.rounded_rectangle((64, 64, 360, 360), 24, outline=W, width=T)
    d.rounded_rectangle((152, 152, 448, 448), 24, outline=W, width=T)
    return img


def archived():
    img, d = canvas()
    d.rectangle((48, 88, 464, 192), outline=W, width=T)
    d.rectangle((88, 192, 424, 432), outline=W, width=T)
    d.line((200, 272, 312, 272), fill=W, width=T)
    return img


ICONS = {
    "type_intel": intel, "type_identity": identity, "type_paper": paper, "type_case": case, "type_kit": kit,
    "delivery_pickup": pickup, "delivery_search": search, "delivery_send": send,
    "state_draft": draft, "state_placed": placed, "state_template": template, "state_archived": archived,
}


def logo():
    src = Image.open(os.path.join(REPO, "public", "assets", "brand", "athena-logo-blanc-1024.png")).convert("RGBA")
    src.thumbnail((512, 256), Image.LANCZOS)
    out = Image.new("RGBA", (512, 256), (0, 0, 0, 0))
    out.paste(src, ((512 - src.width) // 2, (256 - src.height) // 2), src)
    return out


def main():
    os.makedirs(OUT, exist_ok=True)
    for name, draw in ICONS.items():
        save_paa(draw().resize((128, 128), Image.LANCZOS), os.path.join(OUT, f"{name}_ca.paa"))
        print("  +", name)
    save_paa(logo(), os.path.join(OUT, "logo_athena_ca.paa"))
    print("  + logo_athena")


if __name__ == "__main__":
    main()
