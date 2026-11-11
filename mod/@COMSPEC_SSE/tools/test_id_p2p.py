#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Essais hors jeu du contrôle des papiers entre joueurs (addon identity). Aucun jeu lancé.

  python tools/test_id_p2p.py      # 0 si tout passe

1. Règles pures : portage ligne à ligne de fn_idP2pRules.sqf (automate d'une demande, distance et vue, sous-ensemble de
   papiers, anti-spam, motifs, bornes). Les constantes (délais, bornes, motifs) sont relues dans le SQF : les changer
   là-bas sans changer la règle garde les essais justes ; changer la règle demande de la changer ici.
2. Câblage statique : entrées du serveur et du poste, fenêtre, réglages, clés de traduction (français et anglais),
   aucune alerte de l'interface d'Arma dans les nouveaux fichiers.
Même jeu d'essais que tools/test_id_p2p.sqf (à lancer en jeu, console de débogage).
"""
import os
import re
import sys
import xml.dom.minidom

HERE = os.path.dirname(os.path.abspath(__file__))
ID = os.path.join(HERE, "..", "addons", "identity")


def read(*parts):
    with open(os.path.join(ID, *parts), encoding="utf-8") as f:
        return f.read()


RULES = read("functions", "fn_idP2pRules.sqf")


def const(pattern, cast=float):
    m = re.search(pattern, RULES)
    if not m:
        raise SystemExit("Constante introuvable dans fn_idP2pRules.sqf : " + pattern)
    return [cast(g) for g in m.groups()]


COOL, COOL_REFUSED = const(r'>= \(\[(\d+), (\d+)\] select \(_lastState isEqualTo "refused"\)\)')
(VIS_MIN,) = const(r"if \(_vis < ([0-9.]+)\) exitWith")
T_MIN, T_MAX = const(r'case "timeout": \{ \(\(round _a\) max (\d+)\) min (\d+) \}')
D_MIN, D_MAX = const(r'case "distance": \{ \(_a max (\d+)\) min (\d+) \}')
REASONS = re.search(r'case "reason": \{ \["", _a\] select \(_a in \[([^\]]+)\]\) \}', RULES)
REASONS = [r.strip().strip('"') for r in REASONS.group(1).split(",")]


# ---------------------------------------------------------------- portage de fn_idP2pRules.sqf
def enabled(setting, zeus=0):
    if zeus == 1:
        return True
    if zeus == 2:
        return False
    return setting is True


def nxt(state, event, now, deadline, can_refuse=True):
    if state != "pending":
        return ""
    if now > deadline:
        return "timeout" if event in ("accept", "refuse", "tick") else ""
    if event == "accept":
        return "accepted"
    if event == "refuse":
        return "refused" if can_refuse else ""
    if event == "tick":
        return "pending"
    return ""


def rng(dist, mx, vis, on_foot=True):
    if not on_foot:
        return "vehicle"
    if dist > mx:
        return "far"
    if vis < VIS_MIN:
        return "los"
    return ""


def subset(asked, owned, mx=12):
    if not asked or len(asked) > mx:
        return []
    out = []
    for x in asked:
        if not isinstance(x, str) or x not in owned or x in out:
            return []
        out.append(x)
    return out


def rate(now, last_end, last_state):
    return (now - last_end) >= (COOL_REFUSED if last_state == "refused" else COOL)


def reason(code):
    return code if code in REASONS else ""


def mode(code):
    return "give" if code == "give" else "show"


def timeout(v):
    return min(max(round(v), T_MIN), T_MAX)


def distance(v):
    return min(max(v, D_MIN), D_MAX)


FAILS = []


def eq(label, got, want):
    if got != want:
        FAILS.append("%s : %r (attendu %r)" % (label, got, want))


def rules():
    # Réglage et choix du Zeus.
    eq("réglage coché", enabled(True, 0), True)
    eq("réglage décoché", enabled(False, 0), False)
    eq("Zeus autorise malgré le réglage", enabled(False, 1), True)
    eq("Zeus interdit malgré le réglage", enabled(True, 2), False)
    # Automate : pending -> accepted | refused | timeout, états finaux fermés.
    eq("accepté dans les temps", nxt("pending", "accept", 10, 20), "accepted")
    eq("refusé dans les temps", nxt("pending", "refuse", 10, 20), "refused")
    eq("refus interdit par la mission", nxt("pending", "refuse", 10, 20, False), "")
    eq("échéance pas encore passée", nxt("pending", "tick", 20, 20), "pending")
    eq("pas de réponse", nxt("pending", "tick", 20.1, 20), "timeout")
    eq("accord trop tard", nxt("pending", "accept", 21, 20), "timeout")
    eq("refus trop tard", nxt("pending", "refuse", 21, 20), "timeout")
    eq("évènement inconnu", nxt("pending", "cancel", 10, 20), "")
    for final in ("accepted", "refused", "timeout", ""):
        for ev in ("accept", "refuse", "tick"):
            eq("état final %s fermé à %s" % (final or "vide", ev), nxt(final, ev, 10, 20), "")
    # Jamais d'accord automatique : seul l'évènement accept mène à accepted.
    for ev in ("refuse", "tick", "", "present"):
        for now in (0, 20, 99):
            eq("pas d'accord sans accept (%s, %s)" % (ev, now), nxt("pending", ev, now, 20) == "accepted", False)
    # Distance, vue, véhicule.
    eq("face à face", rng(2.5, 3, 1), "")
    eq("à la limite", rng(3, 3, 1), "")
    eq("trop loin", rng(3.2, 3, 1), "far")
    eq("derrière un mur", rng(2, 3, 0), "los")
    eq("à peine visible", rng(2, 3, VIS_MIN), "")
    eq("en véhicule", rng(1, 3, 1, False), "vehicle")
    eq("véhicule avant distance", rng(50, 3, 0, False), "vehicle")
    # Sous-ensemble de papiers réellement portés.
    owned = ["COMSPEC_ID_Card_001", "COMSPEC_ID_Passport_004", "COMSPEC_ID_DrivingLicense_002"]
    eq("un papier", subset([owned[0]], owned), [owned[0]])
    eq("tous", subset(list(owned), owned), owned)
    eq("ordre gardé", subset([owned[2], owned[0]], owned), [owned[2], owned[0]])
    eq("aucun", subset([], owned), [])
    eq("papier d'un autre", subset([owned[0], "COMSPEC_ID_Card_099"], owned), [])
    eq("doublon", subset([owned[0], owned[0]], owned), [])
    eq("pas une classe", subset([owned[0], 7], owned), [])
    eq("trop de papiers", subset(list(owned), owned, 2), [])
    eq("rien sur soi", subset([owned[0]], []), [])
    # Anti-spam par paire.
    eq("première demande", rate(100, -1e6, ""), True)
    eq("trop tôt", rate(100, 100 - COOL + 1, "timeout"), False)
    eq("délai passé", rate(100, 100 - COOL, "accepted"), True)
    eq("après un refus, trop tôt", rate(100, 100 - COOL, "refused"), False)
    eq("après un refus, délai passé", rate(100, 100 - COOL_REFUSED, "refused"), True)
    # Motifs, mode, bornes.
    eq("motif reconnu", reason("not_now"), "not_now")
    eq("motif inventé", reason("<t size='9'>x</t>"), "")
    eq("sans motif", reason(""), "")
    eq("remettre", mode("give"), "give")
    eq("montrer par défaut", mode("n'importe quoi"), "show")
    eq("délai par défaut", timeout(20), 20)
    eq("délai trop court", timeout(1), T_MIN)
    eq("délai trop long", timeout(999), T_MAX)
    eq("distance par défaut", distance(3), 3)
    eq("distance trop grande", distance(50), D_MAX)
    eq("distance trop petite", distance(0), D_MIN)
    if T_MIN > 20 or T_MAX < 20 or D_MIN > 3 or D_MAX < 3:
        FAILS.append("Les valeurs par défaut (20 s, 3 m) sortent des bornes")


def need(label, text, *needles):
    for n in needles:
        if n not in text:
            FAILS.append("%s : « %s » manquant" % (label, n))


def wiring():
    server = read("functions", "fn_idP2pServer.sqf")
    client = read("functions", "fn_idP2pClient.sqf")
    ui = read("functions", "fn_idP2pUi.sqf")
    id_server = read("functions", "fn_idServer.sqf")
    id_client = read("functions", "fn_idClient.sqf")
    config = read("config.cpp")
    dialog = read("dialogs", "idP2p.hpp")
    settings = read("functions", "fn_idInitSettings.sqf")

    need("config", config, "class idP2pRules {};", "class idP2pServer {};", "class idP2pClient {};", "class idP2pUi {};", '#include "dialogs\\idP2p.hpp"',
         "class comspec_sse_fnc_idServer { allowedTargets = 2; };", "class comspec_sse_fnc_idClient { allowedTargets = 1; };")
    # Le relais passe par les deux fonctions déjà déclarées : aucune nouvelle fonction appelable à distance.
    if "idP2p" in config.split("class CfgRemoteExec")[1].split("};")[0]:
        FAILS.append("config : fn_idP2p* ne doit pas être appelable à distance")
    need("fn_idServer", id_server, 'if (isPlayer _t) exitWith { ["ask", [_me, _t]] call comspec_sse_fnc_idP2pServer; };',
         'case "p2pAnswer": { ["answer", _args] call comspec_sse_fnc_idP2pServer; };', 'case "p2pPresent": { ["present", _args] call comspec_sse_fnc_idP2pServer; };',
         'case "p2pZeus": { ["zeus", _args] call comspec_sse_fnc_idP2pServer; };', '["legacy", [_me, _to]] call comspec_sse_fnc_idP2pServer')
    need("fn_idClient", id_client, 'case "p2p":', "comspec_sse_fnc_idP2pClient")
    # Serveur : poste demandeur vérifié, règles pures, messages au seul poste concerné, Zeus vérifié.
    need("fn_idP2pServer", server, "(owner _me) isNotEqualTo remoteExecutedOwner", '_op isEqualTo "tick"', "checkVisibility", '["subset", [_classes, _owned, 12]] call _rules',
         '["next", [', '["rate", [', "comspec_sse_fnc_isAuthority", 'remoteExecCall ["comspec_sse_fnc_idClient", owner _unit]',
         'missionNamespace setVariable ["comspec_sse_idP2pZeus", _v, true]', "comspec_sse_id_p2pEnabled", "comspec_sse_id_p2pRefuse", "comspec_sse_id_p2pPresent",
         "comspec_sse_id_p2pTimeout", "comspec_sse_id_p2pDistance", '"comspec_sse_idRefused"', '"comspec_sse_idPresented"')
    if re.search(r"remoteExec(Call)?\s*\[[^\]]*,\s*(0|-2)\s*[,\]]", server):
        FAILS.append("fn_idP2pServer : aucun message ne doit être diffusé à tous les postes")
    # Poste : journal Athena dans la forme d'un contrôle, par fn_fileSubmit (licence, file hors ligne).
    need("fn_idP2pClient", client, "call comspec_sse_fnc_fileSubmit", '["kind", "id_p2p"]', '"idempotency_key"', '["levels", ["N0"]]', '"p2p_" + _result', '"no_answer"',
         '"presented"', "comspec_sse_id_autoTransmitCommunity", '"presented_name"', '"subject"', '"controller"', '"gestureNo"')
    need("fn_idP2pUi", ui, "_code isEqualTo 2", '"refuse"', "CBA_fnc_removePerFrameHandler")
    need("fenêtre", dialog, "class COMSPEC_SSE_IdP2p", "idd = 95200;", "onUnload = \"['unload', _this] call comspec_sse_fnc_idP2pUi\";", "COMSPEC_SSE_RscButtonClose")
    need("réglages", settings, '"comspec_sse_id_p2pEnabled", "CHECKBOX"', '"comspec_sse_id_p2pTimeout", "SLIDER"', "[5, 120, 20, 0]", '"comspec_sse_id_p2pDistance", "SLIDER"',
         "[2, 4, 3, 1]", '"comspec_sse_id_p2pRefuse", "CHECKBOX"', '"comspec_sse_id_p2pPresent", "CHECKBOX"')
    need("menu ACE", read("functions", "fn_initAceIdentity.sqf"), "COMSPEC_ID_P2pAsk", "COMSPEC_ID_P2pAnswer", '{ ["present", []] call comspec_sse_fnc_idP2pClient }')
    need("raccourcis", read("XEH_postInitClient.sqf"), '"comspec_sse_id_keyAsk"', '"comspec_sse_id_keyPresent"')
    need("Zeus", read("functions", "fn_idZenRegister.sqf"), '["send", ["p2pZeus", [player, _v select 0]]] call comspec_sse_fnc_idClient')
    need("visionneuse", read("dialogs", "idViewer.hpp"), "idc = 93715;", "['fromViewer'] call comspec_sse_fnc_idP2pClient")

    # Aucune alerte de l'interface d'Arma dans les nouveaux fichiers (fenêtre du mod ou téléphone seulement).
    banned = ("hint ", "hintSilent", "systemChat", "titleText", "cutText", "BIS_fnc_showNotification", "zen_common_fnc_showMessage",
              "showCuratorFeedbackMessage", "comspec_sse_fnc_toast", "globalChat", "sideChat")
    for name, text in (("fn_idP2pServer", server), ("fn_idP2pClient", client), ("fn_idP2pUi", ui), ("fn_idP2pRules", RULES)):
        code = re.sub(r"/\*.*?\*/", "", text, flags=re.S)
        code = "\n".join(line.split("//")[0] for line in code.splitlines())
        for b in banned:
            if b in code:
                FAILS.append("%s : alerte interdite « %s »" % (name, b.strip()))

    # Clés de traduction : toutes celles du code existent, en français et en anglais, et aucune n'est orpheline.
    dom = xml.dom.minidom.parse(os.path.join(ID, "stringtable.xml"))
    keys = {}
    for k in dom.getElementsByTagName("Key"):
        kid = k.getAttribute("ID")
        if kid.startswith("STR_COMSPEC_SSE_P2P_"):
            langs = {c.tagName: (c.firstChild.data if c.firstChild else "") for c in k.childNodes if c.nodeType == c.ELEMENT_NODE}
            keys[kid[len("STR_COMSPEC_SSE_P2P_"):]] = langs
    for kid, langs in keys.items():
        for lang in ("Original", "English", "French"):
            if not langs.get(lang, "").strip():
                FAILS.append("stringtable : %s sans texte %s" % (kid, lang))
    used = set()
    sources = [server, client, ui, dialog, settings, read("functions", "fn_initAceIdentity.sqf"), read("XEH_postInitClient.sqf"),
               read("functions", "fn_idZenRegister.sqf"), read("dialogs", "idViewer.hpp")]
    for text in sources:
        used |= set(re.findall(r"STR_COMSPEC_SSE_P2P_(\w+)", text))
    for text in (client, ui):
        used |= set(re.findall(r'"(\w+)" call _L', text))
        # Clé choisie dans une liste : ["T_Present", "T_Request"…] select … : une clé connue dans la liste, toutes doivent l'être.
        for group in re.findall(r'\[("\w+"(?:, "\w+")+)\] select', text):
            names = re.findall(r'"(\w+)"', group)
            if any(n in keys for n in names):
                used |= set(names)
        # Clé passée par une variable (liste parcourue) : tout texte entre guillemets qui est une clé connue.
        used |= {s for s in re.findall(r'"(\w+)"', text) if s in keys}
    # Préfixes (clé composée, commentaire « Set* ») : ce ne sont pas des clés.
    used = {k for k in used if not k.endswith("_") and k != "Set"}
    # Clés composées : motif d'un refus (Reason_ + code) et raison d'un refus du serveur (D_ + code).
    used |= {"Reason_" + r for r in REASONS} | {"Reason_none"}
    denied = set(re.findall(r'"denied", \[_\w+, "(\w+)"\]', server + id_server)) | set(re.findall(r'"denied", \[_\w+, _why\]', server) and ["far", "los", "vehicle"])
    used |= {"D_" + d for d in denied}
    for k in sorted(used - set(keys)):
        FAILS.append("stringtable : clé STR_COMSPEC_SSE_P2P_%s utilisée mais absente" % k)
    for k in sorted(set(keys) - used):
        FAILS.append("stringtable : clé STR_COMSPEC_SSE_P2P_%s jamais utilisée" % k)


def main():
    rules()
    wiring()
    if FAILS:
        print("ÉCHEC (%d)" % len(FAILS))
        for f in FAILS:
            print("  - " + f)
        return 1
    print("Papiers entre joueurs : règles et câblage conformes.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
