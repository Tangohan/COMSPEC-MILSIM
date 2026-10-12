#!/usr/bin/env python3
"""
Génère les textures du terminal (coque téléphone + icônes) en PNG puis PAA.
Dessins originaux COMSPEC (aucune ressource BCE, cTab ou Iceman).

Usage : python3 gen_assets.py <hemtt>
Sortie : Sources/addons/main/data/*.paa
Dépendances : Pillow, CairoSVG, HEMTT (hemtt utils paa convert).
"""
import io, os, subprocess, sys, tempfile
from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont, ImageOps
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
    "app_interteam": '<circle cx="5.5" cy="6" r="2.5"/><circle cx="18.5" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 6h8M7 8.2l3.6 7.6M17 8.2l-3.6 7.6"/><path d="M10 11.5h4" stroke-dasharray="1.5 1.5"/>',
    # Accessoires : téléphone vu de dos, antenne vissée en haut, plaque au centre, fixations latérales.
    "app_accessories": '<rect x="7" y="6" width="10" height="16" rx="2"/><path d="M14.5 6V2"/><circle cx="14.5" cy="2" r="0.6" fill="#fff"/><rect x="9.5" y="11" width="5" height="6" rx="1"/><path d="M3 10h4M3 14h4M17 10h4M17 18h4"/>',
    "app_screentime": '<rect x="6" y="2.5" width="12" height="19" rx="2"/><circle cx="12" cy="12" r="3.8"/><path d="M12 9.8V12l1.6 1.2M10.5 19h3"/>',
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
    "app_datalink": '<path d="M8 2v4M12 2v4"/><rect x="6" y="6" width="8" height="6" rx="1.2"/><path d="M10 12v3.5a3.5 3.5 0 0 0 3.5 3.5H15"/><rect x="15" y="16" width="6" height="6" rx="1"/><path d="M18 9v4M16.5 11.5L18 13l1.5-1.5"/>',
    "app_sse": '<path d="M6 3h8l4 4v6"/><path d="M6 3v18h6"/><circle cx="16" cy="17" r="3"/><path d="M18.2 19.2L21 22"/>',
    "app_explo": '<path d="M9 21h6v-8H9z"/><path d="M12 13V9"/><path d="M12 9c0-3 3-3 4-5"/><path d="M17 2l.7 1.6L19.3 4l-1.6.7L17 6.3l-.7-1.6L14.7 4l1.6-.4z"/>',
    "app_breach": '<rect x="5" y="3" width="11" height="18" rx="1"/><circle cx="13" cy="12" r="1"/><path d="M19 8l2-2M19 12h3M19 16l2 2"/>',
    "app_sniper": '<circle cx="12" cy="12" r="8"/><path d="M12 2v6M12 16v6M2 12h6M16 12h6"/><circle cx="12" cy="12" r="1"/>',
    "app_jtac": '<path d="M3 15l7-2 4-8 2 1-2 7 6 1v2l-6 1 2 7-2 1-4-8-7-2z"/><path d="M3 21l5-5"/>',
    "app_bda": '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
    "app_reports": '<rect x="5" y="3.5" width="14" height="18" rx="1.5"/><path d="M9 3.5V2h6v1.5"/><path d="M8.5 9h7M8.5 12.5h7M8.5 16h4"/><path d="M14.5 17l1.3 1.3 2.7-2.8"/>',
    "app_photos": '<path d="M3 7h4l2-2.5h6L17 7h4v12H3z"/><circle cx="12" cy="13" r="3.5"/>',
    # Galerie : pile de photos, paysage (montagne et soleil) sur celle du dessus.
    "app_gallery": '<rect x="7" y="3" width="14" height="14" rx="1.5"/><path d="M3.5 7v12.5a1.5 1.5 0 0 0 1.5 1.5h12.5"/><path d="M9 14.5l3.2-4 2.4 2.8 1.6-1.8 2.8 3"/><circle cx="17" cy="7.3" r="1.3"/>',
    "app_briefing": '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 21h8M7 12l3-3 2 2 4-4"/>',
    # Plan (carte de briefing) : cadre de carte, zone ennemie, axe en tirets vers l'objectif, bâtiment nettoyé.
    "app_plan": '<rect x="3" y="3" width="18" height="18" rx="1.5"/><path d="M6 6.5h5.5v4H6z"/><path d="M6.5 17.5c3-.5 6-2.5 8.5-6.5" stroke-dasharray="2 1.7"/><path d="M12.6 11l2.6-.4.4 2.7"/><path d="M14.5 17l1.6 1.6 3-3.3"/>',
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
    # Messagerie : canal (#), aide, tri (plus anciens / plus récents en haut), vider l'affichage, supprimer un canal.
    "ui_hash": '<path d="M9.5 3.5L7.5 20.5M16.5 3.5l-2 17M4 8.5h16.5M3.5 15.5H20" stroke-width="2"/>',
    "ui_help": '<circle cx="12" cy="12" r="9.2"/><path d="M9.3 9.4a2.8 2.8 0 1 1 3.9 2.6c-.8.4-1.2 1-1.2 1.9v.6" stroke-width="2"/><circle cx="12" cy="17.4" r="1.1" fill="#fff" stroke="none"/>',
    "ui_sort_up": '<path d="M8 20V5M4.5 8.5L8 5l3.5 3.5" stroke-width="2"/><path d="M14 7h6M14 12h4.5M14 17h3"/>',
    "ui_sort_down": '<path d="M8 4v15M4.5 15.5L8 19l3.5-3.5" stroke-width="2"/><path d="M14 7h3M14 12h4.5M14 17h6"/>',
    "ui_erase": '<path d="M14.2 4.6l5.2 5.2a1.5 1.5 0 0 1 0 2.1L12 19.3H8.3l-3.7-3.7a1.5 1.5 0 0 1 0-2.1l7.5-8.9a1.5 1.5 0 0 1 2.1 0z"/><path d="M9.2 9.2l5.6 5.6M12 19.3h8"/>',
    "ui_trash": '<path d="M4 6.5h16M9.5 6.5V4h5v2.5M6.3 6.5l1 13.5h9.4l1-13.5M10 10.5v6M14 10.5v6"/>',
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
    # Volet rapide (fn_quickSettings) : tuiles et curseur de luminosité.
    "qs_night": '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/>',
    "qs_airplane": '<path d="M12 2.5c.9 0 1.5.8 1.5 1.8V9l7.5 4.5v2L13.5 13v4.6l2.5 1.9V21L12 20l-4 1v-1.5l2.5-1.9V13L3 15.5v-2L10.5 9V4.3c0-1 .6-1.8 1.5-1.8z"/>',
    "qs_silent": '<path d="M6 16.5V11a6 6 0 0 1 9.8-4.6M18 10.5v6l1.5 2H8"/><path d="M10 20.5a2 2 0 0 0 4 0"/><path d="M3.5 3.5l17 17"/>',
    "qs_bt": '<path d="M7 7.5l10 9-5 4.5V3l5 4.5-10 9"/>',
    "qs_ir": '<path d="M9 21h6M10 21V11h4v10"/><rect x="9" y="7" width="6" height="4" rx="1"/><path d="M12 2v2.5M5.5 4.5l1.8 1.8M18.5 4.5l-1.8 1.8M3.5 10h2M18.5 10h2"/>',
    "qs_battery": '<rect x="7" y="4.5" width="10" height="17" rx="1.6"/><path d="M10 2.5h4"/><path d="M9.5 15h5M9.5 18h5" stroke-width="2.2"/>',
    "qs_sun": '<circle cx="12" cy="12" r="4.2"/><path d="M12 1.8v2.6M12 19.6v2.6M1.8 12h2.6M19.6 12h2.6M4.8 4.8l1.8 1.8M17.4 17.4l1.8 1.8M4.8 19.2l1.8-1.8M17.4 6.6l1.8-1.8"/>',
    "qs_sun_low": '<circle cx="12" cy="12" r="3.6"/><path d="M12 5.5v1.2M12 17.3v1.2M5.5 12h1.2M17.3 12h1.2M7.4 7.4l.9.9M15.7 15.7l.9.9M7.4 16.6l.9-.9M15.7 8.3l.9-.9"/>',
    # Réglages (fn_pageSettings) : langue (globe) et personnalisation (palette), distinctes de l'icône Réglages.
    "ui_language": '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 2.6 15.4 0 18M12 3c-2.6 2.6-2.6 15.4 0 18"/><path d="M5 7.5h14M5 16.5h14"/>',
    "ui_palette": '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.8-.8 1.8-1.7 0-1.3-1.1-1.6-1.1-2.7 0-.9.7-1.6 1.6-1.6h2.2A4.5 4.5 0 0 0 21 10.5C21 6.4 17 3 12 3z"/><circle cx="7.5" cy="11" r="1.2"/><circle cx="10" cy="7" r="1.2"/><circle cx="14.5" cy="7" r="1.2"/><circle cx="17" cy="10.8" r="1.2"/>',
    # App Reco (fn_pageReco) : types de note et « sous mon regard ».
    "reco_vehicle": '<path d="M2.5 15.5V9.5a1 1 0 0 1 1-1h9.5v7"/><path d="M13 11h4.2l3.3 3.2v1.3H13"/><circle cx="6.5" cy="17" r="2"/><circle cx="16.8" cy="17" r="2"/><path d="M8.5 16.5h6.3"/>',
    "reco_armed": '<circle cx="8" cy="7" r="2.6"/><circle cx="16" cy="7" r="2.6"/><path d="M3.2 20v-2.6a4.8 4.8 0 0 1 9.6 0V20M11.2 20v-2.6a4.8 4.8 0 0 1 9.6 0V20"/>',
    "reco_static": '<path d="M2.5 19.5h19"/><path d="M4.5 19.5v-4.5a7.5 7.5 0 0 1 15 0v4.5"/><path d="M8.5 14h7"/><path d="M12 3v4"/>',
    "reco_mine": '<path d="M3 17.5h18"/><path d="M6.5 17.5a5.5 5.5 0 0 1 11 0"/><path d="M12 8.5V5M7.6 10.1l-2-2M16.4 10.1l2-2"/>',
    "reco_civil": '<circle cx="12" cy="6.8" r="3.2"/><path d="M5.5 20.5v-2.2a6.5 6.5 0 0 1 13 0v2.2"/>',
    "reco_infra": '<path d="M3.5 20.5V9l8.5-5.5L20.5 9v11.5"/><path d="M2 20.5h20"/><path d="M9.5 20.5v-6h5v6M7.5 11.5h2.2M14.3 11.5h2.2"/>',
    "reco_other": '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1.1 1-1.1 1.7v.6"/><path d="M12 16.9v.2"/>',
    "reco_eye": '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    # Main ouverte : « prendre en main » (pastille Ctrl+Maj+U du téléphone porté).
    "ui_hand": '<path d="M9.1 12V5.6a1.3 1.3 0 0 1 2.6 0V11M11.7 11V4.3a1.3 1.3 0 0 1 2.6 0V11M14.3 11V5.6a1.3 1.3 0 0 1 2.6 0V11M16.9 11.2V8.3a1.3 1.3 0 0 1 2.6 0v5.4c0 4.1-2.8 7.3-6.6 7.3-2.7 0-4.3-1.3-5.8-3.5l-2.6-4a1.4 1.4 0 0 1 2.2-1.7l2.4 2.8V8.2"/>',
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


def erase_box(img, box):
    """Efface un logo incrusté (box en fractions de l'image) : fondu vertical entre les lignes au-dessus et au-dessous,
    puis flou léger. Le logo ATHENA du coin haut gauche passait sous la barre d'app et chevauchait le lanceur."""
    W, H = img.size
    x0, y0, x1, y1 = int(box[0] * W), int(box[1] * H), int(box[2] * W), int(box[3] * H)
    top, bot = max(0, y0 - 1), min(H - 1, y1)
    px = img.load()
    for x in range(x0, x1):
        a, b = px[x, top], px[x, bot]
        for y in range(y0, y1):
            t = (y - y0) / max(1, y1 - y0)
            px[x, y] = tuple(int(a[i] * (1 - t) + b[i] * t) for i in range(3))
    m = 12
    reg = (max(0, x0 - m), max(0, y0 - m), min(W, x1 + m), min(H, y1 + m))
    img.paste(img.crop(reg).filter(ImageFilter.GaussianBlur(10)), reg[:2])
    return img


# Logo incrusté dans la photo source, effacé avant recadrage (fractions de l'image source).
WALL_ERASE = {"wallpaper_athena.jpg": (0.0, 0.0, 0.2, 0.135)}


def photo_wallpaper(path, w, h, dim):
    """Photo en fond : couvre l'écran sans l'étirer ; en vertical, la photo entière sur un fond flouté."""
    from PIL import ImageFilter
    img = Image.open(path).convert("RGB")
    if os.path.basename(path) in WALL_ERASE:
        img = erase_box(img, WALL_ERASE[os.path.basename(path)])
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


def repair_kit_item(size=256):
    """Image d'inventaire du kit de réparation ATAK : trousse olive, croix verte, tournevis et écran de rechange."""
    k = 4
    S = size * k
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    sh = Image.new("L", (S, S), 0)
    ImageDraw.Draw(sh).rounded_rectangle([S * 0.12, S * 0.3, S * 0.9, S * 0.9], radius=S * 0.07, fill=150)
    sh = sh.filter(ImageFilter.GaussianBlur(S * 0.025))
    shadow = Image.new("RGBA", (S, S), (0, 0, 0, 0)); shadow.putalpha(sh)
    img.alpha_composite(shadow)
    d = ImageDraw.Draw(img)
    # Poignée
    d.rounded_rectangle([S * 0.36, S * 0.16, S * 0.64, S * 0.32], radius=S * 0.05, outline=(30, 34, 26, 255), width=int(S * 0.035))
    # Trousse
    d.rounded_rectangle([S * 0.1, S * 0.27, S * 0.88, S * 0.86], radius=S * 0.07, fill=(74, 82, 56, 255), outline=(24, 28, 20, 255), width=int(S * 0.012))
    d.rounded_rectangle([S * 0.13, S * 0.30, S * 0.2, S * 0.83], radius=S * 0.03, fill=(98, 108, 78, 255))
    d.line([(S * 0.1, S * 0.42), (S * 0.88, S * 0.42)], fill=(40, 46, 32, 255), width=int(S * 0.014))
    # Écran de rechange (rectangle sombre fêlé) et tournevis
    d.rounded_rectangle([S * 0.24, S * 0.48, S * 0.5, S * 0.8], radius=S * 0.02, fill=(18, 24, 22, 255), outline=(150, 160, 150, 255), width=int(S * 0.008))
    d.line([(S * 0.28, S * 0.52), (S * 0.37, S * 0.63), (S * 0.33, S * 0.74)], fill=(200, 215, 220, 220), width=int(S * 0.008))
    d.line([(S * 0.37, S * 0.63), (S * 0.46, S * 0.6)], fill=(200, 215, 220, 220), width=int(S * 0.008))
    d.line([(S * 0.58, S * 0.78), (S * 0.78, S * 0.5)], fill=(190, 192, 188, 255), width=int(S * 0.022))
    d.line([(S * 0.55, S * 0.82), (S * 0.62, S * 0.72)], fill=(229, 72, 58, 255), width=int(S * 0.05))
    # Croix verte
    d.rectangle([S * 0.66, S * 0.31, S * 0.72, S * 0.4], fill=(92, 199, 107, 255))
    d.rectangle([S * 0.645, S * 0.335, S * 0.735, S * 0.375], fill=(92, 199, 107, 255))
    f = _font(int(S * 0.065))
    d.text((S * 0.22, S * 0.31), "ATAK", font=f, fill=(220, 226, 214, 255))
    return img.resize((size, size), Image.LANCZOS)


def power_assets(tmp):
    """Démarrage (boot_<port|land>), batterie vide (power_bat_empty) et item batterie (item_battery)."""
    convert(boot_screen(512, 1024).convert("RGB"), "boot_port", tmp)
    convert(boot_screen(1024, 512).convert("RGB"), "boot_land", tmp)
    convert(battery_empty_icon(), "power_bat_empty", tmp)
    convert(battery_item(), "item_battery", tmp)
    convert(repair_kit_item(), "item_repairkit", tmp)


# --- Extraction de données : câble branché au connecteur de la coque et module DL-7 (fn_dataOverlay.sqf) ---
# Repère : pixels de la coque paysage 2048 (android_s7_ca.png). Bout du câble d'origine (sortie du connecteur) : (1883, 1350).
# En portrait la coque est tournée d'un quart de tour horaire : (x, y) devient (2048 - y, x), bout du câble (698, 1883).
# Chaque variante a son cadre (fractions du carré de la coque) et l'emplacement du module, repris dans fn_dataOverlay.
DATALINK = {
    # nom : (cadre x0, y0, l, h), module (x, y, l, h), câble téléphone -> module, câble module -> cible
    "datalink_land_up": ((0.5, 0.0, 0.5, 1.0), (1100, 180, 700, 290),
        "M 1870 1350 L 1880 1350 Q 1910 1350 1910 1310 L 1910 400 Q 1910 325 1840 325 L 1800 325",
        "M 1060 325 C 1040 325 1030 310 1000 280"),
    "datalink_land_down": ((0.5, 0.25, 0.5, 1.0), (1100, 1800, 700, 290),
        "M 1870 1350 L 1880 1350 Q 1910 1350 1910 1395 L 1910 1870 Q 1910 1945 1840 1945 L 1800 1945",
        "M 1060 1945 C 1040 1945 1030 1970 1000 2010"),
    "datalink_port_left": ((-0.5, 0.5, 1.0, 0.5), (-760, 1560, 700, 290),
        "M 698 1870 L 698 1885 Q 698 1925 655 1925 L 170 1925 Q 120 1925 120 1875 L 120 1765 Q 120 1705 60 1705 L -60 1705",
        "M -800 1705 C -880 1705 -940 1680 -1040 1620"),
    "datalink_port_right": ((0.5, 0.5, 1.0, 0.5), (2110, 1560, 700, 290),
        "M 698 1870 L 698 1885 Q 698 1925 741 1925 L 1930 1925 Q 1980 1925 1980 1875 L 1980 1765 Q 1980 1705 2040 1705 L 2110 1705",
        "M 2850 1705 C 2930 1705 2990 1680 3090 1620"),
}


def _cable_svg(d, w=52):
    """Câble gainé noir mat : ombre, gaine, reflet doux."""
    return (f'<path d="{d}" transform="translate(6,12)" stroke="#000" stroke-opacity="0.45" stroke-width="{w + 10}" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            f'<path d="{d}" stroke="#050505" stroke-width="{w + 4}" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            f'<path d="{d}" stroke="#191b1b" stroke-width="{w - 6}" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            f'<path d="{d}" transform="translate(-5,-8)" stroke="#5b6260" stroke-opacity="0.55" stroke-width="{max(4, w // 6)}" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')


def _screw(cx, cy, r):
    """Vis à six pans creux dans un capuchon caoutchouc."""
    return (f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="#0b0c0b" stroke="#2a2c2a" stroke-width="{r * 0.18}"/>'
            f'<circle cx="{cx}" cy="{cy}" r="{r * 0.62}" fill="url(#dlscrew)"/>'
            f'<circle cx="{cx}" cy="{cy}" r="{r * 0.28}" fill="#0a0a0a"/>')


# Teintes du boîtier DL-7 (réglage Personnaliser) : olive (défaut), noir, tan.
MODULE_TINTS = {
    "olive": ("#5c6247", "#4a5039", "#3a3f2d", "#262a1e"),
    "black": ("#3e403f", "#2d2f2e", "#202221", "#121313"),
    "tan": ("#b09a72", "#9c875f", "#82704e", "#5c4f37"),
}


def _module_svg(x, y, w, h, tint="olive"):
    r = h * 0.1
    c0, c1, c2, c3 = MODULE_TINTS[tint]
    out = [
        '<defs>'
        f'<linearGradient id="dlc" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{c0}"/><stop offset="0.12" stop-color="{c1}"/><stop offset="0.6" stop-color="{c2}"/><stop offset="1" stop-color="{c3}"/></linearGradient>'
        '<linearGradient id="dll" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#061009"/><stop offset="1" stop-color="#020604"/></linearGradient>'
        '<radialGradient id="dlscrew" cx="0.4" cy="0.35" r="0.7"><stop offset="0" stop-color="#9da19a"/><stop offset="1" stop-color="#3b3e3a"/></radialGradient>'
        '<radialGradient id="dlbezel" cx="0.4" cy="0.3" r="0.8"><stop offset="0" stop-color="#d5d8d0"/><stop offset="0.55" stop-color="#7c8079"/><stop offset="1" stop-color="#2f322e"/></radialGradient>'
        '<linearGradient id="dlcap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2a2c2a"/><stop offset="1" stop-color="#0b0c0b"/></linearGradient>'
        '</defs>',
        # Ombre portée puis boîtier métal olive, arête haute éclairée, arête basse sombre.
        f'<rect x="{x + 12}" y="{y + 18}" width="{w}" height="{h}" rx="{r}" fill="#000" fill-opacity="0.5"/>',
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{r}" fill="url(#dlc)" stroke="#121410" stroke-width="5"/>',
        f'<rect x="{x + 10}" y="{y + 6}" width="{w - 20}" height="{h * 0.05}" rx="{h * 0.025}" fill="#c9cdb4" fill-opacity="0.22"/>',
        f'<rect x="{x + 10}" y="{y + h * 0.93}" width="{w - 20}" height="{h * 0.04}" rx="{h * 0.02}" fill="#000" fill-opacity="0.35"/>',
        # Petits boutons sur le dessus.
        f'<rect x="{x + w * 0.37}" y="{y - h * 0.05}" width="{w * 0.05}" height="{h * 0.06}" rx="4" fill="#1a1c1a"/>',
        f'<rect x="{x + w * 0.45}" y="{y - h * 0.05}" width="{w * 0.05}" height="{h * 0.06}" rx="4" fill="#1a1c1a"/>',
    ]
    # Capuchons caoutchouc d'angle avec vis creuse.
    k = h * 0.15
    for cx, cy in ((x + k * 0.35, y + k * 0.35), (x + w - k * 0.35, y + k * 0.35), (x + k * 0.35, y + h - k * 0.35), (x + w - k * 0.35, y + h - k * 0.35)):
        out.append(f'<rect x="{cx - k}" y="{cy - k}" width="{2 * k}" height="{2 * k}" rx="{k * 0.35}" fill="url(#dlcap)" stroke="#000" stroke-width="4"/>')
        out.append(_screw(cx, cy, k * 0.5))
    # Prises latérales (blocs noirs) et petites vis de façade.
    for sx in (x - w * 0.015, x + w * 1.015):
        out.append(f'<rect x="{sx - w * 0.035}" y="{y + h * 0.36}" width="{w * 0.07}" height="{h * 0.28}" rx="8" fill="#0d0e0d" stroke="#000" stroke-width="4"/>')
        out.append(f'<rect x="{sx - w * 0.03}" y="{y + h * 0.38}" width="{w * 0.06}" height="{h * 0.05}" rx="4" fill="#ffffff" fill-opacity="0.08"/>')
    for fx, fy in ((0.1, 0.86), (0.9, 0.86)):
        out.append(f'<circle cx="{x + w * fx}" cy="{y + h * fy}" r="{h * 0.022}" fill="#1b1d18" stroke="#8d917f" stroke-width="2"/>')
    # Marquage.
    out.append(f'<text x="{x + w * 0.1}" y="{y + h * 0.29}" font-family="DejaVu Sans" font-weight="bold" font-size="{h * 0.13}" fill="#d6d9cc" letter-spacing="1">COMSPEC</text>')
    out.append(f'<text x="{x + w * 0.88}" y="{y + h * 0.29}" text-anchor="end" font-family="DejaVu Sans" font-size="{h * 0.1}" fill="#d0d3c4" letter-spacing="1">DL-7 · DATA LINK</text>')
    # Écran LCD (texte et lueur dessinés par le jeu) : biseau, verre noir, reflet.
    lx, ly, lw, lh = x + w * 0.1, y + h * 0.38, w * 0.46, h * 0.42
    out.append(f'<rect x="{lx - 8}" y="{ly - 8}" width="{lw + 16}" height="{lh + 16}" rx="12" fill="#0a0b0a" stroke="#2c2f27" stroke-width="3"/>')
    out.append(f'<rect x="{lx}" y="{ly}" width="{lw}" height="{lh}" rx="7" fill="url(#dll)"/>')
    out.append(f'<path d="M {lx + 6} {ly + 6} L {lx + lw * 0.55} {ly + 6} L {lx + lw * 0.35} {ly + lh * 0.45} L {lx + 6} {ly + lh * 0.45} Z" fill="#ffffff" fill-opacity="0.035"/>')
    # Voyants : lunette chromée, lentille éteinte (allumée par le jeu), étiquette.
    for fx, lab in ((0.655, "PWR"), (0.77, "AUTH"), (0.885, "DATA")):
        cx, cy = x + w * fx, y + h * 0.52
        out.append(f'<circle cx="{cx}" cy="{cy}" r="{w * 0.04}" fill="url(#dlbezel)" stroke="#111" stroke-width="2"/>'
                   f'<circle cx="{cx}" cy="{cy}" r="{w * 0.028}" fill="#141614"/>'
                   f'<circle cx="{cx - w * 0.008}" cy="{cy - w * 0.009}" r="{w * 0.008}" fill="#ffffff" fill-opacity="0.18"/>'
                   f'<text x="{cx}" y="{y + h * 0.76}" text-anchor="middle" font-family="DejaVu Sans" font-weight="bold" font-size="{h * 0.07}" fill="#c8cbbd">{lab}</text>')
    # Aérations.
    for i in range(6):
        gx = x + w * (0.635 + i * 0.047)
        out.append(f'<rect x="{gx}" y="{y + h * 0.84}" width="{w * 0.028}" height="{h * 0.022}" rx="3" fill="#121410"/>')
    return "".join(out)


def _boot_svg(x, y, side, long=False, ribs=None):
    """Manchon de câble : bloc de prise, bague, soufflet nervuré (côté gauche ou droit d'une prise horizontale,
    « down » pour une prise verticale qui descend)."""
    n, step, rw = (7, 13, 30) if long else (5, 12, 24)
    if ribs is not None:
        n = ribs
    out = []
    if side in ("left", "right"):
        sgn = -1 if side == "left" else 1
        out.append(f'<rect x="{x + (0 if sgn > 0 else -34)}" y="{y - 30}" width="34" height="60" rx="8" fill="#141514" stroke="#000" stroke-width="4"/>')
        for i in range(n):
            cx = x + sgn * (40 + i * step)
            hh = rw * (1 - i * 0.06)
            out.append(f'<rect x="{cx - 5}" y="{y - hh}" width="10" height="{2 * hh}" rx="4" fill="#1d1f1e" stroke="#060606" stroke-width="3"/>')
    else:
        out.append(f'<rect x="{x - 30}" y="{y}" width="60" height="34" rx="8" fill="#141514" stroke="#000" stroke-width="4"/>')
        for i in range(n):
            cy = y + 40 + i * step
            ww = rw * (1 - i * 0.06)
            out.append(f'<rect x="{x - ww}" y="{cy - 5}" width="{2 * ww}" height="10" rx="4" fill="#1d1f1e" stroke="#060606" stroke-width="3"/>')
    return "".join(out)


def _grain(img, seed=7):
    """Grain et usure du métal, sur les zones opaques."""
    import random
    rnd = random.Random(seed)
    w, h = img.size
    noise = Image.effect_noise((w, h), 28).convert("L")
    alpha = img.split()[3]
    grain = Image.merge("RGBA", (noise, noise, noise, alpha.point(lambda a: 26 if a > 200 else 0)))
    out = Image.alpha_composite(img, grain)
    # Rayures fines claires.
    d = ImageDraw.Draw(out)
    for _ in range(int(w * h / 9000)):
        x0, y0 = rnd.randrange(w), rnd.randrange(h)
        if alpha.getpixel((x0, y0)) < 250:
            continue
        r, g, b, _a = out.getpixel((x0, y0))
        if not (34 < g < 170):  # sur le boîtier seulement (pas sur la gaine noire)
            continue
        x1 = x0 + rnd.randint(-14, 14)
        y1 = y0 + rnd.randint(-3, 3)
        d.line([(x0, y0), (x1, y1)], fill=(190, 194, 170, 60), width=1)
    return out


def datalink_svg(name, px=1024, tint="olive"):
    (fx, fy, fw, fh), (mx, my, mw, mh), c1, c2 = DATALINK[name]
    vx, vy, vw, vh = fx * 2048, fy * 2048, fw * 2048, fh * 2048
    W, H = int(px * fw * 2), int(px * fh * 2)
    port = name.startswith("datalink_port")
    # Prise du téléphone à droite du module (paysage, portrait gauche) ou à gauche (portrait droit).
    phone_right = name != "datalink_port_right"
    render = _module_render(tint)
    cables = _cable_svg(c2, 46) + _cable_svg(c1, 40)
    in_x, out_x = (mx + mw * 1.05, mx - mw * 0.05) if phone_right else (mx - mw * 0.05, mx + mw * 1.05)
    boots = _boot_svg(in_x, my + mh / 2, "right" if phone_right else "left", ribs=2)
    boots += _boot_svg(out_x, my + mh / 2, "left" if phone_right else "right", True)
    boots += _boot_svg(698, 1858, "down") if port else _boot_svg(1866, 1350, "right")
    if render is None:
        return _grain(_svg_img(cables + _module_svg(mx, my, mw, mh, tint) + boots, (vx, vy, vw, vh), W, H))
    # Module rendu en 3D (render_3d.py) entre les câbles et les manchons.
    img = _grain(_svg_img(cables, (vx, vy, vw, vh), W, H))
    _paste_module(img, render, (mx - vx) * W / vw, (my - vy) * H / vh, mw * W / vw)
    img.alpha_composite(_svg_img(boots, (vx, vy, vw, vh), W, H))
    return img


def _svg_img(body, view, W, H):
    vx, vy, vw, vh = view
    svg = f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vx} {vy} {vw} {vh}" width="{W}" height="{H}">{body}</svg>'
    return Image.open(io.BytesIO(cairosvg.svg2png(bytestring=svg.encode(), output_width=W, output_height=H))).convert("RGBA")


# Rendus 3D (render_3d.py) : module DL-7 700 x 290 px avec 70 px de marge (ombre portée, prises latérales).
RENDER_DIR = os.path.join(HERE, "render")
MOD_PAD = 70


def _module_render(tint):
    path = os.path.join(RENDER_DIR, f"dl7_{tint}.png")
    if not os.path.exists(path):
        return None
    import numpy as np
    im = np.array(Image.open(path).convert("RGBA")).astype(float)
    h, w = im.shape[:2]
    # Ombre portée (pixels semi-transparents) : adoucie et fondue vers les bords du cadre, sinon le cadre se voit.
    k = w / (700 + 2 * MOD_PAD)
    pad = MOD_PAD * k
    yy, xx = np.mgrid[0:h, 0:w]
    dx = np.minimum(xx, w - 1 - xx) / pad
    dy = np.minimum(yy, h - 1 - yy) / pad
    fade = np.clip(np.minimum(dx, dy) * 1.25 - 0.1, 0, 1) ** 1.5
    shadow = im[..., 3] < 250
    im[..., 3] = np.where(shadow, im[..., 3] * fade * 0.75, im[..., 3])
    return Image.fromarray(im.clip(0, 255).astype("uint8"), "RGBA")


def _paste_module(img, render, x, y, w):
    """Colle le rendu du module dont la face fait w px de large, coin haut gauche de la face en (x, y)."""
    k = w / 700
    pad = MOD_PAD * k
    tw = int(round((700 + 2 * MOD_PAD) * k))
    th = int(round((290 + 2 * MOD_PAD) * k))
    img.alpha_composite(render.resize((tw, th), Image.LANCZOS), (int(round(x - pad)), int(round(y - pad))))


def datalink_assets(tmp):
    for name in DATALINK:
        for tint in MODULE_TINTS:
            convert(datalink_svg(name, tint=tint), name + ("" if tint == "olive" else "_" + tint), tmp)
    convert(data_cable_item(), "item_datacable", tmp)


def data_cable_item(size=256):
    """Kit de câble de données (objet d'inventaire) : module DL-7 et câble enroulé."""
    view = (1100, 100, 860, 520)
    cable = _cable_svg("M 1200 520 C 1150 600 1350 640 1420 560 C 1490 480 1300 430 1250 520 C 1200 610 1450 640 1520 560", 40)
    boots = _boot_svg(1143, 325, 'left') + _boot_svg(1917, 325, 'right')
    render = _module_render("olive")
    if render is None:
        img = _grain(_svg_img(cable + _module_svg(1180, 180, 700, 290) + boots, view, 512, 310))
    else:
        img = _grain(_svg_img(cable, view, 512, 310))
        k = 512 / 860
        _paste_module(img, render, (1180 - 1100) * k, (180 - 100) * k, 700 * k)
        img.alpha_composite(_svg_img(boots, view, 512, 310))
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    img = img.resize((size, int(size * 310 / 512)), Image.LANCZOS)
    out.alpha_composite(img, (0, (size - img.height) // 2))
    return out


# --- Teintes de coque (réglage Personnaliser) : la coque coyote recolorée en vert OD ou noir ---
SHELL_TINTS = {"od": (64, 71, 47), "black": (40, 41, 40)}


def shell_tint(img, tint):
    """Recolore la coque coyote (teinte 15-60°, saturée) en gardant les ombres et reflets de la texture."""
    import numpy as np
    a = np.array(img.convert("RGBA")).astype(float) / 255
    rgb = a[..., :3]
    mx = rgb.max(-1)
    mn = rgb.min(-1)
    d = mx - mn + 1e-6
    sat = d / (mx + 1e-6)
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    hue = np.where(mx == r, ((g - b) / d) % 6, np.where(mx == g, (b - r) / d + 2, (r - g) / d + 4)) * 60
    w = np.clip((sat - 0.10) / 0.08, 0, 1) * np.clip(1.4 - np.abs(hue - 38) / 22, 0, 1) * (mx > 0.08)
    # Masque adouci : sans lui, le grain de la texture laisse des points coyote dans la nouvelle teinte.
    wi = Image.fromarray((w * 255).astype("uint8"), "L").filter(ImageFilter.GaussianBlur(3)).filter(ImageFilter.MaxFilter(5))
    w = np.minimum(np.array(wi).astype(float) / 255 * 1.3, 1) * (a[..., 3] > 0.05)
    lum = (((rgb * [0.3, 0.55, 0.15]).sum(-1)) / 0.31)[..., None]
    target = np.array(tint, dtype=float)[None, None, :] / 255
    new = np.clip(target * lum, 0, 1)
    out = rgb * (1 - w[..., None]) + new * w[..., None]
    res = np.concatenate([out, a[..., 3:4]], -1)
    return Image.fromarray((res * 255).astype("uint8"), "RGBA")


def shell_variants(tmp):
    for key, tint in SHELL_TINTS.items():
        # La texture de nuit est une silhouette noire : pas de variante de teinte.
        if not os.path.exists(PHONE_SRC):
            continue
        land = shell_tint(Image.open(PHONE_SRC).convert("RGBA"), tint)
        convert(land, f"phone_landscape_{key}", tmp)
        convert(land.rotate(-90, expand=True), f"phone_portrait_{key}", tmp)


# --- Dos du téléphone (action « Voir l'arrière ») : silhouette de la face, miroir, panneau arrière dessiné ---
def phone_back(src):
    """Coque vue de dos (paysage) : même silhouette que la face retournée, appareil photo à droite, plaque de fixation.
    Panneau arrière rendu en 3D (render_3d.py, render/back_panel.png) ; sans rendu, dessin vectoriel."""
    front = ImageOps.mirror(Image.open(src).convert("RGBA"))
    # Le marquage « OPENA » du rebord serait lu à l'envers : recouvert par un morceau de rebord lisse pris plus bas.
    front.paste(front.crop((1722, 960, 1775, 1140)), (1722, 745))
    panel = os.path.join(RENDER_DIR, "back_panel.png")
    if os.path.exists(panel):
        front.alpha_composite(Image.open(panel).convert("RGBA"))
        # Balise IR vissée entre la plaque et les nervures (render_3d.render_irbeacon, rendu au double).
        ir = os.path.join(RENDER_DIR, "irbeacon.png")
        if os.path.exists(ir):
            b = Image.open(ir).convert("RGBA")
            b = b.resize((b.width // 2, b.height // 2), Image.LANCZOS)
            front.alpha_composite(_soft_edges(b), (IR_POS[0] - b.width // 2, IR_POS[1] - b.height // 2))
        return front
    p = []
    p.append('<defs>'
             '<linearGradient id="bk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9a8b64"/><stop offset="0.5" stop-color="#857754"/><stop offset="1" stop-color="#6c6044"/></linearGradient>'
             '<linearGradient id="pl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2c2d2c"/><stop offset="1" stop-color="#121312"/></linearGradient>'
             '<radialGradient id="lens" cx="0.4" cy="0.35" r="0.75"><stop offset="0" stop-color="#3a4a66"/><stop offset="0.45" stop-color="#0c1018"/><stop offset="1" stop-color="#020203"/></radialGradient>'
             '<radialGradient id="scr" cx="0.4" cy="0.35" r="0.7"><stop offset="0" stop-color="#b8bab4"/><stop offset="1" stop-color="#4a4c48"/></radialGradient>'
             '</defs>')
    # Panneau arrière en caoutchouc (même teinte que la coque, recolorée avec elle).
    p.append('<rect x="372" y="690" width="1358" height="668" rx="84" fill="#5d5440"/>')
    p.append('<rect x="384" y="700" width="1334" height="648" rx="78" fill="url(#bk)"/>')
    p.append('<rect x="400" y="712" width="1302" height="22" rx="11" fill="#d8caa0" fill-opacity="0.25"/>')
    # Nervures antidérapantes aux deux bouts.
    for x0 in (430, 1395):
        for i in range(9):
            p.append(f'<rect x="{x0 + (i % 2) * 14}" y="{760 + i * 60}" width="150" height="26" rx="13" fill="#5f5640" stroke="#4a4232" stroke-width="3"/>')
    # Appareil photo, flash, capteur (haut du téléphone, à droite vue de dos).
    p.append('<rect x="1600" y="890" width="96" height="268" rx="44" fill="#151616" stroke="#3b3d3b" stroke-width="5"/>')
    p.append('<rect x="1612" y="905" width="72" height="140" rx="36" fill="#262827"/>')
    p.append('<circle cx="1648" cy="975" r="38" fill="url(#lens)" stroke="#6f726d" stroke-width="5"/>')
    p.append('<circle cx="1638" cy="963" r="9" fill="#fff" fill-opacity="0.35"/>')
    p.append('<rect x="1626" y="1062" width="44" height="30" rx="8" fill="#e8e2c8" stroke="#555" stroke-width="3"/>')
    p.append('<rect x="1630" y="1104" width="36" height="36" rx="8" fill="#0d0d0d" stroke="#555" stroke-width="3"/>')
    # Plaque de fixation (rail MOLLE + bouton de rotation) vissée au centre.
    p.append('<rect x="790" y="742" width="470" height="560" rx="40" fill="#000" fill-opacity="0.45" transform="translate(10,16)"/>')
    p.append('<rect x="790" y="742" width="470" height="560" rx="40" fill="url(#pl)" stroke="#060606" stroke-width="6"/>')
    p.append('<rect x="806" y="754" width="438" height="16" rx="8" fill="#fff" fill-opacity="0.08"/>')
    for yy in (800, 1206):
        p.append(f'<rect x="850" y="{yy}" width="350" height="42" rx="18" fill="#050505" stroke="#3a3b3a" stroke-width="3"/>')
    p.append('<circle cx="1025" cy="1024" r="140" fill="#1a1b1a" stroke="#454745" stroke-width="6"/>')
    p.append('<circle cx="1025" cy="1024" r="104" fill="#232423" stroke="#0a0a0a" stroke-width="4"/>')
    for k in range(12):
        import math
        a = k * math.pi / 6
        p.append(f'<rect x="{1025 + 120 * math.cos(a) - 9}" y="{1024 + 120 * math.sin(a) - 9}" width="18" height="18" rx="4" fill="#0c0c0c" transform="rotate({k * 30} {1025 + 120 * math.cos(a)} {1024 + 120 * math.sin(a)})"/>')
    p.append('<rect x="1005" y="944" width="40" height="160" rx="12" fill="#3d3f3d"/>')
    for sx, sy in ((830, 782), (1220, 782), (830, 1262), (1220, 1262)):
        p.append(f'<circle cx="{sx}" cy="{sy}" r="17" fill="url(#scr)" stroke="#111" stroke-width="3"/><rect x="{sx - 9}" y="{sy - 3}" width="18" height="6" fill="#222"/>')
    # Étiquette du fabricant.
    p.append('<rect x="560" y="1110" width="190" height="170" rx="10" fill="#d9d4c2" stroke="#7a7464" stroke-width="3"/>')
    p.append('<text x="655" y="1146" text-anchor="middle" font-family="DejaVu Sans" font-weight="bold" font-size="24" fill="#222">COMSPEC</text>')
    p.append('<text x="655" y="1172" text-anchor="middle" font-family="DejaVu Sans" font-size="17" fill="#333">ATAK · TAC-S7</text>')
    for i in range(30):
        bw = 2 + (i * 7) % 5
        p.append(f'<rect x="{578 + i * 5.2}" y="1188" width="{bw * 0.8}" height="52" fill="#111"/>')
    p.append('<text x="655" y="1266" text-anchor="middle" font-family="DejaVu Sans Mono" font-size="15" fill="#333">S/N CS-0417-ATK</text>')
    # Bossage du port SMA (antenne) en haut à gauche vu de face : à droite vu de dos.
    svg = f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2048 2048" width="2048" height="2048">{"".join(p)}</svg>'
    over = Image.open(io.BytesIO(cairosvg.svg2png(bytestring=svg.encode(), output_width=2048, output_height=2048))).convert("RGBA")
    out = front.copy()
    out.alpha_composite(over)
    return _grain(out, seed=11)


def back_variants(tmp):
    if not os.path.exists(PHONE_SRC):
        return
    back = phone_back(PHONE_SRC)
    convert(back, "phone_back", tmp)
    for key, tint in SHELL_TINTS.items():
        convert(shell_tint(back, tint), f"phone_back_{key}", tmp)
    vest_phone_atlas(back, tmp)


# --- Accessoires vus de dos : batterie externe sur la plaque (calque), balise IR (dans la texture du dos) ---
IR_POS = (1327, 776)
PLATE_C = (1025, 1020)


def _soft_edges(img, frac=0.12):
    """Rendu sur capteur d'ombre : l'ombre s'efface vers les bords du cadre (pas de boîte visible)."""
    import numpy as np
    a = np.array(img).astype(float)
    h, w = a.shape[:2]
    yy, xx = np.mgrid[0:h, 0:w]
    m = np.minimum.reduce([xx / (w * frac), (w - 1 - xx) / (w * frac), yy / (h * frac), (h - 1 - yy) / (h * frac)]).clip(0, 1)
    a[..., 3] *= m
    return Image.fromarray(a.astype("uint8"), "RGBA")


def powerbank_overlay():
    pb = os.path.join(RENDER_DIR, "powerbank.png")
    out = Image.new("RGBA", (2048, 2048), (0, 0, 0, 0))
    if os.path.exists(pb):
        b = _soft_edges(Image.open(pb).convert("RGBA"))
        out.alpha_composite(b, (PLATE_C[0] - b.width // 2, PLATE_C[1] - b.height // 2))
    return out


def _icon_from(img, size=256, rot=0):
    if rot:
        img = img.rotate(rot, expand=True, resample=Image.BICUBIC)
    img = img.crop(img.getbbox())
    k = (size * 0.9) / max(img.size)
    img = img.resize((max(1, int(img.width * k)), max(1, int(img.height * k))), Image.LANCZOS)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    out.alpha_composite(img, ((size - img.width) // 2, (size - img.height) // 2))
    return out


def nvgfilter_item(size=256):
    """Filtre d'écran JVN : film sombre teinté vert, languette de pose, marquage."""
    k = 4
    S = size * k
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    sh = Image.new("L", (S, S), 0)
    ImageDraw.Draw(sh).rounded_rectangle([S * 0.12, S * 0.26, S * 0.92, S * 0.80], radius=S * 0.06, fill=140)
    sh = sh.filter(ImageFilter.GaussianBlur(S * 0.02))
    shadow = Image.new("RGBA", (S, S), (0, 0, 0, 0)); shadow.putalpha(sh)
    img.alpha_composite(shadow)
    d = ImageDraw.Draw(img)
    d.rounded_rectangle([S * 0.08, S * 0.22, S * 0.88, S * 0.76], radius=S * 0.06, fill=(18, 40, 26, 215), outline=(90, 120, 96, 255), width=int(S * 0.008))
    d.polygon([(S * 0.12, S * 0.26), (S * 0.5, S * 0.26), (S * 0.2, S * 0.72), (S * 0.12, S * 0.72)], fill=(255, 255, 255, 28))
    d.rounded_rectangle([S * 0.80, S * 0.30, S * 0.95, S * 0.40], radius=S * 0.02, fill=(200, 120, 40, 255))
    d.text((S * 0.16, S * 0.60), "NVG-SAFE", font=_font(int(S * 0.07)), fill=(150, 200, 160, 230))
    return img.resize((size, size), Image.LANCZOS)


# --- Autres modèles (render_phones.py) : même ouverture d'écran que le S7 (x 455..1546, y 716..1333) ---
PHONE_MODELS = ["rugged", "iphone", "tablet", "keys", "plate", "flip", "ribbed"]


def _night(img):
    """Variante de nuit : coque très assombrie, alpha gardé (l'ouverture de l'écran reste transparente)."""
    from PIL import ImageEnhance
    a = img.getchannel("A")
    out = ImageEnhance.Brightness(img.convert("RGB")).enhance(0.28).convert("RGBA")
    out.putalpha(a)
    return out


def model_assets(tmp, only=None):
    for m in PHONE_MODELS:
        if only and m not in only:
            continue
        f = os.path.join(RENDER_DIR, f"phone_{m}_front.png")
        if not os.path.exists(f):
            continue
        land = Image.open(f).convert("RGBA")
        for key, img in (("", land), ("_night", _night(land))):
            convert(img, f"phone_landscape_{m}{key}", tmp)
            convert(img.rotate(-90, expand=True), f"phone_portrait_{m}{key}", tmp)
        b = os.path.join(RENDER_DIR, f"phone_{m}_back.png")
        if m != "tablet" and os.path.exists(b):
            convert(Image.open(b).convert("RGBA"), f"phone_back_{m}", tmp)


def accessory_assets(tmp):
    convert(powerbank_overlay(), "back_powerbank", tmp)
    pb = os.path.join(RENDER_DIR, "powerbank.png")
    if os.path.exists(pb):
        convert(_icon_from(_soft_edges(Image.open(pb).convert("RGBA"))), "item_powerbank", tmp)
    convert(nvgfilter_item(), "item_nvgfilter", tmp)
    tab = os.path.join(RENDER_DIR, "phone_tablet_front.png")
    if os.path.exists(tab):
        t = Image.open(tab).convert("RGBA")
        glass = Image.new("RGBA", t.size, (0, 0, 0, 0))
        glass.paste(_glass(1092, 618), (455, 716))
        glass.alpha_composite(t)
        convert(_icon_from(glass), "item_vehiclekit", tmp)


# --- Téléphone porté sur le gilet (vest_phone.p3d, model_vest_phone.py) : atlas face en haut, dos en bas ---
VEST_CASE = (270, 630, 1720, 1425)  # rectangle de la coque dans la texture de la face (le dos en est le miroir)


def _glass(w, h):
    """Écran éteint : verre sombre, léger reflet en diagonale."""
    import numpy as np
    yy, xx = np.mgrid[0:h, 0:w].astype(float)
    base = 14 + 10 * (1 - yy / h)
    band = np.exp(-(((xx / w) - (yy / h) * 0.6 - 0.25) ** 2) / 0.01) * 18
    v = np.clip(base + band, 0, 255)
    rgb = np.stack([v * 0.95, v, v * 1.08], -1).astype("uint8")
    return Image.fromarray(rgb, "RGB").convert("RGBA")


def vest_phone_atlas(back, tmp):
    x0, y0, x1, y1 = VEST_CASE
    front = Image.open(PHONE_SRC).convert("RGBA").crop(VEST_CASE)
    rear = back.crop((2048 - x1, y0, 2048 - x0, y1))
    for key, tint in [("", None)] + list(SHELL_TINTS.items()):
        f = front if tint is None else shell_tint(front, tint)
        r = rear if tint is None else shell_tint(rear, tint)
        face = _glass(*f.size)
        face.alpha_composite(f)
        # Hors coque (coins arrondis) : couleur du rebord, pas de liseré noir au filtrage.
        rim = f.getpixel((1000 - x0, 652 - y0))
        bg = Image.new("RGBA", r.size, rim[:3] + (255,))
        bg.alpha_composite(r)
        atlas = Image.new("RGB", (1024, 1024))
        atlas.paste(face.convert("RGB").resize((1024, 512), Image.LANCZOS), (0, 0))
        atlas.paste(bg.convert("RGB").resize((1024, 512), Image.LANCZOS), (0, 512))
        convert(atlas, f"vest_phone{'_' + key if key else ''}_co", tmp)


# --- Antennes d'extension de débit (port SMA en haut à gauche de la coque, x 430, y 632 de la texture 2048) ---
ANT_BASE = (430, 632)
ANT_FRAME = (238, -832, 384, 1536)  # zone dessinée (x, y, l, h) en pixels de la texture du téléphone


def _sma_svg(x, y):
    """Embase SMA dorée vissée sur la coque."""
    return (f'<rect x="{x - 40}" y="{y - 14}" width="80" height="26" rx="8" fill="#1b1c1b"/>'
            f'<polygon points="{x - 30},{y - 14} {x - 22},{y - 46} {x + 22},{y - 46} {x + 30},{y - 14}" fill="url(#gold)" stroke="#5a4512" stroke-width="3"/>'
            f'<rect x="{x - 6}" y="{y - 44}" width="12" height="28" fill="#fff4c0" fill-opacity="0.35"/>')


def _collar_svg(x, y, w=60, h=54):
    out = [f'<rect x="{x - w / 2}" y="{y - h}" width="{w}" height="{h}" rx="8" fill="#1a1b1a" stroke="#050505" stroke-width="3"/>']
    for i in range(6):
        out.append(f'<rect x="{x - w / 2 + 6 + i * (w - 12) / 6}" y="{y - h + 5}" width="{(w - 12) / 12}" height="{h - 10}" fill="#2e302e"/>')
    return "".join(out)


def antenna_svg(kind):
    x, y = ANT_BASE
    defs = ('<defs><linearGradient id="gold" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#8a6a1c"/><stop offset="0.45" stop-color="#f0d27a"/><stop offset="1" stop-color="#7a5c14"/></linearGradient>'
            '<linearGradient id="rub" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0c0d0c"/><stop offset="0.35" stop-color="#3a3c3a"/><stop offset="0.6" stop-color="#1c1d1c"/><stop offset="1" stop-color="#070707"/></linearGradient>'
            '<linearGradient id="pat" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3a3c3a"/><stop offset="1" stop-color="#111211"/></linearGradient></defs>')
    b = [defs, _sma_svg(x, y)]
    top = y - 46
    if kind == "stub":
        b.append(_collar_svg(x, top))
        b.append(f'<path d="M {x - 27} {top - 50} L {x - 22} {top - 300} Q {x} {top - 330} {x + 22} {top - 300} L {x + 27} {top - 50} Z" fill="url(#rub)" stroke="#000" stroke-width="3"/>')
        for i in range(3):
            b.append(f'<rect x="{x - 27}" y="{top - 70 - i * 18}" width="54" height="7" rx="3" fill="#000" fill-opacity="0.55"/>')
        b.append(f'<text x="{x}" y="{top - 180}" text-anchor="middle" transform="rotate(-90 {x} {top - 180})" font-family="DejaVu Sans" font-weight="bold" font-size="20" fill="#9a9d98">2.4/5.8</text>')
    elif kind == "whip":
        b.append(_collar_svg(x, top))
        b.append(f'<circle cx="{x}" cy="{top - 80}" r="30" fill="url(#rub)" stroke="#000" stroke-width="3"/><circle cx="{x}" cy="{top - 80}" r="9" fill="#555"/>')
        b.append(f'<path d="M {x - 25} {top - 110} L {x - 11} {-760} Q {x} {-790} {x + 11} {-760} L {x + 25} {top - 110} Z" fill="url(#rub)" stroke="#000" stroke-width="3"/>')
        b.append(f'<rect x="{x - 15}" y="-800" width="30" height="44" rx="14" fill="#2a2b2a" stroke="#000" stroke-width="3"/>')
        b.append(f'<rect x="{x - 26}" y="{top - 200}" width="52" height="60" rx="8" fill="#151615" stroke="#000" stroke-width="3"/>')
    else:  # patch : coude SMA à 90°, rotule, panneau carré face à l'opérateur
        b.append(_collar_svg(x, top))
        b.append(f'<rect x="{x - 22}" y="{top - 150}" width="44" height="100" fill="url(#rub)" stroke="#000" stroke-width="3"/>')
        b.append(f'<circle cx="{x}" cy="{top - 160}" r="26" fill="#1c1d1c" stroke="#000" stroke-width="3"/>')
        px0, py0, ps = x - 150, top - 470, 300
        b.append(f'<rect x="{px0 + 10}" y="{py0 + 16}" width="{ps}" height="{ps}" rx="26" fill="#000" fill-opacity="0.45"/>')
        b.append(f'<rect x="{px0}" y="{py0}" width="{ps}" height="{ps}" rx="26" fill="#0f100f" stroke="#000" stroke-width="5"/>')
        b.append(f'<rect x="{px0 + 26}" y="{py0 + 26}" width="{ps - 52}" height="{ps - 52}" rx="16" fill="url(#pat)" stroke="#4a4c4a" stroke-width="3"/>')
        b.append(f'<rect x="{px0 + 34}" y="{py0 + 32}" width="{ps - 68}" height="14" rx="7" fill="#fff" fill-opacity="0.10"/>')
        b.append(f'<circle cx="{x}" cy="{py0 + ps / 2}" r="16" fill="url(#gold)" stroke="#3a2c08" stroke-width="3"/>')
        b.append(f'<text x="{x}" y="{py0 + ps - 44}" text-anchor="middle" font-family="DejaVu Sans" font-weight="bold" font-size="24" fill="#8d908a">PATCH 9 dBi</text>')
    fx, fy, fw, fh = ANT_FRAME
    W, H = 256, 1024
    svg = f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{fx} {fy} {fw} {fh}" width="{W}" height="{H}">{"".join(b)}</svg>'
    return Image.open(io.BytesIO(cairosvg.svg2png(bytestring=svg.encode(), output_width=W, output_height=H))).convert("RGBA")


# Antennes photographiées (fond transparent, connecteur SMA en bas) : remplacent le dessin quand le fichier existe.
ANT_PHOTO_H = {"stub": 450}  # hauteur visible au-dessus du port SMA, en pixels de la texture du téléphone


def antenna_img(kind):
    src = os.path.join(HERE, "src", f"antenna_{kind}_photo.png")
    if kind not in ANT_PHOTO_H or not os.path.exists(src):
        return antenna_svg(kind)
    photo = Image.open(src).convert("RGBA")
    photo = photo.crop(photo.split()[3].point(lambda v: 255 if v > 40 else 0).getbbox())
    fx, fy, fw, fh = ANT_FRAME
    W, H = 256, 1024
    k = W / fw
    h = int(ANT_PHOTO_H[kind] * k)
    w = max(1, int(photo.width * h / photo.height))
    photo = photo.resize((w, h), Image.LANCZOS)
    x, y = ANT_BASE
    out = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    out.alpha_composite(photo, (int((x - fx) * k - w / 2), int((y - fy) * k - h)))
    return out


def antenna_item(kind, size=256):
    img = antenna_img(kind)
    if kind == "whip":  # fouet couché en diagonale, sinon trop fin dans la case d'inventaire
        img = img.rotate(-38, expand=True, resample=Image.BICUBIC)
    box = img.getbbox()
    img = img.crop(box)
    k = (size * 0.9) / max(img.size)
    img = img.resize((max(1, int(img.width * k)), max(1, int(img.height * k))), Image.LANCZOS)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    out.alpha_composite(img, ((size - img.width) // 2, (size - img.height) // 2))
    return out


def antenna_assets(tmp):
    for kind in ("stub", "whip", "patch"):
        land = antenna_img(kind)
        convert(land, f"antenna_{kind}_land", tmp)
        convert(land.rotate(-90, expand=True), f"antenna_{kind}_port", tmp)
        convert(ImageOps.mirror(land), f"antenna_{kind}_back", tmp)
        convert(antenna_item(kind), f"item_antenna_{kind}", tmp)


# --- Patchs velcro au dos du téléphone (réglage Personnaliser) : broderie dessinée, 512 px, patch 480 x 320 centré ---
PATCHES = ["fr", "fr_sub", "us", "us_ir", "skull", "crusader", "alien", "comspec", "name"]


def _patch_content(kind, d, W, H, k):
    import math
    if kind in ("fr", "fr_sub"):
        cols = [(0, 38, 140), (236, 236, 228), (214, 40, 52)] if kind == "fr" else [(52, 58, 44), (150, 140, 108), (92, 84, 62)]
        for i, c in enumerate(cols):
            d.rectangle((i * W / 3, 0, (i + 1) * W / 3, H), fill=c + (255,))
        return (24, 24, 22) if kind == "fr_sub" else (210, 196, 120)
    if kind in ("us", "us_ir"):
        red, white, blue, star = ((178, 34, 52), (240, 238, 230), (40, 52, 110), (240, 238, 230)) if kind == "us" else ((88, 90, 80), (150, 150, 136), (60, 62, 56), (150, 150, 136))
        for i in range(13):
            d.rectangle((0, i * H / 13, W, (i + 1) * H / 13), fill=(red if i % 2 == 0 else white) + (255,))
        cw, ch = W * 0.42, H * 7 / 13
        d.rectangle((0, 0, cw, ch), fill=blue + (255,))
        for row in range(9):
            n = 6 if row % 2 == 0 else 5
            for c in range(n):
                x = cw * ((c + (0.5 if row % 2 == 0 else 1.0)) / 6.0)
                y = ch * (row + 0.75) / 10
                r = 6 * k
                pts = [(x + r * math.cos(math.radians(-90 + j * 36)) * (1 if j % 2 == 0 else 0.42),
                        y + r * math.sin(math.radians(-90 + j * 36)) * (1 if j % 2 == 0 else 0.42)) for j in range(10)]
                d.polygon(pts, fill=star + (255,))
        return (196, 170, 90) if kind == "us" else (40, 40, 36)
    d.rectangle((0, 0, W, H), fill=(18, 18, 18, 255) if kind != "comspec" and kind != "name" else ((44, 50, 36, 255) if kind == "comspec" else (98, 90, 66, 255)))
    cx, cy = W / 2, H / 2
    if kind == "skull":
        bone = (226, 216, 186, 255)
        d.ellipse((cx - 100 * k, cy - 125 * k, cx + 100 * k, cy + 55 * k), fill=bone)
        d.polygon([(cx - 92 * k, cy + 5 * k), (cx + 92 * k, cy + 5 * k), (cx + 62 * k, cy + 75 * k), (cx - 62 * k, cy + 75 * k)], fill=bone)
        d.rounded_rectangle((cx - 58 * k, cy + 60 * k, cx + 58 * k, cy + 118 * k), radius=18 * k, fill=bone)
        for sx in (-1, 1):
            d.ellipse((cx + sx * 52 * k - 32 * k, cy - 40 * k, cx + sx * 52 * k + 32 * k, cy + 22 * k), fill=(18, 18, 18, 255))
        d.polygon([(cx, cy + 30 * k), (cx - 16 * k, cy + 60 * k), (cx + 16 * k, cy + 60 * k)], fill=(18, 18, 18, 255))
        for i in range(-3, 4):
            d.rectangle((cx + i * 18 * k - 6 * k, cy + 76 * k, cx + i * 18 * k + 6 * k, cy + 110 * k), fill=(18, 18, 18, 255))
        return (60, 60, 56)
    if kind == "crusader":
        red = (150, 24, 28, 255)
        a, b, t = 120 * k, 70 * k, 22 * k
        for ang in range(4):
            pts = [(0, -t), (a, -b), (a, b), (0, t)]
            ca, sa = math.cos(ang * math.pi / 2), math.sin(ang * math.pi / 2)
            d.polygon([(cx + x * ca - y * sa, cy + x * sa + y * ca) for x, y in pts], fill=red)
        return (60, 60, 56)
    if kind == "alien":
        grey = (176, 180, 176, 255)
        d.ellipse((cx - 95 * k, cy - 125 * k, cx + 95 * k, cy + 40 * k), fill=grey)
        d.polygon([(cx - 92 * k, cy - 10 * k), (cx + 92 * k, cy - 10 * k), (cx, cy + 125 * k)], fill=grey)
        # Yeux en amande inclinés.
        for sx in (-1, 1):
            ex, ey, rot = cx + sx * 46 * k, cy - 12 * k, math.radians(-30 * sx)
            pts = []
            for i in range(36):
                t = i * math.pi / 18
                x, y = 42 * k * math.cos(t), 18 * k * math.sin(t) * (1.25 if math.sin(t) > 0 else 0.8)
                pts.append((ex + x * math.cos(rot) - y * math.sin(rot), ey + x * math.sin(rot) + y * math.cos(rot)))
            d.polygon(pts, fill=(14, 14, 14, 255))
        return (60, 60, 56)
    if kind == "comspec":
        _emblem(d, W * 0.25, cy, H * 0.30, k)
        f = ImageFont.truetype(FONT_BOLD, int(42 * k))
        d.text((W * 0.44, cy - 24 * k), "COMSPEC", font=f, fill=(226, 232, 214, 255), anchor="lm")
        d.text((W * 0.44, cy + 34 * k), "MILSIM", font=ImageFont.truetype(FONT_BOLD, int(34 * k)), fill=(92, 199, 107, 255), anchor="lm")
        return (20, 22, 18)
    # name : bande nominative vierge, le texte (indicatif ou nom) est écrit par le jeu.
    return (40, 38, 30)


def patch_img(kind, size=512):
    """Patch brodé : contenu, sergé en diagonale, fils, bord surjeté, relief et ombre."""
    import numpy as np
    from PIL import ImageFont as _IF  # noqa: F401
    k = 2
    W, H = 480 * k, 320 * k
    face = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    border = _patch_content(kind, ImageDraw.Draw(face), W, H, k)
    mask = Image.new("L", (W, H), 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, W - 1, H - 1), radius=26 * k, fill=255)
    a = np.array(face).astype(float)
    yy, xx = np.mgrid[0:H, 0:W]
    twill = 1 + 0.07 * np.sin((xx + yy) * 0.9) + 0.04 * np.sin((xx - yy) * 0.33)
    noise = np.array(Image.effect_noise((W, H), 30).convert("L")).astype(float) / 255 - 0.5
    a[..., :3] = np.clip(a[..., :3] * (twill + noise * 0.18)[..., None], 0, 255)
    face = Image.fromarray(a.astype("uint8"), "RGBA")
    d = ImageDraw.Draw(face)
    # Bord surjeté : bande épaisse, points de couture obliques.
    bw = 18 * k
    d.rounded_rectangle((0, 0, W - 1, H - 1), radius=26 * k, outline=border + (255,), width=int(bw))
    step = 7 * k
    per = 2 * (W + H)
    for i in range(int(per / step)):
        t = i * step
        if t < W:
            x, y, dx, dy = t, bw / 2, 0, 1
        elif t < W + H:
            x, y, dx, dy = W - bw / 2, t - W, 1, 0
        elif t < 2 * W + H:
            x, y, dx, dy = W - (t - W - H), H - bw / 2, 0, 1
        else:
            x, y, dx, dy = bw / 2, H - (t - 2 * W - H), 1, 0
        sh = tuple(max(0, c - 40) for c in border) + (200,)
        d.line((x - dx * bw * 0.45 - dy * 4 * k, y - dy * bw * 0.45 - dx * 4 * k, x + dx * bw * 0.45 + dy * 4 * k, y + dy * bw * 0.45 + dx * 4 * k), fill=sh, width=k)
    face.putalpha(mask)
    # Relief : liseré clair en haut à gauche, sombre en bas à droite.
    hl = mask.filter(ImageFilter.GaussianBlur(6 * k))
    out = Image.new("RGBA", (size * k, size * k), (0, 0, 0, 0))
    ox, oy = (size * k - W) // 2, (size * k - H) // 2
    shadow = Image.new("RGBA", (W, H), (0, 0, 0, 150))
    shadow.putalpha(mask.point(lambda v: v * 150 // 255))
    sh_layer = Image.new("RGBA", out.size, (0, 0, 0, 0))
    sh_layer.alpha_composite(shadow, (ox + 6 * k, oy + 9 * k))
    out.alpha_composite(sh_layer.filter(ImageFilter.GaussianBlur(7 * k)))
    out.alpha_composite(face, (ox, oy))
    edge = np.array(mask).astype(float) - np.array(hl).astype(float)
    shade = Image.fromarray(np.clip(edge * 1.2, 0, 255).astype("uint8"), "L")
    dark = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    dark.putalpha(shade.point(lambda v: v * 90 // 255))
    out.alpha_composite(dark, (ox + 2 * k, oy + 2 * k))
    return out.resize((size, size), Image.LANCZOS)


# --- Tablette à touches sur le JPC (jpc_tablet.p3d, model_jpc_tablet.py) : atlas face en haut, dos en bas ---
JPC_CROP = (186, 508, 1864, 1474)  # même rectangle que model_jpc_tablet.CROP
JPC_HOLE = (455, 716, 1547, 1334)


def jpc_tablet_atlas(tmp):
    f = os.path.join(RENDER_DIR, "phone_keys_front.png")
    b = os.path.join(RENDER_DIR, "phone_keys_back.png")
    if not (os.path.exists(f) and os.path.exists(b)):
        print("rendus keys absents : python3 render_phones.py (RENDER_ONLY=keys)")
        return
    x0, y0, x1, y1 = JPC_CROP
    front = Image.open(f).convert("RGBA")
    # Écran éteint dans le trou (l'écran vivant est une sélection à part, posée devant).
    hx0, hy0, hx1, hy1 = JPC_HOLE
    under = Image.new("RGBA", front.size, (0, 0, 0, 0))
    under.paste(_glass(hx1 - hx0, hy1 - hy0), (hx0, hy0))
    under.alpha_composite(front)
    front = under.crop(JPC_CROP)
    rear = Image.open(b).convert("RGBA").crop((2048 - x1, y0, 2048 - x0, y1))
    rim = front.getpixel((1000 - x0, 1400 - y0))
    atlas = Image.new("RGBA", (2048, 2048), rim[:3] + (255,))
    for img, top in ((front, 0), (rear, 1024)):
        bg = Image.new("RGBA", img.size, rim[:3] + (255,))
        bg.alpha_composite(img)
        atlas.paste(bg.resize((2048, 1024), Image.LANCZOS), (0, top))
    convert(atlas.convert("RGB"), "jpc_tablet_co", tmp)


# Téléphones portés ouverts sur le JPC (model_jpc_phones.PHONES : mêmes rectangles) : rectangle recadré.
JPC_PHONES = {
    "s7": (206, 569, 1901, 1752),
    "rugged": (305, 558, 1697, 1555),
    "iphone": (388, 643, 1614, 1407),
    "plate": (145, 605, 1903, 1645),
    "flip": (195, 543, 1853, 1459),
    "ribbed": (240, 605, 1808, 1435),
}


def jpc_phone_atlas(tmp):
    """Atlas jpc_<modèle>_ca : face en haut, dos vu de derrière en bas, transparence = silhouette.
    S7 : coque coyote et variantes de teinte (od, black), dos de phone_back."""
    def pack(front, rear, crop):
        x0, y0, x1, y1 = crop
        hx0, hy0, hx1, hy1 = JPC_HOLE
        under = Image.new("RGBA", front.size, (0, 0, 0, 0))
        under.paste(_glass(hx1 - hx0, hy1 - hy0), (hx0, hy0))
        under.alpha_composite(front)
        atlas = Image.new("RGBA", (2048, 2048), (0, 0, 0, 0))
        atlas.paste(under.crop(crop).resize((2048, 1024), Image.LANCZOS), (0, 0))
        atlas.paste(rear.resize((2048, 1024), Image.LANCZOS), (0, 1024))
        return atlas

    for m, crop in JPC_PHONES.items():
        x0, y0, x1, y1 = crop
        if m == "s7":
            if not os.path.exists(PHONE_SRC):
                continue
            front = Image.open(PHONE_SRC).convert("RGBA")
            back = phone_back(PHONE_SRC)

            def s7_rear(img):
                # phone_back est déjà vu de derrière (miroir de la face), comme les rendus des autres modèles.
                return img.crop((2048 - x1, y0, 2048 - x0, y1))

            convert(pack(front, s7_rear(back), crop), "jpc_s7_ca", tmp)
            for key, tint in SHELL_TINTS.items():
                convert(pack(shell_tint(front, tint), s7_rear(shell_tint(back, tint)), crop), f"jpc_s7_{key}_ca", tmp)
            continue
        f = os.path.join(RENDER_DIR, f"phone_{m}_front.png")
        b = os.path.join(RENDER_DIR, f"phone_{m}_back.png")
        if not (os.path.exists(f) and os.path.exists(b)):
            continue
        rear = Image.open(b).convert("RGBA").crop((2048 - x1, y0, 2048 - x0, y1))
        convert(pack(Image.open(f).convert("RGBA"), rear, crop), f"jpc_{m}_ca", tmp)


def jpc_screen_static(tmp):
    """Écran de la tablette vu par les autres joueurs : carte ATAK figée (topo, barre d'état, outils, positions)."""
    img = topo_wallpaper(1024, 576, 11, (120, 170, 120)).convert("RGBA")
    d = ImageDraw.Draw(img)
    d.rectangle((0, 0, 1023, 26), fill=(0, 0, 0, 255))
    d.text((10, 5), "13:46", font=_font(16), fill=(230, 230, 230, 255))
    d.rectangle((960, 7, 1004, 19), outline=(230, 230, 230, 255), width=2)
    d.rectangle((962, 9, 994, 17), fill=(92, 199, 107, 255))
    d.rectangle((0, 27, 1023, 70), fill=(24, 30, 26, 235))
    for i in range(7):
        d.rounded_rectangle((640 + i * 52, 33, 680 + i * 52, 64), radius=6, fill=(70, 90, 76, 255))
    d.text((14, 38), "CARTE", font=_font(22), fill=(230, 235, 230, 255))
    for x, y in ((300, 260), (360, 300), (330, 340), (420, 280)):
        d.rectangle((x - 9, y - 9, x + 9, y + 9), fill=(40, 120, 230, 255), outline=(255, 255, 255, 255), width=2)
    for x, y in ((700, 200), (760, 240)):
        d.polygon([(x, y - 12), (x + 12, y), (x, y + 12), (x - 12, y)], fill=(220, 50, 40, 255), outline=(255, 255, 255, 255))
    d.ellipse((470, 390, 494, 414), fill=(92, 199, 107, 255), outline=(255, 255, 255, 255), width=2)
    d.rectangle((0, 520, 1023, 575), fill=(18, 22, 20, 230))
    d.text((14, 532), "31U DQ 12345 67890   ALT 112 m", font=_font(20), fill=(210, 220, 210, 255))
    # PAA : côtés en puissances de deux (l'écran étire la texture sur toute sa surface).
    convert(img.convert("RGB").resize((1024, 512), Image.LANCZOS), "jpc_screen_static_co", tmp)


def jpc_rvmats():
    """Écran lumineux (émissif) : jour plus fort, nuit atténuée (fn_jpcScreen choisit selon sunOrMoon)."""
    for name, em in (("jpc_screen", 3.0), ("jpc_screen_night", 1.2)):
        with open(os.path.join(OUT, name + ".rvmat"), "w", newline="\r\n") as fh:
            fh.write("ambient[]={0,0,0,1};\ndiffuse[]={0,0,0,1};\nforcedDiffuse[]={0,0,0,0};\n"
                     f"emmisive[]={{{em},{em},{em},1}};\nspecular[]={{0.05,0.05,0.05,1}};\nspecularPower=80;\n"
                     "PixelShaderID=\"Normal\";\nVertexShaderID=\"Basic\";\n")


def patch_assets(tmp):
    for kind in PATCHES:
        convert(patch_img(kind), f"patch_{kind}", tmp)


def convert(img, name, tmp):
    png = os.path.join(tmp, name + ".png")
    img.save(png)
    paa = os.path.join(OUT, name + ".paa")
    # hemtt ne remplace pas un PAA existant : on l'efface d'abord.
    if os.path.exists(paa):
        os.remove(paa)
    subprocess.run([HEMTT, "utils", "paa", "convert", png, paa], check=True, stdout=subprocess.DEVNULL)


def arsenal_assets(tmp):
    """Item téléphone (item_phone : Samsung S7 écran allumé) et bouton COMSPEC de l'arsenal ACE (arsenal_comspec)."""
    phone = Image.open(PHONE_SRC).convert("RGBA")
    wall = Image.open(os.path.join(HERE, "src", "wallpaper_dark.jpg")).convert("RGBA").resize((1546 - 455, 1333 - 716), Image.LANCZOS)
    under = Image.new("RGBA", phone.size, (0, 0, 0, 0))
    under.paste(wall, (455, 716))
    shot = Image.alpha_composite(under, phone).crop(phone.split()[3].getbbox())
    w, h = shot.size
    sq = Image.new("RGBA", (w, w), (0, 0, 0, 0))
    sq.paste(shot, (0, (w - h) // 2))
    convert(sq.resize((256, 256), Image.LANCZOS), "item_phone", tmp)
    # Bouton de l'arsenal : téléphone blanc en portrait (écran évidé) et « COMSPEC », comme les icônes d'ACE.
    S = 4
    icon = Image.new("RGBA", (128 * S, 128 * S), (0, 0, 0, 0))
    d = ImageDraw.Draw(icon)
    W = (255, 255, 255, 255)
    d.rounded_rectangle((36 * S, 4 * S, 92 * S, 96 * S), radius=10 * S, fill=W)
    d.rounded_rectangle((42 * S, 14 * S, 86 * S, 82 * S), radius=3 * S, fill=(0, 0, 0, 0))
    d.ellipse((60 * S, 86 * S, 68 * S, 92 * S), fill=(0, 0, 0, 0))
    d.rectangle((92 * S, 22 * S, 96 * S, 40 * S), fill=W)
    f = ImageFont.truetype("DejaVuSans-Bold.ttf", 21 * S)
    tw = d.textlength("COMSPEC", font=f)
    d.text(((128 * S - tw) / 2, 100 * S), "COMSPEC", font=f, fill=W)
    icon = icon.resize((128, 128), Image.LANCZOS)
    convert(icon, "arsenal_comspec", tmp)


def camera_item(size=256):
    """Caméra IP compacte (item_camera) : boîtier « bullet » sombre, objectif, pare-soleil, patte de fixation, LED."""
    k = 4
    S = size * k
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    # Patte murale et rotule
    d.rounded_rectangle([S * 0.06, S * 0.62, S * 0.20, S * 0.90], radius=S * 0.02, fill=(52, 56, 52, 255), outline=(110, 116, 108, 255), width=int(S * 0.006))
    d.polygon([(S * 0.18, S * 0.70), (S * 0.40, S * 0.56), (S * 0.44, S * 0.62), (S * 0.20, S * 0.80)], fill=(70, 74, 68, 255))
    d.ellipse([S * 0.36, S * 0.50, S * 0.48, S * 0.62], fill=(84, 88, 82, 255), outline=(130, 136, 128, 255), width=int(S * 0.006))
    # Boîtier
    d.rounded_rectangle([S * 0.20, S * 0.28, S * 0.84, S * 0.54], radius=S * 0.09, fill=(46, 52, 46, 255), outline=(120, 128, 118, 255), width=int(S * 0.008))
    d.rounded_rectangle([S * 0.24, S * 0.31, S * 0.70, S * 0.36], radius=S * 0.02, fill=(255, 255, 255, 30))
    # Pare-soleil
    d.polygon([(S * 0.22, S * 0.26), (S * 0.92, S * 0.20), (S * 0.92, S * 0.26), (S * 0.24, S * 0.31)], fill=(64, 70, 62, 255))
    # Objectif
    d.ellipse([S * 0.74, S * 0.29, S * 0.96, S * 0.53], fill=(24, 26, 24, 255), outline=(150, 156, 146, 255), width=int(S * 0.01))
    d.ellipse([S * 0.79, S * 0.34, S * 0.91, S * 0.48], fill=(20, 60, 90, 255))
    d.ellipse([S * 0.82, S * 0.36, S * 0.86, S * 0.40], fill=(220, 240, 255, 200))
    # LED et marquage
    d.ellipse([S * 0.28, S * 0.44, S * 0.32, S * 0.48], fill=(92, 199, 107, 255))
    d.text((S * 0.38, S * 0.405), "IP CAM", font=_font(int(S * 0.07)), fill=(170, 200, 176, 230))
    return img.resize((size, size), Image.LANCZOS)


def main():
    os.makedirs(OUT, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        if len(sys.argv) > 2 and sys.argv[2] == "antennas":
            antenna_assets(tmp)
            return
        if len(sys.argv) > 2 and sys.argv[2] == "arsenal":
            arsenal_assets(tmp)
            return
        # « accicon » : seulement l'icône de l'app Accessoires.
        if len(sys.argv) > 2 and sys.argv[2] == "accicon":
            convert(icon_png("app_accessories", ICONS["app_accessories"]), "app_accessories", tmp)
            return
        # « hand » : seulement l'icône main (pastille « prendre en main » du téléphone porté).
        if len(sys.argv) > 2 and sys.argv[2] == "hand":
            convert(icon_png("ui_hand", ICONS["ui_hand"]), "ui_hand", tmp)
            return
        # « only nom1,nom2 » : seulement ces icônes de ICONS.
        if len(sys.argv) > 3 and sys.argv[2] == "only":
            for name in sys.argv[3].split(","):
                convert(icon_png(name, ICONS[name]), name, tmp)
            return
        # « qsicons » : seulement les icônes du volet rapide.
        if len(sys.argv) > 2 and sys.argv[2] == "qsicons":
            for name, body in ICONS.items():
                if name.startswith("qs_"):
                    convert(icon_png(name, body), name, tmp)
            return
        # « athena » : seulement le fond ATHENA (logo du coin effacé) et sa variante floutée.
        if len(sys.argv) > 2 and sys.argv[2] == "athena":
            for suffix, (w, h) in (("land", (2048, 1024)), ("port", (1024, 2048))):
                img = photo_wallpaper(os.path.join(HERE, "src", "wallpaper_athena.jpg"), w, h, 0.85)
                convert(img.convert("RGB"), f"wall_athena_{suffix}", tmp)
                convert(blurred(img), f"wall_athena_blur_{suffix}", tmp)
            return
        # « galleryicon » : seulement l'icône de l'app Galerie.
        if len(sys.argv) > 2 and sys.argv[2] == "galleryicon":
            convert(icon_png("app_gallery", ICONS["app_gallery"]), "app_gallery", tmp)
            return
        # « planicon » : seulement l'icône de l'app Plan.
        if len(sys.argv) > 2 and sys.argv[2] == "planicon":
            convert(icon_png("app_plan", ICONS["app_plan"]), "app_plan", tmp)
            return
        # « camera » : seulement l'image de l'item Caméra IP compacte.
        # « jpc » : tablette à touches (coques 2D, dos, atlas du modèle 3D, écran figé, rvmat de l'écran).
        if len(sys.argv) > 2 and sys.argv[2] == "jpc":
            model_assets(tmp, only=["keys"])
            jpc_tablet_atlas(tmp)
            jpc_phone_atlas(tmp)
            jpc_screen_static(tmp)
            jpc_rvmats()
            return
        # « cases » : coques de référence (support JPC, étui multicam, coque nervurée).
        if len(sys.argv) > 2 and sys.argv[2] == "cases":
            model_assets(tmp, only=["plate", "flip", "ribbed"])
            return
        if len(sys.argv) > 2 and sys.argv[2] == "camera":
            convert(camera_item(), "item_camera", tmp)
            return
        # Écran abîmé (fêlures, encre, lignes mortes, verre brisé) ; « cracks » ne régénère que ceux-là.
        damage_overlays(tmp)
        if len(sys.argv) > 2 and sys.argv[2] == "cracks":
            return
        convert(frs_bar(), "frs_bar", tmp)
        convert(disc(), "ui_disc", tmp)
        power_assets(tmp)
        datalink_assets(tmp)
        antenna_assets(tmp)
        patch_assets(tmp)
        accessory_assets(tmp)
        model_assets(tmp)
        jpc_tablet_atlas(tmp)
        jpc_screen_static(tmp)
        jpc_rvmats()
        back_variants(tmp)
        shell_variants(tmp)
        if len(sys.argv) > 2 and sys.argv[2] == "datalink":
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
