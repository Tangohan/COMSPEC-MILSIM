#!/usr/bin/env python3
"""
Traduction anglaise du site (affichage).

Les vues restent écrites en français. Quand la langue du visiteur est l'anglais, App\\Support\\HtmlTranslator
traduit la page rendue (textes entre balises, title, placeholder, aria-label, alt, value des boutons) avec le
dictionnaire lang/en/site_auto.php : ['texte français' => 'English text', ...]. Un texte avec %1, %2... est un modèle
(« Bienvenue, %1 ! » traduit « Bienvenue, Jean ! ») ; dans les vues, chaque <?= ... ?> au milieu d'un texte devient %N.

  python3 tools/i18n/site_i18n.py extract [dossier de views ...]   textes sans traduction -> tools/i18n/site_missing.tsv
  python3 tools/i18n/site_i18n.py merge fichier.tsv                ajoute les lignes « français<TAB>anglais »
  python3 tools/i18n/site_i18n.py stats

Les e-mails (views/emails) sont aussi rendus avec des valeurs repères (render_emails.php) : chaque texte devient un
modèle %1, %2… ; EmailService les traduit pour un destinataire dont le profil est en anglais.
"""
import html
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
VIEWS = os.path.join(ROOT, "views")
DICT = os.path.join(ROOT, "lang", "en", "site_auto.php")
MISSING = os.path.join(ROOT, "tools", "i18n", "site_missing.tsv")

PHP_BLOCK = re.compile(r"<\?(?:php|=)?(.*?)(?:\?>|\Z)", re.S)
TAG = re.compile(r"<[^<>]*>")
ATTR = re.compile(r"""\b(?:title|placeholder|aria-label|alt|value|data-confirm)\s*=\s*(["'])(.*?)\1""", re.S | re.I)
SKIP_BLOCK = re.compile(r"<(script|style|textarea|pre|code)\b[^>]*>.*?</\1\s*>", re.S | re.I)
PHP_STR = re.compile(r"""'((?:[^'\\]|\\.)*)'|"((?:[^"\\$]|\\.)*)\"""")
WORD = re.compile(r"[A-Za-zÀ-ÿ]")
FRENCH_HINT = re.compile(r"[àâäçéèêëîïôöùûüÿœÀÂÇÉÈÊÎÔÙÛŒ]|\b(le|la|les|des|du|un|une|et|ou|pour|avec|sans|dans|sur|par|vos|votre|nos|notre|est|sont|pas|ne|aucun|aucune|à)\b", re.I)


def norm(s):
    s = html.unescape(s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def is_text(s):
    if not s or not WORD.search(s):
        return False
    if re.fullmatch(r"[\w\-\./:#%]+", s) and not re.search(r"[À-ÿ]", s) and " " not in s:
        # Un seul mot : gardé s'il commence par une majuscule ou porte un accent (libellé), sinon identifiant probable.
        return bool(re.fullmatch(r"[A-ZÀ-Ý][a-zà-ÿ]+", s))
    if re.search(r"[{};$]|=>|->|::|\bfunction\b|https?://", s):
        return False
    return True


def templ(chunk):
    """Texte de vue avec <?= ... ?> -> modèle %1, %2..."""
    n = [0]

    def rep(m):
        n[0] += 1
        return f"%{n[0]}"
    return re.sub(r"<\?=.*?\?>", rep, chunk, flags=re.S)


def segments_from_text(t):
    for part in re.split(r"\s+·\s+", t):
        p = norm(part)
        if is_text(p) and not re.fullmatch(r"(%\d\s*)+", p):
            yield p


def extract_view(src):
    out = set()
    # Littéraux PHP (libellés dans des tableaux, ternaires) : seulement s'ils ont l'air français.
    for m in PHP_BLOCK.finditer(src):
        code = m.group(1)
        for sm in PHP_STR.finditer(code):
            lit = sm.group(1) if sm.group(1) is not None else sm.group(2)
            if lit is None:
                continue
            lit = lit.replace("\\'", "'")
            if not FRENCH_HINT.search(lit):
                continue
            for chunk in TAG.split(lit):
                lit2 = re.sub(r"%[sd]", lambda _m, c=[0]: (c.__setitem__(0, c[0] + 1), f"%{c[0]}")[1], chunk)
                out.update(segments_from_text(lit2))
    # Gabarit HTML : PHP non affiché retiré, <?= ?> -> %N dans chaque texte.
    tpl = re.sub(r"<\?php.*?(?:\?>|\Z)", "\x00", src, flags=re.S)
    tpl = SKIP_BLOCK.sub("\x00", tpl)
    for am in ATTR.finditer(tpl):
        v = am.group(2)
        if "<?=" in v:
            v = templ(v)
        out.update(segments_from_text(v))
    pieces = re.split(r"(<(?!\?=)[^<>]*(?:<\?=.*?\?>[^<>]*)*>)", tpl)
    for p in pieces:
        if p.startswith("<") and not p.startswith("<?="):
            continue
        for chunk in p.split("\x00"):
            out.update(segments_from_text(templ(chunk)))
    return out


MARK = re.compile(r"88000\d\d")


def email_segments():
    """Textes des e-mails rendus (HTML et texte brut), valeurs des variables -> %1, %2… par texte."""
    import json
    import subprocess
    try:
        raw = subprocess.run(["php", os.path.join(ROOT, "tools", "i18n", "render_emails.php")], capture_output=True,
                             text=True, timeout=120).stdout
        data = json.loads(raw or "{}")
    except (OSError, ValueError, subprocess.TimeoutExpired):
        return {}
    out = {}

    def add(chunk, name):
        n = [0]

        def rep(_m):
            n[0] += 1
            return f"%{n[0]}"
        for seg in segments_from_text(MARK.sub(rep, chunk)):
            if n[0] <= 9:
                out.setdefault(seg, "emails/" + name)
    for key, parts in data.items():
        name = key.split("#")[0]
        h = SKIP_BLOCK.sub("\x00", parts.get("html", ""))
        for am in ATTR.finditer(h):
            add(am.group(2), name)
        for p in re.split(r"<[^<>]*>", h):
            for chunk in p.split("\x00"):
                add(chunk, name)
        for line in parts.get("text", "").split("\n"):
            add(line, name)
    return out


def view_files(dirs):
    roots = [os.path.join(VIEWS, d) for d in dirs] if dirs else [VIEWS]
    for r in roots:
        if not os.path.isdir(r):
            r = r if os.path.isfile(r) else r + ".php"
            if os.path.isfile(r):
                yield r
            continue
        for base, _, files in os.walk(r):
            for f in sorted(files):
                if f.endswith(".php"):
                    yield os.path.join(base, f)


def load_dict():
    if not os.path.exists(DICT):
        return {}
    txt = open(DICT, encoding="utf-8").read()
    d = {}
    for m in re.finditer(r"^\s*'((?:[^'\\]|\\.)*)'\s*=>\s*'((?:[^'\\]|\\.)*)',\s*$", txt, re.M):
        un = lambda s: s.replace("\\'", "'").replace("\\\\", "\\")
        d[un(m.group(1))] = un(m.group(2))
    return d


def save_dict(d):
    q = lambda s: "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"
    items = sorted(((k, v) for k, v in d.items() if k and v and k != v), key=lambda p: p[0].lower())
    body = "".join(f"    {q(k)} => {q(v)},\n" for k, v in items)
    head = ("<?php\n\ndeclare(strict_types=1);\n\n"
            "// Dictionnaire français -> anglais du site, appliqué à l'affichage par App\\Support\\HtmlTranslator.\n"
            "// Tenu par tools/i18n/site_i18n.py (extract, merge). %1, %2... : valeurs insérées par la vue.\n\n"
            "return [\n")
    with open(DICT, "w", encoding="utf-8", newline="\n") as f:
        f.write(head + body + "];\n")
    return len(items)


def main():
    cmd = sys.argv[1] if len(sys.argv) > 1 else "stats"
    if cmd == "extract":
        have = load_dict()
        found = {}
        for path in view_files(sys.argv[2:]):
            rel = os.path.relpath(path, VIEWS)
            for s in extract_view(open(path, encoding="utf-8", errors="replace").read()):
                found.setdefault(s, rel)
        if not sys.argv[2:]:
            for s, r in email_segments().items():
                found.setdefault(s, r)
        missing = sorted(((s, r) for s, r in found.items() if s not in have), key=lambda p: (p[1], p[0]))
        with open(MISSING, "w", encoding="utf-8") as f:
            for s, r in missing:
                f.write(f"{s}\t{r}\n")
        print(f"{len(found)} textes, {len(missing)} sans traduction -> {os.path.relpath(MISSING, ROOT)}")
    elif cmd == "merge":
        d = load_dict()
        for line in open(sys.argv[2], encoding="utf-8"):
            line = line.rstrip("\n")
            if "\t" not in line:
                continue
            fr, en = line.split("\t")[:2]
            if sorted(re.findall(r"%\d", fr)) != sorted(re.findall(r"%\d", en)):
                print("modèle incohérent, ignoré :", fr, "->", en, file=sys.stderr)
                continue
            d[fr] = en
        print(f"{save_dict(d)} entrées dans {os.path.relpath(DICT, ROOT)}")
    else:
        print(f"{len(load_dict())} traductions")


if __name__ == "__main__":
    main()
