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
    # Liaison ATAK : deux téléphones reliés par un câble, ondes NFC.
    "app_linkally": '<rect x="2.5" y="5" width="7" height="13" rx="1.5"/><rect x="14.5" y="5" width="7" height="13" rx="1.5"/><path d="M6 18v2.5h12V18"/><path d="M11 9.5a2 2 0 0 1 0 4M13 9.5a2 2 0 0 0 0 4"/>',
    # Discord (dessin maison, pas le logo) : bulle de salon avec dièse.
    "app_discord": '<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-9l-5 4v-4H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M10 7.5l-1 7M15 7.5l-1 7M7.5 9.5h9M7 12.5h9"/>',
    "app_resynch": '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8"/><path d="M4 3.5V8h4.5"/><path d="M4 13a8 8 0 0 0 14.3 4.9L20 16"/><path d="M20 20.5V16h-4.5"/>',
    "map_route": '<circle cx="6" cy="18" r="2"/><path d="M8 18h6a3.5 3.5 0 0 0 0-7H10a3.5 3.5 0 0 1 0-7h6"/><path d="M18 2.5l2 2-2 2"/>',
    "nav_straight": '<path d="M12 21V4M6 10l6-6 6 6"/>',
    "nav_left": '<path d="M16 21v-8a4 4 0 0 0-4-4H5M9 5L5 9l4 4"/>',
    "nav_right": '<path d="M8 21v-8a4 4 0 0 1 4-4h7M15 5l4 4-4 4"/>',
    "nav_slight_left": '<path d="M14 21v-7L8 6M7 11V5h6"/>',
    "nav_slight_right": '<path d="M10 21v-7l6-8M17 11V5h-6"/>',
    "nav_uturn": '<path d="M8 21V9a4 4 0 0 1 8 0v8M12 14l4 4 4-4"/>',
    "nav_arrive": '<path d="M6 21V4M6 4h11l-2.5 4L17 12H6"/>',
    "app_medical": '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 7.5v9M7.5 12h9"/>',
    "app_profile": '<rect x="2.5" y="5" width="19" height="14" rx="2"/><circle cx="8.5" cy="11" r="2.3"/><path d="M5 16.5c.8-1.8 2-2.6 3.5-2.6s2.7.8 3.5 2.6M14.5 10h4.5M14.5 13.5h3"/>',
    "app_livecam": '<rect x="2.5" y="6.5" width="13" height="11" rx="2"/><path d="M15.5 10.5l6-3.5v10l-6-3.5z"/><circle cx="6" cy="9.5" r=".6"/>',
    "app_reco": '<circle cx="6.5" cy="15" r="4"/><circle cx="17.5" cy="15" r="4"/><path d="M10.5 15h3M4 11.5l2-6.5h3l1 5M20 11.5l-2-6.5h-3l-1 5"/>',
    "app_alerts": '<path d="M12 3l9.5 17h-19z"/><path d="M12 10v4.5M12 17.5v.3"/>',
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
    "app_wanted": '<rect x="3" y="3" width="18" height="18" rx="1"/><circle cx="12" cy="10" r="3.2"/><path d="M6.5 19c.6-3 2.8-4.6 5.5-4.6s4.9 1.6 5.5 4.6"/><path d="M3 7h3M18 7h3"/>',
    "app_drone": '<circle cx="5" cy="5" r="3"/><circle cx="19" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="M7.5 7.5l3 3M16.5 7.5l-3 3M7.5 16.5l3-3M16.5 16.5l-3-3"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/>',
    # Icônes de drone choisies par le pilote (carte du téléphone, en-tête de l'app), nez vers le haut.
    "drone_quad": '<circle cx="5.5" cy="5.5" r="3"/><circle cx="18.5" cy="5.5" r="3"/><circle cx="5.5" cy="18.5" r="3"/><circle cx="18.5" cy="18.5" r="3"/><path d="M7.6 7.6l2.4 2.4M16.4 7.6L14 10M7.6 16.4l2.4-2.4M16.4 16.4L14 14"/><rect x="10" y="9" width="4" height="6" rx="1"/><path d="M12 9V7.5"/>',
    "drone_fixed": '<path d="M12 2.5c1 0 1.4 1.5 1.4 3.5v11l2.6 2v1.5l-4-1-4 1V19l2.6-2V6c0-2 .4-3.5 1.4-3.5z"/><path d="M10.6 8.5L2 12v1.8l8.6-1.8M13.4 8.5L22 12v1.8l-8.6-1.8"/>',
    "drone_hexa": '<circle cx="12" cy="12" r="2.6"/><circle cx="12" cy="3.6" r="2.2"/><circle cx="19.3" cy="7.8" r="2.2"/><circle cx="19.3" cy="16.2" r="2.2"/><circle cx="12" cy="20.4" r="2.2"/><circle cx="4.7" cy="16.2" r="2.2"/><circle cx="4.7" cy="7.8" r="2.2"/><path d="M12 9.4V5.8M14.3 10.7l3.1-1.8M14.3 13.3l3.1 1.8M12 14.6v3.6M9.7 13.3l-3.1 1.8M9.7 10.7L6.6 8.9"/>',
    "drone_nano": '<rect x="9.5" y="8" width="5" height="8" rx="2.5"/><circle cx="8" cy="8" r="2"/><circle cx="16" cy="8" r="2"/><circle cx="8" cy="16" r="2"/><circle cx="16" cy="16" r="2"/><path d="M12 8V4.5M10.5 4.5h3"/>',
    "drone_fpv": '<circle cx="5" cy="6" r="3.6"/><circle cx="19" cy="6" r="3.6"/><circle cx="5" cy="18" r="3.6"/><circle cx="19" cy="18" r="3.6"/><path d="M7.5 8.5L10 10M16.5 8.5L14 10M7.5 15.5L10 14M16.5 15.5L14 14"/><rect x="9.8" y="8.5" width="4.4" height="9" rx=".8"/><path d="M10.5 8.5L12 5.5l1.5 3"/>',
    "app_dronedetect": '<circle cx="7" cy="13" r="2.5"/><circle cx="17" cy="13" r="2.5"/><path d="M9.3 14l1.7 1h2l1.7-1M12 16v1.5"/><path d="M8 7.5a6 6 0 0 1 8 0M5 4.5a10 10 0 0 1 14 0"/><circle cx="12" cy="10" r=".6"/>',
    "app_aar": '<circle cx="13" cy="12" r="8"/><path d="M13 7v5l3 2"/><path d="M5 12H1.5M3 9.5L1.5 12 3 14.5"/>',
    "app_sse": '<path d="M6 3h8l4 4v6"/><path d="M6 3v18h6"/><circle cx="16" cy="17" r="3"/><path d="M18.2 19.2L21 22"/>',
    "app_explo": '<path d="M9 21h6v-8H9z"/><path d="M12 13V9"/><path d="M12 9c0-3 3-3 4-5"/><path d="M17 2l.7 1.6L19.3 4l-1.6.7L17 6.3l-.7-1.6L14.7 4l1.6-.4z"/>',
    "app_breach": '<rect x="5" y="3" width="11" height="18" rx="1"/><circle cx="13" cy="12" r="1"/><path d="M19 8l2-2M19 12h3M19 16l2 2"/>',
    "app_sniper": '<circle cx="12" cy="12" r="8"/><path d="M12 2v6M12 16v6M2 12h6M16 12h6"/><circle cx="12" cy="12" r="1"/>',
    "app_jtac": '<path d="M3 15l7-2 4-8 2 1-2 7 6 1v2l-6 1 2 7-2 1-4-8-7-2z"/><path d="M3 21l5-5"/>',
    "app_bda": '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
    "app_reports": '<rect x="5" y="3.5" width="14" height="18" rx="1.5"/><path d="M9 3.5V2h6v1.5"/><path d="M8.5 9h7M8.5 12.5h7M8.5 16h4"/><path d="M14.5 17l1.3 1.3 2.7-2.8"/>',
    "app_photos": '<path d="M3 7h4l2-2.5h6L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
    "app_briefing": '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 21h8M7 12l3-3 2 2 4-4"/>',
    "app_status": '<path d="M3 20h18M6 20v-6M10 20V9M14 20v-8M18 20V5"/>',
    "app_settings": '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>',
    "ui_back": '<path d="M15 5l-7 7 7 7"/>',
    "ui_apps": '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/>',
    "ui_gallery": '<rect x="6" y="3" width="15" height="15" rx="2"/><path d="M3 7v12a2 2 0 0 0 2 2h12"/><path d="M8.5 15l3.5-4.5 2.5 3 1.8-2 2.7 3.5z" fill="#fff"/>',
    "ui_folder": '<path d="M3 6.5A1.5 1.5 0 0 1 4.5 5h5l2 2.5h8A1.5 1.5 0 0 1 21 9v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z" fill="#fff"/>',
    "ui_photocam": '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h3l1.5-2.5h6L16.5 7h3A1.5 1.5 0 0 1 21 8.5v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z" fill="#fff"/><circle cx="12" cy="13.5" r="3.6" stroke="#000" stroke-width="2"/>',
    "ui_minus": '<path d="M6 12h12" stroke-width="2.4"/>',
    "ui_clip": '<path d="M15.5 6.5v9a3.5 3.5 0 0 1-7 0V5.5a2.5 2.5 0 0 1 5 0v9.5a1.5 1.5 0 0 1-3 0V7" stroke-width="2"/>',
    "ui_home": '<path d="M3 11.5L12 4l9 7.5V20h-6v-5.5H9V20H3z" fill="#fff"/>',
    "ui_check": '<path d="M6.5 12.5l3.8 3.8 7.2-8" stroke-width="2.8"/>',
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
    "ui_bell": '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
    "ui_comspec_link": '<path d="M12 2.5l8.2 4.75v9.5L12 21.5l-8.2-4.75v-9.5z"/><path d="M10.4 13.6a2.6 2.6 0 0 0 3.7 0l1.9-1.9a2.6 2.6 0 0 0-3.7-3.7l-.6.6"/><path d="M13.6 10.4a2.6 2.6 0 0 0-3.7 0l-1.9 1.9a2.6 2.6 0 0 0 3.7 3.7l.6-.6"/>',
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


# --- Écran abîmé : dessins procéduraux (graine fixe, rendu identique à chaque génération) ---
# Les fêlures sont dessinées en portrait (512x1024) puis tournées pour le paysage ; même variante =
# même point d'impact à tous les niveaux, la toile s'agrandit avec les dégâts.
DMG_SS = 2  # suréchantillonnage (traits lissés)


def _impact(variant, w, h):
    """Points d'impact d'une variante (portrait) : le premier sert à tous les niveaux."""
    import random
    rnd = random.Random(900 + variant * 31)
    pts = [(rnd.uniform(0.25, 0.8) * w, rnd.uniform(0.15, 0.45) * h)]
    pts.append((rnd.uniform(0.15, 0.85) * w, rnd.uniform(0.6, 0.88) * h))
    pts.append((rnd.uniform(0.1, 0.9) * w, rnd.uniform(0.05, 0.95) * h))
    return pts


def _web(rnd, cx, cy, n_rays, rings, reach, jitter=0.18):
    """Toile d'araignée : rayons brisés (listes de points) et anneaux reliant les rayons voisins."""
    import math
    base = sorted(rnd.uniform(0, 2 * math.pi) for _ in range(n_rays))
    rays = []
    for a in base:
        pts = [(cx, cy)]
        steps = len(rings)
        for k, r in enumerate(rings):
            a2 = a + rnd.uniform(-jitter, jitter) * (1 - k / (steps + 1))
            rr_ = r * rnd.uniform(0.85, 1.15)
            pts.append((cx + rr_ * math.cos(a2), cy + rr_ * math.sin(a2)))
        rays.append(pts)
    return rays


def _crack_line(d, pts, width, alpha):
    """Fissure : ombre décalée sombre puis arête claire (reflet du verre)."""
    width *= DMG_SS
    d.line([(x + width * 0.5, y + width * 0.5) for x, y in pts], fill=(0, 0, 0, int(alpha * 0.55)), width=width + DMG_SS, joint="curve")
    d.line(pts, fill=(236, 242, 240, alpha), width=width, joint="curve")


def _walk(rnd, x, y, a, step, n, wobble):
    import math
    pts = [(x, y)]
    for _ in range(n):
        a += rnd.uniform(-wobble, wobble)
        x += step * math.cos(a); y += step * math.sin(a)
        pts.append((x, y))
    return pts


def ink_bleed(level, variant, w, h):
    """Fuite d'encre LCD : taches noires à franges violettes qui s'étalent depuis l'impact (portrait)."""
    import random
    rnd = random.Random(5000 + variant * 97 + level)
    W, H = w * DMG_SS, h * DMG_SS
    s = min(W, H)
    mask = Image.new("L", (W, H), 0)
    dm = ImageDraw.Draw(mask)
    imps = _impact(variant, W, H)[: {2: 1, 3: 2}.get(level, 1)]
    for i, (cx, cy) in enumerate(imps):
        size = s * (0.16 if level <= 2 else 0.34) * (1 if i == 0 else 0.6)
        # Amas de disques autour de l'impact, plus quelques coulures qui descendent.
        for _ in range(28 if level >= 3 else 14):
            r = size * rnd.uniform(0.15, 0.55)
            ox, oy = rnd.gauss(0, size * 0.55), rnd.gauss(0, size * 0.55)
            dm.ellipse([cx + ox - r, cy + oy - r, cx + ox + r, cy + oy + r], fill=255)
        for _ in range(3 if level <= 2 else 7):
            pts = _walk(rnd, cx, cy, rnd.uniform(1.2, 1.95), s * 0.02, rnd.randint(8, 22 if level >= 3 else 12), 0.35)
            dm.line(pts, fill=255, width=int(s * rnd.uniform(0.012, 0.035)), joint="curve")
    if level >= 3:
        # Grande zone morte : l'encre coule le long d'un bord de l'écran.
        x0 = rnd.choice([0.0, 1.0]) * W
        y = H * rnd.uniform(0.0, 0.3); y1 = H * rnd.uniform(0.7, 1.0)
        while y < y1:
            r = s * rnd.uniform(0.12, 0.3)
            dm.ellipse([x0 - r * 1.4, y - r, x0 + r * 1.4, y + r], fill=255)
            y += r * 0.8
    core = mask.filter(ImageFilter.GaussianBlur(s * 0.012))
    fringe = mask.filter(ImageFilter.GaussianBlur(s * 0.04))
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    # Frange violette / magenta, puis cœur noir opaque.
    tint = rnd.choice([(96, 18, 150), (130, 20, 120), (60, 30, 160)])
    halo = Image.new("RGBA", (W, H), tint + (0,))
    halo.putalpha(fringe.point(lambda v: min(255, int(v * 1.6))))
    img.alpha_composite(halo)
    edge = Image.new("RGBA", (W, H), (20, 160, 190, 0))
    edge.putalpha(Image.eval(Image.composite(fringe, Image.new("L", (W, H), 0), core.point(lambda v: 255 if 20 < v < 120 else 0)), lambda v: int(v * 0.5)))
    img.alpha_composite(edge)
    black = Image.new("RGBA", (W, H), (4, 2, 8, 0))
    black.putalpha(core.point(lambda v: 0 if v < 70 else min(250, int((v - 70) * 2.2))))
    img.alpha_composite(black)
    return img.resize((w, h), Image.LANCZOS)


def glass_crack(level, variant, w, h):
    """Verre fêlé (transparent, portrait) : toile d'araignée depuis l'impact, fissures fines, reflet ;
    niveau 2+ : taches d'encre ; niveau 3 : deuxième impact, zone morte et pixels morts."""
    import math, random
    rnd = random.Random(100 + variant * 13 + level * 1000)
    W, H = w * DMG_SS, h * DMG_SS
    s = min(W, H)
    diag = math.hypot(W, H)
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    if level >= 2:
        img.alpha_composite(ink_bleed(level, variant, w, h).resize((W, H), Image.LANCZOS))
    d = ImageDraw.Draw(img)
    imps = _impact(variant, W, H)[: {1: 1, 2: 2, 3: 3}[level]]
    for i, (cx, cy) in enumerate(imps):
        main_ = i == 0
        reach = diag * ({1: 0.35, 2: 0.6, 3: 1.0}[level] if main_ else 0.22 * level)
        n_rays = ({1: 9, 2: 14, 3: 20}[level] if main_ else 7 + level)
        n_rings = {1: 4, 2: 6, 3: 8}[level] if main_ else 3
        rings = [reach * (k / n_rings) ** 1.6 for k in range(1, n_rings + 1)]
        rays = _web(rnd, cx, cy, n_rays, rings, reach)
        # Anneaux : segments entre rayons voisins, plus nombreux près de l'impact.
        for k in range(1, n_rings + 1):
            for j in range(n_rays):
                if rnd.random() < 0.85 - k / n_rings * 0.55:
                    p, q = rays[j][k], rays[(j + 1) % n_rays][k]
                    mx, my = (p[0] + q[0]) / 2, (p[1] + q[1]) / 2
                    bend = rnd.uniform(-0.08, 0.08)
                    mid = (mx + (mx - cx) * bend, my + (my - cy) * bend)
                    _crack_line(d, [p, mid, q], 2 if k < 3 else 1, rnd.randint(110, 180))
        # Rayons : épais près de l'impact, s'affinent ; certains s'arrêtent tôt.
        for pts in rays:
            stop = len(pts) if rnd.random() < 0.7 else rnd.randint(2, len(pts))
            for k in range(1, stop):
                _crack_line(d, [pts[k - 1], pts[k]], max(1, 4 - k), rnd.randint(170, 230))
            # Fissures secondaires fines qui partent des rayons.
            for k in range(1, stop):
                if rnd.random() < 0.35:
                    a = math.atan2(pts[k][1] - cy, pts[k][0] - cx) + rnd.choice([-1, 1]) * rnd.uniform(0.6, 1.3)
                    sub = _walk(rnd, pts[k][0], pts[k][1], a, s * 0.018, rnd.randint(3, 9), 0.4)
                    d.line(sub, fill=(232, 238, 236, rnd.randint(80, 140)), width=DMG_SS, joint="curve")
        # Éclats autour de l'impact : petits triangles clairs, centre blanchi.
        for _ in range(10 + 6 * level if main_ else 6):
            a = rnd.uniform(0, 2 * math.pi); r = s * rnd.uniform(0.005, 0.05)
            px, py = cx + r * math.cos(a), cy + r * math.sin(a)
            tri = [(px, py), (px + rnd.uniform(-1, 1) * s * 0.02, py + rnd.uniform(-1, 1) * s * 0.02), (px + rnd.uniform(-1, 1) * s * 0.02, py + rnd.uniform(-1, 1) * s * 0.02)]
            d.polygon(tri, fill=(240, 246, 244, rnd.randint(40, 110)))
        r0 = s * (0.012 + 0.006 * level)
        d.ellipse([cx - r0, cy - r0, cx + r0, cy + r0], fill=(250, 252, 252, 150))
    # Reflet du verre : bande diagonale très légère.
    shine = Image.new("L", (W, H), 0)
    ImageDraw.Draw(shine).polygon([(W * 0.05, 0), (W * 0.32, 0), (W * 0.95, H), (W * 0.68, H)], fill=26 + 6 * level)
    shine = shine.filter(ImageFilter.GaussianBlur(s * 0.08))
    sh = Image.new("RGBA", (W, H), (255, 255, 255, 0)); sh.putalpha(shine)
    img.alpha_composite(sh)
    if level >= 3:
        # Bande morte et pixels morts.
        d = ImageDraw.Draw(img)
        y0 = int(H * rnd.uniform(0.5, 0.65)); bh = int(H * 0.06)
        d.rectangle([0, y0, W, y0 + bh], fill=(0, 0, 0, 230))
        for _ in range(90):
            x = rnd.randrange(W); y = rnd.randrange(H); k = rnd.choice([4, 6, 8])
            d.rectangle([x, y, x + k, y + k], fill=rnd.choice([(255, 0, 255, 210), (0, 255, 0, 210), (255, 255, 255, 220), (0, 200, 255, 210)]))
    return img.resize((w, h), Image.LANCZOS)


GLITCH_COLORS = [(255, 0, 200), (0, 255, 255), (0, 255, 60), (255, 255, 0), (255, 255, 255), (255, 30, 30), (40, 60, 255), (170, 0, 255)]


def dead_lines(variant, frame, w, h):
    """Lignes mortes LCD : colonnes colorées verticales (dans le sens de l'écran) et blocs de compression.
    Les lignes principales tiennent à la variante, les artefacts changent avec la trame (scintillement)."""
    import random
    base = random.Random(7000 + variant * 53)
    fr = random.Random(7100 + variant * 53 + frame * 7)
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    # Faisceaux de colonnes : quelques groupes arc-en-ciel et des lignes isolées.
    for _ in range(base.randint(3, 5)):
        x = base.randrange(w); n = base.randint(4, 14); cols = base.sample(GLITCH_COLORS, 4)
        for k in range(n):
            if fr.random() < 0.15:
                continue
            c = cols[k % len(cols)]
            wd = base.choice([1, 1, 2, 3])
            y0 = 0 if base.random() < 0.7 else base.randrange(h // 2)
            d.rectangle([x, y0, x + wd - 1, h], fill=c + (fr.randint(170, 240),))
            x += wd + base.choice([0, 0, 1, 2])
    for _ in range(base.randint(8, 16)):
        x = base.randrange(w); c = base.choice(GLITCH_COLORS)
        if fr.random() < 0.25:
            continue
        d.rectangle([x, 0, x + base.choice([0, 0, 1]), h], fill=c + (fr.randint(120, 230),))
    # Large bande colorée dégradée (pilote de colonne HS).
    if base.random() < 0.7:
        x = base.randrange(int(w * 0.1), int(w * 0.85)); bw = base.randint(int(w * 0.02), int(w * 0.07))
        c = base.choice(GLITCH_COLORS)
        d.rectangle([x, 0, x + bw, h], fill=c + (fr.randint(70, 130),))
    # Blocs d'artefacts (macroblocs) groupés, changent à chaque trame.
    for _ in range(fr.randint(2, 4)):
        cx, cy = fr.randrange(w), fr.randrange(h)
        bs = fr.choice([8, 12, 16, 24])
        for _ in range(fr.randint(10, 40)):
            x = cx + fr.randint(-8, 8) * bs; y = cy + fr.randint(-4, 4) * bs
            c = fr.choice(GLITCH_COLORS + [(0, 0, 0)] * 3)
            d.rectangle([x, y, x + bs * fr.randint(1, 3) - 1, y + bs - 1], fill=c + (fr.randint(150, 235),))
    # Déchirures horizontales : fines bandes décalées.
    for _ in range(fr.randint(1, 3)):
        y = fr.randrange(h); th = fr.randint(2, 10)
        d.rectangle([0, y, w, y + th], fill=fr.choice(GLITCH_COLORS) + (fr.randint(50, 110),))
    return img


def shattered(variant, w, h):
    """Écran détruit (portrait) : verre noir presque opaque, éclats en toile depuis l'impact, arêtes brillantes."""
    import math, random
    rnd = random.Random(9000 + variant * 71)
    W, H = w * DMG_SS, h * DMG_SS
    s = min(W, H)
    diag = math.hypot(W, H)
    img = Image.new("RGBA", (W, H), (6, 7, 8, 248))
    d = ImageDraw.Draw(img)
    for idx, (cx, cy) in enumerate(_impact(variant, W, H)[:2]):
        n_rays = 26 if idx == 0 else 14
        n_rings = 10 if idx == 0 else 5
        reach = diag * (1.1 if idx == 0 else 0.35)
        rings = [reach * (k / n_rings) ** 1.7 for k in range(1, n_rings + 1)]
        rays = _web(rnd, cx, cy, n_rays, rings, reach, 0.12)
        rays.sort(key=lambda p: math.atan2(p[-1][1] - cy, p[-1][0] - cx))
        for j in range(n_rays):
            A, B = rays[j], rays[(j + 1) % n_rays]
            for k in range(n_rings):
                cell = [A[k], B[k], B[k + 1], A[k + 1]]
                # Teinte de l'éclat : dépend de son orientation (lumière en haut à gauche) + bruit.
                ang = math.atan2((A[k + 1][1] + B[k + 1][1]) / 2 - cy, (A[k + 1][0] + B[k + 1][0]) / 2 - cx)
                g = int(10 + 18 * max(0, math.cos(ang + 2.3)) + rnd.uniform(-6, 10))
                if idx == 0 or rnd.random() < 0.6:
                    d.polygon(cell, fill=(g, g + 1, g + 3, 250))
                # Arêtes : reflet clair sur un côté, sombre sur l'autre.
                d.line([A[k], A[k + 1]], fill=(150, 160, 165, rnd.randint(90, 200)), width=2 if k < 3 else 1)
                if rnd.random() < 0.7:
                    d.line([A[k + 1], B[k + 1]], fill=(120, 130, 135, rnd.randint(60, 160)), width=1)
                # Éclats secondaires dans la cellule.
                if rnd.random() < 0.3:
                    p = rnd.choice(cell); q = rnd.choice(cell)
                    d.line([p, q], fill=(110, 118, 122, rnd.randint(50, 120)), width=1)
        # Cratère de l'impact : poudre de verre claire.
        for _ in range(140):
            a = rnd.uniform(0, 2 * math.pi); r = abs(rnd.gauss(0, s * 0.03))
            px, py = cx + r * math.cos(a), cy + r * math.sin(a); k = rnd.uniform(1, 4)
            d.ellipse([px - k, py - k, px + k, py + k], fill=(200, 210, 215, rnd.randint(60, 180)))
    # Reflets spéculaires sur quelques éclats.
    glint = Image.new("L", (W, H), 0)
    gd = ImageDraw.Draw(glint)
    for _ in range(5):
        x, y = rnd.uniform(0, W), rnd.uniform(0, H); r = s * rnd.uniform(0.04, 0.12)
        gd.ellipse([x - r, y - r * 0.4, x + r, y + r * 0.4], fill=rnd.randint(25, 55))
    gd.polygon([(W * 0.0, H * 0.1), (W * 0.25, 0), (W, H * 0.75), (W, H * 0.95)], fill=22)
    glint = glint.filter(ImageFilter.GaussianBlur(s * 0.05))
    gl = Image.new("RGBA", (W, H), (210, 225, 235, 0)); gl.putalpha(glint)
    img.alpha_composite(gl)
    return img.resize((w, h), Image.LANCZOS)


DMG_CRACK_VARIANTS = 3
DMG_GLITCH_VARIANTS = 2
DMG_GLITCH_FRAMES = 3
DMG_SHATTER_VARIANTS = 2


def damage_overlays(tmp):
    """Calques de dégâts (voir fn_deviceOverlay.sqf) :
    crack_<niveau 1-3>_<variante>_<port|land>, glitch_<variante>_<trame>_<port|land>, shatter_<variante>_<port|land>."""
    for v in range(DMG_CRACK_VARIANTS):
        for lvl in (1, 2, 3):
            port = glass_crack(lvl, v, 512, 1024)
            convert(port, f"crack_{lvl}_{v}_port", tmp)
            convert(port.rotate(-90, expand=True), f"crack_{lvl}_{v}_land", tmp)
    for v in range(DMG_GLITCH_VARIANTS):
        for f in range(DMG_GLITCH_FRAMES):
            # Lignes verticales dans le sens de l'écran : dessinées pour chaque orientation.
            convert(dead_lines(v, f, 512, 1024), f"glitch_{v}_{f}_port", tmp)
            convert(dead_lines(v, f, 1024, 512), f"glitch_{v}_{f}_land", tmp)
    for v in range(DMG_SHATTER_VARIANTS):
        port = shattered(v, 512, 1024)
        convert(port, f"shatter_{v}_port", tmp)
        convert(port.rotate(-90, expand=True), f"shatter_{v}_land", tmp)
    # Anciennes fêlures (une seule variante) : remplacées.
    for lvl in (1, 2, 3):
        for o in ("port", "land"):
            old = os.path.join(OUT, f"crack_{lvl}_{o}.paa")
            if os.path.exists(old):
                os.remove(old)


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


def frs_bar(w=1024, h=128):
    """Barre du bas de l'app FRS : bandeau violet avec un creux arrondi au centre (le bouton rond s'y loge)."""
    k = 4
    W, H = w * k, h * k
    img = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    top = int(H * 0.30)
    dip = int(H * 0.62)
    import math
    pts = []
    for i in range(0, W + 1, 4):
        x = i / W
        y = top
        if 0.34 < x < 0.66:
            t = (x - 0.34) / 0.32
            y = top + (dip - top) * (0.5 - 0.5 * math.cos(2 * math.pi * t))
        pts.append((i, y))
    pts += [(W, H), (0, H)]
    ImageDraw.Draw(img).polygon(pts, fill=(255, 255, 255, 255))
    return img.resize((w, h), Image.LANCZOS)


def disc(size=128):
    k = 4
    img = Image.new("RGBA", (size * k, size * k), (0, 0, 0, 0))
    ImageDraw.Draw(img).ellipse((2 * k, 2 * k, (size - 2) * k, (size - 2) * k), fill=(255, 255, 255, 255))
    return img.resize((size, size), Image.LANCZOS)


# --- Alimentation : écran de démarrage, batterie vide, batterie ATAK (item d'inventaire) ---
FONT_BOLD = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"


def _font(size):
    from PIL import ImageFont
    try:
        return ImageFont.truetype(FONT_BOLD, size)
    except OSError:
        return ImageFont.load_default()


def _emblem(d, cx, cy, r, k):
    """Emblème COMSPEC ATAK : hexagone, rose des vents et réticule (dessin original)."""
    import math
    green = (92, 199, 107, 255)
    hexa = [(cx + r * math.cos(math.radians(a)), cy + r * math.sin(math.radians(a))) for a in range(-90, 270, 60)]
    d.line(hexa + [hexa[0]], fill=green, width=int(5 * k), joint="curve")
    inner = [(cx + r * 0.84 * math.cos(math.radians(a)), cy + r * 0.84 * math.sin(math.radians(a))) for a in range(-90, 270, 60)]
    d.line(inner + [inner[0]], fill=(92, 199, 107, 90), width=int(2 * k), joint="curve")
    # Rose des vents : pointe nord pleine, trois autres creuses.
    for a in (0, 90, 180, 270):
        t = math.radians(a - 90)
        tip = (cx + r * 0.62 * math.cos(t), cy + r * 0.62 * math.sin(t))
        l = (cx + r * 0.13 * math.cos(t - math.pi / 2), cy + r * 0.13 * math.sin(t - math.pi / 2))
        rr_ = (cx + r * 0.13 * math.cos(t + math.pi / 2), cy + r * 0.13 * math.sin(t + math.pi / 2))
        if a == 0:
            d.polygon([tip, l, (cx, cy), rr_], fill=(235, 242, 238, 255))
        else:
            d.line([tip, l, (cx, cy), rr_, tip], fill=(235, 242, 238, 200), width=int(2.5 * k), joint="curve")
    d.ellipse([cx - r * 0.3, cy - r * 0.3, cx + r * 0.3, cy + r * 0.3], outline=green, width=int(3 * k))
    d.ellipse([cx - r * 0.05, cy - r * 0.05, cx + r * 0.05, cy + r * 0.05], fill=green)


def boot_screen(w, h):
    """Écran de démarrage (portrait ou paysage, dessiné dans chaque sens pour que le logo reste droit).
    Le bas (sous 70 %) reste libre : barre de progression, étape et version y sont posées par fn_deviceOverlay."""
    import math
    k = 2
    W, H = w * k, h * k
    img = Image.new("RGBA", (W, H), (4, 7, 6, 255))
    # Halo vert très léger derrière l'emblème.
    halo = Image.new("L", (W, H), 0)
    s = min(W, H)
    cy = H * (0.36 if h > w else 0.34)
    ImageDraw.Draw(halo).ellipse([W / 2 - s * 0.42, cy - s * 0.42, W / 2 + s * 0.42, cy + s * 0.42], fill=60)
    halo = halo.filter(ImageFilter.GaussianBlur(s * 0.12))
    glow = Image.new("RGBA", (W, H), (40, 120, 70, 0)); glow.putalpha(halo)
    img.alpha_composite(glow)
    d = ImageDraw.Draw(img)
    # Trame de carroyage discrète.
    step = int(s * 0.08)
    for x in range(0, W, step):
        d.line([(x, 0), (x, H)], fill=(20, 40, 30, 70), width=1)
    for y in range(0, H, step):
        d.line([(0, y), (W, y)], fill=(20, 40, 30, 70), width=1)
    r = s * (0.2 if h > w else 0.22)
    _emblem(d, W / 2, cy, r, k * s / 1024)
    f1 = _font(int(s * 0.085)); f2 = _font(int(s * 0.06))
    ty = cy + r * 1.35
    for txt, f, col, sp in (("COMSPEC", f1, (235, 242, 238, 255), 0.018), ("ATAK", f2, (92, 199, 107, 255), 0.04)):
        widths = [d.textlength(c, font=f) for c in txt]
        total = sum(widths) + s * sp * (len(txt) - 1)
        x = W / 2 - total / 2
        for c, cw in zip(txt, widths):
            d.text((x, ty), c, font=f, fill=col)
            x += cw + s * sp
        ty += f.size * 1.25
    return img.resize((w, h), Image.LANCZOS)


def battery_empty_icon(size=256):
    """Batterie vide : contour blanc, fond rouge au ras du bord, éclair barré."""
    k = 4
    S = size * k
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    x0, y0, x1, y1 = S * 0.12, S * 0.3, S * 0.8, S * 0.7
    d.rounded_rectangle([x0, y0, x1, y1], radius=S * 0.05, outline=(235, 242, 238, 255), width=int(S * 0.035))
    d.rounded_rectangle([x1 + S * 0.02, S * 0.42, x1 + S * 0.08, S * 0.58], radius=S * 0.015, fill=(235, 242, 238, 255))
    m = S * 0.06
    d.rectangle([x0 + m, y0 + m, x0 + m + S * 0.05, y1 - m], fill=(229, 72, 58, 255))
    bolt = [(0.5, 0.36), (0.4, 0.52), (0.48, 0.52), (0.44, 0.64), (0.56, 0.47), (0.48, 0.47), (0.52, 0.36)]
    d.polygon([(x * S, y * S) for x, y in bolt], fill=(235, 242, 238, 160))
    d.line([(S * 0.3, S * 0.78), (S * 0.66, S * 0.22)], fill=(229, 72, 58, 255), width=int(S * 0.03))
    return img.resize((size, size), Image.LANCZOS)


def battery_item(size=256):
    """Image d'inventaire de la batterie ATAK : bloc lithium olive, étiquette verte, éclair, bornes."""
    k = 4
    S = size * k
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    # Ombre portée
    sh = Image.new("L", (S, S), 0)
    ImageDraw.Draw(sh).rounded_rectangle([S * 0.24, S * 0.2, S * 0.8, S * 0.92], radius=S * 0.06, fill=150)
    sh = sh.filter(ImageFilter.GaussianBlur(S * 0.025))
    shadow = Image.new("RGBA", (S, S), (0, 0, 0, 0)); shadow.putalpha(sh)
    img.alpha_composite(shadow)
    d = ImageDraw.Draw(img)
    d.rounded_rectangle([S * 0.2, S * 0.16, S * 0.76, S * 0.88], radius=S * 0.06, fill=(58, 66, 46, 255), outline=(24, 28, 20, 255), width=int(S * 0.012))
    # Reflet latéral
    d.rounded_rectangle([S * 0.23, S * 0.19, S * 0.3, S * 0.85], radius=S * 0.03, fill=(86, 96, 70, 255))
    # Bornes
    for x in (0.33, 0.58):
        d.rounded_rectangle([S * x, S * 0.09, S * (x + 0.08), S * 0.17], radius=S * 0.01, fill=(170, 172, 168, 255), outline=(60, 60, 60, 255), width=int(S * 0.006))
    # Étiquette
    d.rectangle([S * 0.2, S * 0.4, S * 0.76, S * 0.66], fill=(92, 199, 107, 255))
    bolt = [(0.5, 0.42), (0.42, 0.54), (0.49, 0.54), (0.45, 0.645), (0.56, 0.51), (0.49, 0.51), (0.53, 0.42)]
    d.polygon([(x * S, y * S) for x, y in bolt], fill=(16, 22, 16, 255))
    f = _font(int(S * 0.07))
    d.text((S * 0.25, S * 0.72), "ATAK", font=f, fill=(220, 226, 214, 255))
    d.text((S * 0.25, S * 0.79), "3.85V", font=_font(int(S * 0.05)), fill=(160, 170, 150, 255))
    return img.resize((size, size), Image.LANCZOS)


def power_assets(tmp):
    """Démarrage (boot_<port|land>), batterie vide (power_bat_empty) et item batterie (item_battery)."""
    convert(boot_screen(512, 1024).convert("RGB"), "boot_port", tmp)
    convert(boot_screen(1024, 512).convert("RGB"), "boot_land", tmp)
    convert(battery_empty_icon(), "power_bat_empty", tmp)
    convert(battery_item(), "item_battery", tmp)


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
        # Écran abîmé (fêlures, encre, lignes mortes, verre brisé) ; « cracks » ne régénère que ceux-là.
        damage_overlays(tmp)
        if len(sys.argv) > 2 and sys.argv[2] == "cracks":
            return
        convert(frs_bar(), "frs_bar", tmp)
        convert(disc(), "ui_disc", tmp)
        power_assets(tmp)
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
