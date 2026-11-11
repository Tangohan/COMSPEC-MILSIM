#!/usr/bin/env python3
"""Textures de l'extension d'exemple (deux documents indépendants du pays) : carte de presse et laissez-passer.

Tout est décrit par un dict déclaratif passé à gen_id_assets.render(spec) (voir gen_id_assets.SPEC_KEYS) : un auteur
d'extension n'a qu'à écrire sa spec (couleurs, bandeau, étiquettes, photo, MRZ…) ; les helpers (guilloche, emblem,
header, labels, photo_frame, chip, footer, stamp, to_paa…) viennent de mod/@COMSPEC_SSE/tools/gen_id_assets.py.
Les ÉTIQUETTES sont peintes ici ; les VALEURS sont posées en jeu (config.cpp de l'extension) à y + 0,04.

  python gen_textures.py            # écrit ../data/*.paa
  python gen_textures.py --png DIR  # garde aussi les PNG sources dans DIR

Sortie : tpl_press_card_recto_co.paa, tpl_laissez_passer_recto_co.paa (1024 x 512, étirés au format réel en jeu : ID-1 1,585,
A6 paysage 1,414), icon_press_card_ca.paa, icon_laissez_passer_ca.paa (256).
Dépendances : Pillow, numpy ; HEMTT dans le PATH (sinon paa_dxt.py de l'ATAK).
"""
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SSE = os.path.dirname(os.path.dirname(os.path.dirname(HERE)))   # mod/@COMSPEC_SSE
sys.path.insert(0, os.path.join(SSE, "tools"))
import gen_id_assets as ida  # noqa: E402

OUT = os.path.join(os.path.dirname(HERE), "data")

NAVY = (28, 44, 92)
INK = (58, 52, 44)        # encre du laissez-passer
CREAM = (246, 240, 226)

# Carte de presse : carte ID-1 bleu marine / blanc, photo, pastille SIMULATION, pied.
PRESS_CARD = {
    "ratio": 1.585,
    "colors": {"main": NAVY, "accent": (160, 120, 70), "bg": (240, 243, 248)},
    "guilloche": {"seed": 21, "rosettes": [(0.92, 0.58, 0.26)]},
    "header": {"name": "CARTE DE PRESSE", "title": "PRESS CARD  ·  SIMULATION — DOCUMENT DE JEU", "code": "PRESS", "band": 0.155,
               "emblem": (0.065, 0.078, 0.058), "pill": "SIMULATION", "name_x": 0.13},
    "photo": (0.045, 0.215, 0.265, 0.57),
    "labels": [
        ("NOM / SURNAME", 0.34, 0.205), ("PRÉNOMS / GIVEN NAMES", 0.34, 0.325),
        ("MÉDIA / MEDIA", 0.34, 0.445), ("FONCTION / POSITION", 0.34, 0.565),
        ("N° DE CARTE / CARD No", 0.34, 0.685), ("VALIDE JUSQU'AU / VALID UNTIL", 0.64, 0.685),
    ],
    "signature": (0.34, 0.60, 0.905),
    "footer": 0.965,
}

# Laissez-passer : feuille A6 paysage, papier crème texturé, en-tête à filet double, tampon rouge délavé ; ni photo ni MRZ.
LAISSEZ_PASSER = {
    "ratio": 1.414,
    "colors": {"main": INK, "accent": (150, 110, 80), "bg": CREAM, "label": (96, 88, 76)},
    "paper": "cream",
    "guilloche": {"seed": 22, "alpha": 0.16, "lattice": False, "bands": 2, "y_range": (0.2, 0.9)},
    "heading": {"text": "LAISSEZ-PASSER  ·  PASS", "y": 0.10, "size": 0.062, "rules": True},
    "microtext": 0.19,
    "labels": [
        ("NOM / SURNAME", 0.08, 0.22), ("PRÉNOMS / GIVEN NAMES", 0.08, 0.34),
        ("DOCUMENT DE RÉFÉRENCE / REFERENCE DOCUMENT", 0.08, 0.46), ("ZONE AUTORISÉE / AUTHORISED AREA", 0.08, 0.58),
        ("VALABLE DU / VALID FROM", 0.08, 0.70), ("AU / TO", 0.40, 0.70), ("AUTORITÉ / AUTHORITY", 0.08, 0.82),
    ],
    "label_size": 0.024,
    "stamp": (0.80, 0.72, 0.16, "SIMULATION", "DOCUMENT DE JEU"),
    "footer": 0.965,
}


def main():
    keep = sys.argv[sys.argv.index("--png") + 1] if "--png" in sys.argv else None
    jobs = {
        "tpl_press_card_recto_co": ida.render(PRESS_CARD),
        "tpl_laissez_passer_recto_co": ida.render(LAISSEZ_PASSER),
        "icon_press_card_ca": ida.icon_card(NAVY, (240, 243, 248), chip_on=False, text="PRESSE"),
        "icon_laissez_passer_ca": ida.icon_sheet(INK, CREAM),
    }
    ida.write_jobs(jobs, OUT, keep)


if __name__ == "__main__":
    main()
