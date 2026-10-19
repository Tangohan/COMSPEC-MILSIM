#!/usr/bin/env python3
"""
Catégories des phrases du modèle de traduction communautaire (page publique /traduire-atak du site).

Pour chaque phrase de localisation/template/xx.txt : l'écran ou la fonction d'où elle vient (fichier source le premier
trouvé par tools/i18n.py collect, pour l'ATAK natif, l'addon Overwatch connect et le mod SSE), rangée dans une
catégorie (Carte, Appui feu, Drone…). Écrit localisation/template/categories.json :

  {"version": 1, "categories": {"map": {"fr": "Carte", "en": "Map"}, ...},
   "strings": {"<sha1(français)[:16]>": ["map", "functions/map/fn_x.sqf"], ...}}

Le site s'en sert pour la progression par catégorie et l'aperçu « où apparaît cette phrase » ; une phrase absente
reste dans « Autres ». Lecture seule des sources (l'addon connect sous UptoDate n'est pas modifié).

  python3 tools/i18n_categories.py      (relancé aussi par « python3 tools/i18n.py community » sans fichier)
"""
import hashlib
import importlib.util
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMPLATE = os.path.join(ROOT, "localisation", "template", "xx.txt")
OUT = os.path.join(ROOT, "localisation", "template", "categories.json")

# id : (français, anglais). Les écrans du téléphone (functions/pages/fn_page<App>.sqf, fn_<app><Action>.sqf) et les
# dossiers de functions/ ; alias pour les noms au pluriel ou abrégés.
LABELS = {
    "map": ("Carte", "Map"), "nav": ("GPS et navigation", "GPS and navigation"), "bft": ("BFT", "BFT"),
    "chat": ("Messagerie", "Messaging"), "fires": ("Appui feu", "Fire support"), "medical": ("Médical", "Medical"),
    "ew": ("Guerre électronique", "Electronic warfare"), "logistics": ("Logistique", "Logistics"),
    "drone": ("Drone", "Drone"), "camera": ("Appareil photo", "Camera"), "photo": ("Photos", "Photos"),
    "livecam": ("Live cam", "Live cam"), "reports": ("Comptes rendus", "Reports"), "plan": ("Planification", "Planning"),
    "tactical": ("Tactique", "Tactical"), "core": ("Cœur du téléphone", "Phone core"), "network": ("Réseau", "Network"),
    "datalink": ("Liaison de données", "Data link"), "ui": ("Écrans et fenêtres", "Screens and windows"),
    "teams": ("Équipes", "Teams"), "shell": ("Accueil et lanceur", "Home and launcher"),
    "bluetooth": ("Bluetooth", "Bluetooth"), "zeus": ("Zeus", "Zeus"), "music": ("Musique", "Music"),
    "garmin": ("Garmin", "Garmin"), "screen": ("Écran", "Screen"), "explo": ("Explosifs", "Explosives"),
    "viz": ("Visualisation", "Visualisation"), "dock": ("Socle de charge", "Charging dock"),
    "vehicle": ("Véhicule", "Vehicle"), "aar": ("RETEX", "AAR"), "settings": ("Réglages", "Settings"),
    "cba": ("Options de l'addon", "Addon options"), "alerts": ("Alertes", "Alerts"), "athena": ("Athena", "Athena"),
    "bda": ("BDA", "BDA"), "briefing": ("Briefing", "Briefing"), "c2": ("C2", "C2"), "credits": ("Crédits", "Credits"),
    "dating": ("Rencontres", "Dating"), "debug": ("Diagnostic", "Diagnostics"), "discord": ("Discord", "Discord"),
    "food": ("Ravitaillement", "Food"), "frs": ("Fiches FRS", "FRS sheets"), "gallery": ("Galerie", "Gallery"),
    "group": ("Groupe", "Group"), "linkally": ("Liaison alliés", "Allied link"), "notifs": ("Notifications", "Notifications"),
    "order": ("Ordres", "Orders"), "osint": ("OSINT", "OSINT"), "profile": ("Profil", "Profile"),
    "recents": ("Récents", "Recents"), "reco": ("Reconnaissance", "Reconnaissance"), "relief": ("Relief", "Relief"),
    "resynch": ("Resynchronisation", "Resync"), "salute": ("SALUTE", "SALUTE"), "sse_page": ("SSE (téléphone)", "SSE (phone)"),
    "status": ("État", "Status"), "tasks": ("Tâches", "Tasks"), "text": ("Textes", "Texts"), "tracking": ("Suivi", "Tracking"),
    "wanted": ("Avis de recherche", "Wanted"), "waverelay": ("Wave Relay", "Wave Relay"), "weather": ("Météo", "Weather"),
    "overwatch": ("Connexion Overwatch", "Overwatch connection"), "sse": ("Mod SSE", "SSE mod"),
    "other": ("Autres", "Other"),
}
ALIASES = {"messages": "chat", "logi": "logistics", "photos": "photo", "task": "tasks", "ordercompose": "order",
           "sse": "sse_page"}


def load_collect(mode):
    """Résultat de collect() de tools/i18n.py ({français: fichier}) pour main, --connect ou --sse."""
    argv = sys.argv
    sys.argv = ["i18n.py"] + ([mode] if mode else [])
    try:
        spec = importlib.util.spec_from_file_location("i18n_" + (mode.strip("-") or "main"), os.path.join(ROOT, "tools", "i18n.py"))
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)
        return mod.collect()
    finally:
        sys.argv = argv


def page_app(name):
    """fn_pageFires.sqf -> fires ; fn_firesCff.sqf -> fires ; fn_linkAllyInit.sqf -> linkally."""
    base = re.sub(r"^fn_", "", os.path.splitext(name)[0])
    base = re.sub(r"^page", "", base)
    m = re.match(r"[A-Za-z][a-z0-9]*(?:Ally|Relay|Compose)?", base)
    app = (m.group(0) if m else base).lower()
    return ALIASES.get(app, app)


def category(rel):
    parts = rel.replace("\\", "/").split("/")
    if parts[0] == "functions" and len(parts) > 2:
        if parts[1] == "pages":
            app = page_app(parts[2])
            return app if app in LABELS else "other"
        return parts[1] if parts[1] in LABELS else "other"
    if parts[0].startswith("XEH_"):
        return "cba"
    if parts[0] == "ui":
        return "ui"
    return "other"


def main():
    with open(TEMPLATE, encoding="utf-8-sig") as f:
        keys = [m.group(1).replace('""', '"') for m in re.finditer(r'\[\s*"((?:[^"]|"")*)"\s*,', f.read())]
    main_src, connect_src, sse_src = load_collect(""), load_collect("--connect"), load_collect("--sse")
    strings, used = {}, set()
    for fr in keys:
        if fr in main_src:
            rel = main_src[fr].replace("\\", "/")
            cat = category(rel)
        elif fr in connect_src:
            rel, cat = "Overwatch connect/" + connect_src[fr].replace("\\", "/"), "overwatch"
        elif fr in sse_src:
            rel, cat = "@COMSPEC_SSE/" + sse_src[fr].replace("\\", "/"), "sse"
        else:
            rel, cat = "", "other"
        used.add(cat)
        strings[hashlib.sha1(fr.encode("utf-8")).hexdigest()[:16]] = [cat, rel]
    cats = {k: {"fr": v[0], "en": v[1]} for k, v in LABELS.items() if k in used}
    with open(OUT, "w", encoding="utf-8", newline="\n") as f:
        json.dump({"version": 1, "categories": cats, "strings": strings}, f, ensure_ascii=False, separators=(",", ":"))
        f.write("\n")
    other = sum(1 for v in strings.values() if v[0] == "other")
    print(f"{OUT} : {len(strings)} phrases, {len(cats)} catégories, {other} dans « Autres »")


if __name__ == "__main__":
    main()
