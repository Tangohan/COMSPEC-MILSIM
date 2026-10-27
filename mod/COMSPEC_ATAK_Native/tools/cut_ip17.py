#!/usr/bin/env python3
"""
iPhone 17 Pro durci (image fournie par TTRD, 2026-10-26 : src/ip17_photo.png, face en paysage, coque olive, support
JPC dessous, batterie et câble à droite, sur fond noir) : détoure la face, ouvre l'écran et dessine le dos.

Face : déjà en paysage le haut à gauche (Dynamic Island à gauche), comme les autres photos de frame_photos.py.
L'écran laissé transparent commence juste après la Dynamic Island (zone sûre d'iOS en paysage) : l'île et le
verre qui l'entoure restent dessinés par la coque, l'interface de l'ATAK ne passe jamais dessous.
Dos : miroir de la face (même coque, support et batterie vus de derrière), l'intérieur remplacé par le dos moulé
de la coque olive et sa fenêtre sur le plateau photo pleine largeur de l'iPhone 17 Pro (trois objectifs en
triangle, flash, LiDAR), aluminium bleu intense ; aucun logo.

    python3 cut_ip17.py     # écrit src/frame_ip17.png (face) et src/frame_ip17_back.png (dos)
    python3 frame_photos.py # puis gen_assets.py <hemtt> ip17
"""
import os

import numpy as np
from PIL import Image, ImageChops, ImageDraw, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "src")
photo = Image.open(os.path.join(SRC, "ip17_photo.png")).convert("RGB")
W, H = photo.size

# Fond : pixels presque noirs reliés aux bords (seuil bas : le support JPC est noir lui aussi).
BG_DARK = 20
# Écran (px de la photo) : à droite de la Dynamic Island (x 302..357), dans le verre (x 283..1313, y 168..601) ;
# x1 en deçà de l'encoche du bouton de la coque (x 1306). Coins droits arrondis comme la dalle.
SCREEN = (372, 176, 1302, 594)
SCREEN_R = 28
# Intérieur de la coque (verre et cadre titane) remplacé au dos par le panneau moulé.
PANEL = (262, 150, 1334, 620, 52)


def components(mask):
    """Étiquettes des zones 4-connexes d'un masque booléen (union-find par lignes, sans scipy)."""
    h, w = mask.shape
    lab = np.zeros((h, w), np.int32)
    parent = [0]

    def find(i):
        while parent[i] != i:
            parent[i] = parent[parent[i]]
            i = parent[i]
        return i

    for y in range(h):
        row = mask[y]
        x = 0
        while x < w:
            if not row[x]:
                x += 1
                continue
            x0 = x
            while x < w and row[x]:
                x += 1
            ids = set()
            if y:
                up = lab[y - 1, x0:x]
                ids = {find(int(v)) for v in np.unique(up[up > 0])}
            if ids:
                r = min(ids)
                for i in ids:
                    parent[i] = r
            else:
                r = len(parent)
                parent.append(r)
            lab[y, x0:x] = r
    roots = np.array([find(i) for i in range(len(parent))], np.int32)
    return roots[lab]


def front():
    a = np.array(photo).astype(int).sum(2)
    lab = components(a < BG_DARK)
    edge = set(np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]]))) - {0}
    bg = np.isin(lab, list(edge))
    A = Image.fromarray(np.where(bg, 0, 255).astype("uint8"), "L")
    # Bord adouci (anti-crénelage) sans grignoter l'objet.
    A = A.filter(ImageFilter.MaxFilter(3)).filter(ImageFilter.GaussianBlur(0.8))
    A = np.where(bg, np.array(A), 255)
    # Écran : rectangle net, coins droits arrondis ; le bord gauche (côté île) reste droit.
    r = Image.new("L", (W, H), 0)
    d = ImageDraw.Draw(r)
    x0, y0, x1, y1 = SCREEN
    d.rounded_rectangle([x0, y0, x1, y1], radius=SCREEN_R, fill=255)
    d.rectangle([x0, y0, x0 + SCREEN_R * 2, y1], fill=255)
    A = np.where(np.array(r) == 255, 0, A).astype("uint8")
    out = photo.convert("RGBA")
    out.putalpha(Image.fromarray(A, "L"))
    return out


def grain(w, h, amp, blur=0.6):
    n = Image.effect_noise((w, h), 40).filter(ImageFilter.GaussianBlur(blur))
    return (np.array(n).astype(float) - 128) / 128 * amp


def case_back(w, h, rad):
    """Dos moulé de la coque olive : polymère grainé, bord en creux, nervures, deux vis."""
    yy, xx = np.mgrid[0:h, 0:w].astype(float)
    base = np.array([112, 102, 79], float)
    light = 1.06 - 0.14 * (yy / h) - 0.05 * np.abs(xx / w - 0.5)
    rgb = base[None, None, :] * light[..., None] + grain(w, h, 7)[..., None]
    img = Image.fromarray(np.clip(rgb, 0, 255).astype("uint8"), "RGB")
    d = ImageDraw.Draw(img)
    # Creux central (zone d'appui de la main) et nervures de préhension.
    d.rounded_rectangle([w * 0.40, h * 0.16, w * 0.93, h * 0.84], radius=40, outline=(88, 80, 61), width=5)
    d.rounded_rectangle([w * 0.40 + 5, h * 0.16 + 5, w * 0.93 - 5, h * 0.84 - 5], radius=36, outline=(132, 121, 95), width=2)
    for i in range(6):
        x = w * (0.50 + i * 0.065)
        d.rounded_rectangle([x, h * 0.30, x + 14, h * 0.70], radius=7, fill=(95, 87, 67))
        d.line([x + 2, h * 0.30 + 6, x + 2, h * 0.70 - 6], fill=(128, 118, 92), width=2)
    for fx, fy in ((0.36, 0.08), (0.36, 0.92), (0.96, 0.08), (0.96, 0.92)):
        x, y, s = int(w * fx), int(h * fy), 11
        d.ellipse([x - s, y - s, x + s, y + s], fill=(40, 40, 38), outline=(84, 80, 70), width=2)
        d.line([x - s + 4, y, x + s - 4, y], fill=(90, 88, 82), width=3)
    img = img.filter(ImageFilter.SMOOTH)
    # Bord en creux : ombre intérieure le long du contour.
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, w - 1, h - 1], radius=rad, fill=255)
    inner = Image.new("L", (w, h), 0)
    ImageDraw.Draw(inner).rounded_rectangle([10, 10, w - 11, h - 11], radius=max(4, rad - 10), fill=255)
    shade = ImageChops.subtract(mask, inner.filter(ImageFilter.GaussianBlur(6)))
    img = Image.composite(Image.new("RGB", (w, h), (62, 56, 42)), img, shade.point(lambda v: v * 0.8))
    out = img.convert("RGBA")
    out.putalpha(mask)
    return out


def lens(d, img, cx, cy, r):
    """Objectif : bague en saillie usinée (dégradé radial), verre saphir noir, traitement bleuté, deux reflets."""
    R = r + 8
    yy, xx = np.mgrid[-R:R + 1, -R:R + 1].astype(float)
    rho = np.hypot(xx, yy) / r
    ang = np.arctan2(yy, xx)
    rgb = np.zeros(rho.shape + (3,))
    a = np.clip((r + 7 - rho * r) * 255 / 2, 0, 255)  # ombre portée au pied de la bague, bord adouci
    # Ombre douce autour de la bague, puis bague (clair en haut à gauche, sombre en bas à droite).
    rgb[:] = (18, 24, 36)
    ring = (rho <= 1.0) & (rho > 0.80)
    sheen = 0.5 + 0.5 * np.cos(ang + 2.3)
    rgb[ring] = (np.array([70, 80, 98]) + sheen[ring][:, None] * np.array([110, 112, 118]))
    edge = (rho <= 1.0) & (rho > 0.95)
    rgb[edge] = (205, 210, 220)
    glass = rho <= 0.80
    g = np.clip(1 - rho / 0.80, 0, 1)
    rgb[glass] = (np.array([6, 7, 10]) + (g[glass] ** 2)[:, None] * np.array([14, 20, 46]))
    coat = (rho > 0.40) & (rho <= 0.48)
    rgb[coat] = rgb[coat] + np.array([18, 24, 48]) * (0.6 + 0.4 * np.cos(ang[coat] * 2))[:, None]
    for (hx, hy, hr, col) in ((-0.32, -0.34, 0.16, (150, 175, 230)), (0.26, 0.24, 0.07, (180, 120, 210))):
        m = np.hypot(xx / r - hx, yy / r - hy) / hr
        k = np.clip(1 - m, 0, 1) ** 1.5
        rgb = rgb * (1 - k[..., None]) + np.array(col) * k[..., None]
    patch = np.dstack([np.clip(rgb, 0, 255), a]).astype("uint8")
    img.alpha_composite(Image.fromarray(patch, "RGBA").filter(ImageFilter.SMOOTH), (cx - R, cy - R))


def plateau(w, h, rad):
    """Plateau photo de l'iPhone 17 Pro, aluminium bleu intense : vue de dos, le haut du téléphone à droite."""
    yy, xx = np.mgrid[0:h, 0:w].astype(float)
    base = np.array([46, 58, 82], float)
    light = 1.10 - 0.25 * (yy / h) + 0.08 * (xx / w)
    rgb = base[None, None, :] * light[..., None] + grain(w, h, 3, 1.2)[..., None]
    img = Image.fromarray(np.clip(rgb, 0, 255).astype("uint8"), "RGB").convert("RGBA")
    d = ImageDraw.Draw(img)
    # Chanfrein poli du plateau.
    d.rounded_rectangle([2, 2, w - 3, h - 3], radius=rad, outline=(120, 136, 166), width=3)
    d.rounded_rectangle([7, 7, w - 8, h - 8], radius=rad - 5, outline=(34, 44, 64), width=2)
    r = int(h * 0.105)
    # Deux objectifs sur le bord haut (vue de dos : vers la droite), le troisième en dessous, entre les deux.
    c1 = (w - r - 26, int(h * 0.17))
    c2 = (w - 3 * r - 46, int(h * 0.17))
    c3 = ((c1[0] + c2[0]) // 2, int(h * 0.17 + 1.85 * r))
    for c in (c1, c2, c3):
        lens(d, img, c[0], c[1], r)
    # Flash, LiDAR et micro en bas du plateau.
    fx, fy, fr = c1[0], int(h * 0.80), int(r * 0.42)
    d.ellipse([fx - fr - 4, fy - fr - 4, fx + fr + 4, fy + fr + 4], fill=(28, 34, 46))
    d.ellipse([fx - fr, fy - fr, fx + fr, fy + fr], fill=(222, 214, 180))
    d.ellipse([fx - fr * 0.6, fy - fr * 0.6, fx + fr * 0.6, fy + fr * 0.6], fill=(246, 236, 196))
    lx, ly, lr = c2[0], fy, int(r * 0.40)
    d.ellipse([lx - lr - 4, ly - lr - 4, lx + lr + 4, ly + lr + 4], fill=(28, 34, 46))
    d.ellipse([lx - lr, ly - lr, lx + lr, ly + lr], fill=(6, 6, 8))
    d.ellipse([lx - lr * 0.45, ly - lr * 0.55, lx - lr * 0.05, ly - lr * 0.15], fill=(60, 60, 70))
    mx, my = (fx + lx) // 2, fy
    d.ellipse([mx - 5, my - 5, mx + 5, my + 5], fill=(14, 16, 22))
    img = img.filter(ImageFilter.SMOOTH)
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, w - 1, h - 1], radius=rad, fill=255)
    img.putalpha(mask)
    return img


def back(face):
    rear = face.transpose(Image.FLIP_LEFT_RIGHT)
    x0, y0, x1, y1, rad = PANEL
    bx0, bx1 = W - x1, W - x0
    pw, ph = x1 - x0, y1 - y0
    pnl = case_back(pw, ph, rad)
    # Fenêtre de la coque sur le plateau, côté haut du téléphone (à droite vu de dos), sur toute la largeur.
    ww, wm = int(ph * 0.58), 26
    win = (pw - ww - wm, wm, pw - wm, ph - wm)
    d = ImageDraw.Draw(pnl)
    d.rounded_rectangle([win[0] - 6, win[1] - 6, win[2] + 6, win[3] + 6], radius=46, fill=(70, 63, 48))
    d.rounded_rectangle([win[0] - 2, win[1] - 2, win[2] + 2, win[3] + 2], radius=42, fill=(18, 18, 18))
    pnl.alpha_composite(plateau(win[2] - win[0], win[3] - win[1], 38), (win[0], win[1]))
    rear.alpha_composite(pnl, (bx0, y0))
    return rear


def crop_box(img):
    return img.getchannel("A").point(lambda v: 255 if v > 8 else 0).getbbox()


face = front()
rear = back(face.copy())
box = crop_box(face)
mirror = (W - box[2], box[1], W - box[0], box[3])
face.crop(box).save(os.path.join(SRC, "frame_ip17.png"))
rear.crop(mirror).save(os.path.join(SRC, "frame_ip17_back.png"))
print("frame_ip17", box, "screen", SCREEN)
