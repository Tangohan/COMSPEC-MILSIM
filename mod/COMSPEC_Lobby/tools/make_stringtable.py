"""Génère Sources/addons/main/stringtable.xml (trié) depuis la table ci-dessous : clé -> (français, anglais).
python make_stringtable.py"""
import os
from xml.sax.saxutils import escape

T = {
    "Grade": ("Grade", "Rank"),
    "Unit": ("Unité", "Unit"),
    "Role": ("Fonction", "Role"),
    "Quals": ("Qualifications", "Qualifications"),
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
