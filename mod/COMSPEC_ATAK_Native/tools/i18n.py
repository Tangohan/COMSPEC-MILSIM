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
                                     localisation/template/xx.txt quand F est omis
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
SKIP_FILES = {"fn_tr.sqf", "fn_langApply.sqf", "fn_aceAction.sqf", "fn_zenAction.sqf", "fn_zenModule.sqf", "fn_mainMenuButton.sqf"}
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
        if fr and en and fr != en:
            seen[fr] = en
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
    CFG_FILES = ["config.cpp", "CfgZeusRelayModules.hpp"] + sorted(
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
        for m in re.finditer(r'<Key ID="([^"]+)">\s*<Original>(.*?)</Original>\s*(?:<English>(.*?)</English>)?', open(STRINGTABLE, encoding="utf-8").read(), re.S):
            keys[m.group(1)] = _h.unescape(m.group(2))
            # Anglais écrit à la main dans la stringtable (absent de en.txt) : gardé.
            if m.group(3) and m.group(3) != m.group(2):
                kept.setdefault(_h.unescape(m.group(2)), _h.unescape(m.group(3)))
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
        rows.append(f'        <Key ID="{k}">\n            <Original>{esc(fr)}</Original>\n            <English>{esc(e)}</English>\n            <French>{esc(fr)}</French>\n        </Key>')
    xml = ('<?xml version="1.0" encoding="utf-8"?>\n<!-- Généré par tools/i18n.py stringtable : traductions dans i18n/en.txt. -->\n'
           '<Project name="' + ("COMSPEC_SSE" if SSE else ("COMSPEC_Overwatch_connect" if CONNECT else "COMSPEC_ATAK_Native")) + '">\n    <Package name="main">\n' + "\n".join(rows) + '\n    </Package>\n</Project>\n')
    open(STRINGTABLE, "w", encoding="utf-8", newline="\n").write(xml)
    print(f"{len(keys)} textes de config, {len(missing)} sans anglais")
    for fr in missing:
        print("  " + fr)


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
    elif cmd == "community":
        pairs = load_dict()
        path = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "localisation", "template", "xx.txt")
        if len(sys.argv) <= 2 and os.path.exists(path):
            os.remove(path)
        total, done = save_community(path, pairs)
        print(f"{path} : {done}/{total} phrases traduites ({total - done} restent en anglais)")
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
