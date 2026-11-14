"""Génère Sources/addons/main/stringtable.xml (trié) depuis la table ci-dessous : clé -> (français, anglais).
python make_stringtable.py"""
import os
from xml.sax.saxutils import escape

T = {
    "PinTitle": ("Code du poste refusé", "Slot code refused"),
    "PinSubtitle": ("Le serveur n'a pas accepté votre code", "The server did not accept your code"),
    "PinDescription": (
        "Ce poste est protégé par un code. Revenez à l'écran des rôles, choisissez le poste, saisissez le code au pavé du dossier, puis rejoignez la mission. "
        "Après plusieurs refus, le poste vous est fermé pendant dix minutes.",
        "This slot is protected by a code. Go back to the role screen, pick the slot, enter the code on the dossier keypad, then join the mission. "
        "After several refusals, the slot is closed to you for ten minutes."),
    "Dossier": ("Dossier", "Dossier"),
    "Grade": ("Grade", "Rank"),
    "Unit": ("Unité", "Unit"),
    "Role": ("Fonction", "Role"),
    "Quals": ("Qualifications", "Qualifications"),
    "Pin": ("Code du poste", "Slot code"),
    "NoPin": ("Aucun code pour ce poste", "No code for this slot"),
    "PinSaved": ("Code gardé : le serveur le vérifiera à votre arrivée", "Code kept: the server will check it when you arrive"),
    "PinNeeded": ("Poste protégé : saisissez le code", "Protected slot: enter the code"),
    "Locked": ("Code", "Code"),
    "PortraitLocal": ("Portrait", "Portrait"),
    "ZenCategory": ("COMSPEC Lobby", "COMSPEC Lobby"),
    "ZenModule": ("Code du poste", "Slot code"),
    "ZenTag": ("Poste (étiquette)", "Slot (tag)"),
    "ZenTagTip": ("L'étiquette entre crochets dans la description du rôle, par exemple A-1.", "The tag in square brackets in the role description, for example A-1."),
    "ZenPin": ("Code (4 chiffres)", "Code (4 digits)"),
    "ZenPinTip": ("Quatre chiffres. Vide : le poste n'a plus de code posé par Zeus.", "Four digits. Empty: the slot no longer has a code set by Zeus."),
    "Set_Enabled": ("Codes de poste actifs", "Slot codes enabled"),
    "Set_Enabled_Tip": ("Coché : le serveur vérifie le code des postes protégés.  Exemple : coché.", "Ticked: the server checks the code of protected slots.  Example: ticked."),
    "Set_Attempts": ("Essais avant blocage", "Attempts before lockout"),
    "Set_Attempts_Tip": ("Après ce nombre de codes faux, le joueur est bloqué dix minutes.  Exemple : 3.", "After this many wrong codes, the player is locked out for ten minutes.  Example: 3."),
    "Set_Grace": ("Délai pour saisir le code (s)", "Time to enter the code (s)"),
    "Set_Grace_Tip": ("Un joueur arrivé sur un poste protégé sans avoir été déverrouillé dans ce délai est écarté.  Exemple : 45.", "A player on a protected slot who has not been unlocked within this time is removed.  Example: 45."),
    "Set_Kick": ("Expulser en cas d'échec", "Kick on failure"),
    "Set_Kick_Tip": ("Coché : un joueur qui n'a pas le code est expulsé (#kick) ; sinon son unité est gelée.  Exemple : coché.", "Ticked: a player without the code is kicked (#kick); otherwise their unit is frozen.  Example: ticked."),
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
