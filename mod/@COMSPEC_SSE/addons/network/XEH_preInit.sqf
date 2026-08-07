#include "script_component.hpp"

[
    "comspec_sse_caseReference",
    "EDITBOX",
    ["Référence dossier SSE", "Ex. SSE-2026-0007 — rattachement Athena."],
    ["COMSPEC SSE", "Réseau"],
    "",
    1,
    {
        params ["_value"];
        [_value] call comspec_sse_fnc_setCaseReference;
    },
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_mapId",
    "EDITBOX",
    ["Identifiant carte / contexte", "mapId Athena (souvent 1)."],
    ["COMSPEC SSE", "Réseau"],
    "1",
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_preferExtension",
    "CHECKBOX",
    ["Préférer l'extension COMSPEC", "Tente SubmitSsePerson / SendSSE avant sendIntel texte."],
    ["COMSPEC SSE", "Réseau"],
    true,
    1,
    {},
    true
] call CBA_fnc_addSetting;

if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };
if (isNil "comspec_sse_caseRef") then { comspec_sse_caseRef = ""; };

["network preInit OK"] call comspec_sse_fnc_log;
