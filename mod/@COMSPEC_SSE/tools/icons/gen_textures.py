"""
Textures des previews d'items SSE (lot 1) : écrans, pages, étiquettes, réglet, chevalets.
Tout est fictif (pays d'Arma : Takistan, Altis), aucune donnée réelle.

    python3 gen_textures.py   ->  tex/*.png
"""
import math
import os
import random

from PIL import Image, ImageDraw, ImageFilter, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "tex")
os.makedirs(OUT, exist_ok=True)
random.seed(7)

F = "/usr/share/fonts/truetype/"


def font(name, size):
    paths = {
        "sans": F + "dejavu/DejaVuSans.ttf",
        "sansb": F + "dejavu/DejaVuSans-Bold.ttf",
        "obl": F + "freefont/FreeSerifItalic.ttf",
        "serif": F + "dejavu/DejaVuSerif.ttf",
        "serifb": F + "dejavu/DejaVuSerif-Bold.ttf",
        "mono": F + "dejavu/DejaVuSansMono.ttf",
        "monob": F + "dejavu/DejaVuSansMono-Bold.ttf",
        "cond": F + "liberation/LiberationSans-Regular.ttf",
        "condb": F + "liberation/LiberationSans-Bold.ttf",
    }
    return ImageFont.truetype(paths[name], size)


def save(img, name):
    img.save(os.path.join(OUT, name))


def paper(w, h, base=(238, 233, 220), grain=10, seed=0):
    rnd = random.Random(seed)
    img = Image.new("RGB", (w, h), base)
    px = img.load()
    for y in range(0, h, 2):
        for x in range(0, w, 2):
            d = rnd.randint(-grain, grain)
            c = tuple(max(0, min(255, v + d)) for v in base)
            px[x, y] = c
    return img.filter(ImageFilter.GaussianBlur(0.8))


def handwriting(draw, xy, text, size=34, ink=(28, 40, 110), seed=1, width=None):
    """Écriture manuscrite approximative : glyphes obliques, jitter vertical, épaisseur variable."""
    rnd = random.Random(seed)
    x0, y = xy
    x = x0
    f = font("obl", size)
    for word in text.split(" "):
        wlen = draw.textlength(word + " ", font=f)
        if width and x + wlen > x0 + width:
            x = x0 + rnd.randint(-4, 4)
            y += int(size * 1.45)
        for ch in word + " ":
            dy = rnd.uniform(-2.5, 2.5)
            draw.text((x + rnd.uniform(-0.8, 0.8), y + dy), ch, font=f, fill=ink)
            x += draw.textlength(ch, font=f) * rnd.uniform(0.86, 0.98)
    return y


# --- Smartphone : écran verrouillé avec notifications -------------------------------------------
def smartphone_screen():
    w, h = 540, 1140
    img = Image.new("RGB", (w, h))
    d = ImageDraw.Draw(img)
    for y in range(h):
        t = y / h
        d.line([(0, y), (w, y)], fill=(int(18 + 30 * t), int(32 + 20 * t), int(58 + 30 * (1 - t))))
    # bokeh discret
    for _ in range(18):
        cx, cy, r = random.randint(0, w), random.randint(0, h), random.randint(30, 110)
        ov = Image.new("L", (w, h), 0)
        ImageDraw.Draw(ov).ellipse([cx - r, cy - r, cx + r, cy + r], fill=random.randint(10, 26))
        img.paste((120, 150, 200), (0, 0), ov.filter(ImageFilter.GaussianBlur(20)))
    d = ImageDraw.Draw(img)
    d.text((30, 22), "Tak-Cell  4G", font=font("sans", 22), fill=(230, 230, 235))
    d.text((w - 120, 22), "37 %", font=font("sans", 22), fill=(230, 230, 235))
    d.text((w // 2, 190), "04:17", font=font("sans", 150), fill=(245, 245, 250), anchor="mm")
    d.text((w // 2, 300), "mardi 14 octobre", font=font("sans", 30), fill=(220, 225, 235), anchor="mm")
    notes = [("Messages", "Abu Karim", "Le colis part ce soir, route du nord."),
             ("Appel manqué", "+992 93 41 07 22", "3 appels manqués"),
             ("Messages", "Farid", "Change de numéro après."),
             ]
    y = 460
    for app, who, msg in notes:
        d.rounded_rectangle([24, y, w - 24, y + 120], 22, fill=(235, 238, 245))
        d.text((44, y + 14), app.upper(), font=font("sansb", 16), fill=(90, 95, 110))
        d.text((w - 90, y + 14), "maint.", font=font("sans", 16), fill=(90, 95, 110))
        d.text((44, y + 40), who, font=font("sansb", 24), fill=(20, 22, 30))
        d.text((44, y + 76), msg, font=font("sans", 20), fill=(50, 52, 60))
        y += 136
    d.ellipse([w // 2 - 34, h - 150, w // 2 + 34, h - 82], outline=(230, 230, 235), width=4)
    d.text((w // 2, h - 50), "Déverrouiller", font=font("sans", 20), fill=(220, 220, 230), anchor="mm")
    save(img, "smartphone_screen.png")


# --- Téléphone basique : écran LCD + clavier ----------------------------------------------------
def basic_phone():
    w, h = 256, 200
    img = Image.new("RGB", (w, h), (150, 178, 120))
    d = ImageDraw.Draw(img)
    d.text((8, 6), "Yll", font=font("monob", 16), fill=(30, 40, 20))
    d.text((w - 46, 6), "▮▮▯", font=font("mono", 16), fill=(30, 40, 20))
    d.text((w // 2, 80), "12:52", font=font("monob", 54), fill=(25, 35, 18), anchor="mm")
    d.text((w // 2, 132), "1 nouveau msg", font=font("mono", 18), fill=(25, 35, 18), anchor="mm")
    d.text((10, h - 28), "Menu", font=font("monob", 18), fill=(25, 35, 18))
    d.text((w - 70, h - 28), "Noms", font=font("monob", 18), fill=(25, 35, 18))
    save(img, "basic_screen.png")
    # touches
    k = Image.new("RGB", (512, 512), (36, 36, 38))
    d = ImageDraw.Draw(k)
    labels = ["1", "2 abc", "3 def", "4 ghi", "5 jkl", "6 mno", "7 pqrs", "8 tuv", "9 wxyz", "*", "0 +", "#"]
    for i, lab in enumerate(labels):
        cx, cy = 85 + (i % 3) * 171, 64 + (i // 3) * 128
        d.rounded_rectangle([cx - 72, cy - 50, cx + 72, cy + 50], 30, fill=(58, 58, 62))
        parts = lab.split(" ")
        d.text((cx, cy - 8), parts[0], font=font("sansb", 50), fill=(225, 225, 225), anchor="mm")
        if len(parts) > 1:
            d.text((cx, cy + 32), parts[1], font=font("sans", 20), fill=(170, 170, 170), anchor="mm")
    save(k, "basic_keys.png")


# --- Radio portative : LCD ----------------------------------------------------------------------
def radio_lcd():
    w, h = 256, 128
    img = Image.new("RGB", (w, h), (90, 160, 210))
    d = ImageDraw.Draw(img)
    d.text((8, 4), "A", font=font("monob", 22), fill=(10, 25, 45))
    d.text((40, 6), "446.0062", font=font("monob", 40), fill=(10, 25, 45))
    d.text((8, 60), "B", font=font("monob", 22), fill=(10, 25, 45))
    d.text((40, 62), "145.5000", font=font("monob", 40), fill=(10, 25, 45))
    d.text((8, 106), "VOX  H  ▮▮▮▯", font=font("mono", 16), fill=(10, 25, 45))
    save(img, "radio_lcd.png")


def satphone_lcd():
    w, h = 256, 192
    img = Image.new("RGB", (w, h), (8, 10, 14))
    d = ImageDraw.Draw(img)
    d.text((10, 8), "GMPCS", font=font("sansb", 18), fill=(120, 200, 255))
    d.text((w - 70, 8), "GPS", font=font("sansb", 18), fill=(120, 255, 140))
    d.text((w // 2, 80), "Recherche", font=font("sans", 26), fill=(230, 230, 230), anchor="mm")
    d.text((w // 2, 112), "satellite…", font=font("sans", 26), fill=(230, 230, 230), anchor="mm")
    d.text((w // 2, 160), "N 33°41'  E 66°02'", font=font("mono", 16), fill=(160, 160, 160), anchor="mm")
    save(img, "satphone_lcd.png")


# --- Portable : bureau Windows-like (fictif) ----------------------------------------------------
def laptop_screen():
    w, h = 1280, 800
    img = Image.new("RGB", (w, h))
    d = ImageDraw.Draw(img)
    for y in range(h):
        t = y / h
        d.line([(0, y), (w, y)], fill=(int(30 + 40 * t), int(70 + 30 * t), int(110 + 40 * t)))
    for i, name in enumerate(["Documents", "Photos_2025", "contacts.xlsx", "itineraire.kml", "Corbeille"]):
        x, y = 40, 30 + i * 110
        d.rounded_rectangle([x + 18, y, x + 78, y + 56], 6, fill=(240, 200, 90) if i < 2 else (235, 235, 240))
        d.text((x + 48, y + 74), name, font=font("sans", 16), fill=(255, 255, 255), anchor="mm")
    # fenêtre de mail
    d.rectangle([330, 120, 1180, 660], fill=(250, 250, 252))
    d.rectangle([330, 120, 1180, 160], fill=(40, 90, 160))
    d.text((350, 130), "Messagerie - Boîte de réception (3)", font=font("sans", 20), fill=(255, 255, 255))
    rows = [("Abu Karim", "RE: livraison jeudi", "13:04"),
            ("transport.kabir@mail.tk", "Facture n°2291 - camion", "11:52"),
            ("Farid", "photos du site", "hier"),
            ("Banque Nationale TK", "Votre virement a été effectué", "lun."),
            ]
    y = 175
    for who, sub, t in rows:
        d.text((350, y), who, font=font("sansb", 18), fill=(20, 20, 30))
        d.text((650, y), sub, font=font("sans", 18), fill=(40, 40, 50))
        d.text((1110, y), t, font=font("sans", 16), fill=(100, 100, 110))
        d.line([(340, y + 34), (1170, y + 34)], fill=(225, 225, 230))
        y += 46
    d.text((350, 390), "Rendez-vous au dépôt de Sakhe, entrée est. Venir seul.", font=font("sans", 20), fill=(30, 30, 40))
    d.rectangle([0, h - 44, w, h], fill=(25, 28, 36))
    d.text((w - 120, h - 34), "04:21", font=font("sans", 18), fill=(230, 230, 230))
    save(img, "laptop_screen.png")


# --- Carnet : double page manuscrite ------------------------------------------------------------
def notebook_pages():
    w, h = 1400, 1000
    img = paper(w, h, (236, 228, 205), 8, 3)
    d = ImageDraw.Draw(img)
    for y in range(110, h - 40, 52):
        d.line([(30, y), (w // 2 - 30, y)], fill=(150, 170, 200), width=2)
        d.line([(w // 2 + 30, y), (w - 30, y)], fill=(150, 170, 200), width=2)
    d.line([(w // 2, 0), (w // 2, h)], fill=(180, 170, 150), width=3)
    lines_l = ["14/10  Karim -> dépôt nord", "camion blanc Hilux", "plaque 47-219 TK",
               "2 caisses + bidons", "appeler F. avant 22h", "+992 93 41 07 22", "", "mot de passe : falcon77"]
    lines_r = ["Route : Sakhe -> Garmsar", "point de RDV = puits", "(voir carte, croix rouge)", "",
               "Payer 4000 $ moitié avant", "Ne plus utiliser l'ancien", "téléphone.", ""]
    y = 70
    for i, l in enumerate(lines_l):
        handwriting(d, (60, y + 4), l, 34, seed=10 + i)
        y += 52
    y = 70
    for i, l in enumerate(lines_r):
        handwriting(d, (w // 2 + 60, y + 4), l, 34, seed=40 + i, ink=(30, 30, 40))
        y += 52
    # croquis
    d.line([(w // 2 + 120, 640), (w // 2 + 420, 720), (w // 2 + 560, 860)], fill=(30, 30, 40), width=4)
    d.ellipse([w // 2 + 390, 690, w // 2 + 450, 750], outline=(30, 30, 40), width=4)
    d.line([(w // 2 + 520, 820), (w // 2 + 580, 880)], fill=(190, 30, 30), width=6)
    d.line([(w // 2 + 580, 820), (w // 2 + 520, 880)], fill=(190, 30, 30), width=6)
    save(img, "notebook_pages.png")
    c = paper(512, 512, (40, 34, 30), 6, 4)
    save(c, "notebook_cover.png")


# --- Passeport (page de données) et carte d'identité fictifs ------------------------------------
def face(size, seed=5):
    """Silhouette photo d'identité neutre (pas de visage réel)."""
    rnd = random.Random(seed)
    img = Image.new("RGB", size, (205, 212, 218))
    d = ImageDraw.Draw(img)
    w, h = size
    d.ellipse([w * 0.12, h * 0.62, w * 0.88, h * 1.25], fill=(60, 62, 70))
    d.rectangle([w * 0.42, h * 0.52, w * 0.58, h * 0.7], fill=(176, 132, 100))
    d.ellipse([w * 0.28, h * 0.16, w * 0.72, h * 0.64], fill=(184, 140, 106))
    d.chord([w * 0.27, h * 0.1, w * 0.73, h * 0.48], 180, 360, fill=(30, 26, 24))
    d.ellipse([w * 0.3, h * 0.36, w * 0.7, h * 0.7], fill=None)
    d.pieslice([w * 0.3, h * 0.4, w * 0.7, h * 0.72], 0, 180, fill=(40, 34, 30))  # barbe
    d.ellipse([w * 0.38, h * 0.36, w * 0.44, h * 0.4], fill=(30, 25, 22))
    d.ellipse([w * 0.56, h * 0.36, w * 0.62, h * 0.4], fill=(30, 25, 22))
    return img.filter(ImageFilter.GaussianBlur(1.6))


def guilloche(d, box, color, n=26):
    x0, y0, x1, y1 = box
    for k in range(n):
        pts = []
        for i in range(120):
            t = i / 119
            x = x0 + (x1 - x0) * t
            y = (y0 + y1) / 2 + math.sin(t * 9 + k * 0.4) * (y1 - y0) * 0.42 * math.cos(k * 0.21)
            pts.append((x, y))
        d.line(pts, fill=color, width=1)


def passport():
    w, h = 1250, 880
    img = paper(w, h, (226, 230, 218), 6, 8)
    d = ImageDraw.Draw(img)
    guilloche(d, (0, 0, w, h), (196, 210, 200), 40)
    d.text((40, 30), "RÉPUBLIQUE ISLAMIQUE DU TAKISTAN  ·  ISLAMIC REPUBLIC OF TAKISTAN", font=font("sansb", 26), fill=(40, 70, 60))
    d.text((40, 70), "PASSEPORT / PASSPORT", font=font("sans", 22), fill=(40, 70, 60))
    img.paste(face((300, 380)), (50, 140))
    fields = [("Type / Type", "P"), ("Code / Code", "TKS"), ("N° du passeport / Passport No.", "K0471932"),
              ("Nom / Surname", "RAHIMI"), ("Prénoms / Given names", "ABDUL KARIM"),
              ("Date de naissance / Date of birth", "03 MAR 1984"), ("Lieu de naissance / Place of birth", "GARMSAR"),
              ("Date de délivrance / Date of issue", "12 JUN 2019"), ("Date d'expiration / Date of expiry", "11 JUN 2029")]
    y = 130
    for lab, val in fields:
        d.text((400, y), lab, font=font("sans", 16), fill=(70, 90, 80))
        d.text((400, y + 20), val, font=font("monob", 30), fill=(20, 20, 24))
        y += 66
    d.rectangle([0, h - 150, w, h], fill=(232, 234, 226))
    mrz1 = "P<TKSRAHIMI<<ABDUL<KARIM<<<<<<<<<<<<<<<<<<<"
    mrz2 = "K0471932<7TKS8403038M2906115<<<<<<<<<<<<<<04"
    d.text((40, h - 130), mrz1, font=font("mono", 38), fill=(25, 25, 28))
    d.text((40, h - 72), mrz2, font=font("mono", 38), fill=(25, 25, 28))
    save(img, "passport_data.png")
    cov = Image.new("RGB", (512, 720), (34, 64, 46))
    d = ImageDraw.Draw(cov)
    d.text((256, 110), "TAKISTAN", font=font("serifb", 54), fill=(200, 170, 90), anchor="mm")
    d.ellipse([156, 250, 356, 450], outline=(200, 170, 90), width=6)
    d.regular_polygon((256, 350, 60), 5, fill=(200, 170, 90))
    d.text((256, 560), "PASSEPORT", font=font("serif", 44), fill=(200, 170, 90), anchor="mm")
    d.text((256, 620), "PASSPORT", font=font("serif", 36), fill=(200, 170, 90), anchor="mm")
    save(cov, "passport_cover.png")

    w, h = 856, 540
    idc = Image.new("RGB", (w, h), (226, 222, 205))
    d = ImageDraw.Draw(idc)
    guilloche(d, (0, 0, w, h), (214, 200, 170), 30)
    d.rectangle([0, 0, w, 70], fill=(120, 40, 36))
    d.text((24, 18), "TAKISTAN · CARTE NATIONALE D'IDENTITÉ", font=font("sansb", 28), fill=(245, 230, 200))
    idc.paste(face((220, 280), 9), (30, 100))
    for i, (lab, val) in enumerate([("NOM", "RAHIMI"), ("PRÉNOM", "ABDUL KARIM"), ("NÉ LE", "03.03.1984"),
                                    ("N°", "TK-0091-44187"), ("DISTRICT", "GARMSAR")]):
        d.text((290, 100 + i * 78), lab, font=font("sans", 18), fill=(110, 80, 60))
        d.text((290, 122 + i * 78), val, font=font("monob", 32), fill=(25, 22, 20))
    save(idc, "idcard.png")


# --- Dossier : document dactylographié avec tampon ----------------------------------------------
def document():
    w, h = 1000, 1414
    img = paper(w, h, (240, 237, 228), 7, 11)
    d = ImageDraw.Draw(img)
    d.text((80, 80), "ENTREPRISE DE TRANSPORT AL-KABIR", font=font("serifb", 34), fill=(25, 25, 30))
    d.text((80, 128), "Garmsar - Takistan · tél. +992 93 40 11 06", font=font("serif", 22), fill=(60, 60, 64))
    d.line([(80, 170), (w - 80, 170)], fill=(30, 30, 30), width=3)
    d.text((80, 210), "BON DE CHARGEMENT N° 2291", font=font("serifb", 30), fill=(25, 25, 30))
    body = ["Date : 12/10", "Véhicule : Toyota Hilux blanc, plaque 47-219 TK", "Chauffeur : R. Abdul Karim",
            "Origine : dépôt nord, Sakhe", "Destination : Garmsar (puits)", "",
            "Désignation                         Qté      Poids",
            "Caisses bois (pièces détachées)      2      84 kg",
            "Bidons 20 L                          6     120 kg",
            "Sacs engrais (nitrate)              10     500 kg",
            "Câble électrique, bobine             1      15 kg", "",
            "Paiement : 4 000 USD, moitié à la commande.", "Contact sur place : Farid."]
    y = 290
    for l in body:
        d.text((80, y), l, font=font("mono", 24), fill=(30, 30, 34))
        y += 44
    # tampon
    st = Image.new("RGBA", (360, 360), (0, 0, 0, 0))
    sd = ImageDraw.Draw(st)
    sd.ellipse([10, 10, 350, 350], outline=(40, 60, 150, 200), width=10)
    sd.ellipse([50, 50, 310, 310], outline=(40, 60, 150, 200), width=4)
    sd.text((180, 180), "AL-KABIR", font=font("sansb", 44), fill=(40, 60, 150, 200), anchor="mm")
    st = st.rotate(-18, expand=True)
    img.paste(st, (560, 950), st)
    handwriting(d, (120, 1130), "Vu - ok pour jeudi", 40, seed=77)
    d.line([(120, 1230), (340, 1200), (380, 1250), (460, 1190)], fill=(28, 40, 110), width=4)
    save(img, "document.png")
    f = Image.new("RGB", (1024, 1024), (186, 156, 104))
    d = ImageDraw.Draw(f)
    for _ in range(4000):
        x, y = random.randint(0, 1023), random.randint(0, 1023)
        c = random.randint(-12, 12)
        d.point((x, y), fill=(186 + c, 156 + c, 104 + c))
    d.rectangle([300, 120, 720, 220], fill=(240, 236, 226))
    handwriting(d, (320, 140), "TRANSPORT 2025", 44, seed=5, ink=(20, 20, 20))
    save(f.filter(ImageFilter.GaussianBlur(0.6)), "folder.png")


# --- Sac à scellés : étiquette de traçabilité ---------------------------------------------------
def evidence_label():
    w, h = 900, 1100
    img = Image.new("RGB", (w, h), (250, 250, 248))
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, w, 120], fill=(200, 30, 36))
    d.text((w // 2, 60), "SCELLÉ · EVIDENCE", font=font("sansb", 54), fill=(255, 255, 255), anchor="mm")
    rows = [("N° de scellé", "SSE-2026-0007-04"), ("Site", "Objectif FALCON, bât. B"), ("Objet", "Clé USB 32 Go noire"),
            ("Trouvé par", "Sgt ...........  Équipe 2"), ("Date / heure", "14/10  04:32"), ("Grille", "41S PU 1234 5678")]
    y = 160
    for lab, val in rows:
        d.text((40, y), lab.upper(), font=font("sansb", 22), fill=(120, 120, 120))
        d.line([(40, y + 82), (w - 40, y + 82)], fill=(160, 160, 160), width=2)
        handwriting(d, (50, y + 34), val, 36, seed=y, ink=(20, 24, 70))
        y += 108
    d.text((40, y + 10), "CHAÎNE DE CUSTODY", font=font("sansb", 24), fill=(200, 30, 36))
    for i in range(3):
        d.rectangle([40, y + 50 + i * 54, w - 40, y + 100 + i * 54], outline=(160, 160, 160), width=2)
    # code-barres
    x = 60
    while x < w - 60:
        bw = random.choice([3, 3, 6, 9])
        d.rectangle([x, h - 120, x + bw, h - 40], fill=(10, 10, 10))
        x += bw + random.choice([3, 5, 7])
    save(img, "evidence_label.png")
    t = Image.new("RGB", (2048, 128), (205, 30, 36))
    d = ImageDraw.Draw(t)
    for i in range(0, 2048, 512):
        d.text((i + 20, 34), "SCELLÉ - NE PAS OUVRIR  ·", font=font("sansb", 50), fill=(255, 255, 255))
    save(t, "seal_tape.png")


# --- Portefeuille : billet fictif, carte SIM, microSD, clé USB ----------------------------------
def small_items():
    w, h = 1024, 480
    img = Image.new("RGB", (w, h), (176, 196, 170))
    d = ImageDraw.Draw(img)
    guilloche(d, (0, 0, w, h), (150, 176, 150), 30)
    d.text((40, 30), "BANQUE CENTRALE DU TAKISTAN", font=font("serifb", 34), fill=(40, 60, 50))
    d.text((w - 60, 40), "1000", font=font("serifb", 70), fill=(40, 60, 50), anchor="ra")
    d.ellipse([620, 140, 900, 420], outline=(80, 110, 90), width=8)
    d.text((760, 280), "1000", font=font("serifb", 70), fill=(80, 110, 90), anchor="mm")
    d.text((40, h - 80), "MILLE AFGHANIS TAKISTANAIS  ·  AA 0372291", font=font("mono", 26), fill=(40, 60, 50))
    save(img, "banknote.png")
    s = Image.new("RGB", (512, 320), (240, 240, 240))
    d = ImageDraw.Draw(s)
    d.rectangle([0, 0, 512, 320], fill=(232, 80, 40))
    d.text((24, 20), "Tak-Cell", font=font("sansb", 48), fill=(255, 255, 255))
    d.text((24, 260), "8999 2301 4471 0072", font=font("mono", 26), fill=(255, 255, 255))
    d.rounded_rectangle([300, 90, 470, 210], 14, fill=(212, 180, 90))
    for i in range(1, 4):
        d.line([(300, 90 + i * 30), (470, 90 + i * 30)], fill=(150, 120, 50), width=3)
    d.line([(385, 90), (385, 210)], fill=(150, 120, 50), width=3)
    save(s, "simcard.png")
    u = Image.new("RGB", (512, 128), (20, 20, 22))
    d = ImageDraw.Draw(u)
    d.text((30, 30), "DataGo  32GB", font=font("sansb", 52), fill=(210, 210, 215))
    save(u, "usb_label.png")
    sd = Image.new("RGB", (256, 320), (24, 24, 28))
    d = ImageDraw.Draw(sd)
    d.text((128, 90), "micro", font=font("sans", 40), fill=(220, 220, 220), anchor="mm")
    d.text((128, 160), "SD", font=font("sansb", 80), fill=(220, 220, 220), anchor="mm")
    d.text((128, 250), "64GB", font=font("sansb", 50), fill=(220, 60, 50), anchor="mm")
    save(sd, "microsd.png")
    hd = Image.new("RGB", (512, 512), (24, 24, 26))
    d = ImageDraw.Draw(hd)
    d.text((256, 220), "StoreJet", font=font("sansb", 64), fill=(170, 170, 175), anchor="mm")
    d.text((256, 300), "1 TB", font=font("sans", 40), fill=(140, 140, 145), anchor="mm")
    save(hd, "hdd_label.png")


# --- Réglet photo ABFO + chevalet numéroté ------------------------------------------------------
def forensic():
    w, h = 2000, 200
    img = Image.new("RGB", (w, h), (246, 246, 244))
    d = ImageDraw.Draw(img)
    for mm in range(0, 201):
        x = int(mm * (w / 200))
        ln = 70 if mm % 10 == 0 else (45 if mm % 5 == 0 else 26)
        d.line([(x, 0), (x, ln)], fill=(10, 10, 10), width=3 if mm % 10 == 0 else 2)
        if mm % 10 == 0 and mm < 200:
            d.text((x + 6, 76), str(mm // 10), font=font("sansb", 34), fill=(10, 10, 10))
    for i in range(0, 20, 2):
        d.rectangle([i * 100, 150, i * 100 + 100, 200], fill=(10, 10, 10))
    d.text((w - 360, 100), "SSE · cm", font=font("sansb", 34), fill=(200, 30, 36))
    save(img, "ruler.png")
    for n in range(1, 25):
        p = Image.new("RGB", (256, 256), (250, 200, 30))
        d = ImageDraw.Draw(p)
        d.text((128, 128), str(n), font=font("sansb", 160), fill=(10, 10, 10), anchor="mm")
        save(p, f"placard_{n:02d}.png")


def mat():
    """Tapis de table d'exploitation gris foncé, légère texture."""
    w = h = 1024
    img = Image.new("RGB", (w, h), (58, 60, 62))
    px = img.load()
    rnd = random.Random(2)
    for y in range(h):
        for x in range(w):
            v = 58 + rnd.randint(-6, 6)
            px[x, y] = (v, v + 2, v + 4)
    img = img.filter(ImageFilter.GaussianBlur(1.2))
    save(img, "mat.png")



# --- Atlas de touches (4 x 4) : chiffres et touches de fonction --------------------------------
KEYS = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "*", "0", "#", "MENU", "EXIT", "A/B", "BAND"]


def key_atlas(name, bg, fg, sub=(160, 160, 160)):
    n = 128
    img = Image.new("RGB", (n * 4, n * 4), bg)
    d = ImageDraw.Draw(img)
    letters = {"2": "abc", "3": "def", "4": "ghi", "5": "jkl", "6": "mno", "7": "pqrs", "8": "tuv", "9": "wxyz", "0": "+"}
    for i, k in enumerate(KEYS):
        cx, cy = (i % 4) * n + n // 2, (i // 4) * n + n // 2
        big = len(k) == 1
        d.text((cx, cy - (8 if k in letters else 0)), k, font=font("sansb", 58 if big else 30), fill=fg, anchor="mm")
        if k in letters:
            d.text((cx, cy + 34), letters[k], font=font("sans", 22), fill=sub, anchor="mm")
    save(img, name)


def sat_extras():
    w, h = 512, 96
    img = Image.new("RGB", (w, h), (14, 15, 16))
    d = ImageDraw.Draw(img)
    d.text((w // 2, h // 2), "SATCOM  GMR", font=font("sansb", 44), fill=(150, 152, 156), anchor="mm")
    save(img, "sat_brand.png")
    img = Image.new("RGB", (w, h), (20, 21, 22))
    d = ImageDraw.Draw(img)
    d.text((w // 2, h // 2), "DUAL BAND  FM TRANSCEIVER", font=font("sansb", 30), fill=(170, 172, 176), anchor="mm")
    save(img, "radio_brand.png")



# --- Caches : bois de caisse, pochoirs, emballages ---------------------------------------------
def wood(w, h, base, seed, planks=4, paint=None):
    rnd = random.Random(seed)
    img = Image.new("RGB", (w, h), base)
    d = ImageDraw.Draw(img)
    ph = h // planks
    for p in range(planks):
        tone = rnd.randint(-14, 14)
        for y in range(p * ph, (p + 1) * ph):
            for k in range(0, w, 3):
                g = int(10 * math.sin(k * 0.013 + y * 0.21 + p * 3 + rnd.random() * 0.3))
                c = tuple(max(0, min(255, v + tone + g)) for v in base)
                d.line([(k, y), (k + 3, y)], fill=c)
        d.line([(0, (p + 1) * ph - 1), (w, (p + 1) * ph - 1)], fill=tuple(int(v * 0.45) for v in base), width=4)
        for _ in range(2):
            kx, ky = rnd.randint(40, w - 40), rnd.randint(p * ph + 10, (p + 1) * ph - 10)
            d.ellipse([kx - 9, ky - 5, kx + 9, ky + 5], fill=tuple(int(v * 0.6) for v in base))
    if paint:
        ov = Image.new("RGB", (w, h), paint)
        mask = Image.new("L", (w, h), 200)
        md = ImageDraw.Draw(mask)
        for _ in range(260):
            x, y = rnd.randint(0, w), rnd.randint(0, h)
            md.ellipse([x - rnd.randint(4, 30), y - rnd.randint(2, 8), x + rnd.randint(4, 30), y + rnd.randint(2, 8)], fill=rnd.randint(150, 190))
        img = Image.composite(ov, img, mask.filter(ImageFilter.GaussianBlur(2)))
    return img


def stencil(img, xy, text, size, fill, anchor="mm"):
    d = ImageDraw.Draw(img)
    d.text(xy, text, font=font("sansb", size), fill=fill, anchor=anchor)


def caches():
    # caisse d'armes, flanc : bois peint vert, pochoir cyrillique (fictif)
    side = wood(1024, 512, (128, 104, 70), 1, 4, paint=(70, 84, 52))
    stencil(side, (512, 170), "АВТОМАТ 7,62  —  2 ШТ.", 62, (232, 228, 214))
    stencil(side, (512, 290), "ПАРТ. 14-87   ВЕС 21 КГ", 46, (232, 228, 214))
    stencil(side, (512, 400), "ЛОТ 0372 / TK", 40, (232, 228, 214))
    save(side.filter(ImageFilter.GaussianBlur(0.7)), "crate_side.png")
    save(wood(1024, 1024, (128, 104, 70), 2, 7, paint=(70, 84, 52)).filter(ImageFilter.GaussianBlur(0.7)), "crate_wood.png")
    save(wood(1024, 1024, (176, 140, 92), 3, 7).filter(ImageFilter.GaussianBlur(0.7)), "wood_raw.png")
    save(wood(1024, 256, (92, 40, 22), 5, 1).filter(ImageFilter.GaussianBlur(0.8)), "stock_wood.png")
    side2 = wood(1024, 512, (128, 104, 70), 6, 4, paint=(70, 84, 52))
    stencil(side2, (512, 170), "ПАТРОНЫ 7,62 × 39", 62, (232, 228, 214))
    stencil(side2, (512, 290), "2 × 1080 ШТ.   ВЕС 32 КГ", 46, (232, 228, 214))
    stencil(side2, (512, 400), "ЛОТ 188-81 / TK", 40, (232, 228, 214))
    save(side2.filter(ImageFilter.GaussianBlur(0.7)), "crate_ammo_side.png")
    # caisse à munitions métallique (olive) : pochoir jaune
    can = Image.new("RGB", (1024, 512), (72, 80, 52))
    d = ImageDraw.Draw(can)
    for _ in range(3000):
        x, y = random.randint(0, 1023), random.randint(0, 511)
        c = random.randint(-8, 8)
        d.point((x, y), fill=(72 + c, 80 + c, 52 + c))
    stencil(can, (512, 150), "CARTOUCHES 7,62 MM", 64, (226, 196, 60))
    stencil(can, (512, 260), "880 CART.  ·  BANDES", 50, (226, 196, 60))
    stencil(can, (512, 370), "LOT TK-4471-09", 44, (226, 196, 60))
    save(can.filter(ImageFilter.GaussianBlur(0.8)), "ammocan.png")
    # boîte zinc (fer-blanc gris) étiquette papier
    z = Image.new("RGB", (1024, 512), (150, 152, 150))
    d = ImageDraw.Draw(z)
    d.rectangle([200, 120, 824, 400], fill=(214, 204, 176))
    d.text((512, 190), "7,62 × 39", font=font("monob", 64), fill=(40, 30, 24), anchor="mm")
    d.text((512, 280), "1080 ШТ  ·  ЛПС  ГЖ", font=font("mono", 42), fill=(40, 30, 24), anchor="mm")
    d.text((512, 350), "188-81", font=font("mono", 38), fill=(40, 30, 24), anchor="mm")
    save(z.filter(ImageFilter.GaussianBlur(0.6)), "zinc.png")
    # pain d'explosif : film plastique olive, étiquette générique
    e = Image.new("RGB", (1024, 512), (84, 92, 60))
    d = ImageDraw.Draw(e)
    d.rectangle([60, 60, 964, 452], outline=(30, 34, 22), width=6)
    d.text((512, 170), "CHARGE DE DÉMOLITION", font=font("sansb", 58), fill=(20, 22, 16), anchor="mm")
    d.text((512, 270), "EXPLOSIF  ·  1 KG", font=font("sansb", 64), fill=(20, 22, 16), anchor="mm")
    d.text((512, 370), "LOT 22-061  ·  NE PAS EXPOSER À LA CHALEUR", font=font("sans", 32), fill=(20, 22, 16), anchor="mm")
    save(e.filter(ImageFilter.GaussianBlur(0.6)), "explosive.png")
    # obus : marquages au pochoir
    sh = Image.new("RGB", (1024, 512), (64, 70, 48))
    d = ImageDraw.Draw(sh)
    d.rectangle([0, 60, 1024, 110], fill=(200, 170, 50))
    d.text((512, 270), "ОФ   152 мм", font=font("sansb", 70), fill=(20, 20, 18), anchor="mm")
    d.text((512, 370), "А-IX-2   12-86-479", font=font("sansb", 50), fill=(20, 20, 18), anchor="mm")
    save(sh.filter(ImageFilter.GaussianBlur(0.6)), "shell.png")
    # boîte de détonateurs carton
    b = Image.new("RGB", (512, 512), (186, 160, 110))
    d = ImageDraw.Draw(b)
    d.rectangle([30, 30, 482, 482], outline=(160, 30, 30), width=10)
    d.text((256, 180), "DÉTONATEURS", font=font("sansb", 44), fill=(160, 30, 30), anchor="mm")
    d.text((256, 260), "10 PIÈCES", font=font("sansb", 40), fill=(40, 30, 20), anchor="mm")
    d.text((256, 340), "FRAGILE", font=font("sans", 36), fill=(40, 30, 20), anchor="mm")
    save(b, "detbox.png")


if __name__ == "__main__":
    smartphone_screen()
    basic_phone()
    radio_lcd()
    satphone_lcd()
    laptop_screen()
    notebook_pages()
    passport()
    document()
    evidence_label()
    small_items()
    forensic()
    mat()
    key_atlas("keys_sat.png", (40, 42, 44), (235, 235, 235))
    key_atlas("keys_radio.png", (22, 22, 23), (230, 230, 230), (230, 140, 40))
    sat_extras()
    caches()
    print("ok", len(os.listdir(OUT)))
