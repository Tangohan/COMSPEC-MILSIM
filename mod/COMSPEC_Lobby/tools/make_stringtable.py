"""Génère Sources/addons/main/stringtable.xml (trié) depuis la table ci-dessous : clé -> (français, anglais).
python make_stringtable.py"""
import os
from xml.sax.saxutils import escape

T = {
    "Grade": ("Grade", "Rank"),
    "Unit": ("Unité", "Unit"),
    "Role": ("Fonction", "Role"),
    "Quals": ("Qualifications", "Qualifications"),
    "Sync": ("Synchroniser", "Synchronise"),
    "SyncRunning": ("Synchronisation avec Athena…", "Synchronising with Athena…"),
    "SyncDone": ("Dossier à jour.", "Dossier up to date."),
    "SyncNoUid": ("Jouez une partie avec COMSPEC ATAK : votre dossier sera gardé à la fin.", "Play one game with COMSPEC ATAK: your dossier is saved at the end."),
    "SyncNoAtak": ("COMSPEC ATAK n'est pas chargé : pas de dossier Athena.", "COMSPEC ATAK is not loaded: no Athena dossier."),
    "SyncNoLink": ("Athena n'est pas joignable d'ici. Entrez en jeu : le dossier se met à jour pendant la partie.", "Athena cannot be reached from here. Join the game: the dossier updates during play."),
    "PortraitLocal": ("Portrait", "Portrait"),
}

HERE = os.path.dirname(os.path.abspath(__file__))
out = os.path.join(HERE, "..", "Sources", "addons", "main", "stringtable.xml")
lines = ['<?xml version="1.0" encoding="utf-8"?>', '<Project name="COMSPEC Lobby">', '    <Package name="main">', '        <Container name="main">']
for k in sorted(T):
    fr, en = T[k]
    lines += [f'            <Key ID="STR_COMSPEC_LOBBY_{k}">', f"                <Original>{escape(fr)}</Original>",
              f"                <English>{escape(en)}</English>", f"                <French>{escape(fr)}</French>", "            </Key>"]
lines += ["        </Container>", "    </Package>", "</Project>", ""]
open(out, "w", encoding="utf-8", newline="").write("\n".join(lines))
print(len(T), "clés écrites")
