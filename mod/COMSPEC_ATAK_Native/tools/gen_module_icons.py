#!/usr/bin/env python3
"""
Icônes des modules Zeus et Eden (ATAK et SSE), toutes dessinées ici : même trait, blanc sur transparent, 64 px.

    python gen_module_icons.py DOSSIER_PNG
    hemtt utils paa convert DOSSIER_PNG/mod_relay.png ../Sources/addons/main/data/mod_relay.paa
    (idem vers mod/@COMSPEC_SSE/addons/zeus/data/)
"""
import os
import sys

from PIL import Image, ImageDraw

S = 256
W = 18
C = (255, 255, 255, 255)


def phone(d):
    d.rounded_rectangle((76, 24, 180, 232), radius=18, outline=C, width=W)
    d.line((110, 196, 146, 196), fill=C, width=W)


def relay(d):
    d.line((128, 104, 84, 232), fill=C, width=W)
    d.line((128, 104, 172, 232), fill=C, width=W)
    d.line((102, 180, 154, 180), fill=C, width=W - 4)
    d.ellipse((110, 72, 146, 108), fill=C)
    d.arc((72, 34, 184, 146), 205, 335, fill=C, width=W - 4)


def jam(d):
    d.ellipse((36, 36, 220, 220), outline=C, width=W)
    d.line((70, 70, 186, 186), fill=C, width=W)


def screen(d):
    d.rounded_rectangle((24, 44, 232, 176), radius=10, outline=C, width=W)
    d.line((128, 176, 128, 216), fill=C, width=W)
    d.line((80, 218, 176, 218), fill=C, width=W)


def ai(d):
    d.ellipse((92, 28, 164, 100), outline=C, width=W)
    d.arc((48, 116, 208, 276), 180, 360, fill=C, width=W)
    d.line((48, 196, 208, 196), fill=C, width=W)


def data(d):
    d.rounded_rectangle((60, 28, 196, 92), radius=8, outline=C, width=W)
    d.rounded_rectangle((60, 164, 196, 228), radius=8, outline=C, width=W)
    d.line((128, 92, 128, 164), fill=C, width=W)


def net(d):
    pts = [(128, 48), (48, 200), (208, 200)]
    for i, a in enumerate(pts):
        d.line((a, pts[(i + 1) % 3]), fill=C, width=W - 4)
    for x, y in pts:
        d.ellipse((x - 26, y - 26, x + 26, y + 26), fill=C)


def map_(d):
    d.ellipse((72, 24, 184, 136), outline=C, width=W)
    d.polygon([(84, 118), (172, 118), (128, 232)], fill=C)
    d.ellipse((110, 62, 146, 98), fill=C)


def person(d):
    d.ellipse((88, 28, 168, 108), fill=C)
    d.pieslice((40, 124, 216, 300), 180, 360, fill=C)


def site(d):
    d.line([(20, 124), (128, 28), (236, 124)], fill=C, width=W)
    d.line([(56, 104), (56, 228), (200, 228), (200, 104)], fill=C, width=W)


def evidence(d):
    d.ellipse((36, 36, 164, 164), outline=C, width=W)
    d.line((150, 150, 224, 224), fill=C, width=W + 8)


def digital(d):
    d.rounded_rectangle((60, 60, 196, 196), radius=8, outline=C, width=W)
    for t in (96, 128, 160):
        d.line((24, t, 60, t), fill=C, width=10)
        d.line((196, t, 232, t), fill=C, width=10)
        d.line((t, 24, t, 60), fill=C, width=10)
        d.line((t, 196, t, 232), fill=C, width=10)


def doc(d):
    d.line([(60, 28), (152, 28), (196, 72), (196, 228), (60, 228), (60, 28)], fill=C, width=W, joint="curve")
    for y in (104, 144, 184):
        d.line((88, y, 168, y), fill=C, width=12)


def save(d):
    d.line((128, 28, 128, 130), fill=C, width=W + 4)
    d.polygon([(72, 112), (184, 112), (128, 180)], fill=C)
    d.line([(36, 170), (36, 226), (220, 226), (220, 170)], fill=C, width=W)


def tool(d):
    for y, x in ((64, 92), (128, 164), (192, 112)):
        d.line((28, y, 228, y), fill=C, width=12)
        d.ellipse((x - 26, y - 26, x + 26, y + 26), fill=C)


def trash(d):
    d.line((40, 68, 216, 68), fill=C, width=W)
    d.line((100, 36, 156, 36), fill=C, width=W)
    d.line([(64, 72), (80, 228), (176, 228), (192, 72)], fill=C, width=W, joint="curve")


def id_(d):
    d.rounded_rectangle((20, 56, 236, 200), radius=14, outline=C, width=W)
    d.ellipse((52, 88, 104, 140), fill=C)
    d.pieslice((40, 144, 116, 220), 180, 360, fill=C)
    for y in (104, 144):
        d.line((132, y, 208, y), fill=C, width=12)


def check(d):
    d.line([(128, 24), (216, 60), (216, 136), (128, 232), (40, 136), (40, 60), (128, 24)], fill=C, width=W, joint="curve")
    d.line([(88, 124), (118, 156), (172, 96)], fill=C, width=W, joint="curve")


def flag(d):
    d.line((64, 24, 64, 236), fill=C, width=W)
    d.polygon([(72, 36), (216, 80), (72, 128)], fill=C)


ICONS = {"phone": phone, "relay": relay, "jam": jam, "screen": screen, "ai": ai, "data": data, "net": net, "map": map_, "person": person,
         "site": site, "evidence": evidence, "digital": digital, "doc": doc, "save": save, "tool": tool, "trash": trash, "id": id_,
         "check": check, "flag": flag}

if __name__ == "__main__":
    out = sys.argv[1]
    os.makedirs(out, exist_ok=True)
    for name, fn in ICONS.items():
        im = Image.new("RGBA", (S, S), (0, 0, 0, 0))
        fn(ImageDraw.Draw(im))
        im.resize((64, 64), Image.LANCZOS).save(os.path.join(out, f"mod_{name}.png"))
    print(len(ICONS), "icones")
