#!/usr/bin/env python3
"""Fonds d'écran tirés des images du site et textures de l'écran de verrouillage (addon items, pas main : main.pbo est
près des 100 Mo de GitHub, items.pbo en est loin). Sources : uniquement des images du projet, logo Athena et bannières
Workshop de public/assets (aucune photo tierce). Sortie : Sources/addons/items/data/walls et data/lock, en PAA.

Usage : python tools/gen_lock_assets.py [--preview dossier]   (hemtt dans le PATH ; --preview garde aussi les PNG)
Les noms de fonds sont ceux de fn_wallPath : wall_<clé>_<land|port>.paa et wall_<clé>_blur_<land|port>.paa.
"""
import os
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(os.path.dirname(os.path.dirname(HERE)))
ASSETS = os.path.join(REPO, "public", "assets")
ITEMS = os.path.join(os.path.dirname(HERE), "Sources", "addons", "items", "data")
SIZES = (("land", (2048, 1024)), ("port", (1024, 2048)))
# Texte descriptif des bannières (français, illisible en fond d'écran) effacé : (x0, y0, x1, y1) sur l'image 1260 x 560.
BANNERS = {
    "atak": ("workshop/atak-natif/fr/banniere.png", (620, 345, 1250, 540)),
    "sse": ("workshop/sse/fr/banniere.png", (620, 345, 1250, 540)),
    "sof": ("workshop/base-sof/fr/banniere.png", (640, 345, 1250, 540)),
}


def gradient(w, h, top, bottom):
    img = Image.new("RGB", (1, 256))
    for y in range(256):
        k = y / 255
        img.putpixel((0, y), tuple(int(top[i] + (bottom[i] - top[i]) * k) for i in range(3)))
    return img.resize((w, h), Image.BICUBIC)


def glow(img, cx, cy, radius, color, strength):
    """Halo doux de couleur, centré en fractions de l'image."""
    w, h = img.size
    mask = Image.new("L", (w // 4, h // 4), 0)
    d = ImageDraw.Draw(mask)
    x, y, r = cx * w / 4, cy * h / 4, radius * min(w, h) / 4
    d.ellipse((x - r, y - r, x + r, y + r), fill=int(255 * strength))
    mask = mask.filter(ImageFilter.GaussianBlur(r * 0.55)).resize((w, h), Image.BICUBIC)
    return Image.composite(Image.new("RGB", (w, h), color), img, mask)


def brand(name):
    return Image.open(os.path.join(ASSETS, "brand", name)).convert("RGBA")


def paste_logo(img, art, cx, cy, height, opacity, tint=(255, 255, 255)):
    k = height / art.height
    a = art.resize((int(art.width * k), int(art.height * k)), Image.LANCZOS)
    alpha = a.getchannel("A").point(lambda v: int(v * opacity))
    layer = Image.new("RGBA", a.size, tint + (0,))
    layer.putalpha(alpha)
    out = img.convert("RGBA")
    out.alpha_composite(layer, (max(int(cx * img.width - a.width / 2), 0), max(int(cy * img.height - a.height / 2), 0)))
    return out.convert("RGB")


def wall_eagle(w, h):
    """Aigle Athena en très grand, décalé, sur un dégradé nuit."""
    img = gradient(w, h, (10, 20, 34), (5, 9, 14))
    img = glow(img, 0.72, 0.42, 0.75, (38, 92, 150), 0.55)
    return paste_logo(img, brand("athena-embleme-blanc-512.png"), 0.62 if w > h else 0.5, 0.5, h * (0.92 if w > h else 0.36), 0.82)


def wall_logo(w, h):
    """Logo Athena complet, centré, sur fond vert sombre (couleurs du site)."""
    img = gradient(w, h, (7, 24, 17), (3, 8, 6))
    img = glow(img, 0.5, 0.45, 0.8, (34, 110, 68), 0.5)
    return paste_logo(img, brand("athena-logo-blanc-1024.png"), 0.5, 0.48, h * (0.66 if w > h else 0.3), 0.92)


def banner(key, w, h):
    rel, box = BANNERS[key]
    src = Image.open(os.path.join(ASSETS, rel)).convert("RGB")
    x0, y0, x1, y1 = box
    # Le bas de la colonne de texte est un fond uni : recopié sur le texte.
    strip = src.crop((x0, y1, x1, min(y1 + 14, src.height))).resize((x1 - x0, y1 - y0), Image.BICUBIC)
    src.paste(strip, (x0, y0))
    k = max(w / src.width, h / src.height)
    big = src.resize((int(src.width * k) + 1, int(src.height * k) + 1), Image.LANCZOS)
    if w > h:
        # Calé à gauche : l'appareil et le titre restent entiers.
        out = big.crop((0, (big.height - h) // 2, w, (big.height - h) // 2 + h))
    else:
        back = big.crop(((big.width - w) // 2, 0, (big.width - w) // 2 + w, h)).filter(ImageFilter.GaussianBlur(26))
        out = ImageEnhance.Brightness(back).enhance(0.5)
        fit = src.resize((w, int(src.height * w / src.width)), Image.LANCZOS)
        out.paste(fit, (0, (h - fit.height) // 2))
    return out.filter(ImageFilter.UnsharpMask(2, 60, 2))


def blurred(img):
    """Même règle que gen_assets.blurred : 1024 px suffisent, le flou n'a pas de détail."""
    small = img.resize((img.width // 2, img.height // 2), Image.LANCZOS)
    return Image.eval(small.filter(ImageFilter.GaussianBlur(14)), lambda v: int(v * 0.85))


def walls():
    for suffix, (w, h) in SIZES:
        yield "eagle", suffix, wall_eagle(w, h)
        yield "logo", suffix, wall_logo(w, h)
        for key in BANNERS:
            yield key, suffix, banner(key, w, h)


def boot(w, h):
    """Écran de démarrage (fn_deviceOverlay, bas libre pour la barre) : même fond et même logo Athena que le verrouillage."""
    img = gradient(w, h, (7, 24, 17), (3, 8, 6))
    img = glow(img, 0.5, 0.4, 0.8, (34, 110, 68), 0.5)
    return paste_logo(img, brand("athena-logo-blanc-1024.png"), 0.5, 0.42 if w > h else 0.36, h * (0.6 if w > h else 0.22), 0.95)


def app_tenant(d, n):
    """Communauté : trois personnes."""
    w = int(n * 0.07)
    for cx, cy, r in ((0.5, 0.34, 0.13), (0.2, 0.42, 0.09), (0.8, 0.42, 0.09)):
        d.ellipse((n * (cx - r), n * (cy - r), n * (cx + r), n * (cy + r)), outline="white", width=w)
    d.arc((n * 0.26, n * 0.52, n * 0.74, n * 1.05), 180, 360, fill="white", width=w)
    d.arc((n * 0.04, n * 0.56, n * 0.36, n * 0.95), 180, 280, fill="white", width=w)
    d.arc((n * 0.64, n * 0.56, n * 0.96, n * 0.95), 260, 360, fill="white", width=w)


def item_keyloader(d, n):
    """Chargeur de clés : boîtier, écran, clé."""
    w = int(n * 0.06)
    d.rounded_rectangle((n * 0.26, n * 0.08, n * 0.74, n * 0.92), n * 0.07, outline="white", width=w)
    d.rectangle((n * 0.34, n * 0.17, n * 0.66, n * 0.34), outline="white", width=w // 2)
    d.ellipse((n * 0.36, n * 0.46, n * 0.54, n * 0.64), outline="white", width=w)
    d.line((n * 0.53, n * 0.58, n * 0.66, n * 0.74), fill="white", width=w)
    d.line((n * 0.61, n * 0.68, n * 0.54, n * 0.75), fill="white", width=w)


def item_goniometer(d, n):
    """Goniomètre : cadran, aiguille, antenne."""
    w = int(n * 0.06)
    d.ellipse((n * 0.14, n * 0.3, n * 0.86, n * 0.96), outline="white", width=w)
    d.line((n * 0.5, n * 0.63, n * 0.68, n * 0.42), fill="white", width=w)
    d.ellipse((n * 0.45, n * 0.58, n * 0.55, n * 0.68), fill="white")
    d.line((n * 0.5, n * 0.3, n * 0.5, n * 0.04), fill="white", width=w)
    d.arc((n * 0.34, n * 0.02, n * 0.66, n * 0.26), 200, 340, fill="white", width=w // 2)


def rounded(w, h, radius):
    s = 4
    im = Image.new("RGBA", (w * s, h * s), (255, 255, 255, 0))
    ImageDraw.Draw(im).rounded_rectangle((0, 0, w * s - 1, h * s - 1), radius * s, fill=(255, 255, 255, 255))
    return im.resize((w, h), Image.LANCZOS)


def ring(size, width):
    s = 4
    im = Image.new("RGBA", (size * s, size * s), (255, 255, 255, 0))
    m = s * 2
    ImageDraw.Draw(im).ellipse((m, m, size * s - m, size * s - m), outline=(255, 255, 255, 255), width=width * s)
    return im.resize((size, size), Image.LANCZOS)


def veil():
    """Voile vertical : plus dense en haut et en bas (heure, boutons), léger au milieu pour laisser voir le fond."""
    im = Image.new("RGBA", (64, 256), (0, 0, 0, 0))
    for y in range(256):
        k = abs(y / 255 - 0.46) / 0.54
        a = int(255 * (0.18 + 0.62 * (k ** 1.6)))
        for x in range(64):
            im.putpixel((x, y), (0, 0, 0, a))
    return im


def icon(draw_fn, size=128):
    s = 4
    im = Image.new("RGBA", (size * s, size * s), (255, 255, 255, 0))
    draw_fn(ImageDraw.Draw(im), size * s)
    return im.resize((size, size), Image.LANCZOS)


def lock_closed(d, n):
    w = int(n * 0.085)
    d.rounded_rectangle((n * 0.2, n * 0.44, n * 0.8, n * 0.9), n * 0.08, fill="white")
    d.arc((n * 0.3, n * 0.1, n * 0.7, n * 0.62), 180, 360, fill="white", width=w)
    d.line((n * 0.3 + w / 2, n * 0.36, n * 0.3 + w / 2, n * 0.46), fill="white", width=w)
    d.line((n * 0.7 - w / 2, n * 0.36, n * 0.7 - w / 2, n * 0.46), fill="white", width=w)


def lock_open(d, n):
    w = int(n * 0.085)
    d.rounded_rectangle((n * 0.2, n * 0.44, n * 0.8, n * 0.9), n * 0.08, fill="white")
    d.arc((n * 0.42, n * 0.08, n * 0.82, n * 0.6), 180, 360, fill="white", width=w)
    d.line((n * 0.42 + w / 2, n * 0.34, n * 0.42 + w / 2, n * 0.46), fill="white", width=w)


def backspace(d, n):
    w = int(n * 0.08)
    pts = [(n * 0.34, n * 0.2), (n * 0.92, n * 0.2), (n * 0.92, n * 0.8), (n * 0.34, n * 0.8), (n * 0.06, n * 0.5), (n * 0.34, n * 0.2)]
    d.line(pts, fill="white", width=w, joint="curve")
    d.line((n * 0.48, n * 0.36, n * 0.76, n * 0.64), fill="white", width=w)
    d.line((n * 0.76, n * 0.36, n * 0.48, n * 0.64), fill="white", width=w)


def textures():
    return {
        "card": rounded(1024, 128, 22),
        "pill": rounded(256, 64, 32),
        "dot": rounded(64, 64, 32),
        "ring": ring(64, 6),
        "key": rounded(128, 128, 64),
        "veil": veil(),
        "lock_closed": icon(lock_closed),
        "lock_open": icon(lock_open),
        "backspace": icon(backspace),
    }


def to_paa(img, path, tmp):
    png = os.path.join(tmp, os.path.basename(path) + ".png")
    img.save(png)
    if os.path.exists(path):
        os.remove(path)  # hemtt ne remplace pas un PAA existant
    subprocess.run(["hemtt", "utils", "paa", "convert", png, path], check=True, stdout=subprocess.DEVNULL)


def main():
    preview = sys.argv[sys.argv.index("--preview") + 1] if "--preview" in sys.argv else ""
    os.makedirs(os.path.join(ITEMS, "walls"), exist_ok=True)
    os.makedirs(os.path.join(ITEMS, "lock"), exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        for key, suffix, img in walls():
            to_paa(img, os.path.join(ITEMS, "walls", f"wall_{key}_{suffix}.paa"), tmp)
            to_paa(blurred(img), os.path.join(ITEMS, "walls", f"wall_{key}_blur_{suffix}.paa"), tmp)
            if preview:
                img.save(os.path.join(preview, f"wall_{key}_{suffix}.png"))
        for name, img in textures().items():
            to_paa(img, os.path.join(ITEMS, "lock", name + ".paa"), tmp)
            if preview:
                img.save(os.path.join(preview, name + ".png"))
        main_data = os.path.join(os.path.dirname(HERE), "Sources", "addons", "main", "data")
        for name, size in (("boot_land", (1024, 512)), ("boot_port", (512, 1024))):
            img = boot(*size)
            to_paa(img, os.path.join(main_data, name + ".paa"), tmp)
            if preview:
                img.save(os.path.join(preview, name + ".png"))
        for name, fn in (("app_tenant", app_tenant), ("item_keyloader", item_keyloader), ("item_goniometer", item_goniometer)):
            to_paa(icon(fn), os.path.join(main_data, name + ".paa"), tmp)
            if preview:
                icon(fn).save(os.path.join(preview, name + ".png"))


if __name__ == "__main__":
    main()
