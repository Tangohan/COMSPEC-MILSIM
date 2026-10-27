#include "script_component.hpp"
/*
    Campagnes : relais serveur, fin de mission, notes, menus ACE et Zeus.
*/
if (isServer) then {
    // Demandes des clients : appel distant de comspec_sse_fnc_campaignOp / campaignAthena (CfgRemoteExec, serveur
    // seulement). Le demandeur est retrouvé par remoteExecutedOwner ; plus d'événement CBA portant un demandeur
    // choisi par le client. Le contrôle Zeus / administrateur se fait dans campaignOp et campaignAthena.
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
    // Jamais dans le chat du jeu : notification du téléphone (bulle si fermé), traduite avant la mise en forme.
    [_msg, "INFO", "SSE · Campagne"] call comspec_sse_fnc_notify;
}] call CBA_fnc_addEventHandler;

["comspec_sse_campExport", {
    params [["_text", "", [""]]];
    copyToClipboard _text;
    if (isNil "zen_dialog_fnc_create") exitWith { ["Campagne copiée dans le presse-papiers.", "SUCCESS", "SSE · Campagne"] call comspec_sse_fnc_notify; };
    [
        "Campagne exportée",
        [["EDIT:MULTI", ["Texte (déjà copié)", "Ctrl+A puis Ctrl+C si le presse-papiers est bloqué par le serveur."], [_text, {}, 8]]],
        {}
    ] call comspec_sse_fnc_zenDialog;
}] call CBA_fnc_addEventHandler;

// Liste des campagnes d'Athena reçue du serveur : choix de celle à récupérer.
["comspec_sse_campAthenaList", {
    params [["_rows", [], [[]]]];
    if (isNil "zen_dialog_fnc_create") exitWith { ["Zeus Enhanced requis pour ce formulaire.", "warn"] call comspec_sse_fnc_zeusNotify; };
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
    ] call comspec_sse_fnc_zenDialog;
}] call CBA_fnc_addEventHandler;

// Fiche de campagne : onglet JOURNAL de l'app « SSE terrain » du téléphone (plus d'action ACE personnelle).

[{ !isNil "zen_custom_modules_fnc_register" }, {
    {
        _x params ["_name", "_mode", "_icon"];
        ["COMSPEC SSE · Mission", _name, compile format ["['%1'] call comspec_sse_fnc_campaignZeus", _mode], _icon] call comspec_sse_fnc_zenModule;
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
