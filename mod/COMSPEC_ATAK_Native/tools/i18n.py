#!/usr/bin/env python3
"""
Traduction de l'interface de l'ATAK natif (français -> anglais).

Le code SQF reste écrit en français. À l'affichage, comspec_atak_native_fnc_tr remplace le texte par sa
traduction quand la langue active est l'anglais (réglage CBA « Langue du téléphone »). Le dictionnaire est
Sources/addons/main/i18n/en.txt : un tableau [["français", "anglais"], ...] lu par parseSimpleArray.

  python3 tools/i18n.py wrap      fait passer les textes affichés par fnc_tr (idempotent, à relancer après un rebase)
  python3 tools/i18n.py extract   écrit tools/i18n/missing.tsv : textes du code sans traduction
  python3 tools/i18n.py merge F   ajoute au dictionnaire les lignes « français<TAB>anglais » du fichier F
  python3 tools/i18n.py stats     couverture du dictionnaire
  python3 tools/i18n.py --connect|--sse wrap|extract|stats   même chose pour l'addon Overwatch connect embarqué / le mod SSE
                                     (UptoDate/.../connect ; même dictionnaire, via comspec_overwatch_connect_fnc_tr)
  python3 tools/i18n.py community F  crée ou met à jour le fichier F d'un mod de traduction communautaire (autre langue,
                                     localisation/README.md) : lignes ["français", "traduction", "anglais"], traductions
                                     gardées, phrases nouvelles ajoutées vides, phrases disparues retirées ; écrit aussi
                                     localisation/template/xx.txt (et categories.json, tools/i18n_categories.py)
                                     quand F est omis, avec template/stringtable.json (clés des stringtables) ;
                                     --game Spanish : écrit aussi stringtable.xml à côté de F (noms d'objets et de
                                     modules Eden/Zeus dans la langue du jeu)
  python3 tools/i18n.py fr           assemble i18n/fr.txt (réglage Français) : textes écrits en anglais dans le code
                                     (tools/i18n_fr_source.tsv, anglais<TAB>français) + anglais -> français des stringtables
                                     (ATAK natif, SSE, Overwatch connect : noms d'objets et textes lus par getText)
  python3 tools/i18n.py stringtable  noms d'objets et modules Eden/Zeus (config.cpp, modules.hpp) -> stringtable.xml
                                     (--sse : tout le SSE ; --connect : config.cpp et modules/ d'Overwatch connect)
                                     ($STR_..., langue du jeu ; anglais pris dans en.txt)

Un texte avec %1, %2... est un modèle : « il y a %1 s » -> « %1 s ago » traduit aussi « il y a 12 s ».
"""
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ADDON = os.path.join(ROOT, "Sources", "addons", "main")
DICT = os.path.join(ADDON, "i18n", "en.txt")
MISSING = os.path.join(ROOT, "tools", "i18n", "missing.tsv")
TR = "comspec_atak_native_fnc_tr"

# Commandes dont l'argument de droite est affiché à l'écran.
SINKS_BINARY = {"ctrlsettext", "ctrlsetstructuredtext", "ctrlsettooltip", "lbadd", "lbsettext", "lnbaddrow", "lnbsettext", "lbsettooltip", "ctrlsetstructuredtext"}
SINKS_UNARY = {"hint", "hintsilent", "systemchat", "hintc"}
# Fichiers à ne pas toucher : la traduction elle-même.
SKIP_FILES = {"fn_tr.sqf", "fn_langApply.sqf", "fn_aceAction.sqf", "fn_zenAction.sqf", "fn_zenModule.sqf"}
IDENT = re.compile(r"[A-Za-z0-9_]")
# Addon Overwatch connect embarqué dans @COMSPEC_ATAK_Native (--connect) et mod SSE (--sse) : mêmes règles, même
# dictionnaire. Leur fn_tr passe la main à celui de l'ATAK natif quand il est chargé ; leurs écrans traduisent leurs
# textes fixes au chargement (fn_trDisplay).
CONNECT = "--connect" in sys.argv
SSE = "--sse" in sys.argv
EXTRA = CONNECT or SSE
if CONNECT:
    sys.argv.remove("--connect")
    ADDON = os.path.join(os.path.dirname(ROOT), "UptoDate", "Sources", "comspec-overwatch-addons", "connect")
    MISSING = os.path.join(ROOT, "tools", "i18n", "missing_connect.tsv")
    TR = "comspec_overwatch_connect_fnc_tr"
elif SSE:
    sys.argv.remove("--sse")
    ADDON = os.path.join(os.path.dirname(ROOT), "@COMSPEC_SSE", "addons")
    MISSING = os.path.join(ROOT, "tools", "i18n", "missing_sse.tsv")
    TR = "comspec_sse_fnc_tr"
if EXTRA:
    SKIP_FILES = {"fn_tr.sqf", "fn_trDisplay.sqf", "fn_aceAction.sqf", "fn_zenAction.sqf", "fn_zenModule.sqf", "fn_addSetting.sqf"}
TRDISPLAY = "(_this select 0) call " + TR + "Display; "
ONLOAD = re.compile(r'^(    onLoad\s*=\s*")(?!' + re.escape(TRDISPLAY.strip()) + ')', re.M)
HELPERS = [("ace_interact_menu_fnc_createAction", "aceAction"), ("zen_context_menu_fnc_createAction", "zenAction"),
           ("zen_custom_modules_fnc_register", "zenModule")]
# connect, SSE : réglages CBA dans la langue du joueur (fn_addSetting -> comspec_atak_native_fnc_trMenu).
if EXTRA:
    HELPERS.append(("CBA_fnc_addSetting", "addSetting"))
LOGLINE = re.compile(r"diag_log|fnc_(log\w*|appendLinkLog|appendModuleLog|callExtLogged)\b")
# ATAK natif : journaux (diag_log, fnc_log, journal de liaison) jamais traduits (TTRD : les logs restent en français) ;
# fnc_log seul, pas fnc_logisticsAction.
LOGLINE_MAIN = re.compile(r"diag_log|fnc_(log|appendLinkLog|appendModuleLog|callExtLogged|perfLog)\b")


def display_files():
    if SSE:
        out = []
        for base, _, files in os.walk(ADDON):
            if os.path.basename(base) == "dialogs":
                out += [os.path.join(base, f) for f in sorted(files) if f.endswith(".hpp")]
        return sorted(out)
    return [os.path.join(ADDON, f) for f in sorted(os.listdir(ADDON)) if f.startswith("display") and f.endswith(".hpp")]


def sqf_files():
    for base, _, files in os.walk(ADDON):
        for f in sorted(files):
            if f.endswith(".sqf") and f not in SKIP_FILES:
                yield os.path.join(base, f)


def skip_string_or_comment(src, i):
    """Si src[i] ouvre une chaîne ou un commentaire, renvoie l'index après sa fin, sinon None."""
    c = src[i]
    if c in "\"'":
        j = i + 1
        while j < len(src):
            if src[j] == c:
                if j + 1 < len(src) and src[j + 1] == c:
                    j += 2
                    continue
                return j + 1
            j += 1
        return len(src)
    if src.startswith("//", i):
        j = src.find("\n", i)
        return len(src) if j < 0 else j
    if src.startswith("/*", i):
        j = src.find("*/", i + 2)
        return len(src) if j < 0 else j + 2
    if c == "#" and (i == 0 or src[i - 1] == "\n"):
        j = src.find("\n", i)
        return len(src) if j < 0 else j
    return None


def strings(src):
    """Littéraux de chaîne (contenu décodé) du code SQF."""
    i = 0
    while i < len(src):
        c = src[i]
        if c in "\"'":
            j = skip_string_or_comment(src, i)
            yield src[i + 1:j - 1].replace(c + c, c)
            i = j
            continue
        j = skip_string_or_comment(src, i)
        i = j if j is not None else i + 1


def expr_end(src, i):
    """Fin de l'expression qui commence en i : ; , ou crochet fermant au niveau 0 (sans les blancs finaux)."""
    depth = 0
    last = i
    j = i
    while j < len(src):
        k = skip_string_or_comment(src, j)
        if k is not None:
            if src[j] in "\"'":
                last = k
            j = k
            continue
        c = src[j]
        if c in "([{":
            depth += 1
        elif c in ")]}":
            if depth == 0:
                break
            depth -= 1
        elif c in ";," and depth == 0:
            break
        if not c.isspace():
            last = j + 1
        j += 1
    return last


def wrap_source(src):
    out = []
    i = 0
    n = 0
    while i < len(src):
        k = skip_string_or_comment(src, i)
        if k is not None:
            out.append(src[i:k])
            i = k
            continue
        m = re.match(r"[A-Za-z_][A-Za-z0-9_]*", src[i:])
        if not m or (i > 0 and IDENT.match(src[i - 1])):
            out.append(src[i])
            i += 1
            continue
        word = m.group(0)
        low = word.lower()
        j = i + len(word)
        if low not in SINKS_BINARY and low not in SINKS_UNARY:
            out.append(word)
            i = j
            continue
        # Argument : après les blancs, et après parseText pour le texte structuré.
        a = j
        while a < len(src) and src[a].isspace():
            a += 1
        if low == "ctrlsetstructuredtext":
            pm = re.match(r"parseText\b", src[a:], re.I)
            if not pm:
                out.append(word)
                i = j
                continue
            a += pm.end()
            while a < len(src) and src[a].isspace():
                a += 1
        if a >= len(src) or src[a] in ";,)]}=" or src.startswith("==", a):
            out.append(word)
            i = j
            continue
        e = expr_end(src, a)
        arg = src[a:e]
        line_end = src.find("\n", e)
        line = src[src.rfind("\n", 0, i) + 1:len(src) if line_end < 0 else line_end]
        # « i18n:brut » sur la ligne : texte saisi (champ d'édition), jamais traduit.
        if arg.endswith("call " + TR + ")") or not arg or "i18n:brut" in line:
            out.append(src[i:e])
            i = e
            continue
        out.append(src[i:a] + "((" + arg + ") call " + TR + ")")
        n += 1
        i = e
    return "".join(out), n


TAG = re.compile(r"<[^<>]*>")
SEPS = re.compile(r"\s+·\s+|\n|\r")
PLACEHOLDER = re.compile(r"%(\d+)")
ADJACENT = re.compile(r"%\d+%\d")
MULTIDIGIT = re.compile(r"(?<!%)%\d\d")
WORD = re.compile(r"[A-Za-zÀ-ÿ]")


def looks_like_code(s):
    t = s.strip()
    if not WORD.search(t):
        return True
    if re.fullmatch(r"[A-Za-z0-9_\-\.]+", t) and ("_" in t or re.search(r"[a-z][A-Z]", t) or re.fullmatch(r"[A-Z0-9_\-\.]+", t)):
        return True
    if "\\" in t or "/" in t and " " not in t:
        return True
    if re.search(r"\.(paa|jpg|png|ogg|wss|p3d|sqf|hpp|rtm)\b", t, re.I):
        return True
    if re.search(r"\b_[a-z]\w*|\bcall\b|\bparams\b|\bthen\b|fnc_|\bselect\b|\bisEqualTo\b|\bprivate\b|getVariable", t):
        return True
    if t.startswith("#") or t.startswith("%") and not re.search(r"[A-Za-zÀ-ÿ]{3,}", PLACEHOLDER.sub("", t)):
        return True
    if re.fullmatch(r"[a-z0-9_\-]+", t) and len(t) <= 3:
        return True
    # Préfixe court collé à des valeurs (« CR%1%2 » : identifiant de compte rendu) : code, pas une phrase. Traduit,
    # il réécrivait tout texte commençant par « CR » (« CROW 1-1 » -> « RPTOW 1-1 »).
    if re.fullmatch(r"[A-Z]{1,4}(%\d)+", t):
        return True
    return False


def segments(lit):
    """Morceaux de texte affichables d'un littéral (hors balises de texte structuré)."""
    for chunk in TAG.split(lit):
        for part in SEPS.split(chunk):
            p = part.strip()
            if p and not looks_like_code(p):
                yield p


def load_dict():
    if not os.path.exists(DICT):
        return []
    with open(DICT, encoding="utf-8") as f:
        txt = f.read()
    # parseSimpleArray : chaînes à guillemets doublés. On convertit en JSON.
    pairs = []
    for m in re.finditer(r'\[\s*"((?:[^"]|"")*)"\s*,\s*"((?:[^"]|"")*)"\s*\]', txt):
        pairs.append((m.group(1).replace('""', '"'), m.group(2).replace('""', '"')))
    return pairs


def save_dict(pairs):
    os.makedirs(os.path.dirname(DICT), exist_ok=True)
    seen = {}
    for fr, en in pairs:
        # fn_tr cherche le texte sans ses espaces de bord (gardés autour de la traduction) : clé "dont %1", jamais " dont %1".
        fr, en = fr.strip(), en.strip()
        if fr and en and fr != en:
            # fn_langApply ne lit qu'un chiffre après « % » : « à %10 m » serait lu « à %1 » + « 0 m » et détournerait
            # « à %1 m » (« à 250 m » -> « at  m »). Modèle refusé : renuméroter %1…%9 ou découper le texte dans le code.
            if MULTIDIGIT.search(fr) or MULTIDIGIT.search(en):
                print("Numéro à deux chiffres (%10+), modèle refusé : renuméroter de %1 à %9 :", fr, file=sys.stderr)
                continue
            seen[fr] = en
            # Valeurs collées (%1%2) : fn_langApply laisse la première capturer les deux (la seconde vide), sauf si la
            # seconde revient ailleurs dans le texte (elle s'afficherait deux fois) : ce modèle-là est ignoré en jeu.
            if any(len(re.findall("%" + m.group(2) + r"(?!\d)", fr)) > 1 for m in re.finditer(r"%(\d)%(\d)", fr)):
                print("Valeurs collées (%1%2) dont la seconde revient plus loin, modèle ignoré en jeu : séparer les valeurs dans le code :", fr, file=sys.stderr)
    q = lambda s: '"' + s.replace('"', '""') + '"'
    lines = [f"[{q(fr)}, {q(en)}]" for fr, en in sorted(seen.items(), key=lambda p: p[0].lower())]
    with open(DICT, "w", encoding="utf-8", newline="\n") as f:
        f.write("[\n" + ",\n".join(lines) + "\n]\n")
    return len(lines)


def load_community(path):
    """Fichier d'un mod de traduction : {français: traduction}, lignes à 2 ou 3 colonnes (la 3e, l'anglais, ignorée)."""
    if not os.path.exists(path):
        return {}
    with open(path, encoding="utf-8-sig") as f:
        txt = f.read()
    out = {}
    for m in re.finditer(r'\[\s*"((?:[^"]|"")*)"\s*,\s*"((?:[^"]|"")*)"\s*(?:,\s*"(?:[^"]|"")*"\s*)?\]', txt):
        out[m.group(1).replace('""', '"')] = m.group(2).replace('""', '"')
    return out


# Stringtables des trois addons (noms d'objets, modules Eden/Zeus : langue du jeu, pas fn_tr). Un mod de traduction
# communautaire peut livrer sa propre stringtable.xml avec ces clés et la colonne de sa langue (community F --game).
STRINGTABLES = [("atak", os.path.join(ROOT, "Sources", "addons", "main", "stringtable.xml")),
                ("sse", os.path.join(os.path.dirname(ROOT), "@COMSPEC_SSE", "addons", "main", "stringtable.xml")),
                ("connect", os.path.join(os.path.dirname(ROOT), "UptoDate", "Sources", "comspec-overwatch-addons", "connect", "stringtable.xml"))]
# Colonnes de stringtable d'Arma 3 (valeurs de la commande language).
GAME_COLUMNS = ["English", "French", "German", "Italian", "Spanish", "Czech", "Polish", "Russian", "Portuguese",
                "Turkish", "Japanese", "Korean", "Chinese", "Chinesesimp"]


# <Original> est l'anglais (langue par défaut des jeux sans colonne propre : allemand, russe…) ; le texte source, en
# français, est dans <French>. Anciennes stringtables (Original = français, pas de <French>) : Original pris comme source.
KEY_RE = re.compile(r'<Key ID="([^"]+)">\s*<Original>(.*?)</Original>\s*(?:<English>(.*?)</English>)?\s*(?:<French>(.*?)</French>)?', re.S)


def stringtable_rows():
    """[(clé $STR_, texte original, anglais, addon), ...] des trois stringtables."""
    import html as _h
    rows = []
    for pkg, path in STRINGTABLES:
        if not os.path.exists(path):
            continue
        for m in KEY_RE.finditer(open(path, encoding="utf-8").read()):
            orig = _h.unescape(m.group(4) if m.group(4) is not None else m.group(2))
            rows.append((m.group(1), orig, _h.unescape(m.group(3)) if m.group(3) else orig, pkg))
    return rows


def community_pairs():
    """Phrases du modèle communautaire : dictionnaire anglais (textes écrits en français dans le code), puis textes
    écrits en anglais dans le code (tools/i18n_fr_source.tsv : clé = le texte anglais lui-même, que fn_tr cherche tel
    quel dans le dictionnaire de la langue), puis textes des stringtables absents du dictionnaire."""
    pairs = load_dict()
    seen = {fr for fr, _ in pairs}
    src = os.path.join(ROOT, "tools", "i18n_fr_source.tsv")
    for line in open(src, encoding="utf-8"):
        line = line.rstrip("\n")
        if line.startswith("#") or "\t" not in line:
            continue
        en = line.split("\t", 1)[0]
        if en.strip() and en not in seen:
            seen.add(en)
            pairs.append((en, en))
    for _, orig, en, _ in stringtable_rows():
        if WORD.search(orig) and orig not in seen:
            seen.add(orig)
            pairs.append((orig, en))
    return pairs


def save_stringtable_keys(path):
    """localisation/template/stringtable.json : {"version": 1, "keys": [[clé, texte original, anglais, addon], ...]}, lu par le
    site (/traduire-atak) pour ajouter la stringtable.xml de la langue au mod téléchargé."""
    rows = [[k, orig, en, pkg] for k, orig, en, pkg in stringtable_rows() if WORD.search(orig)]
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        json.dump({"version": 1, "keys": rows}, f, ensure_ascii=False, separators=(",", ":"))
        f.write("\n")
    return len(rows)


def write_lang_stringtable(path, code, game, translations):
    """stringtable.xml d'un mod de traduction : pour chaque clé dont le texte original est traduit, l'anglais d'origine
    et la colonne de la langue du jeu (Spanish, German…). Chargée après COMSPEC (requiredAddons), elle remplace les
    noms d'objets et de modules Eden/Zeus quand Arma est dans cette langue."""
    import html as _h
    esc = lambda t: _h.escape(t, quote=False)
    rows = []
    for k, orig, en, _ in stringtable_rows():
        tr = translations.get(orig, "")
        if not tr:
            continue
        rows.append(f'        <Key ID="{k}">\n            <Original>{esc(en)}</Original>\n            <English>{esc(en)}</English>\n'
                    f'            <{game}>{esc(tr)}</{game}>\n        </Key>')
    xml = ('<?xml version="1.0" encoding="utf-8"?>\n<Project name="comspec_atak_lang_' + code + '">\n    <Package name="main">\n'
           + "\n".join(rows) + '\n    </Package>\n</Project>\n')
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write(xml)
    return len(rows)


def save_community(path, pairs):
    """Écrit [["français", "traduction", "anglais"], ...] dans l'ordre du dictionnaire anglais ; renvoie (total, traduites)."""
    old = load_community(path)
    q = lambda s: '"' + s.replace('"', '""') + '"'
    lines, done = [], 0
    for fr, en in pairs:
        tr = old.get(fr, "")
        done += 1 if tr else 0
        if tr and sorted(PLACEHOLDER.findall(fr)) != sorted(PLACEHOLDER.findall(tr)):
            print("%1, %2... différents de l'original (la phrase s'affichera mal) :", fr, "->", tr, file=sys.stderr)
        lines.append(f"[{q(fr)}, {q(tr)}, {q(en)}]")
    os.makedirs(os.path.dirname(os.path.abspath(path)), exist_ok=True)
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write("[\n" + ",\n".join(lines) + "\n]\n")
    return len(lines), done


def collect():
    found = {}
    for path in sqf_files():
        with open(path, encoding="utf-8", errors="replace") as f:
            src = f.read()
        rel = os.path.relpath(path, ADDON)
        if EXTRA:
            # connect, SSE : beaucoup de journaux techniques, jamais affichés au joueur ; addon debug ignoré.
            if rel.startswith("debug" + os.sep):
                continue
            src = "\n".join(l for l in src.split("\n") if not LOGLINE.search(l))
        else:
            src = "\n".join(l for l in src.split("\n") if not LOGLINE_MAIN.search(l))
        for lit in strings(src):
            for seg in segments(lit):
                found.setdefault(seg, rel)
    # Textes fixes des écrans (ui/*.hpp ; display*.hpp pour connect) : traduits au chargement de l'écran
    # (fn_displayLoad ; fn_trDisplay pour connect).
    if EXTRA:
        hpps = display_files()
    else:
        ui = os.path.join(ADDON, "ui")
        hpps = [os.path.join(ui, f) for f in sorted(os.listdir(ui))]
    for path in hpps:
        with open(path, encoding="utf-8", errors="replace") as fh:
            for m in re.finditer(r'\b(?:text|tooltip)\s*=\s*"((?:[^"]|"")*)"', fh.read()):
                for seg in segments(m.group(1).replace('""', '"')):
                    found.setdefault(seg, os.path.relpath(path, ADDON))
    return found


CFG_FILES = ["config.cpp", "modules.hpp"]
CFG_PREFIX = "STR_COMSPEC_ATAK_CFG_"
STRINGTABLE = os.path.join(ADDON, "stringtable.xml")
if SSE:
    # SSE : config.cpp et .hpp de tous les addons (hors écrans, traduits au chargement) ; une seule stringtable dans main.
    CFG_FILES = sorted(os.path.relpath(os.path.join(b, f), ADDON) for b, _, fs in os.walk(ADDON) for f in fs
                       if f.endswith((".cpp", ".hpp")) and "dialogs" not in b and not f.startswith("script_")
                       and not b.startswith(os.path.join(ADDON, "debug")))
    CFG_PREFIX = "STR_COMSPEC_SSE_CFG_"
    STRINGTABLE = os.path.join(ADDON, "main", "stringtable.xml")
if CONNECT:
    # connect : modules Eden/Zeus et objets (config.cpp, modules/, relais Zeus) ; écrans exclus (traduits au chargement).
    CFG_FILES = ["config.cpp"] + sorted(
        os.path.join("modules", f) for f in os.listdir(os.path.join(ADDON, "modules")) if f.endswith(".hpp"))
    CFG_PREFIX = "STR_COMSPEC_OW_CFG_"
CFG_ATTR = re.compile(r'\b(displayName|description|tooltip|name|displayNameShort|descriptionShort)(\s*=\s*)"((?:[^"]|"")*)"')


def cfg_key(fr):
    import hashlib
    return CFG_PREFIX + hashlib.sha1(fr.encode("utf-8")).hexdigest()[:10].upper()


def stringtable():
    """Textes de config (noms d'objets, modules Eden/Zeus) -> $STR_ ; stringtable.xml régénéré (français + anglais)."""
    import html as _h
    en = dict(load_dict())
    keys = {}
    kept = {}
    if os.path.exists(STRINGTABLE):
        for m in KEY_RE.finditer(open(STRINGTABLE, encoding="utf-8").read()):
            fr = m.group(4) if m.group(4) is not None else m.group(2)
            keys[m.group(1)] = _h.unescape(fr)
            # Anglais écrit à la main dans la stringtable (absent de en.txt) : gardé.
            if m.group(3) and m.group(3) != fr:
                kept.setdefault(_h.unescape(fr), _h.unescape(m.group(3)))
    missing = []
    for f in CFG_FILES:
        path = os.path.join(ADDON, f)
        src = open(path, encoding="utf-8").read()

        def rep(m):
            fr = m.group(3).replace('""', '"')
            if not WORD.search(fr) or looks_like_code(fr) or fr.startswith("$STR_"):
                return m.group(0)
            # name= : seulement les choix des modules Eden (Values) ; ailleurs c'est une donnée lue par le code.
            if m.group(1) == "name" and not re.match(r"(modules|attributes)\w*\.hpp$", os.path.basename(f)) and not (
                    CONNECT and f.startswith("modules" + os.sep)):
                return m.group(0)
            k = cfg_key(fr)
            keys[k] = fr
            return f'{m.group(1)}{m.group(2)}"${k}"'
        new = CFG_ATTR.sub(rep, src)
        if new != src:
            open(path, "w", encoding="utf-8", newline="").write(new)
    rows = []
    for k, fr in sorted(keys.items()):
        e = kept.get(fr) or en.get(fr)
        if not e:
            missing.append(fr)
            e = fr
        esc = lambda t: _h.escape(t, quote=False)
        rows.append(f'        <Key ID="{k}">\n            <Original>{esc(e)}</Original>\n            <English>{esc(e)}</English>\n            <French>{esc(fr)}</French>\n        </Key>')
    xml = ('<?xml version="1.0" encoding="utf-8"?>\n<!-- Généré par tools/i18n.py stringtable : traductions dans i18n/en.txt. -->\n'
           '<Project name="' + ("COMSPEC_SSE" if SSE else ("COMSPEC_Overwatch_connect" if CONNECT else "COMSPEC_ATAK_Native")) + '">\n    <Package name="main">\n' + "\n".join(rows) + '\n    </Package>\n</Project>\n')
    open(STRINGTABLE, "w", encoding="utf-8", newline="\n").write(xml)
    print(f"{len(keys)} textes de config, {len(missing)} sans anglais")
    for fr in missing:
        print("  " + fr)


FR_AMBIGUOUS = {"Dock", "Support"}


def build_fr():
    """i18n/fr.txt : [["texte anglais", "français"], ...], chargé par fn_langApply quand la langue est le français."""
    import html as _h
    mod = os.path.dirname(ROOT)
    pairs = {}
    tables = [os.path.join(ROOT, "Sources", "addons", "main", "stringtable.xml"),
              os.path.join(mod, "@COMSPEC_SSE", "addons", "main", "stringtable.xml"),
              os.path.join(mod, "UptoDate", "Sources", "comspec-overwatch-addons", "connect", "stringtable.xml")]
    for t in tables:
        if not os.path.exists(t):
            continue
        for m in re.finditer(r"<English>(.*?)</English>\s*<French>(.*?)</French>", open(t, encoding="utf-8").read(), re.S):
            en, fr = _h.unescape(m.group(1)), _h.unescape(m.group(2))
            if en and fr and en != fr:
                pairs.setdefault(en, fr)
    src = os.path.join(ROOT, "tools", "i18n_fr_source.tsv")
    for line in open(src, encoding="utf-8"):
        line = line.rstrip("\n")
        if line.startswith("#") or "\t" not in line:
            continue
        en, fr = line.split("\t", 1)
        if en != fr:
            pairs[en] = fr
    # Mots anglais à double sens : fn_tr traduit chaque texte affiché, donc « Dock » (repère de carte) renommerait le
    # pli « Dock » des réglages en « Quai », et « Support » (nom par défaut d'un appareil DL-7) deviendrait « Soutien ».
    for en in FR_AMBIGUOUS:
        pairs.pop(en, None)
    out = os.path.join(ROOT, "Sources", "addons", "main", "i18n", "fr.txt")
    esc = lambda t: t.replace('"', '""')
    with open(out, "w", encoding="utf-8", newline="\n") as f:
        f.write("[\n" + ",\n".join(f'["{esc(e)}", "{esc(r)}"]' for e, r in sorted(pairs.items())) + "\n]\n")
    print(f"{len(pairs)} entrées dans {os.path.relpath(out, ROOT)}")


def main():
    cmd = sys.argv[1] if len(sys.argv) > 1 else "stats"
    if cmd == "wrap":
        total = 0
        for path in sqf_files():
            with open(path, encoding="utf-8", errors="replace", newline="") as f:
                src = f.read()
            new, n = wrap_source(src)
            # Noms d'actions ACE / ZEN et modules Zeus ZEN : passés par les variantes qui traduisent (fn_aceAction…).
            pre = TR[:-len("_fnc_tr")]
            for fn, helper in HELPERS:
                new = re.sub(r"\bcall(\s+)" + fn + r"\b", lambda m: "call" + m.group(1) + pre + "_fnc_" + helper, new)
            if new != src:
                with open(path, "w", encoding="utf-8", newline="") as f:
                    f.write(new)
                total += n
        if EXTRA:
            for path in display_files():
                with open(path, encoding="utf-8", newline="") as f:
                    src = f.read()
                new = ONLOAD.sub(lambda m: m.group(1) + TRDISPLAY, src)
                if new != src:
                    with open(path, "w", encoding="utf-8", newline="") as f:
                        f.write(new)
        print(f"{total} affichages passés par {TR}")
    elif cmd == "stringtable":
        stringtable()
    elif cmd == "extract":
        have = {fr for fr, _ in load_dict()}
        found = collect()
        missing = [(s, src) for s, src in found.items() if s not in have]
        os.makedirs(os.path.dirname(MISSING), exist_ok=True)
        with open(MISSING, "w", encoding="utf-8") as f:
            for s, src in sorted(missing, key=lambda p: (p[1], p[0])):
                f.write(f"{s}\t{src}\n")
        print(f"{len(found)} textes, {len(missing)} sans traduction -> {os.path.relpath(MISSING, ROOT)}")
    elif cmd == "fr":
        build_fr()
    elif cmd == "community":
        game = ""
        if "--game" in sys.argv:
            i = sys.argv.index("--game")
            game = sys.argv[i + 1] if i + 1 < len(sys.argv) else ""
            del sys.argv[i:i + 2]
            match = [c for c in GAME_COLUMNS if c.lower() == game.lower()]
            if not match or match[0] in ("English", "French"):
                sys.exit("--game : une langue d'Arma parmi " + ", ".join(GAME_COLUMNS[2:]))
            game = match[0]
        pairs = community_pairs()
        path = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "localisation", "template", "xx.txt")
        if len(sys.argv) <= 2 and os.path.exists(path):
            os.remove(path)
        total, done = save_community(path, pairs)
        print(f"{path} : {done}/{total} phrases traduites ({total - done} restent en anglais)")
        if game:
            code = os.path.splitext(os.path.basename(path))[0]
            st = os.path.join(os.path.dirname(os.path.abspath(path)), "stringtable.xml")
            n = write_lang_stringtable(st, code, game, load_community(path))
            print(f"{st} : {n} noms d'objets et de modules ({game})")
        if len(sys.argv) <= 2:
            st = os.path.join(ROOT, "localisation", "template", "stringtable.json")
            print(f"{st} : {save_stringtable_keys(st)} clés de stringtable")
            # Catégories des phrases pour l'atelier /traduire-atak du site (template/categories.json).
            import subprocess
            subprocess.run([sys.executable, os.path.join(ROOT, "tools", "i18n_categories.py")], check=False)
    elif cmd == "merge":
        pairs = load_dict()
        with open(sys.argv[2], encoding="utf-8") as f:
            for line in f:
                line = line.rstrip("\n")
                if "\t" in line:
                    fr, en = line.split("\t", 1)
                    if PLACEHOLDER.findall(fr) and sorted(PLACEHOLDER.findall(fr)) != sorted(PLACEHOLDER.findall(en)):
                        print("modèle incohérent, ignoré :", fr, "->", en, file=sys.stderr)
                        continue
                    pairs.append((fr, en.split("\t")[0]))
        print(f"{save_dict(pairs)} entrées dans {os.path.relpath(DICT, ROOT)}")
    else:
        have = {fr for fr, _ in load_dict()}
        found = collect()
        print(f"{len(have)} traductions, {len(found)} textes dans le code, {sum(1 for s in found if s in have)} couverts")


if __name__ == "__main__":
    main()
