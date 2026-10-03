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
    "map_route": '<circle cx="6" cy="18" r="2"/><path d="M8 18h6a3.5 3.5 0 0 0 0-7H10a3.5 3.5 0 0 1 0-7h6"/><path d="M18 2.5l2 2-2 2"/>',
    "nav_straight": '<path d="M12 21V4M6 10l6-6 6 6"/>',
    "nav_left": '<path d="M16 21v-8a4 4 0 0 0-4-4H5M9 5L5 9l4 4"/>',
    "nav_right": '<path d="M8 21v-8a4 4 0 0 1 4-4h7M15 5l4 4-4 4"/>',
    "nav_slight_left": '<path d="M14 21v-7L8 6M7 11V5h6"/>',
    "nav_slight_right": '<path d="M10 21v-7l6-8M17 11V5h-6"/>',
    "nav_uturn": '<path d="M8 21V9a4 4 0 0 1 8 0v8M12 14l4 4 4-4"/>',
    "nav_arrive": '<path d="M6 21V4M6 4h11l-2.5 4L17 12H6"/>',
    "app_music": '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
    "app_gps": '<circle cx="12" cy="10" r="3"/><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/>',
    "app_credits": '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
    "app_weather": '<path d="M7 18h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.5 1.5A3.3 3.3 0 0 0 7 18z"/><path d="M9 21l1-2M13 21l1-2"/>',
    "app_waverelay": '<circle cx="12" cy="12" r="2"/><circle cx="4" cy="6" r="1.6"/><circle cx="20" cy="6" r="1.6"/><circle cx="5" cy="19" r="1.6"/><circle cx="19" cy="19" r="1.6"/><path d="M5.3 7l5.2 3.8M18.7 7l-5.2 3.8M6.3 18l4.4-4.6M17.7 18l-4.4-4.6M5.6 6h12.8"/>',
    "app_relief": '<path d="M2 20l6-10 4 6 3-4 7 8z"/><path d="M8 10l1.5 2.5"/>',
    "app_logistics": '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
    "app_ew": '<path d="M4 18a11 11 0 0 1 0-12M20 6a11 11 0 0 1 0 12M7.5 15a6 6 0 0 1 0-6M16.5 9a6 6 0 0 1 0 6"/><circle cx="12" cy="12" r="1.8"/><path d="M3 3l18 18"/>',
    "app_debug": '<rect x="7" y="7" width="10" height="13" rx="5"/><path d="M12 7V4M9 4.5l1.5 2.5M15 4.5L13.5 7M7 12H3M21 12h-4M7 16l-3 2M17 16l3 2M7 9L4 7M17 9l3-2M12 11v6"/>',
    "app_waypoints": '<circle cx="5" cy="19" r="2"/><circle cx="12" cy="11" r="2"/><circle cx="19" cy="5" r="2"/><path d="M6.4 17.6l4.2-5.2M13.4 9.6l4.2-3.2" stroke-dasharray="2 2"/>',
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
    "app_food": '<path d="M5 8h14l-1.5 13h-11z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/><path d="M9 13h6"/>',
    "app_dating": '<path d="M12 20s-8-4.8-8-10.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5C20 15.2 12 20 12 20z"/>',
    "app_osint": '<circle cx="10" cy="10" r="7"/><path d="M3 10h14M10 3c2.2 2 2.2 12 0 14M10 3c-2.2 2-2.2 12 0 14"/><path d="M15.5 15.5L21 21"/>',
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
        for suffix, (w, h) in (("land", (2048, 1024)), ("port", (1024, 2048))):
            out.append((f"wall_desert_{suffix}", topo_wallpaper(w, h, 33, (214, 170, 96))))
            out.append((f"wall_olive_{suffix}", topo_wallpaper(w, h, 47, (150, 160, 80))))
    except ImportError:
        print("numpy absent : fonds topographiques non générés")
    for name, src, dim in (("athena", "wallpaper_athena.jpg", 0.85), ("ops", "wallpaper_ops.png", 0.62), ("dark", "wallpaper_dark.jpg", 1.0)):
        path = os.path.join(HERE, "src", src)
        if os.path.exists(path):
            for suffix, (w, h) in (("land", (2048, 1024)), ("port", (1024, 2048))):
                out.append((f"wall_{name}_{suffix}", photo_wallpaper(path, w, h, dim)))
    soar = os.path.join(HERE, "src", "logo_soar.png")
    dark = os.path.join(HERE, "src", "wallpaper_dark.jpg")
    if os.path.exists(soar) and os.path.exists(dark):
        for suffix, (w, h) in (("land", (2048, 1024)), ("port", (1024, 2048))):
            out.append((f"wall_soar_{suffix}", soar_wallpaper(soar, dark, w, h)))
    return out


def soar_wallpaper(logo, bgpath, w, h):
    """Fond SOAR : topo sombre et logo de l'équipe en trait clair au centre."""
    bg = photo_wallpaper(bgpath, w, h, 0.9)
    raw = Image.open(logo).convert("RGBA")
    flat = Image.new("RGBA", raw.size, (255, 255, 255, 255))
    flat.alpha_composite(raw)
    gray = flat.convert("L")
    box = gray.point(lambda v: 255 if v < 235 else 0).getbbox() or (0, 0, raw.width, raw.height)
    gray = gray.crop(box)
    # Traits sombres -> blanc opaque, fond clair -> transparent.
    alpha = gray.point(lambda v: max(0, min(255, int((235 - v) * 1.4))))
    art = Image.new("RGBA", gray.size, (225, 232, 226, 0))
    art.putalpha(alpha.point(lambda v: int(v * 0.55)))
    k = (min(w, h) * 0.55) / max(art.width, art.height)
    art = art.resize((int(art.width * k), int(art.height * k)), Image.LANCZOS)
    bg.alpha_composite(art, ((w - art.width) // 2, (h - art.height) // 2))
    return bg


def blurred(img):
    """Variante floutée d'un fond (réglage « Flou du fond ») : 1024 px suffisent, le flou n'a pas de détail."""
    small = img.resize((img.width // 2, img.height // 2), Image.LANCZOS)
    return Image.eval(small.filter(ImageFilter.GaussianBlur(14)).convert("RGB"), lambda v: int(v * 0.85))


def crack(level, w, h, seed):
    """Écran fêlé (transparent) : impact, fissures rayonnantes, éclats ; niveau 3 = zone morte."""
    import math, random
    rnd = random.Random(seed)
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    s = min(w, h)
    impacts = [(rnd.uniform(0.55, 0.8) * w, rnd.uniform(0.15, 0.35) * h)]
    if level >= 2:
        impacts.append((rnd.uniform(0.15, 0.4) * w, rnd.uniform(0.6, 0.85) * h))
    if level >= 3:
        # Zone morte : bande noire et pixels morts.
        y0 = int(h * rnd.uniform(0.35, 0.55)); bh = int(h * 0.09)
        d.rectangle([0, y0, w, y0 + bh], fill=(0, 0, 0, 235))
        for _ in range(60):
            x = rnd.randrange(w); y = rnd.randrange(h)
            d.rectangle([x, y, x + 3, y + 3], fill=rnd.choice([(255, 0, 255, 200), (0, 255, 0, 200), (255, 255, 255, 220)]))
    n_rays = {1: 7, 2: 11, 3: 16}[level]
    for (cx, cy) in impacts:
        # Toile d'araignée : anneaux brisés autour de l'impact.
        for ring in range(1, level + 2):
            r = s * 0.035 * ring
            pts = []
            for k in range(13):
                a = k / 12 * 2 * math.pi
                rr_ = r * rnd.uniform(0.75, 1.25)
                pts.append((cx + rr_ * math.cos(a), cy + rr_ * math.sin(a)))
            for a_, b_ in zip(pts, pts[1:]):
                if rnd.random() < 0.8:
                    d.line([a_, b_], fill=(235, 240, 238, 150), width=2)
        for k in range(n_rays):
            a = rnd.uniform(0, 2 * math.pi)
            x, y = cx, cy
            length = s * rnd.uniform(0.25, 0.9) * (0.6 + 0.2 * level)
            step = s * 0.03
            travelled = 0
            while travelled < length:
                a += rnd.uniform(-0.12, 0.12)
                nx, ny = x + step * math.cos(a), y + step * math.sin(a)
                d.line([(x + 1, y + 1), (nx + 1, ny + 1)], fill=(0, 0, 0, 120), width=3)
                d.line([(x, y), (nx, ny)], fill=(240, 245, 243, 210), width=2)
                if rnd.random() < 0.12:
                    ba = a + rnd.choice([-1, 1]) * rnd.uniform(0.5, 1.1)
                    bx, by = x, y
                    for _ in range(rnd.randint(2, 6)):
                        ba += rnd.uniform(-0.3, 0.3)
                        ex, ey = bx + step * 0.8 * math.cos(ba), by + step * 0.8 * math.sin(ba)
                        d.line([(bx, by), (ex, ey)], fill=(235, 240, 238, 160), width=1)
                        bx, by = ex, ey
                x, y = nx, ny
                travelled += step
        # Éclats à l'impact
        d.ellipse([cx - s * 0.02, cy - s * 0.02, cx + s * 0.02, cy + s * 0.02], fill=(255, 255, 255, 120))
    return img.filter(ImageFilter.SMOOTH)


def soar_logo(path, size=512):
    """Logo de l'équipe SOAR (app Crédits) : recadré sur le dessin, posé sur un disque clair pour rester lisible sur fond sombre."""
    raw = Image.open(path).convert("RGBA")
    # Fond transparent ou blanc : on aplatit sur du blanc avant de chercher le dessin.
    src = Image.new("RGBA", raw.size, (255, 255, 255, 255))
    src.alpha_composite(raw)
    gray = src.convert("L")
    box = gray.point(lambda v: 255 if v < 235 else 0).getbbox() or (0, 0, src.width, src.height)
    art = src.crop(box)
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    ImageDraw.Draw(img).ellipse([4, 4, size - 4, size - 4], fill=(255, 255, 255, 255))
    inner = int(size * 0.68)
    k = inner / max(art.width, art.height)
    art = art.resize((max(1, int(art.width * k)), max(1, int(art.height * k))), Image.LANCZOS)
    img.alpha_composite(art, ((size - art.width) // 2, (size - art.height) // 2))
    # Hors du disque : transparent.
    mask = Image.new("L", (size, size), 0)
    ImageDraw.Draw(mask).ellipse([4, 4, size - 4, size - 4], fill=255)
    img.putalpha(Image.composite(img.getchannel("A"), mask, mask))
    return img


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
        # Fêlures de l'écran (dégâts du téléphone), mêmes dessins en portrait et paysage.
        for lvl in (1, 2, 3):
            port = crack(lvl, 512, 1024, 40 + lvl)
            convert(port, f"crack_{lvl}_port", tmp)
            convert(port.rotate(-90, expand=True), f"crack_{lvl}_land", tmp)
        if len(sys.argv) > 2 and sys.argv[2] == "cracks":
            return
        if len(sys.argv) > 2 and sys.argv[2] == "icons":
            for name, body in ICONS.items():
                convert(icon_png(name, body), name, tmp)
            return
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
            base, suffix = name.rsplit("_", 1)
            convert(blurred(img), f"{base}_blur_{suffix}", tmp)
        # Logo ATAK (faucon blanc) et viseur du mode photo.
        hawk = os.path.join(HERE, "src", "takos_hawk_white.png")
        if os.path.exists(hawk):
            convert(Image.open(hawk).convert("RGBA").resize((128, 128), Image.LANCZOS), "logo_atak", tmp)
        soar = os.path.join(HERE, "src", "logo_soar.png")
        if os.path.exists(soar):
            convert(soar_logo(soar), "logo_soar", tmp)
        overlay = os.path.join(HERE, "src", "camera_overlay.png")
        if os.path.exists(overlay):
            convert(Image.open(overlay).convert("RGBA"), "camera_overlay", tmp)
        # Aperçu PNG pour la revue (non packagé)
        land.save(os.path.join(HERE, "preview_phone_landscape.png"))
    print("ok", len(os.listdir(OUT)), "textures")


if __name__ == "__main__":
    main()
