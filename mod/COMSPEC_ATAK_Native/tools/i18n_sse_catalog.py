#!/usr/bin/env python3
"""
Phrases du mod SSE pour le modèle de traduction communautaire (page /traduire-atak du site).

L'écran SSE demande ses traductions au téléphone ATAK quand il est chargé : ses phrases rejoignent donc le même
dictionnaire. Ce script écrit localisation/template/sse.json, lu par App\\Support\\AtakTranslationTemplate :

  {"version": 1,
   "categories": {"sse_seek": {"fr": "SSE : SEEK II", "en": "SSE: SEEK II"}, ...},
   "place": {"<sha1(français)[:16]>": ["sse_seek", "@COMSPEC_SSE/biometrics/functions/fn_x.sqf"], ...},
   "extra": [["phrase absente de xx.txt", "anglais de i18n/en.txt"], ...]}

- place : écran SSE d'où vient chaque phrase (elle passe dans la catégorie SSE correspondante, même si elle est
  déjà dans xx.txt) ;
- extra : phrases SSE qui ne sont pas encore dans xx.txt (le modèle les ajoute à la suite).

Sources lues (sans les modifier) : le mod @COMSPEC_SSE (tools/i18n.py --sse collect) et les écrans SEEK de l'addon
Overwatch connect (fn_sse*.sqf, display_sse*.hpp, via tools/i18n.py --connect collect).

  python3 tools/i18n_sse_catalog.py
"""
import hashlib
import importlib.util
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMPLATE = os.path.join(ROOT, "localisation", "template", "xx.txt")
DICT = os.path.join(ROOT, "Sources", "addons", "main", "i18n", "en.txt")
OUT = os.path.join(ROOT, "localisation", "template", "sse.json")

# id : (français, anglais), dans l'ordre d'affichage de l'atelier.
LABELS = {
    "sse_seek": ("SSE : SEEK II et biométrie", "SSE: SEEK II and biometrics"),
    "sse_bureau": ("SSE : bureau du renseignement", "SSE: intelligence office"),
    "sse_digital": ("SSE : exploitation numérique", "SSE: digital exploitation"),
    "sse_evidence": ("SSE : indices et scellés", "SSE: evidence"),
    "sse_interaction": ("SSE : fouille et interactions", "SSE: search and interactions"),
    "sse_zeus": ("SSE : Zeus et Eden", "SSE: Zeus and Eden"),
    "sse_guide": ("SSE : guide", "SSE: guide"),
    "sse_core": ("SSE : cœur du mod", "SSE: mod core"),
}
# Dossier d'addon du mod SSE -> catégorie.
ADDONS = {
    "biometrics": "sse_seek",
    "analysis": "sse_bureau", "intel": "sse_bureau", "campaign": "sse_bureau",
    "digital": "sse_digital",
    "evidence": "sse_evidence",
    "interaction": "sse_interaction",
    "zeus": "sse_zeus", "eden": "sse_zeus",
    "guide": "sse_guide",
}
CONNECT_SSE = re.compile(r"(^|/)(fn_sse\w*\.sqf|display_sse\w*\.hpp)$")


def load_collect(mode):
    """collect() de tools/i18n.py ({français: fichier relatif}) pour --sse ou --connect."""
    argv = sys.argv
    sys.argv = ["i18n.py", mode]
    try:
        spec = importlib.util.spec_from_file_location("i18n_" + mode.strip("-"), os.path.join(ROOT, "tools", "i18n.py"))
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)
        return mod.collect()
    finally:
        sys.argv = argv


def read_pairs(path):
    if not os.path.exists(path):
        return []
    with open(path, encoding="utf-8-sig") as f:
        txt = f.read()
    return [(m.group(1).replace('""', '"'), m.group(2).replace('""', '"'))
            for m in re.finditer(r'\[\s*"((?:[^"]|"")*)"\s*,\s*"((?:[^"]|"")*)"', txt)]


def looks_like_text(fr):
    """Phrase affichée plutôt que valeur technique : collect() ratisse large (relu à la main pour en.txt).
    On ne garde que des phrases d'au moins deux mots dont un en minuscules (pas de noms propres générés, de codes ni de
    journaux de débogage)."""
    t = fr.strip()
    if len(t) < 6 or " " not in t or "=" in t or re.search(r"XEH_|\bfnc_|\bSSE-", t):
        return False
    return bool(re.search(r"\b[a-zà-ÿ]{3,}\b", t))


def key(fr):
    return hashlib.sha1(fr.encode("utf-8")).hexdigest()[:16]


def main():
    template = {fr for fr, _ in read_pairs(TEMPLATE)}
    english = dict(read_pairs(DICT))
    found = []  # (français, catégorie, fichier) dans l'ordre de découverte
    for fr, rel in load_collect("--sse").items():
        rel = rel.replace("\\", "/")
        found.append((fr, ADDONS.get(rel.split("/")[0], "sse_core"), "@COMSPEC_SSE/" + rel))
    for fr, rel in load_collect("--connect").items():
        rel = rel.replace("\\", "/")
        if CONNECT_SSE.search(rel):
            found.append((fr, "sse_seek", "Overwatch connect/" + rel))
    place, extra, seen, used = {}, [], set(), set()
    for fr, cat, rel in found:
        if fr in seen:
            continue
        seen.add(fr)
        used.add(cat)
        place[key(fr)] = [cat, rel]
        if fr not in template and (fr in english or looks_like_text(fr)):
            extra.append([fr, english.get(fr, "")])
    # Les deux écrans principaux restent listés même vides, pour que l'atelier les propose toujours.
    used |= {"sse_seek", "sse_bureau"}
    cats = {k: {"fr": v[0], "en": v[1]} for k, v in LABELS.items() if k in used}
    with open(OUT, "w", encoding="utf-8", newline="\n") as f:
        json.dump({"version": 1, "categories": cats, "place": place, "extra": extra}, f, ensure_ascii=False, separators=(",", ":"))
        f.write("\n")
    print(f"{OUT} : {len(place)} phrases SSE, {len(extra)} hors du modèle, {len(cats)} catégories")


if __name__ == "__main__":
    main()
