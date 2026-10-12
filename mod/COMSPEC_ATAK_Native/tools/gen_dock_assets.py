"""
Socle de recharge CS-10 (10 téléphones) : images 2D.

- Marquages sérigraphiés du socle (bandeau avant, bandeau du gradin, plaque arrière, plaque de marque), en PNG dans
  tools/render/ : ce sont des décalques lus par model_charging_dock.py, cuits dans la texture du socle.
- Écran de charge des téléphones posés (portrait, fond d'écran sombre du téléphone, batterie et pourcentage) :
  data/dock_screen_0..5_co.paa (0 : moins de 20 %, 5 : chargé).
- Voyant éteint par défaut (data/dock_led_co.paa) ; les couleurs allumées sont des textures procédurales (fn_dockVisual).

    python3 gen_dock_assets.py /chemin/vers/hemtt
"""
import os
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
RENDER = os.path.join(HERE, "render")
HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"
FONT_B = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_R = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
FONT_C = "/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf"
INK = (226, 228, 222)       # encre de sérigraphie, blanc cassé
PANEL = (34, 36, 38)        # anodisation gris très foncé du socle

# Bandeaux : même rapport que les faces du modèle (model_charging_dock.FRONT_STRIP / RISER_STRIP), 4 px par mm.
PX_MM = 4


def font(path, size):
    return ImageFont.truetype(path if os.path.exists(path) else FONT_B, size)


def paa(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    out = os.path.join(DATA, name + ".paa")
    if os.path.exists(out):
        os.remove(out)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, out], check=True, stdout=subprocess.DEVNULL)


def strip(width_mm, height_mm, first, slots_x_mm, title, sub):
    """Bandeau sérigraphié : numéro d'emplacement sous chaque voyant, cadre du voyant, texte de marque à gauche."""
    w, h = width_mm * PX_MM, height_mm * PX_MM
    im = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    f_num = font(FONT_B, int(h * 0.30))
    for i, x_mm in enumerate(slots_x_mm):
        x = x_mm * PX_MM
        # Cadre autour du voyant (le voyant lui-même est une pièce du modèle) et numéro dessous.
        d.rounded_rectangle((x - 9 * PX_MM, h * 0.12, x + 9 * PX_MM, h * 0.50), radius=PX_MM * 2, outline=INK + (210,), width=3)
        d.text((x, h * 0.80), f"{first + i:02d}", font=f_num, fill=INK + (240,), anchor="mm")
    if title:
        d.text((3 * PX_MM, h * 0.36), title, font=font(FONT_C, int(h * 0.26)), fill=INK + (235,), anchor="lm")
        d.text((3 * PX_MM, h * 0.76), sub, font=font(FONT_R, int(h * 0.17)), fill=INK + (200,), anchor="lm")
    return im


def rear_plate():
    """Plaque signalétique arrière (alu gravé) : 90 x 34 mm."""
    w, h = 90 * PX_MM, 34 * PX_MM
    im = Image.new("RGBA", (w, h), (168, 170, 166, 255))
    d = ImageDraw.Draw(im)
    d.rectangle((4, 4, w - 5, h - 5), outline=(60, 62, 60, 255), width=3)
    lines = [("COMSPEC CS-10", FONT_B, 34), ("Station de charge 10 terminaux", FONT_R, 20),
             ("Entrée 100-240 V~ 50/60 Hz 2,5 A", FONT_R, 18), ("Sortie 10 x USB-C PD 5 V 3 A", FONT_R, 18),
             ("S/N CS10-24-0417   IP54   CE", FONT_R, 18)]
    y = 12
    for txt, fp, sz in lines:
        d.text((16, y), txt, font=font(fp, sz), fill=(30, 31, 30, 255))
        y += sz + 7
    return im


def brand_plate():
    """Plaque de marque du dessus du gradin : 120 x 16 mm, logo COMSPEC et nom."""
    w, h = 120 * PX_MM, 16 * PX_MM
    im = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.text((w / 2, h / 2), "COMSPEC  ·  CS-10  ·  TACTICAL CHARGING STATION", font=font(FONT_C, int(h * 0.42)), fill=INK + (235,), anchor="mm")
    return im


def screen(level):
    """Écran de charge, portrait 256 x 512 : fond d'écran sombre, heure, batterie remplie au niveau, éclair."""
    w, h = 256, 512
    wall = Image.open(os.path.join(HERE, "src", "wallpaper_dark.jpg")).convert("RGB")
    # Recadrage portrait du fond paysage, assombri comme un écran verrouillé.
    ww, wh = wall.size
    cw = int(wh * w / h)
    wall = wall.crop(((ww - cw) // 2, 0, (ww - cw) // 2 + cw, wh)).resize((w, h), Image.LANCZOS)
    wall = Image.blend(wall, Image.new("RGB", (w, h), (0, 0, 0)), 0.55)
    im = wall.convert("RGBA")
    d = ImageDraw.Draw(im)
    d.text((w / 2, 70), "14:32", font=font(FONT_R, 54), fill=(240, 242, 240, 255), anchor="mm")
    d.text((w / 2, 112), "COMSPEC ATAK", font=font(FONT_B, 16), fill=(200, 205, 200, 230), anchor="mm")
    pct = [12, 32, 52, 72, 92, 100][level]
    col = [(226, 60, 48), (236, 150, 40), (236, 190, 40), (120, 200, 70), (70, 205, 90), (70, 205, 90)][level]
    # Batterie verticale.
    bx0, by0, bx1, by1 = 88, 190, 168, 360
    d.rounded_rectangle((bx0 + 24, by0 - 14, bx1 - 24, by0 + 2), radius=4, fill=(220, 222, 220, 255))
    d.rounded_rectangle((bx0, by0, bx1, by1), radius=12, outline=(220, 222, 220, 255), width=6)
    fy = by1 - 8 - (by1 - by0 - 16) * pct / 100
    glow = Image.new("RGBA", im.size, (0, 0, 0, 0))
    ImageDraw.Draw(glow).rounded_rectangle((bx0 + 8, fy, bx1 - 8, by1 - 8), radius=6, fill=col + (255,))
    im.alpha_composite(glow.filter(ImageFilter.GaussianBlur(6)))
    im.alpha_composite(glow)
    d = ImageDraw.Draw(im)
    if level < 5:
        cx, cy = (bx0 + bx1) / 2, (by0 + by1) / 2
        d.polygon([(cx + 6, cy - 46), (cx - 22, cy + 6), (cx - 2, cy + 6), (cx - 8, cy + 46), (cx + 22, cy - 8), (cx + 2, cy - 8)], fill=(255, 255, 255, 240))
    d.text((w / 2, 400), "Chargé" if level == 5 else "En charge", font=font(FONT_B, 24), fill=(236, 238, 236, 255), anchor="mm")
    d.text((w / 2, 432), "Socle CS-10 · USB-C PD", font=font(FONT_R, 14), fill=(180, 186, 180, 220), anchor="mm")
    return im.convert("RGB")


def main():
    os.makedirs(RENDER, exist_ok=True)
    # Bandeau avant (rangée basse, emplacements 01 à 05) et bord du gradin (rangée haute, 06 à 10) : 570 x 34 mm et 570 x 22 mm.
    # Abscisses des emplacements en mm depuis le bord gauche (vu de face), pas de 106 mm centrés.
    xs = [285 + (i - 2) * 106 for i in range(5)]
    strip(570, 34, 1, xs, "", "").save(os.path.join(RENDER, "dock_strip_front.png"))
    strip(570, 22, 6, xs, "", "").save(os.path.join(RENDER, "dock_strip_riser.png"))
    rear_plate().save(os.path.join(RENDER, "dock_plate_rear.png"))
    brand_plate().save(os.path.join(RENDER, "dock_plate_brand.png"))
    with tempfile.TemporaryDirectory() as tmp:
        for lv in range(6):
            img = screen(lv)
            img.save(os.path.join(RENDER, f"dock_screen_{lv}.png"))
            paa(img, f"dock_screen_{lv}_co", tmp)
        paa(Image.new("RGB", (16, 16), (22, 23, 22)), "dock_led_co", tmp)
    print("ok")


if __name__ == "__main__":
    main()
