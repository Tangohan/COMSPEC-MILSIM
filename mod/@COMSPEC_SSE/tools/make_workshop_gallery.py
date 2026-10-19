"""Galerie Steam Workshop de COMSPEC SSE, dans la charte des pages Workshop COMSPEC (celle d'ATAK natif et de
public/assets/workshop/sse : fond vert très sombre, accent vert, capitales condensées).

Repris de mod/COMSPEC_ATAK_Native/tools/make_workshop_gallery.py (branche release-2.1.0), rendu paramétrable.

Sorties : <sortie>/{fr,en}/sse-NN-<nom>.jpg en 1920 x 1080 (format de la galerie Steam), < 1 Mo chacune.
Par défaut <sortie> = mod/@COMSPEC_SSE/docs/workshop/gallery, HORS de mod/publisher (que workshop-pack.ps1 vide et
remplit à chaque pack) et hors du pack (docs/ n'est jamais copié).

Trois mises en page :
  hero     une capture en jeu plein cadre (1920 x 1080 conseillé) dans les deux tiers droits, fondue vers un panneau
           de texte à gauche (badge, titre, sous-titre, pastilles) ; même rendu que la galerie ATAK.
  panel    un visuel de la charte (public/assets/workshop/sse/<langue>/...) présenté en carte sous un titre.
  objets   les rendus des objets SSE (tools/icons/png/*.png) en cartes légendées.

Sources :
  capture:<fichier>   fichier du dossier source (--source, par défaut docs/workshop/source) : captures en jeu. Si le
                      fichier existe en <source>/<langue>/<fichier>, cette version est prise pour la langue.
  visuel:<fichier>    public/assets/workshop/sse/<langue>/<fichier>
  objets              tools/icons/png/<objet>.png
Une diapositive dont la source manque est sautée (message « absent »), les autres sont produites : on peut donc
déposer les captures au fur et à mesure (liste dans docs/workshop/CAPTURES_A_FAIRE.md) et relancer.

Usage (Python 3 + Pillow ; police Bahnschrift de Windows, sinon DejaVu / police par défaut) :

    python tools/make_workshop_gallery.py                       tout, FR et EN
    python tools/make_workshop_gallery.py --lang fr             une langue
    python tools/make_workshop_gallery.py --only seek,objets    seulement ces diapositives (nom ou numéro)
    python tools/make_workshop_gallery.py --source D:/captures  autre dossier de captures
    python tools/make_workshop_gallery.py --out D:/galerie      autre dossier de sortie
    python tools/make_workshop_gallery.py --png                 PNG au lieu de JPEG (plus lourd)
    python tools/make_workshop_gallery.py --list                liste des diapositives et de leurs sources

Les textes des diapositives sont dans SLIDES ci-dessous (FR et EN). N'y écrire que ce que le mod fait vraiment.
"""
import argparse
import io
import os
import sys

from PIL import Image, ImageDraw, ImageEnhance, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
MOD = os.path.normpath(os.path.join(HERE, ".."))
ROOT = os.path.normpath(os.path.join(MOD, "..", ".."))
VISUELS = os.path.join(ROOT, "public", "assets", "workshop", "sse")
OBJETS = os.path.join(HERE, "icons", "png")
SOURCE = os.path.join(MOD, "docs", "workshop", "source")
OUT = os.path.join(MOD, "docs", "workshop", "gallery")
PUBLISHER = os.path.normpath(os.path.join(MOD, "..", "publisher"))

W, H = 1920, 1080
MAX_BYTES = 1_000_000

# Charte (relevée sur public/assets/workshop/sse : mêmes valeurs que la charte ATAK).
BG0, BG1 = (10, 14, 11), (20, 34, 23)
ACCENT = (92, 199, 107)
TEXT = (238, 241, 236)
SUB = (214, 222, 214)
MUTED = (150, 164, 152)
CARD = (17, 24, 19)
EDGE = (36, 46, 39)
CHIP_EDGE = (52, 64, 55)
DOT = (26, 38, 29)
BADGE = "COMSPEC SSE"
FONTS = [r"C:\Windows\Fonts\bahnschrift.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"]

# Objets de la diapositive « objets » : (fichier dans tools/icons/png, légende FR, légende EN).
OBJETS_LISTE = [
    ("seekii", "TERMINAL SEEK II", "SEEK II TERMINAL"),
    ("fingerprintkit", "KIT D'EMPREINTES", "FINGERPRINT KIT"),
    ("dnakit", "KIT ADN", "DNA KIT"),
    ("camera", "APPAREIL PHOTO", "CAMERA"),
    ("evidencebag", "SACHET DE PREUVE", "EVIDENCE BAG"),
    ("headbag", "SAC DE TÊTE", "HEAD BAG"),
    ("usb", "CLÉ USB SAISIE", "SEIZED USB KEY"),
    ("laptop", "PORTABLE SAISI", "SEIZED LAPTOP"),
]

# Diapositives, dans l'ordre de la galerie Steam. Champs :
#   nom, mise en page (hero / panel / objets), source, textes {langue: (titre, sous-titre, [pastilles])}
#   focus (x, y) du recadrage d'une capture (0..1), bright : luminosité de la capture.
SLIDES = [
    {"nom": "presentation", "layout": "panel", "src": "visuel:banniere.png", "txt": {
        "fr": ("EXPLOITATION DE SITE SENSIBLE", "Fouiller, collecter, identifier et exploiter le renseignement sur "
               "l'objectif, puis le transmettre au Bureau SSE d'Athena.", ["ARMA 3", "ACE3", "ATAK NATIVE"]),
        "en": ("SENSITIVE SITE EXPLOITATION", "Search, collect, identify and exploit intelligence on the objective, "
               "then send it to the Athena SSE office.", ["ARMA 3", "ACE3", "ATAK NATIVE"])}},
    {"nom": "fouille", "layout": "hero", "src": "capture:fouille.jpg", "focus": (0.55, 0.5), "txt": {
        "fr": ("FOUILLER L'OBJECTIF", "Personnes, corps, objets et véhicules se fouillent au menu ACE : documents, "
               "téléphones et caches remontent dans le dossier.", ["MENU ACE", "DOCUMENTS", "CACHES"]),
        "en": ("SEARCH THE OBJECTIVE", "Search people, bodies, objects and vehicles from the ACE menu: documents, "
               "phones and caches go into the case file.", ["ACE MENU", "DOCUMENTS", "CACHES"])}},
    {"nom": "seek", "layout": "panel", "src": "visuel:vignettes-seek.png", "txt": {
        "fr": ("BIOMÉTRIE SEEK II", "Empreintes, iris, visage et ADN sur un vrai terminal. Recherche 1:N, watchlist "
               "tiers 1 à 4, fausses alertes réalistes.", ["10 DOIGTS", "IRIS", "WATCHLIST"]),
        "en": ("SEEK II BIOMETRICS", "Fingerprints, iris, face and DNA on a real terminal. 1:N search, tier 1 to 4 "
               "watchlist, realistic false alarms.", ["10 FINGERS", "IRIS", "WATCHLIST"])}},
    {"nom": "releve", "layout": "hero", "src": "capture:releve-biometrique.jpg", "focus": (0.6, 0.5), "txt": {
        "fr": ("CHAQUE SUJET EST UNIQUE", "Empreintes, iris et ADN propres à chaque personnage, identiques d'une "
               "mission à l'autre dans une campagne.", ["IDENTITÉS UNIQUES", "NFIQ", "LABO ADN"]),
        "en": ("EVERY SUBJECT IS UNIQUE", "Fingerprints, iris and DNA unique to each character, the same from one "
               "mission to the next in a campaign.", ["UNIQUE IDENTITIES", "NFIQ", "DNA LAB"])}},
    {"nom": "numerique", "layout": "hero", "src": "capture:exploitation-dl7.jpg", "focus": (0.55, 0.5), "txt": {
        "fr": ("EXPLOITATION NUMÉRIQUE", "Téléphones, ordinateurs, clés USB : contacts, messages, fichiers. Avec "
               "ATAK Native, câble DL-7, mot de passe et forçage.", ["DOMEX", "DL-7", "ATAK NATIVE"]),
        "en": ("DIGITAL EXPLOITATION", "Phones, computers, USB keys: contacts, messages, files. With ATAK Native, "
               "DL-7 cable, password and brute force.", ["DOMEX", "DL-7", "ATAK NATIVE"])}},
    {"nom": "scelles", "layout": "hero", "src": "capture:scelles.jpg", "focus": (0.55, 0.5), "txt": {
        "fr": ("CHAÎNE DE TRAÇABILITÉ", "Chaque pièce a son numéro, chaque scellé son journal infalsifiable : "
               "saisie, collecte, remise, ouverture.", ["SCELLÉS", "REGISTRE", "REMISE"]),
        "en": ("CHAIN OF CUSTODY", "Every item has its number, every seal a tamper-evident log: seizure, "
               "collection, hand-over, opening.", ["SEALS", "REGISTER", "HAND-OVER"])}},
    {"nom": "liens", "layout": "hero", "src": "capture:tableau-liens.jpg", "focus": (0.6, 0.5), "txt": {
        "fr": ("REMONTER LE RÉSEAU", "Tableau des liens en graphe ou en ORBAT : personnes, organisations, pièces "
               "et lieux, soupçonnés ou confirmés.", ["GRAPHE", "ORBAT", "ORGANISATIONS"]),
        "en": ("MAP THE NETWORK", "Link chart as a graph or ORBAT: people, organisations, items and places, "
               "suspected or confirmed.", ["GRAPH", "ORBAT", "ORGANISATIONS"])}},
    {"nom": "fonctionnalites", "layout": "panel", "src": "visuel:fonctionnalites.png", "txt": {
        "fr": ("TOUT LE SSE DANS UN MOD", "Du premier contact au dossier transmis : terrain, biométrie, numérique, "
               "Zeus, Eden et Athena.", ["ZEUS", "EDEN", "ATHENA"]),
        "en": ("ALL OF SSE IN ONE MOD", "From first contact to the transmitted file: field work, biometrics, "
               "digital, Zeus, Eden and Athena.", ["ZEUS", "EDEN", "ATHENA"])}},
    {"nom": "zeus", "layout": "hero", "src": "capture:zeus-eden.jpg", "focus": (0.55, 0.5), "txt": {
        "fr": ("ZEUS ET EDEN", "Un site complet en un module, 24 modèles, un scénario prêt à jouer, des campagnes "
               "sur plusieurs missions.", ["MODULES", "CAMPAGNES", "TABLEAU DE BORD"]),
        "en": ("ZEUS AND EDEN", "A full site in one module, 24 templates, a ready-to-play scenario, campaigns over "
               "several missions.", ["MODULES", "CAMPAIGNS", "DASHBOARD"])}},
    {"nom": "athena", "layout": "hero", "src": "capture:athena-bureau-sse.jpg", "focus": (0.5, 0.4), "txt": {
        "fr": ("LIAISON ATHENA", "Les fiches transmises arrivent au Bureau SSE du portail, rangées par affaire. "
               "Hors liaison, elles attendent.", ["BUREAU SSE", "AFFAIRES", "FILE HORS LIAISON"]),
        "en": ("ATHENA LINK", "Transmitted records reach the portal's SSE office, filed by case. Without a link, "
               "they wait in a queue.", ["SSE OFFICE", "CASES", "OFFLINE QUEUE"])}},
    {"nom": "objets", "layout": "objets", "src": "objets", "txt": {
        "fr": ("LE MATÉRIEL SSE", "Un kit complet dans l'Arsenal, ou aucun objet exigé : au choix du serveur.",
               ["ARSENAL", "KIT SSE", "RÉGLAGE CBA"]),
        "en": ("SSE EQUIPMENT", "A full kit in the Arsenal, or no item required at all: the server decides.",
               ["ARSENAL", "SSE KIT", "CBA SETTING"])}},
]


def font(size, style="Bold Condensed"):
    for path in FONTS:
        if os.path.isfile(path):
            f = ImageFont.truetype(path, size)
            try:
                f.set_variation_by_name(style)
            except (OSError, ValueError, AttributeError):
                pass
            return f
    return ImageFont.load_default()


def gradient(w, h):
    im = Image.new("RGB", (w, h), BG0)
    d = ImageDraw.Draw(im)
    for y in range(h):
        t = y / max(1, h - 1)
        d.line([(0, y), (w, y)], fill=tuple(int(BG1[i] * (1 - t) + BG0[i] * t) for i in range(3)))
    return im


def dots(im, step=22):
    d = ImageDraw.Draw(im)
    for y in range(step // 2, im.height, step):
        for x in range(step // 2, im.width, step):
            d.point((x, y), fill=DOT)


def background():
    im = gradient(W, H)
    dots(im)
    return im


def rounded(im, r):
    m = Image.new("L", im.size, 0)
    ImageDraw.Draw(m).rounded_rectangle([0, 0, im.width - 1, im.height - 1], r, fill=255)
    out = Image.new("RGBA", im.size, (0, 0, 0, 0))
    out.paste(im.convert("RGB"), (0, 0), m)
    return out


def cover(img, w, h, fx=0.5, fy=0.5):
    s = max(w / img.width, h / img.height)
    img = img.resize((round(img.width * s), round(img.height * s)), Image.LANCZOS)
    l, t = int((img.width - w) * fx), int((img.height - h) * fy)
    return img.crop((l, t, l + w, t + h))


def fit(img, w, h):
    s = min(w / img.width, h / img.height)
    return img.resize((round(img.width * s), round(img.height * s)), Image.LANCZOS)


def grade(img, bright=0.92, sat=0.9, contrast=1.08):
    img = ImageEnhance.Color(img).enhance(sat)
    img = ImageEnhance.Contrast(img).enhance(contrast)
    return ImageEnhance.Brightness(img).enhance(bright)


def wrap(d, text, f, width):
    lines, cur = [], ""
    for word in text.split():
        test = (cur + " " + word).strip()
        if d.textlength(test, font=f) > width and cur:
            lines.append(cur)
            cur = word
        else:
            cur = test
    if cur:
        lines.append(cur)
    return lines


def chip(d, x, y, label, f):
    w = d.textlength(label, font=f) + 30
    d.rounded_rectangle([x, y, x + w, y + 40], 6, fill=CARD, outline=CHIP_EDGE, width=2)
    d.text((x + 15, y + 20), label, font=f, fill=TEXT, anchor="lm")
    return x + w + 10


def badge(d, x, y):
    f = font(28)
    w = d.textlength(BADGE, font=f) + 60
    d.rounded_rectangle([x, y, x + w, y + 50], 25, outline=ACCENT, width=3)
    d.text((x + w / 2, y + 25), BADGE, font=f, fill=ACCENT, anchor="mm")


def hero(src, txt, focus=(0.5, 0.5), bright=0.95):
    """Capture à droite, texte à gauche (même rendu que la galerie ATAK)."""
    title, sub, chips = txt
    PX = 620
    photo = grade(cover(src.convert("RGB"), W - PX, H, focus[0], focus[1]), bright=bright)
    im = background()
    fade = Image.new("L", photo.size, 255)
    fd = ImageDraw.Draw(fade)
    for x in range(360):
        fd.line([(x, 0), (x, H)], fill=int(255 * (x / 360) ** 1.4))
    im.paste(photo, (PX, 0), fade)
    d = ImageDraw.Draw(im)
    x0 = 110
    badge(d, x0, 150)
    y = 240
    for ln in wrap(d, title, font(118), 820):
        d.text((x0 - 4, y), ln, font=font(118), fill=TEXT)
        y += 118
    d.rounded_rectangle([x0, y + 26, x0 + 110, y + 34], 4, fill=ACCENT)
    y += 70
    f = font(38, "SemiLight")
    for ln in wrap(d, sub, f, 760):
        d.text((x0, y), ln, font=f, fill=SUB)
        y += 50
    y += 40
    cx = x0
    for c in chips:
        cx = chip(d, cx, y, c, font(24, "SemiBold Condensed"))
    return im


def header(d, txt, x0=110, y0=80, width=1700):
    """Bandeau de titre des mises en page panel / objets ; renvoie l'ordonnée libre en dessous."""
    title, sub, chips = txt
    badge(d, x0, y0)
    tf = font(96)
    d.text((x0 - 3, y0 + 88), title, font=tf, fill=TEXT)
    y = y0 + 88 + 104
    d.rounded_rectangle([x0, y, x0 + 110, y + 8], 4, fill=ACCENT)
    y += 30
    f = font(36, "SemiLight")
    for ln in wrap(d, sub, f, width):
        d.text((x0, y), ln, font=f, fill=SUB)
        y += 46
    # Pastilles alignées à droite, à hauteur du badge.
    cf = font(24, "SemiBold Condensed")
    total = sum(d.textlength(c, font=cf) + 40 for c in chips) - 10
    cx = W - x0 - total
    for c in chips:
        cx = chip(d, cx, y0 + 5, c, cf)
    return y


def panel(src, txt):
    """Visuel de la charte en carte, sous un bandeau de titre."""
    im = background()
    d = ImageDraw.Draw(im)
    top = header(d, txt) + 34
    vis = fit(src.convert("RGB"), W - 2 * 110, H - top - 70)
    x, y = (W - vis.width) // 2, top + (H - top - 70 - vis.height) // 2
    card = rounded(vis, 22)
    im.paste(card, (x, y), card)
    d.rounded_rectangle([x, y, x + vis.width - 1, y + vis.height - 1], 22, outline=EDGE, width=2)
    return im


def objets(lang, txt):
    """Rendus des objets SSE en cartes légendées (2 rangées de 4)."""
    im = background()
    d = ImageDraw.Draw(im)
    top = header(d, txt) + 40
    pad, gap, cols = 110, 26, 4
    items = [o for o in OBJETS_LISTE if os.path.isfile(os.path.join(OBJETS, o[0] + ".png"))]
    rows = (len(items) + cols - 1) // cols
    cw = (W - 2 * pad - (cols - 1) * gap) // cols
    ch = (H - top - 70 - (rows - 1) * gap) // rows
    lf = font(30)
    for i, (name, fr, en) in enumerate(items):
        x = pad + (i % cols) * (cw + gap)
        y = top + (i // cols) * (ch + gap)
        card = Image.new("RGB", (cw, ch), CARD)
        cd = ImageDraw.Draw(card)
        ic = Image.open(os.path.join(OBJETS, name + ".png")).convert("RGBA")
        ic = fit(ic, cw - 40, ch - 80)
        card.paste(ic, ((cw - ic.width) // 2, (ch - 70 - ic.height) // 2 + 6), ic)
        cd.text((cw // 2, ch - 42), fr if lang == "fr" else en, font=lf, fill=TEXT, anchor="mm")
        cd.rounded_rectangle([cw // 2 - 20, ch - 18, cw // 2 + 20, ch - 14], 2, fill=ACCENT)
        r = rounded(card, 14)
        im.paste(r, (x, y), r)
        d.rounded_rectangle([x, y, x + cw - 1, y + ch - 1], 14, outline=EDGE, width=1)
    return im


def resolve(spec, lang, source):
    """Chemin de la source d'une diapositive, ou None si elle manque."""
    kind, _, name = spec.partition(":")
    if kind == "objets":
        return OBJETS if os.path.isdir(OBJETS) else None
    if kind == "visuel":
        p = os.path.join(VISUELS, lang, name)
        return p if os.path.isfile(p) else None
    if kind == "capture":
        for p in (os.path.join(source, lang, name), os.path.join(source, name)):
            if os.path.isfile(p):
                return p
        # Même nom, autre extension (.png / .jpg / .jpeg).
        base = os.path.splitext(name)[0]
        for folder in (os.path.join(source, lang), source):
            for ext in (".png", ".jpg", ".jpeg"):
                p = os.path.join(folder, base + ext)
                if os.path.isfile(p):
                    return p
        return None
    raise ValueError("source inconnue : " + spec)


def save(im, path, png):
    """JPEG (qualité réduite si besoin pour rester sous 1 Mo) ou PNG optimisé."""
    os.makedirs(os.path.dirname(path), exist_ok=True)
    if png:
        im.save(path, "PNG", optimize=True)
        return os.path.getsize(path)
    for q in (90, 86, 82, 78, 74, 70):
        buf = io.BytesIO()
        im.convert("RGB").save(buf, "JPEG", quality=q, optimize=True, progressive=True)
        if buf.tell() <= MAX_BYTES or q == 70:
            with open(path, "wb") as fh:
                fh.write(buf.getvalue())
            return buf.tell()


def main(argv=None):
    ap = argparse.ArgumentParser(description="Galerie Steam Workshop de COMSPEC SSE (1920 x 1080).")
    ap.add_argument("--source", default=SOURCE, help="dossier des captures en jeu (défaut : docs/workshop/source)")
    ap.add_argument("--out", default=OUT, help="dossier de sortie (défaut : docs/workshop/gallery)")
    ap.add_argument("--lang", choices=["fr", "en", "all"], default="all")
    ap.add_argument("--only", default="", help="noms ou numéros de diapositives, séparés par des virgules")
    ap.add_argument("--png", action="store_true", help="PNG au lieu de JPEG")
    ap.add_argument("--list", action="store_true", help="lister les diapositives et leurs sources")
    a = ap.parse_args(argv)

    out = os.path.abspath(a.out)
    rel = os.path.relpath(out, PUBLISHER) if os.path.splitdrive(out)[0].lower() == os.path.splitdrive(PUBLISHER)[0].lower() else ".."
    if not rel.startswith(".."):
        sys.exit("Refus : %s est dans mod/publisher, que workshop-pack.ps1 vide. Choisir un autre --out." % out)

    langs = ["fr", "en"] if a.lang == "all" else [a.lang]
    only = {s.strip() for s in a.only.split(",") if s.strip()}
    done = missing = 0
    for n, sl in enumerate(SLIDES, 1):
        tag = "sse-%02d-%s" % (n, sl["nom"])
        if only and sl["nom"] not in only and str(n) not in only and "%02d" % n not in only:
            continue
        for lang in langs:
            path = resolve(sl["src"], lang, a.source)
            if a.list:
                print("%-24s %-3s %-7s %-34s %s" % (tag, lang, sl["layout"], sl["src"], path or "ABSENT"))
                continue
            if not path:
                print("absent  %s/%s  (source %s)" % (lang, tag, sl["src"]))
                missing += 1
                continue
            txt = sl["txt"][lang]
            if sl["layout"] == "hero":
                im = hero(Image.open(path), txt, sl.get("focus", (0.5, 0.5)), sl.get("bright", 0.95))
            elif sl["layout"] == "panel":
                im = panel(Image.open(path), txt)
            else:
                im = objets(lang, txt)
            dest = os.path.join(out, lang, tag + (".png" if a.png else ".jpg"))
            size = save(im, dest, a.png)
            print("ok      %s  (%d Ko)" % (dest, size // 1024))
            done += 1
    if not a.list:
        print("%d image(s) produite(s), %d absente(s)." % (done, missing))


if __name__ == "__main__":
    main()
