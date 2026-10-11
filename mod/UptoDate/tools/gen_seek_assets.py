#!/usr/bin/env python3
"""
Textures du terminal SEEK (écran, tuiles d'accueil, viseur du visage).
Dessins originaux COMSPEC, Pillow seulement.

Usage : python3 gen_seek_assets.py <hemtt>
Sortie : Sources/comspec-overwatch-addons/connect/img/device/seek_*.paa
HEMTT (`hemtt utils paa convert`) n'écrase pas un fichier existant : on supprime d'abord.
"""
import math
import os
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "comspec-overwatch-addons", "connect", "img", "device")
HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
SS = 4  # suréchantillonnage

INK = (216, 245, 232, 255)
INK_DIM = (120, 190, 168, 255)
TILE_BG = (20, 52, 48, 245)
TILE_EDGE = (58, 128, 110, 255)


def font(size):
    for path in (
        "/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    ):
        if os.path.exists(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def screen_bg(w=1024, h=512):
    """Verre LCD : dégradé sarcelle, lignes de balayage, reflet en haut à gauche, vignette."""
    img = Image.new("RGBA", (w, h))
    px = img.load()
    for y in range(h):
        t = y / (h - 1)
        base = (int(16 - 6 * t), int(34 - 10 * t), int(33 - 9 * t))
        scan = 3 if y % 3 == 0 else 0
        for x in range(w):
            dx = (x / w - 0.5) * 2
            dy = (t - 0.5) * 2
            v = 1 - 0.32 * min(1.0, (dx * dx * 0.6 + dy * dy))
            px[x, y] = (
                max(0, int(base[0] * v) - scan),
                max(0, int(base[1] * v) - scan),
                max(0, int(base[2] * v) - scan),
                255,
            )
    glare = Image.new("L", (w, h), 0)
    d = ImageDraw.Draw(glare)
    d.polygon([(0, 0), (int(w * 0.46), 0), (int(w * 0.18), h), (0, h)], fill=16)
    glare = glare.filter(ImageFilter.GaussianBlur(w // 30))
    white = Image.new("RGBA", (w, h), (190, 240, 225, 255))
    img = Image.composite(white, img, glare)
    img.putalpha(255)
    return img


def tile(icon_fn, label, sub, w=512, h=256):
    W, H = w * SS, h * SS
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    r = 22 * SS
    d.rounded_rectangle((2 * SS, 2 * SS, W - 2 * SS, H - 2 * SS), radius=r, fill=TILE_BG, outline=TILE_EDGE, width=3 * SS)
    # Liseré clair en haut (relief).
    hi = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(hi).rounded_rectangle((8 * SS, 8 * SS, W - 8 * SS, 30 * SS), radius=12 * SS, fill=(80, 160, 140, 40))
    img.alpha_composite(hi)
    d = ImageDraw.Draw(img)
    # Pastille d'icône à gauche.
    cx, cy, rad = int(H * 0.5), int(H * 0.5), int(H * 0.36)
    d.ellipse((cx - rad, cy - rad, cx + rad, cy + rad), fill=(12, 34, 31, 255), outline=(70, 150, 128, 255), width=3 * SS)
    icon_fn(d, cx, cy, int(rad * 0.62))
    # Libellé gravé dans la tuile (le bouton posé dessus est transparent).
    tx = int(H * 0.94)
    f1 = font(54 * SS)
    while d.textlength(label, font=f1) > W - tx - 14 * SS:
        f1 = font(int(f1.size * 0.92))
    d.text((tx, int(H * 0.22)), label, font=f1, fill=INK)
    f2 = font(31 * SS)
    while max(d.textlength(line, font=f2) for line in sub.split("\n")) > W - tx - 14 * SS:
        f2 = font(int(f2.size * 0.94))
    for i, line in enumerate(sub.split("\n")):
        d.text((tx, int(H * 0.54) + i * 36 * SS), line, font=f2, fill=INK_DIM)
    return img.resize((w, h), Image.LANCZOS)


def lw(n=1.0):
    return max(1, int(5 * SS * n))


def ic_subject(d, cx, cy, s):
    d.ellipse((cx - s * 0.42, cy - s * 0.95, cx + s * 0.42, cy - s * 0.1), outline=INK, width=lw())
    d.arc((cx - s * 0.95, cy + s * 0.05, cx + s * 0.95, cy + s * 1.9), 200, 340, fill=INK, width=lw())
    d.line([(cx - s * 0.86, cy + s * 0.62), (cx - s * 0.86, cy + s * 0.95)], fill=INK, width=lw())
    d.line([(cx + s * 0.86, cy + s * 0.62), (cx + s * 0.86, cy + s * 0.95)], fill=INK, width=lw())


def ic_context(d, cx, cy, s):
    d.rounded_rectangle((cx - s * 0.7, cy - s * 0.85, cx + s * 0.7, cy + s * 0.95), radius=int(s * 0.12), outline=INK, width=lw())
    d.rectangle((cx - s * 0.3, cy - s * 1.0, cx + s * 0.3, cy - s * 0.7), fill=INK)
    for i, k in enumerate((-0.35, 0.05, 0.45)):
        d.ellipse((cx - s * 0.45 - s * 0.08, cy + s * k - s * 0.08, cx - s * 0.45 + s * 0.08, cy + s * k + s * 0.08), fill=INK)
        d.line([(cx - s * 0.25, cy + s * k), (cx + s * (0.5 - 0.12 * i), cy + s * k)], fill=INK, width=lw(0.8))


def ic_bio(d, cx, cy, s):
    # Empreinte : anneaux concentriques ouverts, coupure décalée à chaque anneau.
    for i in range(5):
        r = s * (0.18 + i * 0.19)
        gap0 = 95 + i * 14
        d.arc((cx - r * 0.85, cy - r * 1.1, cx + r * 0.85, cy + r * 1.1), gap0 + 28, gap0 + 360 - 28, fill=INK, width=lw(0.75))


def ic_terrain(d, cx, cy, s):
    d.ellipse((cx - s * 0.75, cy - s * 0.85, cx + s * 0.35, cy + s * 0.25), outline=INK, width=lw())
    d.line([(cx + s * 0.22, cy + s * 0.12), (cx + s * 0.85, cy + s * 0.8)], fill=INK, width=lw(1.6))
    d.line([(cx - s * 0.5, cy - s * 0.3), (cx + s * 0.1, cy - s * 0.3)], fill=INK_DIM, width=lw(0.7))
    d.line([(cx - s * 0.2, cy - s * 0.6), (cx - s * 0.2, cy)], fill=INK_DIM, width=lw(0.7))


def ic_photo(d, cx, cy, s):
    d.rounded_rectangle((cx - s * 0.95, cy - s * 0.55, cx + s * 0.95, cy + s * 0.8), radius=int(s * 0.18), outline=INK, width=lw())
    d.rectangle((cx - s * 0.35, cy - s * 0.78, cx + s * 0.25, cy - s * 0.55), fill=INK)
    d.ellipse((cx - s * 0.45, cy - s * 0.32, cx + s * 0.45, cy + s * 0.58), outline=INK, width=lw())
    d.ellipse((cx - s * 0.16, cy - s * 0.03, cx + s * 0.16, cy + s * 0.29), fill=INK_DIM)
    d.ellipse((cx + s * 0.6, cy - s * 0.4, cx + s * 0.78, cy - s * 0.22), fill=INK)


def ic_dossier(d, cx, cy, s):
    d.polygon([
        (cx - s * 0.95, cy - s * 0.7), (cx - s * 0.3, cy - s * 0.7), (cx - s * 0.15, cy - s * 0.5),
        (cx + s * 0.95, cy - s * 0.5), (cx + s * 0.95, cy + s * 0.8), (cx - s * 0.95, cy + s * 0.8),
    ], outline=INK, width=lw())
    d.line([(cx - s * 0.95, cy - s * 0.25), (cx + s * 0.95, cy - s * 0.25)], fill=INK, width=lw(0.8))
    d.line([(cx - s * 0.2, cy + s * 0.3), (cx + s * 0.55, cy + s * 0.3)], fill=INK_DIM, width=lw(0.8))


def portrait(canvas_fn, w=384, h=512, out=512):
    """Dessin en 3:4 puis étiré en carré : la PAA est affichée dans un cadre 3:4."""
    img = Image.new("RGBA", (w * SS, h * SS), (0, 0, 0, 0))
    canvas_fn(ImageDraw.Draw(img), w * SS, h * SS, img)
    return img.resize((out, out), Image.LANCZOS)


def face_guide(d, W, H, img):
    c = (150, 255, 200, 230)
    t = lw(0.9)
    m, L = int(W * 0.06), int(W * 0.16)
    for (x, y, sx, sy) in ((m, m, 1, 1), (W - m, m, -1, 1), (m, H - m, 1, -1), (W - m, H - m, -1, -1)):
        d.line([(x, y), (x + sx * L, y)], fill=c, width=t)
        d.line([(x, y), (x, y + sy * L)], fill=c, width=t)
    # Ovale de visage en pointillés (tête au tiers supérieur, comme une photo d'identité).
    ox0, oy0, ox1, oy1 = W * 0.27, H * 0.16, W * 0.73, H * 0.66
    a, b = (ox1 - ox0) / 2, (oy1 - oy0) / 2
    ocx, ocy = (ox0 + ox1) / 2, (oy0 + oy1) / 2
    n = 56
    for i in range(n):
        if i % 2:
            continue
        t0, t1 = 2 * math.pi * i / n, 2 * math.pi * (i + 1) / n
        d.line([(ocx + a * math.cos(t0), ocy + b * math.sin(t0)), (ocx + a * math.cos(t1), ocy + b * math.sin(t1))], fill=(150, 255, 200, 170), width=lw(0.6))
    # Ligne des yeux et axe vertical.
    ey = oy0 + (oy1 - oy0) * 0.45
    d.line([(ox0 - W * 0.05, ey), (ox0 + W * 0.04, ey)], fill=c, width=lw(0.6))
    d.line([(ox1 - W * 0.04, ey), (ox1 + W * 0.05, ey)], fill=c, width=lw(0.6))
    d.line([(ocx, oy0 - H * 0.05), (ocx, oy0 + H * 0.02)], fill=c, width=lw(0.6))
    d.line([(ocx, oy1 - H * 0.02), (ocx, oy1 + H * 0.05)], fill=c, width=lw(0.6))
    # Épaules.
    d.arc((W * 0.05, H * 0.72, W * 0.95, H * 1.35), 205, 335, fill=(150, 255, 200, 110), width=lw(0.6))


def face_empty(d, W, H, img):
    d.rectangle((0, 0, W, H), fill=(10, 24, 23, 255))
    sil = (34, 70, 63, 255)
    d.ellipse((W * 0.31, H * 0.17, W * 0.69, H * 0.62), fill=sil)
    d.rounded_rectangle((W * 0.42, H * 0.56, W * 0.58, H * 0.72), radius=int(W * 0.04), fill=sil)
    d.ellipse((W * 0.06, H * 0.68, W * 0.94, H * 1.3), fill=sil)
    face_guide(d, W, H, img)


def save_paa(img, name):
    os.makedirs(OUT, exist_ok=True)
    dst = os.path.join(OUT, name + ".paa")
    with tempfile.TemporaryDirectory() as tmp:
        png = os.path.join(tmp, name + ".png")
        img.save(png)
        if os.path.exists(dst):
            os.remove(dst)
        subprocess.run([HEMTT, "utils", "paa", "convert", png, dst], check=True)
    print("ok", name)


TILES = (
    ("subject", ic_subject, "SUJET", "Identité\nArmement"),
    ("context", ic_context, "CONTEXTE", "Statut\nCirconstances"),
    ("bio", ic_bio, "BIOMÉTRIE", "Empreintes\nIris, ADN"),
    ("terrain", ic_terrain, "TERRAIN", "Site, preuves\nGraphe"),
    ("photo", ic_photo, "PHOTO", "Cliché du\nvisage"),
    ("dossier", ic_dossier, "DOSSIER", "Signature\nTransmission"),
)


def main():
    save_paa(screen_bg(), "seek_screen_bg_co")
    for name, fn, label, sub in TILES:
        save_paa(tile(fn, label, sub), "seek_tile_" + name + "_ca")
    save_paa(portrait(face_guide), "seek_face_guide_ca")
    save_paa(portrait(face_empty), "seek_face_empty_ca")


if __name__ == "__main__":
    main()
