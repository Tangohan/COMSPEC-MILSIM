#!/usr/bin/env python3
"""
Coques photo du terminal ATAK (images fournies par TTRD, 2026-10-14) à la place des rendus 3D de render_phones.py :
  iphone        V2 ENHANCED   Samsung S7 en coque durcie OPENA, fixation et câble (src/frame_v2_enhanced.png)
  rugged        XIAOMI X3D    cadre tactique Bunker Supply, très allongé (src/frame_xiaomi_x3d.png)
  keys, tablet  TABLETTE      tablette durcie, la sélectionnable et celle des véhicules (src/frame_tablette.png)
  sonim         SONIM XP8     Sonim durci et radio maillée goTenna Pro clipsée au dos, qui dépasse en haut
                              (src/frame_sonim.png : téléphone à gauche, goTenna de face puis côté clip à droite)
  s23           SAMSUNG S23   S23 en coque durcie tan (planche de TTRD, 2026-10-19, découpée par cut_s23.py) ;
                              son dos est la vraie photo du dos (src/frame_s23_back.png), pas un panneau dessiné
Les identifiants restent ceux des anciens modèles : un profil qui a gardé « iphone » ou « rugged » ne casse pas.

Chaque photo garde ses proportions : l'ouverture de l'écran n'est donc plus la même pour tous les modèles. La photo
est mise à l'échelle pour que son ouverture fasse 1092 px de large, centrée sur l'ouverture du S7 (x 455..1546,
y 716..1333 : centre 1000,5 ; 1024,5) dans la toile 2048 x 2048. Le script affiche les fractions à reporter dans
fn_layoutGet (SCREEN, écran paysage par modèle) et les écrit dans render/frame_screens.json.

    python3 frame_photos.py            # écrit render/phone_<modèle>_front.png et _back.png
    python3 gen_assets.py <hemtt> models    # puis les PAA (paysage, portrait, nuit, dos)
    python3 gen_dirt.py <hemtt> case iphone rugged sonim keys tablet s23

Dos : miroir de la face (même silhouette une fois retournée), l'intérieur de la coque remplacé par un panneau en
polymère sombre avec le module photo ; les marquages de la coque (OPENA, BUNKER SUPPLY) sont remis à l'endroit.
"""
import json
import os

from PIL import Image, ImageChops, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "src")
OUT = os.path.join(HERE, "render")
SIZE = 2048
HOLE_W = 1092  # largeur de l'ouverture dans la toile (celle du S7)
CENTER = (1000.5, 1024.5)  # centre de l'ouverture du S7

# modèle : (photo, panneau du dos (x0, y0, x1, y1, rayon) en px de la photo, zones de marquage à remettre à l'endroit)
FRAMES = {
    "iphone": ("frame_v2_enhanced.png", (128, 98, 1368, 638, 60), [(92, 150, 130, 275)]),
    "rugged": ("frame_xiaomi_x3d.png", (186, 106, 1460, 593, 34), [(92, 150, 160, 550)]),
    "keys": ("frame_tablette.png", (160, 104, 1523, 794, 30), []),
    "tablet": ("frame_tablette.png", (160, 104, 1523, 794, 30), []),
    "sonim": ("frame_sonim.png", (58, 117, 1130, 696, 40), []),  # panneau en px de la photo assemblée
    "s23": ("frame_s23.png", None, []),
}
# Vraie photo du dos, téléphone retourné (haut à droite) : remplace le miroir de la face et le panneau dessiné.
BACKS = {"s23": "frame_s23_back.png"}
# Photos à assembler : (boîte du téléphone, [(boîte de l'accessoire vu de face, vu de dos, position dans la boîte du
# téléphone)]) ; l'accessoire passe derrière le téléphone (clipsé au dos), le dos montre son côté clip.
COMPOSE = {
    "sonim": ((34, 37, 1198, 667), [((1254, 213, 2137, 323), (1254, 392, 2137, 503), (240, -78))]),
}


def compose(photo, model):
    """Téléphone recadré, accessoires posés derrière ; renvoie la face et la base du dos (accessoire côté clip)."""
    if model not in COMPOSE:
        return photo, photo
    box, extras = COMPOSE[model]
    phone = photo.crop(box)
    top = max([0] + [-pos[1] for _, _, pos in extras])
    out = []
    for side in (0, 1):
        img = Image.new("RGBA", (phone.width, phone.height + top), (0, 0, 0, 0))
        for front, back, (x, y) in extras:
            img.alpha_composite(photo.crop((front, back)[side]), (x, y + top))
        img.alpha_composite(phone, (0, top))
        out.append(img)
    return out[0], out[1]


def clean(img):
    """Détourage : les franges presque transparentes (jaune, rouge) laissées par le fond retiré disparaissent."""
    a = img.getchannel("A").point(lambda v: 0 if v < 12 else v)
    img.putalpha(a)
    return img


def hole_bbox(img):
    """Ouverture de l'écran : la zone transparente qui contient le centre de la photo (remplissage par diffusion)."""
    w, h = img.size
    a = img.getchannel("A").point(lambda v: 255 if v < 10 else 0)
    ImageDraw.floodfill(a, (w // 2, h // 2), 128)
    return a.point(lambda v: 255 if v == 128 else 0).getbbox()  # x1, y1 exclus


def back_panel(w, h, r):
    """Dos de la coque : polymère sombre grainé, module photo en haut à gauche (vu de dos), vis aux coins."""
    base = Image.new("RGB", (w, h), (34, 35, 33))
    noise = Image.effect_noise((w, h), 18).convert("RGB")
    base = ImageChops.add(base, noise.point(lambda v: v // 6), 1, -20)
    d = ImageDraw.Draw(base)
    # Léger dégradé (lumière venant du haut).
    for y in range(h):
        k = int(10 * (1 - y / h))
        d.line([(0, y), (w, y)], fill=(34 + k, 35 + k, 33 + k))
    base = ImageChops.add(base, noise.point(lambda v: v // 7), 1, -18)
    d = ImageDraw.Draw(base)
    # Nervures horizontales discrètes.
    for i in range(5):
        y = int(h * (0.62 + i * 0.05))
        d.rounded_rectangle([w * 0.30, y, w * 0.70, y + max(4, h // 90)], radius=3, fill=(26, 27, 25))
    # Module photo.
    m = min(w, h)
    cx, cy, cr = int(w * 0.12), int(h * 0.20), int(m * 0.075)
    d.rounded_rectangle([cx - cr * 1.5, cy - cr * 1.4, cx + cr * 3.6, cy + cr * 1.4], radius=cr * 0.6, fill=(18, 18, 18), outline=(62, 62, 60), width=3)
    for ox in (0, int(cr * 2.2)):
        d.ellipse([cx + ox - cr, cy - cr, cx + ox + cr, cy + cr], fill=(10, 10, 12), outline=(90, 90, 88), width=4)
        d.ellipse([cx + ox - cr * 0.55, cy - cr * 0.55, cx + ox + cr * 0.55, cy + cr * 0.55], fill=(14, 24, 36))
        d.ellipse([cx + ox - cr * 0.22, cy - cr * 0.42, cx + ox + cr * 0.05, cy - cr * 0.15], fill=(120, 140, 160))
    # Vis.
    for fx, fy in ((0.04, 0.08), (0.96, 0.08), (0.04, 0.92), (0.96, 0.92)):
        x, y, s = int(w * fx), int(h * fy), max(6, m // 60)
        d.ellipse([x - s, y - s, x + s, y + s], fill=(22, 22, 22), outline=(80, 80, 78), width=2)
        d.line([x - s + 3, y, x + s - 3, y], fill=(60, 60, 58), width=2)
    base = base.filter(ImageFilter.SMOOTH)
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, w - 1, h - 1], radius=r, fill=255)
    out = base.convert("RGBA")
    out.putalpha(mask)
    return out


def build(model):
    name, panel, labels = FRAMES[model]
    photo, back_base = compose(clean(Image.open(os.path.join(SRC, name)).convert("RGBA")), model)
    hx0, hy0, hx1, hy1 = hole_bbox(photo)
    s = HOLE_W / (hx1 - hx0)
    w, h = round(photo.width * s), round(photo.height * s)
    scaled = photo.resize((w, h), Image.LANCZOS)
    ox = round(CENTER[0] - (hx0 + hx1) / 2 * s)
    oy = round(CENTER[1] - (hy0 + hy1) / 2 * s)
    front = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    front.alpha_composite(scaled, (ox, oy))
    assert ox >= 0 and oy >= 0 and ox + w <= SIZE and oy + h <= SIZE, (model, ox, oy, w, h)
    # Écran paysage (fractions de la toile) : bords de l'ouverture, coins arrondis compris (la coque passe devant).
    scr = [(ox + hx0 * s) / SIZE, (oy + hy0 * s) / SIZE, (ox + hx1 * s) / SIZE, (oy + hy1 * s) / SIZE]
    vis = front.getchannel("A").point(lambda v: 255 if v > 40 else 0).getbbox()
    vis = [vis[0] / SIZE, vis[1] / SIZE, vis[2] / SIZE, vis[3] / SIZE]

    back = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    if model in BACKS:
        # Photo du dos à la même hauteur que la face, centrée sur la place de la face retournée.
        rear = clean(Image.open(os.path.join(SRC, BACKS[model])).convert("RGBA"))
        rw = round(rear.width * h / rear.height)
        back.alpha_composite(rear.resize((rw, h), Image.LANCZOS), (SIZE - ox - w + (w - rw) // 2, oy))
        return save(model, front, back, scr, vis, ox, oy, hx0, hy0, hx1, hy1, s)
    # Dos : face retournée, intérieur remplacé par le panneau, marquages remis à l'endroit.
    back_src = back_base.copy()
    px0, py0, px1, py1, pr = panel
    pnl = back_panel(px1 - px0, py1 - py0, pr)
    back_src.alpha_composite(pnl, (px0, py0))
    back_src = back_src.transpose(Image.FLIP_LEFT_RIGHT)
    for lx0, ly0, lx1, ly1 in labels:
        # La zone, en miroir, tombe en (W - x1 .. W - x0) : on la retourne sur place pour relire le texte.
        bx0, bx1 = photo.width - lx1, photo.width - lx0
        region = back_src.crop((bx0, ly0, bx1, ly1)).transpose(Image.FLIP_LEFT_RIGHT)
        back_src.paste(region, (bx0, ly0))
    back.alpha_composite(back_src.resize((w, h), Image.LANCZOS), (SIZE - ox - w, oy))
    return save(model, front, back, scr, vis, ox, oy, hx0, hy0, hx1, hy1, s)


def save(model, front, back, scr, vis, ox, oy, hx0, hy0, hx1, hy1, s):
    os.makedirs(OUT, exist_ok=True)
    front.save(os.path.join(OUT, f"phone_{model}_front.png"))
    back.save(os.path.join(OUT, f"phone_{model}_back.png"))
    return {"screen": [round(v, 4) for v in scr], "visible": [round(v, 4) for v in vis],
            "hole_px": [round(ox + hx0 * s), round(oy + hy0 * s), round(ox + hx1 * s) - 1, round(oy + hy1 * s) - 1]}


def main():
    res = {m: build(m) for m in FRAMES}
    with open(os.path.join(OUT, "frame_screens.json"), "w") as fh:
        json.dump(res, fh, indent=1)
    for m, r in res.items():
        print(m, "screen", r["screen"], "visible", r["visible"], "hole_px", r["hole_px"])


if __name__ == "__main__":
    main()
