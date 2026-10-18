"""
Textures propres aux icônes d'inventaire SSE (en plus de celles des previews, gen_textures.py) : écran du SEEK II,
écran du terminal SSE, étiquette du kit ADN, fiche décadactylaire, étiquette de carte SD. Tout est fictif.

    python3 icon_textures.py   ->  tex/*.png
"""
import math
import os
import random

from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "tex")
D = "/usr/share/fonts/truetype/dejavu/"


def font(size, bold=False, mono=False):
    name = ("DejaVuSansMono" if mono else "DejaVuSans") + ("-Bold" if bold else "") + ".ttf"
    return ImageFont.truetype(D + name, size)


def save(img, name):
    img.save(os.path.join(OUT, name))
    print("texture", name)


def fingerprint(size, seed, ink=(20, 20, 24), bg=None):
    """Empreinte stylisée : boucles concentriques déformées, bords flous."""
    rnd = random.Random(seed)
    w, h = size
    im = Image.new("RGBA", size, (0, 0, 0, 0) if bg is None else bg)
    d = ImageDraw.Draw(im)
    cx, cy = w * (0.5 + rnd.uniform(-0.05, 0.05)), h * (0.55 + rnd.uniform(-0.05, 0.05))
    ph = rnd.uniform(0, 6.3)
    for i in range(1, 22):
        r = i * min(w, h) / 44
        pts = []
        for k in range(73):
            a = k / 72 * 2 * math.pi
            wob = 1 + 0.05 * math.sin(a * 3 + ph + i * 0.3)
            pts.append((cx + math.cos(a) * r * 0.78 * wob, cy + math.sin(a) * r * 1.08 * wob - (r * 0.25 if a > math.pi else 0)))
        d.line(pts, fill=ink + (230,), width=max(1, int(min(w, h) / 70)))
    mask = Image.new("L", size, 0)
    ImageDraw.Draw(mask).ellipse((w * 0.12, h * 0.06, w * 0.88, h * 0.96), fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(min(w, h) / 18))
    a = Image.composite(im, Image.new("RGBA", size, (0, 0, 0, 0)), mask)
    return a.filter(ImageFilter.GaussianBlur(0.6))


def face(size, seed=3):
    """Portrait de face neutre (silhouette ombrée), pour les écrans biométriques."""
    w, h = size
    im = Image.new("RGB", size, (58, 66, 76))
    d = ImageDraw.Draw(im)
    d.ellipse((w * 0.08, h * 0.72, w * 0.92, h * 1.4), fill=(54, 58, 48))
    d.rectangle((w * 0.4, h * 0.6, w * 0.6, h * 0.8), fill=(150, 112, 88))
    d.ellipse((w * 0.26, h * 0.12, w * 0.74, h * 0.7), fill=(170, 128, 100))
    d.pieslice((w * 0.24, h * 0.06, w * 0.76, h * 0.5), 180, 360, fill=(30, 24, 20))
    d.ellipse((w * 0.27, h * 0.48, w * 0.73, h * 0.78), fill=(48, 38, 30))
    d.ellipse((w * 0.36, h * 0.36, w * 0.44, h * 0.41), fill=(30, 26, 22))
    d.ellipse((w * 0.56, h * 0.36, w * 0.64, h * 0.41), fill=(30, 26, 22))
    return im.filter(ImageFilter.GaussianBlur(1.2))


def seek_screen():
    W, H = 800, 600
    im = Image.new("RGB", (W, H), (14, 22, 30))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 0, W, 54), fill=(28, 44, 60))
    d.text((18, 12), "SEEK II  ·  ENROL", font=font(28, True), fill=(220, 232, 240))
    d.text((W - 150, 14), "14:32", font=font(26, mono=True), fill=(160, 190, 210))
    im.paste(face((230, 280)), (24, 78))
    d.rectangle((24, 78, 254, 358), outline=(70, 160, 220), width=3)
    fp = fingerprint((200, 250), 4, ink=(220, 230, 235), bg=(18, 26, 34, 255))
    im.paste(fp, (290, 92), fp)
    d.rectangle((282, 78, 500, 358), outline=(70, 160, 220), width=3)
    for i, r in enumerate((60, 46, 30)):
        d.ellipse((650 - r, 200 - r, 650 + r, 200 + r), outline=(70 + i * 40, 150, 200), width=4)
    d.ellipse((634, 184, 666, 216), fill=(20, 20, 24))
    d.rectangle((530, 78, 776, 358), outline=(70, 160, 220), width=3)
    d.rounded_rectangle((24, 386, 776, 470), 12, fill=(26, 110, 60))
    d.text((48, 404), "MATCH  98.4 %  ·  WATCHLIST", font=font(34, True), fill=(235, 250, 238))
    for i, t in enumerate(("FACE ✓", "FINGERS 10/10", "IRIS L/R")):
        d.rounded_rectangle((24 + i * 254, 494, 254 + i * 254, 576), 10, fill=(32, 48, 64))
        d.text((44 + i * 254, 520), t, font=font(26, True), fill=(200, 220, 235))
    save(im, "seek_screen.png")


def terminal_screen():
    W, H = 1200, 800
    im = Image.new("RGB", (W, H), (12, 18, 24))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 0, W, 64), fill=(24, 36, 30))
    d.text((24, 14), "SSE  ·  EXPLOITATION", font=font(34, True), fill=(214, 228, 200))
    d.text((W - 260, 18), "ALTIS  14:32Z", font=font(28, mono=True), fill=(170, 196, 150))
    # liste des preuves
    d.rectangle((0, 64, 330, H), fill=(18, 26, 30))
    rnd = random.Random(9)
    for i in range(11):
        y = 84 + i * 64
        d.rounded_rectangle((16, y, 314, y + 52), 8, fill=(30, 42, 46) if i != 2 else (44, 78, 60))
        d.rectangle((28, y + 12, 56, y + 40), fill=[(200, 160, 60), (90, 160, 220), (200, 90, 80), (120, 190, 120)][i % 4])
        d.line((70, y + 18, 70 + rnd.randint(120, 220), y + 18), fill=(190, 205, 200), width=6)
        d.line((70, y + 36, 70 + rnd.randint(60, 160), y + 36), fill=(110, 130, 128), width=4)
    # graphe de liens
    nodes = [(560, 230), (760, 160), (900, 320), (700, 420), (500, 470), (860, 560), (640, 640), (1040, 450)]
    for a, b in ((0, 1), (1, 2), (2, 3), (3, 0), (3, 4), (2, 5), (5, 6), (3, 6), (2, 7), (5, 7)):
        d.line((nodes[a], nodes[b]), fill=(90, 130, 110), width=4)
    for i, (x, y) in enumerate(nodes):
        r = 30 if i in (2, 3) else 22
        c = (220, 80, 70) if i == 2 else (220, 180, 70) if i == 3 else (100, 170, 220)
        d.ellipse((x - r, y - r, x + r, y + r), fill=c, outline=(240, 240, 230), width=3)
    d.rectangle((1100, 64, W, H), fill=(18, 26, 30))
    for i in range(6):
        d.rounded_rectangle((1112, 84 + i * 110, 1188, 170 + i * 110), 10, fill=(34, 48, 52))
    save(im, "terminal_screen.png")


def dna_label():
    W, H = 800, 500
    im = Image.new("RGB", (W, H), (240, 242, 244))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 0, W, 150), fill=(20, 72, 150))
    d.text((32, 22), "DNA", font=font(96, True), fill=(255, 255, 255))
    d.text((300, 36), "COLLECTION KIT", font=font(44, True), fill=(220, 232, 250))
    d.text((300, 92), "KIT DE PRÉLÈVEMENT ADN", font=font(30), fill=(200, 216, 240))
    # double hélice
    for k in range(40):
        t = k / 39
        x = 40 + t * 300
        y1, y2 = 300 + 60 * math.sin(t * 9), 300 - 60 * math.sin(t * 9)
        d.line((x, y1, x, y2), fill=(150, 170, 200), width=3)
        d.ellipse((x - 7, y1 - 7, x + 7, y1 + 7), fill=(20, 72, 150))
        d.ellipse((x - 7, y2 - 7, x + 7, y2 + 7), fill=(200, 60, 60))
    for i, t in enumerate(("2 x BUCCAL SWAB", "STERILE  ·  EO", "LOT 26-1018")):
        d.text((400, 200 + i * 54), t, font=font(32, True), fill=(40, 46, 56))
    for i in range(48):
        w = random.Random(i).choice((3, 3, 6, 9))
        d.rectangle((400 + i * 8, 380, 400 + i * 8 + w // 2, 470), fill=(20, 20, 20))
    save(im, "dna_label.png")


def fp_card():
    W, H = 900, 600
    im = Image.new("RGB", (W, H), (244, 242, 236))
    d = ImageDraw.Draw(im)
    d.text((30, 18), "FD-258  ·  FICHE DÉCADACTYLAIRE", font=font(30, True), fill=(40, 40, 60))
    for r in range(2):
        for c in range(5):
            x, y = 30 + c * 170, 80 + r * 250
            d.rectangle((x, y, x + 160, y + 230), outline=(60, 60, 90), width=3)
            fp = fingerprint((130, 180), r * 5 + c, ink=(16, 16, 20))
            im.paste(fp, (x + 15, y + 30), fp)
    save(im, "fp_card.png")


def sd_label():
    W, H = 240, 320
    im = Image.new("RGB", (W, H), (22, 22, 26))
    d = ImageDraw.Draw(im)
    d.rectangle((0, 120, W, 200), fill=(196, 34, 40))
    d.text((24, 30), "SD", font=font(70, True), fill=(235, 235, 235))
    d.text((24, 132), "64GB", font=font(48, True), fill=(255, 255, 255))
    d.text((24, 230), "HC  I  U3", font=font(32, True), fill=(200, 200, 200))
    save(im, "sd_label.png")


if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    random.seed(11)
    seek_screen()
    terminal_screen()
    dna_label()
    fp_card()
    sd_label()
