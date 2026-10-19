#include "script_component.hpp"

[
    "comspec_sse_caseReference",
    "EDITBOX",
    ["Référence du dossier SSE", "Ex. SSE-2026-0007 : sert à rattacher la mission au bon dossier sur Athena."],
    ["COMSPEC SSE", "Réseau"],
    "",
    1,
    {
        params ["_value"];
        [_value] call comspec_sse_fnc_setCaseReference;
    },
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_mapId",
    "EDITBOX",
    ["Numéro de carte Athena", "Numéro de la carte Athena de la mission (en général 1)."],
    ["COMSPEC SSE", "Réseau"],
    "1",
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_preferExtension",
    "CHECKBOX",
    ["Passer par COMSPEC Link en priorité", "Envoie les fiches SSE complètes via COMSPEC Link, et ne passe à l'envoi en simple texte que si ça échoue."],
    ["COMSPEC SSE", "Réseau"],
    true,
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };
if (isNil "comspec_sse_caseRef") then { comspec_sse_caseRef = ""; };

["network preInit OK"] call comspec_sse_fnc_log;
