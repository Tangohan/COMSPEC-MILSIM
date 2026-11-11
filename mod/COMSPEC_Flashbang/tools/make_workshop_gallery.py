"""Page Steam Workshop de COMSPEC Flashbang : galerie, aperçu et bannières, composés avec Pillow.

Charte Athena / COMSPEC : fond bleu nuit (#050810 -> #0b1220), accent émeraude (#10b981 / #34d399), capitales
condensées (Bahnschrift de Windows, comme les galeries ATAK et SSE). Aucun petit libellé au-dessus des titres.

Entrées (tools/render/) :
  workshop_lineup_dark.jpg    rang des dix grenades sur fond sombre, 1920 x 1080 (rendu Blender)
  workshop_lineup_alpha.webp  même rang sur fond transparent, avec ombres, 2400 x 760 (rendu Blender)
  fb_afterimage_ca.png        tache rémanente du mod (texture réelle de l'effet)
  fb_module_ca.png            icône du module Eden
  Si les rendus bruts ws_lineup_dark.png / ws_lineup_alpha.png existent (WORKSHOP=1 blender -b -P
  model_flashbang.py), ils sont d'abord compressés vers les deux fichiers ci-dessus, puis effacés.

Sorties :
  workshop/gallery/<fr|en>/fb-NN-<nom>.jpg     galerie Steam, 1920 x 1080, JPEG qualité 85, < 1 Mo
  workshop/preview.jpg                         aperçu de l'objet Workshop, 1280 x 720, < 1 Mo, sans texte traduit
  public/assets/workshop/flashbang/<fr|en>/banniere.png   bannière en tête de description (1260 x 560, comme ATAK/SSE)

Les diapositives 03 à 07 sont des SCHÉMAS dessinés ici, pas des captures du jeu : elles le disent en pied d'image.
Les captures en jeu à faire plus tard sont listées dans workshop/CAPTURES_A_FAIRE.md.

    py -3 tools/make_workshop_gallery.py                 tout (FR, EN, aperçu, bannières)
    py -3 tools/make_workshop_gallery.py --lang fr --only 3,timeline
    py -3 tools/make_workshop_gallery.py --out <dossier>  galerie ailleurs (essais)
"""
import argparse
import io
import math
import os

from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
MOD = os.path.normpath(os.path.join(HERE, ".."))
ROOT = os.path.normpath(os.path.join(MOD, "..", ".."))
RENDER = os.path.join(HERE, "render")
WS = os.path.join(MOD, "workshop")
BANNERS = os.path.join(ROOT, "public", "assets", "workshop", "flashbang")

W, H = 1920, 1080
MAX_BYTES = 1_000_000
BG0, BG1 = (5, 8, 16), (11, 18, 32)
SURFACE = (13, 21, 37)
EDGE = (36, 46, 64)
ACCENT = (16, 185, 129)
ACCENT2 = (52, 211, 153)
TEXT = (238, 242, 246)
SUB = (200, 208, 220)
MUTED = (130, 142, 160)
FLASH = (255, 246, 214)
WARN = (245, 158, 11)
FONT = r"C:\Windows\Fonts\bahnschrift.ttf"
X0 = 110


def font(size, style="Bold Condensed"):
    if os.path.isfile(FONT):
        f = ImageFont.truetype(FONT, size)
        try:
            f.set_variation_by_name(style)
        except (OSError, ValueError, AttributeError):
            pass
        return f
    return ImageFont.load_default(size)


def background(w=W, h=H):
    im = Image.new("RGB", (w, h), BG0)
    d = ImageDraw.Draw(im)
    for y in range(h):
        t = y / max(1, h - 1)
        d.line([(0, y), (w, y)], fill=tuple(int(BG1[i] * (1 - t) + BG0[i] * t) for i in range(3)))
    return im


def wrap(d, text, f, width):
    lines, cur = [], ""
    words = []
    for w in text.split():                      # ponctuation haute française : jamais en début de ligne
        if w in (":", ";", "!", "?") and words:
            words[-1] += " " + w
        else:
            words.append(w)
    for word in words:
        test = (cur + " " + word).strip()
        if d.textlength(test, font=f) > width and cur:
            lines.append(cur)
            cur = word
        else:
            cur = test
    if cur:
        lines.append(cur)
    return lines


def text_block(d, x, y, text, f, fill, width, lh):
    for ln in wrap(d, text, f, width):
        d.text((x, y), ln, font=f, fill=fill)
        y += lh
    return y


def chip(d, x, y, label, f, outline=EDGE, fill=TEXT, h=42):
    w = d.textlength(label, font=f) + 32
    d.rounded_rectangle([x, y, x + w, y + h], 8, fill=SURFACE, outline=outline, width=2)
    d.text((x + w / 2, y + h / 2 + 1), label, font=f, fill=fill, anchor="mm")
    return x + w + 12


def header(d, title, sub, width=1500):
    """Titre, trait d'accent, sous-titre ; renvoie l'ordonnée libre en dessous."""
    d.text((X0 - 4, 70), title, font=font(92), fill=TEXT)
    y = 70 + 104
    d.rounded_rectangle([X0, y, X0 + 110, y + 8], 4, fill=ACCENT)
    y += 30
    return text_block(d, X0, y, sub, font(36, "SemiLight"), SUB, width, 46)


def footer(d, lang, schema):
    f = font(24, "SemiBold Condensed")
    d.text((X0, H - 52), "COMSPEC FLASHBANG", font=f, fill=MUTED, anchor="lm")
    if schema:
        msg = "Schéma explicatif, pas une capture du jeu" if lang == "fr" else "Explanatory diagram, not a game screenshot"
        d.text((W - X0, H - 52), msg, font=f, fill=MUTED, anchor="rm")


def card(im, box, r=18, fill=SURFACE, outline=EDGE):
    d = ImageDraw.Draw(im)
    d.rounded_rectangle(box, r, fill=fill, outline=outline, width=2)


def glow(im, cx, cy, r, color=FLASH, strength=1.0):
    """Halo lumineux additif centré en (cx, cy)."""
    layer = Image.new("L", im.size, 0)
    ImageDraw.Draw(layer).ellipse([cx - r * 0.35, cy - r * 0.35, cx + r * 0.35, cy + r * 0.35], fill=int(255 * strength))
    layer = layer.filter(ImageFilter.GaussianBlur(r * 0.35))
    col = Image.new("RGB", im.size, color)
    im.paste(ImageChops.multiply(col, Image.merge("RGB", [layer] * 3)), (0, 0), layer)


def save_jpg(im, path, q=85):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    for qq in (q, 80, 75, 70):
        buf = io.BytesIO()
        im.convert("RGB").save(buf, "JPEG", quality=qq, optimize=True, progressive=True)
        if buf.tell() <= MAX_BYTES or qq == 70:
            with open(path, "wb") as fh:
                fh.write(buf.getvalue())
            return buf.tell()


# ---------------------------------------------------------------------------------------------------------- sources

def prepare_sources():
    raw_dark = os.path.join(RENDER, "ws_lineup_dark.png")
    raw_alpha = os.path.join(RENDER, "ws_lineup_alpha.png")
    if os.path.isfile(raw_dark):
        Image.open(raw_dark).convert("RGB").save(os.path.join(RENDER, "workshop_lineup_dark.jpg"), quality=90, optimize=True)
        os.remove(raw_dark)
        print("source  render/workshop_lineup_dark.jpg")
    if os.path.isfile(raw_alpha):
        im = Image.open(raw_alpha).convert("RGBA")
        im = im.crop(im.getchannel("A").point(lambda a: 255 if a > 8 else 0).getbbox())
        im.save(os.path.join(RENDER, "workshop_lineup_alpha.webp"), quality=90, method=6)
        os.remove(raw_alpha)
        print("source  render/workshop_lineup_alpha.webp")


def lineup_alpha():
    return Image.open(os.path.join(RENDER, "workshop_lineup_alpha.webp")).convert("RGBA")


def lineup_dark():
    return Image.open(os.path.join(RENDER, "workshop_lineup_dark.jpg")).convert("RGB")


def fit(img, w, h):
    s = min(w / img.width, h / img.height)
    return img.resize((round(img.width * s), round(img.height * s)), Image.LANCZOS)


def cover(img, w, h, fx=0.5, fy=0.5):
    s = max(w / img.width, h / img.height)
    img = img.resize((round(img.width * s), round(img.height * s)), Image.LANCZOS)
    l, t = int((img.width - w) * fx), int((img.height - h) * fy)
    return img.crop((l, t, l + w, t + h))


# ---------------------------------------------------------------------------------------------------------- textes

T = {
    "titre": {
        "fr": ("COMSPEC FLASHBANG", "Grenades assourdissantes pour Arma 3 : 1 à 9 flashs, effets réalistes.",
               ["1 À 9 FLASHS", "GRENADE DRILL", "CBA_A3 SEUL REQUIS"]),
        "en": ("COMSPEC FLASHBANG", "Stun grenades for Arma 3: 1 to 9 flashes, realistic effects.",
               ["1 TO 9 FLASHES", "DRILL GRENADE", "ONLY CBA_A3 REQUIRED"])},
    "grenades": {
        "fr": ("1 À 9 FLASHS ET DRILL", "Le chiffre sur le corps est le nombre de flashs. Drill bleue : entraînement, "
               "aucun effet. Chaque modèle existe aussi en mèche 3 s et 8 s.",
               ["CAISSE FLASH", "CAISSE DÉLAIS", "MÈCHE 3 S / 8 S", "ARSENAL"]),
        "en": ("1 TO 9 FLASHES AND DRILL", "The number on the body is the number of flashes. The blue Drill is for "
               "training: a pop, no effect. Every model also comes with a 3 s and 8 s fuse.",
               ["FLASH CRATE", "DELAY CRATE", "3 S / 8 S FUSE", "ARSENAL"])},
    "timeline": {
        "fr": ("UN BANG PAR FLASH", "Une grenade à 5 flashs fait exactement 5 flashs et 5 bangs, environ toutes les demi-secondes, "
               "pareil chez tous les joueurs. Un seul sifflement, relancé à chaque flash."),
        "en": ("ONE BANG PER FLASH", "A 5-flash grenade makes exactly 5 flashes and 5 bangs, about every half second, the "
               "same for every player. A single ringing, renewed by each flash.")},
    "effets": {
        "fr": ("CE QUE VOIT LE JOUEUR", "Exposé de face à 3 m : la vue se vide, puis revient. Pendant ce temps, "
               "sons étouffés et sifflement qui s'éteint."),
        "en": ("WHAT THE PLAYER SEES", "Facing the flash at 3 m: sight goes white, then comes back. Meanwhile, "
               "muffled sound and a fading ringing.")},
    "lieux": {
        "fr": ("DEDANS OU DEHORS", "Le mod regarde où la grenade éclate : murs, plafond, taille de la pièce."),
        "en": ("INDOORS OR OUTDOORS", "The mod checks where the grenade goes off: walls, ceiling, room size.")},
    "reglages": {
        "fr": ("VOTRE DOSAGE", "32 réglages CBA, chacun avec un exemple. Trois préréglages pour commencer."),
        "en": ("YOUR OWN DOSE", "32 CBA settings, each with an example. Three presets to start with.")},
    "compat": {
        "fr": ("COMPATIBLE AVEC VOS MODS", "Seul CBA_A3 est obligatoire. Le reste est reconnu s'il est chargé."),
        "en": ("WORKS WITH YOUR MODS", "Only CBA_A3 is required. The rest is detected when loaded.")},
}


# ---------------------------------------------------------------------------------------------------------- diapositives

def slide_titre(lang):
    title, sub, chips = T["titre"][lang]
    im = background()
    ren = lineup_dark()
    off = 250                                   # rendu descendu : le texte occupe le haut, les grenades le bas
    fade = Image.new("L", ren.size, 255)
    fd = ImageDraw.Draw(fade)
    for y in range(260):
        fd.line([(0, y), (ren.width, y)], fill=int(255 * (y / 260) ** 1.6))
    im.paste(ren, (0, off), fade)
    d = ImageDraw.Draw(im)
    d.text((X0 - 6, 70), title, font=font(150), fill=TEXT)
    d.rounded_rectangle([X0, 240, X0 + 130, 250], 5, fill=ACCENT)
    d.text((X0, 278), sub, font=font(44, "SemiLight"), fill=SUB)
    cx = X0
    for c in chips:
        cx = chip(d, cx, 360, c, font(26, "SemiBold Condensed"))
    return im


def grenade_centres(li):
    """Abscisses des dix grenades dans le rendu transparent (colonnes opaques = objets, ombres semi-transparentes)."""
    a = li.getchannel("A")
    solid = a.point(lambda v: 255 if v > 250 else 0)
    cols = [solid.crop((x, 0, x + 1, li.height)).getbbox() is not None for x in range(li.width)]
    runs, start = [], None
    for x, on in enumerate(cols + [False]):
        if on and start is None:
            start = x
        elif not on and start is not None:
            if x - start > li.width * 0.02:
                runs.append((start + x) / 2)
            start = None
    return runs if len(runs) == 10 else [li.width * (k + 0.5) / 10 for k in range(10)]


def soften_shadows(li, k=0.55, edge=140):
    """Ombres plus légères et fondues sur les bords, pour poser le rendu sur le fond de la charte."""
    a = li.getchannel("A")
    a = a.point(lambda v: v if v > 250 else int(v * k))
    m = Image.new("L", li.size, 255)
    md = ImageDraw.Draw(m)
    for x in range(edge):
        v = int(255 * x / edge)
        md.line([(x, 0), (x, li.height)], fill=v)
        md.line([(li.width - 1 - x, 0), (li.width - 1 - x, li.height)], fill=v)
    solid = a.point(lambda v: 255 if v > 250 else 0)
    a = Image.composite(a, ImageChops.multiply(a, m), solid)
    out = li.copy()
    out.putalpha(a)
    return out


def slide_grenades(lang):
    title, sub, chips = T["grenades"][lang]
    im = background()
    d = ImageDraw.Draw(im)
    y = header(d, title, sub, 1300)
    cf = font(24, "SemiBold Condensed")
    total = sum(d.textlength(c, font=cf) + 44 for c in chips) - 12
    cx = W - X0 - total
    for c in chips:
        cx = chip(d, cx, 78, c, cf)
    top = y + 40
    area_h = H - top - 190
    src = lineup_alpha()
    centres = grenade_centres(src)
    li = soften_shadows(fit(src, W - 2 * X0, area_h))
    s = li.width / src.width
    lx = (W - li.width) // 2
    im.paste(li, (lx, top + (area_h - li.height) // 2), li)
    d = ImageDraw.Draw(im)
    ly = top + area_h + 34
    lf, nf = font(38), font(24, "SemiLight")
    for i in range(10):
        cxm = lx + centres[i] * s
        name = "Drill" if i == 9 else "×%d" % (i + 1)
        n = (("aucun effet" if lang == "fr" else "no effect") if i == 9 else
             ("%d flash%s" % (i + 1, "s" if i and lang == "fr" else ("es" if i else ""))))
        d.text((cxm, ly), name, font=lf, fill=(96, 165, 250) if i == 9 else TEXT, anchor="mm")
        d.text((cxm, ly + 40), n, font=nf, fill=MUTED, anchor="mm")
    footer(d, lang, False)
    return im


def slide_timeline(lang):
    title, sub = T["timeline"][lang]
    im = background()
    d = ImageDraw.Draw(im)
    y = header(d, title, sub, 1500)
    fr = lang == "fr"
    top = y + 40
    card(im, [X0, top, W - X0, H - 100])
    d = ImageDraw.Draw(im)
    px0, px1 = X0 + 330, W - X0 - 60
    tmax = 8.0

    def tx(t):
        return px0 + (px1 - px0) * t / tmax

    rows = [("FLASH", top + 70, 120), ("BANG", top + 230, 120),
            ("ACOUPHÈNE" if fr else "RINGING", top + 390, 150)]
    times = [1.5, 2.04, 2.47, 3.0, 3.43]
    lab = font(34)
    for name, ry, rh in rows:
        d.text((X0 + 50, ry + rh / 2), name, font=lab, fill=TEXT, anchor="lm")
        d.line([(px0, ry + rh), (px1, ry + rh)], fill=EDGE, width=2)
    # Mèche.
    fy = rows[0][1] + rows[0][2]
    d.line([(tx(0), fy + 22), (tx(1.5), fy + 22)], fill=MUTED, width=3)
    d.line([(tx(0), fy + 12), (tx(0), fy + 32)], fill=MUTED, width=3)
    d.text((tx(0.75), fy + 50), "mèche 1,5 s" if fr else "1.5 s fuse", font=font(26, "SemiLight"), fill=MUTED, anchor="mm")
    # Flashs : barres lumineuses.
    ry, rh = rows[0][1], rows[0][2]
    for t in times:
        x = tx(t)
        glow(im, x, ry + rh * 0.45, 120, FLASH, 0.55)
        d = ImageDraw.Draw(im)
        d.rounded_rectangle([x - 7, ry + 6, x + 7, ry + rh], 6, fill=FLASH)
    # Bangs : salves d'onde amorties.
    ry, rh = rows[1][1], rows[1][2]
    mid = ry + rh / 2
    for k, t in enumerate(times):
        pts = []
        for i in range(0, 160):
            u = i / 160 * 0.42
            amp = (rh * 0.46) * math.exp(-u * 11) * (1 if k else 1.0)
            pts.append((tx(t + u), mid + amp * math.sin(u * 190)))
        d.line(pts, fill=ACCENT2, width=3)
        d.text((tx(t), ry - 2), str(k + 1), font=font(26), fill=ACCENT2, anchor="mb")
    # Acouphène : une seule courbe, relancée à chaque bang, plafonnée, puis décroissante.
    ry, rh = rows[2][1], rows[2][2]
    base = ry + rh
    level, pts, cap = 0.0, [], 1.0
    steps = 900
    for i in range(steps + 1):
        t = tmax * i / steps
        for tb in times:
            if abs(t - tb) < tmax / steps / 2:
                level = min(cap, level + (0.75 if level == 0 else 0.22))
        if t > times[0]:
            level *= math.exp(-0.32 * tmax / steps)
        pts.append((tx(t), base - level * rh * 0.92))
    poly = pts + [(tx(tmax), base), (tx(0), base)]
    fill = Image.new("L", im.size, 0)
    ImageDraw.Draw(fill).polygon(poly, fill=70)
    im.paste(Image.new("RGB", im.size, WARN), (0, 0), fill)
    d = ImageDraw.Draw(im)
    d.line(pts, fill=WARN, width=4)
    note = ("un seul sifflement, qui s'éteint en 45 s au plus" if fr else "one ringing only, fading within 45 s at most")
    d.text((tx(4.3), ry + 4), note, font=font(28, "SemiLight"), fill=SUB, anchor="lm")
    # Axe du temps.
    ay = H - 100 - 70
    d.line([(px0, ay), (px1, ay)], fill=MUTED, width=2)
    tf = font(24, "SemiLight")
    for s in range(0, int(tmax) + 1):
        d.line([(tx(s), ay - 8), (tx(s), ay + 8)], fill=MUTED, width=2)
        d.text((tx(s), ay + 30), "%d s" % s, font=tf, fill=MUTED, anchor="mm")
    footer(d, lang, True)
    return im


def scene(w, h):
    """Pièce vue de l'intérieur, en traits : mur du fond, porte, fenêtre, sol. Renvoie (image, point du flash)."""
    im = Image.new("RGB", (w, h), (34, 40, 52))
    d = ImageDraw.Draw(im)
    bx0, by0, bx1, by1 = w * 0.24, h * 0.22, w * 0.76, h * 0.70
    d.polygon([(0, h), (w, h), (bx1, by1), (bx0, by1)], fill=(58, 60, 64))
    d.polygon([(0, 0), (w, 0), (bx1, by0), (bx0, by0)], fill=(44, 50, 60))
    d.polygon([(0, 0), (bx0, by0), (bx0, by1), (0, h)], fill=(52, 58, 70))
    d.polygon([(w, 0), (bx1, by0), (bx1, by1), (w, h)], fill=(48, 54, 66))
    d.rectangle([bx0, by0, bx1, by1], fill=(64, 72, 86))
    dw = (bx1 - bx0) * 0.24
    d.rectangle([bx0 + (bx1 - bx0) * 0.12, by1 - (by1 - by0) * 0.72, bx0 + (bx1 - bx0) * 0.12 + dw, by1], fill=(20, 24, 30))
    d.rectangle([bx1 - (bx1 - bx0) * 0.42, by0 + (by1 - by0) * 0.18, bx1 - (bx1 - bx0) * 0.1, by0 + (by1 - by0) * 0.5],
                fill=(120, 150, 180))
    for a, b in (((0, 0), (bx0, by0)), ((w, 0), (bx1, by0)), ((0, h), (bx0, by1)), ((w, h), (bx1, by1))):
        d.line([a, b], fill=(90, 98, 112), width=2)
    d.rectangle([bx0, by0, bx1, by1], outline=(90, 98, 112), width=2)
    fx, fy = w * 0.58, h * 0.78
    d.ellipse([fx - 9, fy - 5, fx + 9, fy + 5], fill=(20, 22, 20))
    return im, (fx, fy)


def slide_effets(lang):
    title, sub = T["effets"][lang]
    fr = lang == "fr"
    im = background()
    d = ImageDraw.Draw(im)
    y = header(d, title, sub, 1500)
    top = y + 50
    n, gap = 4, 30
    pw = (W - 2 * X0 - (n - 1) * gap) // n
    ph = H - top - 330
    after = Image.open(os.path.join(RENDER, "fb_afterimage_ca.png")).convert("RGBA")
    caps = [
        ("0 S", "Écran blanc d'un coup" if fr else "Sudden white screen"),
        ("1 À 6 S" if fr else "1 TO 6 S", "La vue revient, couleurs ternes" if fr else "Sight returns, colours dull"),
        ("JUSQU'À 25 S" if fr else "UP TO 25 S", "Tache là où était le flash" if fr else "Spot where the flash was"),
        ("ENSUITE" if fr else "AFTER", "Vue normale" if fr else "Normal sight"),
    ]
    for i in range(n):
        base, (fx, fy) = scene(pw, ph)
        if i == 0:
            img = Image.blend(base, Image.new("RGB", base.size, (250, 250, 246)), 0.94)
        elif i == 1:
            img = ImageEnhance.Color(base.filter(ImageFilter.GaussianBlur(4))).enhance(0.35)
            img = Image.blend(img, Image.new("RGB", base.size, (236, 236, 230)), 0.45)
        elif i == 2:
            img = base.copy()
            img = ImageEnhance.Brightness(img).enhance(0.85)
            a = after.resize((int(ph * 0.42), int(ph * 0.42)), Image.LANCZOS)
            al = a.getchannel("A")
            rim = Image.new("RGBA", a.size, (120, 200, 170, 0))
            rim.putalpha(al.filter(ImageFilter.GaussianBlur(14)).point(lambda v: int(v * 0.55)))
            core = Image.new("RGBA", a.size, (12, 6, 28, 0))
            core.putalpha(al.point(lambda v: min(255, int(v * 1.6))))
            pos = (int(fx - a.width / 2), int(fy - a.height / 2 - ph * 0.04))
            img.paste(rim, pos, rim)
            img.paste(core, pos, core)
        else:
            img = base
        x = X0 + i * (pw + gap)
        mask = Image.new("L", (pw, ph), 0)
        ImageDraw.Draw(mask).rounded_rectangle([0, 0, pw - 1, ph - 1], 16, fill=255)
        im.paste(img, (x, top), mask)
        d = ImageDraw.Draw(im)
        d.rounded_rectangle([x, top, x + pw - 1, top + ph - 1], 16, outline=EDGE, width=2)
        d.text((x, top + ph + 30), caps[i][0], font=font(40), fill=ACCENT2 if i < 3 else TEXT)
        text_block(d, x, top + ph + 82, caps[i][1], font(30, "SemiLight"), SUB, pw - 10, 38)
        if i < n - 1:
            ax = x + pw + gap / 2
            d.polygon([(ax - 7, top + ph / 2 - 12), (ax + 8, top + ph / 2), (ax - 7, top + ph / 2 + 12)], fill=MUTED)
    # Rappels sous les vignettes.
    ny = top + ph + 190
    notes = (["De dos : effet bien plus faible", "JVN allumées : image saturée", "Lunette vers le flash : halo"] if fr else
             ["Back turned: much weaker", "NVG on: saturated image", "Scope on the flash: halo"])
    cx = X0
    for t in notes:
        cx = chip(d, cx, ny, t, font(26, "SemiBold Condensed"))
    footer(d, lang, True)
    return im


def person(d, x, y, facing_deg, level, r=18):
    """Joueur vu de dessus : disque coloré selon l'effet, petit triangle = direction du regard."""
    col = {"fort": FLASH, "moyen": (250, 204, 21), "faible": (148, 163, 184), "aucun": (51, 65, 85)}[level]
    a = math.radians(facing_deg)
    tip = (x + math.cos(a) * (r + 16), y + math.sin(a) * (r + 16))
    l = (x + math.cos(a + 0.5) * r, y + math.sin(a + 0.5) * r)
    rr = (x + math.cos(a - 0.5) * r, y + math.sin(a - 0.5) * r)
    d.polygon([tip, l, rr], fill=col)
    d.ellipse([x - r, y - r, x + r, y + r], fill=col, outline=(15, 23, 42), width=3)


def slide_lieux(lang):
    title, sub = T["lieux"][lang]
    fr = lang == "fr"
    im = background()
    d = ImageDraw.Draw(im)
    y = header(d, title, sub, 1500)
    top = y + 44
    gap = 40
    cw = (W - 2 * X0 - gap) // 2
    ch = H - top - 230
    lf = font(28, "SemiLight")
    hf = font(44)
    for k in range(2):
        x = X0 + k * (cw + gap)
        card(im, [x, top, x + cw, top + ch])
        d = ImageDraw.Draw(im)
        d.text((x + 36, top + 30), ("DEHORS" if fr else "OUTDOORS") if k == 0 else ("PIÈCE FERMÉE" if fr else "CLOSED ROOM"),
               font=hf, fill=TEXT)
        if k == 0:
            cx, cy = x + cw * 0.30, top + ch * 0.57
            r25 = min(cw * 0.27, ch * 0.36)
            r6 = r25 * 0.26
            glow(im, cx, cy, r25 * 1.5, FLASH, 0.22)
            d = ImageDraw.Draw(im)
            d.ellipse([cx - r25, cy - r25, cx + r25, cy + r25], outline=(100, 116, 139), width=2)
            d.ellipse([cx - r6, cy - r6, cx + r6, cy + r6], outline=FLASH, width=3)
            d.text((cx, cy - r6 - 10), "6 m", font=font(24, "SemiLight"), fill=FLASH, anchor="mb")
            d.text((cx, cy - r25 - 10), "25 m", font=font(24, "SemiLight"), fill=SUB, anchor="mb")
            d.ellipse([cx - 9, cy - 9, cx + 9, cy + 9], fill=FLASH)
            pts = [((cx + r6 * 1.9, cy + r6 * 0.4), 185, "fort"),
                   ((cx - r25 * 0.55, cy + r25 * 0.45), 140, "faible"),
                   ((cx + r25 * 0.9, cy - r25 * 0.78), 140, "aucun")]
            notes = ["Face au flash : ébloui" if fr else "Facing the flash: dazzled",
                     "De dos : peu touché" if fr else "Back turned: barely hit",
                     "Au-delà de 25 m : pas ébloui, mais le bang porte" if fr else "Beyond 25 m: no dazzle, but the bang carries"]
        else:
            cx, cy = x + cw * 0.27, top + ch * 0.57
            rw, rh_ = cw * 0.30, ch * 0.50
            rx0, ry0 = cx - rw / 2, cy - rh_ / 2
            room = Image.new("L", im.size, 0)
            ImageDraw.Draw(room).rectangle([rx0, ry0, rx0 + rw, ry0 + rh_], fill=120)
            room = room.filter(ImageFilter.GaussianBlur(5))
            im.paste(Image.new("RGB", im.size, FLASH), (0, 0), room)
            d = ImageDraw.Draw(im)
            wall = (203, 213, 225)
            gy = ry0 + rh_ * 0.30
            for seg in (((rx0, ry0), (rx0 + rw, ry0)), ((rx0, ry0 + rh_), (rx0 + rw, ry0 + rh_)),
                        ((rx0, ry0), (rx0, ry0 + rh_)), ((rx0 + rw, ry0), (rx0 + rw, gy)),
                        ((rx0 + rw, gy + 64), (rx0 + rw, ry0 + rh_))):
                d.line(seg, fill=wall, width=10)
            d.ellipse([cx - 9, cy - 9, cx + 9, cy + 9], fill=(30, 30, 30))
            pts = [((rx0 + rw * 0.25, ry0 + rh_ * 0.75), 135, "fort"),
                   ((rx0 - 52, cy + rh_ * 0.1), 180, "aucun"),
                   ((rx0 + rw + 62, gy + 32), 180, "fort")]
            notes = ["Dedans, même de dos : ébloui" if fr else "Inside, even back turned: dazzled",
                     "Derrière le mur : bang sourd, pas de flash" if fr else "Behind the wall: dull bang, no flash",
                     "Face à l'ouverture : ébloui" if fr else "Facing the opening: dazzled"]
        nb = font(22)
        for n, ((px, py), facing, lvl) in enumerate(pts, 1):
            person(d, px, py, facing, lvl)
            bx, by = px + 30, py - 30
            d.ellipse([bx - 15, by - 15, bx + 15, by + 15], fill=ACCENT)
            d.text((bx, by + 1), str(n), font=nb, fill=BG0, anchor="mm")
        # Notes numérotées à droite.
        ax = x + cw * 0.60
        ny = top + 130
        for n, t in enumerate(notes, 1):
            d.ellipse([ax, ny + 2, ax + 30, ny + 32], fill=ACCENT)
            d.text((ax + 15, ny + 18), str(n), font=nb, fill=BG0, anchor="mm")
            ny = text_block(d, ax + 46, ny, t, lf, SUB, x + cw - 36 - ax - 46, 36) + 30
    # Légende commune.
    ly = top + ch + 40
    lx = X0
    for lvl, t in (("fort", "ébloui" if fr else "dazzled"), ("faible", "peu touché" if fr else "barely hit"),
                   ("aucun", "pas ébloui" if fr else "not dazzled")):
        person(d, lx + 18, ly + 22, 0, lvl, 14)
        d.text((lx + 56, ly + 22), t, font=font(26, "SemiLight"), fill=SUB, anchor="lm")
        lx += 90 + d.textlength(t, font=font(26, "SemiLight")) + 40
    d.text((W - X0, ly + 22), ("Le regard (triangle) et les murs comptent autant que la distance." if fr else
                               "Gaze (triangle) and walls matter as much as distance."),
           font=font(26, "SemiLight"), fill=MUTED, anchor="rm")
    footer(d, lang, True)
    return im


def slide_reglages(lang):
    title, sub = T["reglages"][lang]
    fr = lang == "fr"
    im = background()
    d = ImageDraw.Draw(im)
    y = header(d, title, sub, 1500)
    top = y + 44
    gap = 34
    cw = (W - 2 * X0 - 2 * gap) // 3
    ch = 330
    presets = [("RÉALISTE" if fr else "REALISTIC", "par défaut, valeurs des sources" if fr else "default, values from sources", 1.0, 1.0),
               ("ARCADE", "plus fort, plus long" if fr else "stronger, longer", 1.25, 1.6),
               ("LÉGER" if fr else "LIGHT", "plus doux, plus court" if fr else "softer, shorter", 0.7, 0.6)]
    for i, (name, desc, force, dur) in enumerate(presets):
        x = X0 + i * (cw + gap)
        card(im, [x, top, x + cw, top + ch], outline=ACCENT if i == 0 else EDGE)
        d = ImageDraw.Draw(im)
        d.text((x + 36, top + 30), name, font=font(52), fill=TEXT)
        d.text((x + 36, top + 96), desc, font=font(28, "SemiLight"), fill=SUB)
        for j, (lab, v) in enumerate((("Force" if fr else "Strength", force), ("Durée" if fr else "Duration", dur))):
            by = top + 170 + j * 70
            d.text((x + 36, by), lab, font=font(26, "SemiBold Condensed"), fill=MUTED)
            bx0, bx1 = x + 190, x + cw - 120
            d.rounded_rectangle([bx0, by + 4, bx1, by + 26], 11, fill=(30, 41, 59))
            full = bx0 + (bx1 - bx0) * v / 1.6
            d.rounded_rectangle([bx0, by + 4, full, by + 26], 11, fill=ACCENT if i == 0 else ACCENT2)
            d.text((x + cw - 36, by + 15), "%d %%" % round(v * 100), font=font(28), fill=TEXT, anchor="rm")
    ey = top + ch + 50
    d.text((X0, ey), "Exemples" if fr else "Examples", font=font(40), fill=TEXT)
    ex = ([("Rayon maximal", "25 m", "15 m pour un effet plus local"),
           ("Effet sur son camp", "oui", "non pour un entraînement entre amis"),
           ("Durée max de l'acouphène", "45 s", "20 s pour une partie plus nerveuse")] if fr else
          [("Maximum radius", "25 m", "15 m for a more local effect"),
           ("Effect on own side", "on", "off for a friendly training session"),
           ("Max tinnitus duration", "45 s", "20 s for a faster-paced game")])
    ry = ey + 70
    for name, dflt, tip in ex:
        card(im, [X0, ry, W - X0, ry + 72], r=12)
        d = ImageDraw.Draw(im)
        d.text((X0 + 30, ry + 36), name, font=font(32), fill=TEXT, anchor="lm")
        d.text((X0 + 700, ry + 36), ("défaut " if fr else "default ") + dflt, font=font(30, "SemiLight"), fill=ACCENT2, anchor="lm")
        d.text((X0 + 1000, ry + 36), tip, font=font(30, "SemiLight"), fill=SUB, anchor="lm")
        ry += 86
    footer(d, lang, False)
    return im


def slide_compat(lang):
    title, sub = T["compat"][lang]
    fr = lang == "fr"
    im = background()
    d = ImageDraw.Draw(im)
    y = header(d, title, sub, 1500)
    top = y + 44
    gap = 30
    cw = (W - 2 * X0 - 2 * gap) // 3
    ch = (H - top - 110 - gap) // 2
    req, opt = ("OBLIGATOIRE", "OPTIONNEL") if fr else ("REQUIRED", "OPTIONAL")
    items = [
        ("CBA_A3", req, "Réglages, événements et horloge commune des flashs." if fr else
         "Settings, events and the shared clock of the flashes."),
        ("ACE3", opt, "Bouchons et casques protègent, lunettes teintées, Arsenal ACE." if fr else
         "Earplugs and headsets protect, tinted glasses, ACE Arsenal."),
        ("ACRE2 / TFAR", opt, "La radio est étouffée elle aussi, puis revient." if fr else
         "The radio is muffled too, then comes back."),
        ("ZEN", opt, "Modules Zeus : lancer un flashbang, effet sur une zone." if fr else
         "Zeus modules: throw a flashbang, area effect."),
        ("EDEN", "INCLUS" if fr else "BUILT IN", "Module Flashbang : grenade, zone ou Drill, au départ ou par déclencheur." if fr else
         "Flashbang module: grenade, area or Drill, at start or from a trigger."),
        ("MULTIJOUEUR" if fr else "MULTIPLAYER", "INCLUS" if fr else "BUILT IN",
         "Mêmes flashs chez tous ; effets seulement sur les joueurs exposés." if fr else
         "Same flashes for all; effects only on exposed players."),
    ]
    nf, pf, tf = font(46), font(22, "SemiBold Condensed"), font(34, "SemiLight")
    for i, (name, status, txt) in enumerate(items):
        x = X0 + (i % 3) * (cw + gap)
        yy = top + (i // 3) * (ch + gap)
        card(im, [x, yy, x + cw, yy + ch], outline=ACCENT if i == 0 else EDGE)
        d = ImageDraw.Draw(im)
        d.text((x + 36, yy + 34), name, font=nf, fill=TEXT)
        nw = d.textlength(name, font=nf)
        col = ACCENT2 if status == req else (MUTED if status == opt else (96, 165, 250))
        chip(d, x + 36 + nw + 18, yy + 40, status, pf, outline=col, fill=col, h=36)
        text_block(d, x + 36, yy + 116, txt, tf, SUB, cw - 72, 46)
    footer(d, lang, False)
    return im


SLIDES = [("titre", slide_titre), ("grenades", slide_grenades), ("timeline", slide_timeline),
          ("effets", slide_effets), ("lieux", slide_lieux), ("reglages", slide_reglages), ("compat", slide_compat)]


# ---------------------------------------------------------------------------------------------------------- aperçu, bannières

def preview():
    """Aperçu Workshop 1280 x 720 : même image pour toutes les langues, donc texte non traduit (nom et modèles)."""
    im = background(1280, 720)
    ren = lineup_dark().resize((1280, 720), Image.LANCZOS)
    off = 165
    fade = Image.new("L", ren.size, 255)
    fd = ImageDraw.Draw(fade)
    for y in range(180):
        fd.line([(0, y), (1280, y)], fill=int(255 * (y / 180) ** 1.6))
    im.paste(ren, (0, off), fade)
    d = ImageDraw.Draw(im)
    d.text((66, 40), "COMSPEC FLASHBANG", font=font(104), fill=TEXT)
    d.rounded_rectangle([70, 160, 170, 168], 4, fill=ACCENT)
    d.text((70, 190), "×1 … ×9  ·  DRILL", font=font(40, "SemiBold Condensed"), fill=SUB)
    return im


def banner(lang):
    """Bannière 1260 x 560 en tête de description (même format que celles d'ATAK et de SSE)."""
    fr = lang == "fr"
    bw, bh = 1260, 560
    im = background(bw, bh)
    pw = 680
    photo = cover(lineup_dark(), pw, bh, 0.42, 0.5)
    fade = Image.new("L", photo.size, 255)
    fd = ImageDraw.Draw(fade)
    for x in range(220):
        fd.line([(pw - 1 - x, 0), (pw - 1 - x, bh)], fill=int(255 * (x / 220) ** 1.3))
    im.paste(photo, (0, 0), fade)
    d = ImageDraw.Draw(im)
    x = 700
    d.text((x - 4, 70), "COMSPEC", font=font(108), fill=TEXT)
    d.text((x - 4, 174), "FLASHBANG", font=font(108), fill=TEXT)
    d.rounded_rectangle([x, 304, x + 96, 312], 4, fill=ACCENT)
    sub = ("Grenades assourdissantes réalistes : 1 à 9 flashs, grenade d'entraînement Drill." if fr else
           "Realistic stun grenades: 1 to 9 flashes, Drill training grenade.")
    yy = text_block(d, x, 336, sub, font(30, "SemiLight"), SUB, 500, 40)
    cx = x
    for c in (["ARMA 3", "CBA_A3", "ZEN", "ACE3"]):
        cx = chip(d, cx, yy + 22, c, font(22, "SemiBold Condensed"), h=38)
    return im


def main(argv=None):
    ap = argparse.ArgumentParser(description="Galerie, aperçu et bannières Workshop de COMSPEC Flashbang.")
    ap.add_argument("--out", default=os.path.join(WS, "gallery"), help="dossier de la galerie")
    ap.add_argument("--lang", choices=["fr", "en", "all"], default="all")
    ap.add_argument("--only", default="", help="noms ou numéros de diapositives, séparés par des virgules")
    ap.add_argument("--no-extras", action="store_true", help="ni aperçu ni bannières")
    a = ap.parse_args(argv)
    prepare_sources()
    langs = ["fr", "en"] if a.lang == "all" else [a.lang]
    only = {s.strip() for s in a.only.split(",") if s.strip()}
    for n, (name, fn) in enumerate(SLIDES, 1):
        if only and name not in only and str(n) not in only:
            continue
        for lang in langs:
            dest = os.path.join(a.out, lang, "fb-%02d-%s.jpg" % (n, name))
            print("ok      %s  (%d Ko)" % (dest, save_jpg(fn(lang), dest) // 1024))
    if a.no_extras or only:
        return
    dest = os.path.join(WS, "preview.jpg")
    print("ok      %s  (%d Ko)" % (dest, save_jpg(preview(), dest, 88) // 1024))
    for lang in langs:
        dest = os.path.join(BANNERS, lang, "banniere.png")
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        banner(lang).save(dest, optimize=True)
        print("ok      %s  (%d Ko)" % (dest, os.path.getsize(dest) // 1024))


if __name__ == "__main__":
    main()
