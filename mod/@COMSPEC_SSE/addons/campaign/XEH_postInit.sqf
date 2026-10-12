#include "script_component.hpp"
/*
    Campagnes : relais serveur, fin de mission, notes, menus ACE et Zeus.
*/
if (isServer) then {
    ["comspec_sse_campOp", { params ["_op", "_args", ["_req", objNull]]; [_op, _args, _req] call comspec_sse_fnc_campaignOp; }] call CBA_fnc_addEventHandler;
    ["comspec_sse_campAthena", { params ["_mode", "_args", ["_req", objNull]]; [_mode, _args, _req] call comspec_sse_fnc_campaignAthena; }] call CBA_fnc_addEventHandler;
    // L'index des sauvegardes est connu de tous dès le départ (formulaires Zeus).
    ["publish", []] call comspec_sse_fnc_campaignOp;
    {
        addMissionEventHandler [_x, {
            if (missionNamespace getVariable ["comspec_sse_campSaveAtEnd", false] && {count (missionNamespace getVariable ["comspec_sse_campaign", createHashMap]) > 0}) then {
                ["end", []] call comspec_sse_fnc_campaignOp;
            };
        }];
    } forEach ["MPEnded", "Ended"];
};

if (!hasInterface) exitWith {};

["comspec_sse_campNote", {
    params [["_msg", "", [""]]];
    if (_msg isEqualTo "") exitWith {};
    systemChat ("SSE · Campagne : " + _msg);
    if (!isNull (getAssignedCuratorLogic player) || {isServer}) then { hint parseText format ["<t color='#7fd08a'>SSE · Campagne</t><br/>%1", _msg]; };
}] call CBA_fnc_addEventHandler;

["comspec_sse_campExport", {
    params [["_text", "", [""]]];
    copyToClipboard _text;
    if (isNil "zen_dialog_fnc_create") exitWith { hint "Campagne copiée dans le presse-papiers."; };
    [
        "Campagne exportée",
        [["EDIT:MULTI", ["Texte (déjà copié)", "Ctrl+A puis Ctrl+C si le presse-papiers est bloqué par le serveur."], [_text, {}, 8]]],
        {}
    ] call zen_dialog_fnc_create;
}] call CBA_fnc_addEventHandler;

// Liste des campagnes d'Athena reçue du serveur : choix de celle à récupérer.
["comspec_sse_campAthenaList", {
    params [["_rows", [], [[]]]];
    if (isNil "zen_dialog_fnc_create") exitWith { hint "Zeus Enhanced requis pour ce formulaire."; };
    private _types = createHashMapFromArray [["permanente", "permanente"], ["recurrente", "récurrente"], ["temporaire", "temporaire"]];
    [
        "Campagne : récupérer depuis Athena",
        [
            ["COMBO", ["Campagne dans Athena", "Gérée dans Bureau SSE › Campagnes : copies pour un drill, remises à zéro, campagnes copiées du catalogue partagé."],
                [_rows apply { _x select 0 }, _rows apply { format ["%1 · %2 · %3 mission(s) · %4 personnage(s)", _x param [1, ""], _types getOrDefault [_x param [2, ""], _x param [2, ""]], _x param [3, "0"], _x param [4, "0"]] }, 0]],
            ["EDIT", ["Identifiant sur ce serveur (vide = le même)", "La sauvegarde du serveur portant cet identifiant est remplacée."], ""],
            ["CHECKBOX", ["Charger tout de suite", "Remplace la campagne en cours de la partie."], false]
        ],
        { params ["_v"]; ["pull", [_v select 0, _v select 1, _v select 2]] call comspec_sse_fnc_campaignAthena; }
    ] call zen_dialog_fnc_create;
}] call CBA_fnc_addEventHandler;

[{ !isNil "ace_interact_menu_fnc_createAction" && {!isNull player} }, {
    private _act = ["COMSPEC_SSE_Campaign", "Campagne en cours", "\a3\ui_f\data\igui\cfg\simpleTasks\types\map_ca.paa",
        { hint parseText ([] call comspec_sse_fnc_campaignInfo) }, { true }, { [] }, [], {[0, 0, 0]}, 1, [false, false, false, false, true]] call ace_interact_menu_fnc_createAction;
    [player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _act] call ace_interact_menu_fnc_addActionToObject;
    player addEventHandler ["Respawn", {
        params ["_unit"];
        private _act = ["COMSPEC_SSE_Campaign", "Campagne en cours", "\a3\ui_f\data\igui\cfg\simpleTasks\types\map_ca.paa",
            { hint parseText ([] call comspec_sse_fnc_campaignInfo) }, { true }, { [] }, [], {[0, 0, 0]}, 1, [false, false, false, false, true]] call ace_interact_menu_fnc_createAction;
        [_unit, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _act] call ace_interact_menu_fnc_addActionToObject;
    }];
}, [], 60] call CBA_fnc_waitUntilAndExecute;

[{ !isNil "zen_custom_modules_fnc_register" }, {
    {
        _x params ["_name", "_mode", "_icon"];
        ["COMSPEC SSE · Mission", _name, compile format ["['%1'] call comspec_sse_fnc_campaignZeus", _mode], _icon] call zen_custom_modules_fnc_register;
    } forEach [
        ["Campagne : charger ou créer", "load", "\a3\ui_f\data\igui\cfg\simpleTasks\types\map_ca.paa"],
        ["Campagne : sauvegarder maintenant", "save", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa"],
        ["Campagne : état et journal", "info", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa"],
        ["Campagne : remettre à zéro (drill)", "reset", "\a3\ui_f\data\igui\cfg\simpleTasks\types\refuel_ca.paa"],
        ["Campagne : fin de mission (sauver ou vider)", "end", "\a3\ui_f\data\igui\cfg\simpleTasks\types\exit_ca.paa"],
        ["Campagne : exporter", "export", "\a3\ui_f\data\igui\cfg\simpleTasks\types\upload_ca.paa"],
        ["Campagne : importer", "import", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa"],
        ["Campagne : supprimer une sauvegarde", "delete", "\a3\ui_f\data\igui\cfg\simpleTasks\types\danger_ca.paa"],
        ["Campagne : envoyer à Athena", "athenaPush", "\a3\ui_f\data\igui\cfg\simpleTasks\types\upload_ca.paa"],
        ["Campagne : récupérer depuis Athena", "athenaPull", "\a3\ui_f\data\igui\cfg\simpleTasks\types\download_ca.paa"]
    ];
}, [], 45] call CBA_fnc_waitUntilAndExecute;
