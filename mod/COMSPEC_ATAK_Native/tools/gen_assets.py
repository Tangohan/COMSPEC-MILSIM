#!/usr/bin/env python3
"""
Génère les textures du terminal (coque téléphone + icônes) en PNG puis PAA.
Dessins originaux COMSPEC (aucune ressource BCE, cTab ou Iceman).

Usage : python3 gen_assets.py <hemtt>
Sortie : Sources/addons/main/data/*.paa
Dépendances : Pillow, CairoSVG, HEMTT (hemtt utils paa convert).
"""
import io, os, subprocess, sys, tempfile
from PIL import Image, ImageDraw, ImageFilter
import cairosvg

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "Sources", "addons", "main", "data")
HEMTT = sys.argv[1] if len(sys.argv) > 1 else "hemtt"

# Coque fournie par COMSPEC (Samsung S7 en coque olive, 2048x2048, écran transparent).
# Écran paysage (fractions de l'image) — reprises dans fn_layoutGet.sqf : (0.2222, 0.3496)-(0.7549, 0.6509).
PHONE_SRC = os.path.join(HERE, "src", "android_s7_ca.png")
PHONE_NIGHT_SRC = os.path.join(HERE, "src", "android_s7_night_ca.png")


def rr(draw, box, r, fill):
    draw.rounded_rectangle(box, radius=r, fill=fill)


def phone_landscape(w=2048, h=1024):
    s = 2  # suréchantillonnage
    W, H = w * s, h * s
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    case = (122, 112, 80, 255)
    case_dark = (92, 84, 60, 255)
    # Clips haut / bas (support MOLLE)
    rr(d, (int(W * 0.44), int(H * 0.0), int(W * 0.60), int(H * 0.10)), 18 * s, (28, 28, 28, 255))
    rr(d, (int(W * 0.44), int(H * 0.90), int(W * 0.60), int(H * 1.0)), 18 * s, (28, 28, 28, 255))
    # Coque renforcée
    rr(d, (int(W * 0.01), int(H * 0.06), int(W * 0.99), int(H * 0.94)), 120 * s, case_dark)
    rr(d, (int(W * 0.018), int(H * 0.075), int(W * 0.982), int(H * 0.925)), 110 * s, case)
    # Bumpers d'angle
    for (x0, y0) in [(0.012, 0.06), (0.93, 0.06), (0.012, 0.80), (0.93, 0.80)]:
        rr(d, (int(W * x0), int(H * y0), int(W * (x0 + 0.058)), int(H * (y0 + 0.14))), 50 * s, case_dark)
    # Corps du téléphone
    rr(d, (int(W * 0.055), int(H * 0.105), int(W * 0.945), int(H * 0.895)), 90 * s, (52, 54, 56, 255))
    rr(d, (int(W * 0.062), int(H * 0.115), int(W * 0.938), int(H * 0.885)), 84 * s, (34, 36, 38, 255))
    # Écran (noir, recouvert par l'interface)
    x0, y0, x1, y1 = SCREEN_L
    d.rectangle((int(W * x0) - 4 * s, int(H * y0) - 4 * s, int(W * x1) + 4 * s, int(H * y1) + 4 * s), fill=(8, 10, 9, 255))
    # Haut-parleur + capteurs (côté gauche)
    rr(d, (int(W * 0.074), int(H * 0.40), int(W * 0.084), int(H * 0.60)), 8 * s, (70, 72, 74, 255))
    d.ellipse((int(W * 0.072), int(H * 0.25), int(W * 0.088), int(H * 0.282)), fill=(18, 18, 18, 255))
    # Boutons de navigation (côté droit)
    rr(d, (int(W * 0.900), int(H * 0.40), int(W * 0.922), int(H * 0.60)), 14 * s, (24, 24, 24, 255))
    # Boutons latéraux sur la coque
    rr(d, (int(W * 0.20), int(H * 0.035), int(W * 0.30), int(H * 0.075)), 10 * s, (40, 40, 40, 255))
    rr(d, (int(W * 0.66), int(H * 0.925), int(W * 0.80), int(H * 0.965)), 10 * s, (40, 40, 40, 255))
    img = img.resize((w, h), Image.LANCZOS)
    return img


ICONS = {
    "app_map": '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
    "app_chat": '<path d="M4 4h16v11H9l-5 4v-4H4z"/><path d="M8 9h8M8 12h5"/>',
    "app_group": '<circle cx="8" cy="8" r="3"/><circle cx="16.5" cy="9" r="2.5"/><path d="M2.5 19c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><path d="M14 14.2c.8-.5 1.6-.7 2.5-.7 2.7 0 5 2 5 5"/>',
    "app_tasks": '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M8.5 9l1.8 1.8L13.5 7.5M8.5 15h7"/>',
    "app_c2": '<path d="M6 21V10M18 21V10"/><path d="M12 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM12 13v8"/><path d="M8.5 7.5a5 5 0 0 1 7 0M5.5 4.5a9 9 0 0 1 13 0"/>',
    "app_bft": '<circle cx="12" cy="12" r="8"/><path d="M12 6l3.5 9L12 13l-3.5 2z"/>',
    "app_intel": '<path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    "app_sse": '<path d="M6 3h8l4 4v6"/><path d="M6 3v18h6"/><circle cx="16" cy="17" r="3"/><path d="M18.2 19.2L21 22"/>',
    "app_bda": '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
    "app_photos": '<path d="M3 7h4l2-2.5h6L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
    "app_briefing": '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 21h8M7 12l3-3 2 2 4-4"/>',
    "app_status": '<path d="M3 20h18M6 20v-6M10 20V9M14 20v-8M18 20V5"/>',
    "app_settings": '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>',
    "ui_back": '<path d="M15 5l-7 7 7 7"/>',
    "ui_apps": '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/>',
    "ui_expand": '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
    "ui_collapse": '<path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/>',
    "ui_rotate": '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M2 14a8 8 0 0 0 5 6M22 10a8 8 0 0 0-5-6"/>',
    "ui_send": '<path d="M3 11l18-8-7 18-3-7z"/><path d="M11 14l10-11"/>',
    "ui_close": '<path d="M6 6l12 12M18 6L6 18"/>',
    "ui_gps": '<path d="M12 21s-6-6.2-6-11a6 6 0 0 1 12 0c0 4.8-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/><path d="M5 21h14"/>',
    "ui_weather": '<path d="M7 18a4 4 0 0 1-.4-8 5.5 5.5 0 0 1 10.6 1.5A3.3 3.3 0 0 1 17 18z"/>',
    "ui_wind": '<path d="M3 9h11a2.5 2.5 0 1 0-2.5-2.5M3 13h15a2.5 2.5 0 1 1-2.5 2.5M3 17h8"/>',
    "map_center": '<circle cx="12" cy="12" r="6"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/><circle cx="12" cy="12" r="1.2" fill="#fff"/>',
    "map_zoomin": '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21M8 10.5h5M10.5 8v5"/>',
    "map_zoomout": '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21M8 10.5h5"/>',
    "map_marker": '<path d="M12 21s-6-6.2-6-11a6 6 0 0 1 12 0c0 4.8-6 11-6 11z"/><path d="M12 7v6M9 10h6"/>',
    "map_measure": '<path d="M3 17L17 3l4 4L7 21z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/>',
    "map_labels": '<path d="M4 6h16M12 6v13M8 19h8"/>',
    "map_clear": '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
    "map_compass": '<circle cx="12" cy="12" r="9.5"/><path d="M12 4l3 8h-6z" fill="#fff"/><path d="M12 20l-3-8h6z"/>',
    "map_follow": '<path d="M12 3l7 17-7-4-7 4z"/>',
    "map_tools": '<path d="M4 20h16M4 20L14 4M9 20a6 6 0 0 0-1.6-4.2"/>',
    "map_house": '<path d="M3 11l9-7 9 7M5 9.5V20h14V9.5"/><path d="M10 20v-5h4v5"/>',
    "map_height": '<path d="M2 20l6-9 4 5 3-4 7 8z"/><path d="M18 3v7M15.5 5.5L18 3l2.5 2.5"/>',
    "map_grid": '<path d="M3 3h18v18H3zM9 3v18M15 3v18M3 9h18M3 15h18"/>',
    "map_flat": '<path d="M2 18h20M5 18l2-6h10l2 6"/><path d="M9 8h6M12 5v6"/>',
    "app_fires": '<path d="M4 20l6-6"/><path d="M10 14l2-6 6-4-4 6-6 2z"/><circle cx="18" cy="18" r="3"/><path d="M18 13v2M18 21v2M13 18h2M21 18h2"/>',
    "map_los": '<circle cx="5" cy="17" r="2"/><path d="M7 15.5L20 6"/><path d="M14 20l2-4 2 4" /><circle cx="20" cy="6" r="1.5"/>',
    "map_distance": '<path d="M3 12h18M3 8v8M21 8v8"/><path d="M7 10l-2 2 2 2M17 10l2 2-2 2"/>',
    "app_network": '<circle cx="12" cy="18" r="1.6"/><path d="M8.5 14.5a5 5 0 0 1 7 0M5.5 11.5a9 9 0 0 1 13 0M2.5 8.5a13 13 0 0 1 19 0"/>',
    "app_athena": '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
    "ui_camera": '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="6" fill="#fff"/>',
    "ui_link": '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    "ui_vibrate": '<rect x="8" y="4" width="8" height="16" rx="1.5"/><path d="M4 8v8M20 8v8M2 10v4M22 10v4"/>',
}


def compass_ring(size=256):
    s = 4
    S = size * s
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    c = S / 2
    d.ellipse((6 * s, 6 * s, S - 6 * s, S - 6 * s), fill=(10, 14, 12, 190), outline=(255, 255, 255, 230), width=4 * s)
    import math
    for a in range(0, 360, 15):
        r0 = c - 6 * s - (22 if a % 90 == 0 else 12) * s
        r1 = c - 8 * s
        x0, y0 = c + r0 * math.sin(math.radians(a)), c - r0 * math.cos(math.radians(a))
        x1, y1 = c + r1 * math.sin(math.radians(a)), c - r1 * math.cos(math.radians(a))
        d.line((x0, y0, x1, y1), fill=(255, 255, 255, 220), width=(4 if a % 90 == 0 else 2) * s)
    # Triangle nord rouge
    d.polygon([(c, 10 * s), (c - 14 * s, 40 * s), (c + 14 * s, 40 * s)], fill=(229, 72, 58, 255))
    return img.resize((size, size), Image.LANCZOS)


def compass_needle(size=256):
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="{size}" height="{size}">
<path d="M12 4.5l3.2 10L12 12.6l-3.2 1.9z" fill="#5cc76b" stroke="#0b0f0c" stroke-width="0.5"/></svg>'''
    return Image.open(io.BytesIO(cairosvg.svg2png(bytestring=svg.encode(), output_width=size, output_height=size))).convert("RGBA")


def icon_png(name, body, size=128):
    svg = f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="{size}" height="{size}" fill="none" stroke="#ffffff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{body}</svg>'
    return Image.open(io.BytesIO(cairosvg.svg2png(bytestring=svg.encode(), output_width=size, output_height=size))).convert("RGBA")


def battery(level, w=128, h=64):
    s = 4
    img = Image.new("RGBA", (w * s, h * s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    d.rounded_rectangle((6 * s, 12 * s, 110 * s, 52 * s), radius=6 * s, outline=(255, 255, 255, 255), width=5 * s)
    d.rectangle((112 * s, 24 * s, 122 * s, 40 * s), fill=(255, 255, 255, 255))
    fw = int((110 - 16) * level / 100)
    if fw > 0:
        d.rectangle((14 * s, 20 * s, (14 + fw) * s, 44 * s), fill=(255, 255, 255, 255))
    return img.resize((w, h), Image.LANCZOS)


def signal(bars, w=128, h=64):
    s = 4
    img = Image.new("RGBA", (w * s, h * s), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    for i in range(4):
        x = (20 + i * 24) * s
        top = (52 - (i + 1) * 10) * s
        fill = (255, 255, 255, 255) if i < bars else (255, 255, 255, 70)
        d.rectangle((x, top, x + 14 * s, 56 * s), fill=fill)
    return img.resize((w, h), Image.LANCZOS)


def topo_wallpaper(w, h, seed, hue):
    """Carte topographique sombre générée : courbes de niveau anticrénelées, maîtresses tous les 5, carroyage 1 km, logo."""
    import numpy as np
    from PIL import ImageFilter
    rng = np.random.default_rng(seed)
    field = np.zeros((h, w), dtype=np.float64)
    for octave, amp in ((6, 1.0), (12, 0.5), (24, 0.22), (48, 0.08)):
        gh, gw = max(2, octave * h // max(w, h) + 2), max(2, octave * w // max(w, h) + 2)
        g = Image.fromarray((rng.random((gh, gw)) * 255).astype(np.uint8), "L").resize((w, h), Image.BICUBIC)
        field += amp * np.asarray(g, dtype=np.float64) / 255.0
    # Lissage en flottant (pas de passage en 8 bits, sinon les courbes crénellent).
    def box(a, r, axis):
        pad = [(0, 0), (0, 0)]; pad[axis] = (r + 1, r)
        c = np.cumsum(np.pad(a, pad, mode="edge"), axis=axis)
        return (np.take(c, range(2 * r + 1, c.shape[axis]), axis=axis) - np.take(c, range(0, c.shape[axis] - 2 * r - 1), axis=axis)) / (2 * r + 1)
    field = field / field.max()
    for _ in range(3):
        field = box(box(field, 3, 0), 3, 1)
    v = field * 26.0
    gy, gx = np.gradient(v)
    grad = np.sqrt(gx * gx + gy * gy) + 1e-6
    dist = np.abs(((v + 0.5) % 1.0) - 0.5) / grad
    major = (np.floor(v + 0.5).astype(int) % 5) == 0
    line = np.clip(1.2 - dist, 0, 1) * np.where(major, 0.55, 0.22)
    line += np.clip(1.9 - dist, 0, 1) * np.where(major, 0.18, 0.0)
    yy, xx = np.mgrid[0:h, 0:w]
    vign = 1.0 - 0.55 * (((xx / w - 0.5) ** 2 + (yy / h - 0.5) ** 2) * 2.2)
    base = np.stack([np.full((h, w), c) for c in (6, 14, 11)], -1).astype(np.float64)
    base += (field[..., None] * np.array([6, 16, 12]))
    accent = np.array(hue, dtype=np.float64)
    img = base * vign[..., None] + line[..., None] * accent
    # Carroyage : une ligne fine tous les 256 px.
    grid = ((xx % 256) < 1) | ((yy % 256) < 1)
    img[grid] = img[grid] * 0.6 + accent * 0.18
    out = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8), "RGB").convert("RGBA")
    hawk = os.path.join(HERE, "src", "takos_hawk_white.png")
    if os.path.exists(hawk):
        size = min(w, h) // 8
        logo = Image.open(hawk).convert("RGBA").resize((size, size), Image.LANCZOS)
        alpha = logo.split()[3].point(lambda a: int(a * 0.16))
        logo.putalpha(alpha)
        out.alpha_composite(logo, ((w - size) // 2, (h - size) // 2))
    return out


def photo_wallpaper(path, w, h, dim):
    """Photo en fond : couvre l'écran sans l'étirer ; en vertical, la photo entière sur un fond flouté."""
    from PIL import ImageFilter
    img = Image.open(path).convert("RGB")
    img = Image.eval(img, lambda v: int(v * dim))
    sw, sh = img.size
    cover = max(w / sw, h / sh)
    bg = img.resize((int(sw * cover) + 1, int(sh * cover) + 1), Image.LANCZOS)
    bg = bg.crop(((bg.width - w) // 2, (bg.height - h) // 2, (bg.width - w) // 2 + w, (bg.height - h) // 2 + h))
    if h > w:
        bg = Image.eval(bg.filter(ImageFilter.GaussianBlur(24)), lambda v: int(v * 0.55))
        fit = w / sw
        fg = img.resize((w, int(sh * fit)), Image.LANCZOS)
        bg.paste(fg, (0, (h - fg.height) // 2))
    return bg.convert("RGBA")


def wallpapers():
    out = []
    try:
        for suffix, (w, h) in (("land", (2048, 1024)), ("port", (1024, 2048))):
            out.append((f"wall_topo_{suffix}", topo_wallpaper(w, h, 7, (92, 199, 107))))
            out.append((f"wall_night_{suffix}", topo_wallpaper(w, h, 21, (90, 150, 230))))
    except ImportError:
        print("numpy absent : fonds topographiques non générés")
    for name, src, dim in (("athena", "wallpaper_athena.jpg", 0.85), ("ops", "wallpaper_ops.png", 0.62)):
        path = os.path.join(HERE, "src", src)
        if os.path.exists(path):
            for suffix, (w, h) in (("land", (2048, 1024)), ("port", (1024, 2048))):
                out.append((f"wall_{name}_{suffix}", photo_wallpaper(path, w, h, dim)))
    return out


def convert(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    paa = os.path.join(OUT, name + ".paa")
    # hemtt ne remplace pas un PAA existant : on l'efface d'abord.
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def main():
    os.makedirs(OUT, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        land = Image.open(PHONE_SRC).convert("RGBA") if os.path.exists(PHONE_SRC) else phone_landscape()
        convert(land, "phone_landscape", tmp)
        # Rotation horaire : le bouton home du S7 passe en bas.
        convert(land.rotate(-90, expand=True), "phone_portrait", tmp)
        # Variante nuit (écran et coque assombris), fournie avec la texture S7.
        if os.path.exists(PHONE_NIGHT_SRC):
            night = Image.open(PHONE_NIGHT_SRC).convert("RGBA")
            convert(night, "phone_landscape_night", tmp)
            convert(night.rotate(-90, expand=True), "phone_portrait_night", tmp)
        convert(compass_ring(), "compass_ring", tmp)
        convert(compass_needle(), "compass_needle", tmp)
        for name, body in ICONS.items():
            convert(icon_png(name, body), name, tmp)
        for lvl in (100, 75, 50, 25, 10):
            convert(battery(lvl), f"bat_{lvl}", tmp)
        for b in range(5):
            convert(signal(b), f"sig_{b}", tmp)
        # Fonds d'écran (accueil), 2048 px : topographique généré (net à toute taille) et photos.
        for name, img in wallpapers():
            # Sans alpha : compression DXT1, deux fois plus légère.
            convert(img.convert("RGB"), name, tmp)
        # Logo ATAK (faucon blanc) et viseur du mode photo.
        hawk = os.path.join(HERE, "src", "takos_hawk_white.png")
        if os.path.exists(hawk):
            convert(Image.open(hawk).convert("RGBA").resize((128, 128), Image.LANCZOS), "logo_atak", tmp)
        overlay = os.path.join(HERE, "src", "camera_overlay.png")
        if os.path.exists(overlay):
            convert(Image.open(overlay).convert("RGBA"), "camera_overlay", tmp)
        # Aperçu PNG pour la revue (non packagé)
        land.save(os.path.join(HERE, "preview_phone_landscape.png"))
    print("ok", len(os.listdir(OUT)), "textures")


if __name__ == "__main__":
    main()
