/*
    Formulaires Zeus (ZEN) des campagnes.
    [_mode] call comspec_sse_fnc_campaignZeus
      load · save · reset · end · delete · export · import · info · athenaPush · athenaPull
*/
params [["_mode", "info", [""]]];
if (isNil "zen_dialog_fnc_create") exitWith { ["Zeus Enhanced requis pour ce formulaire.", "warn"] call comspec_sse_fnc_zeusNotify; };
private _index = missionNamespace getVariable ["comspec_sse_campIndex", []];
private _cur = missionNamespace getVariable ["comspec_sse_campaign", createHashMap];
private _types = [["permanente", "recurrente", "temporaire"], ["Permanente (vraie campagne, tout est gardé)", "Récurrente (bootcamp : mêmes personnages, tout redevient inconnu)", "Temporaire (drill SOT jetable, rien n'est gardé)"], 0];
private _ids = [""] + (_index apply { _x select 0 });
private _lbls = ["Nouvelle campagne (saisir ci-dessous)"] + (_index apply { format ["%1 · %2 · %3 · %4 mission(s)", _x select 1, _x select 2, _x select 3, _x select 4] });

switch (_mode) do {
    case "load": {
        [
            "Campagne SSE : charger ou créer",
            [
                ["COMBO", ["Campagne sauvegardée", "Charger remplace les organisations, ce que les joueurs savent et le registre des scellés de la partie en cours."], [_ids, _lbls, 0]],
                ["EDIT", ["Nouvelle : identifiant", "Lettres et chiffres, par exemple OP-SABLE ou BOOTCAMP."], ""],
                ["EDIT", "Nouvelle : nom", ""],
                ["COMBO", "Nouvelle : type", _types],
                ["COMBO", ["Copier la structure de", "Pour un drill : personnages et organisations d'une autre campagne, sans rien de découvert."], [_ids, ["Rien"] + (_lbls select [1]), 0]],
                ["SLIDER", "Sauvegarde automatique (min, 0 = jamais)", [0, 120, 15, 0]]
            ],
            {
                params ["_v"];
                _v params ["_pick", "_newId", "_name", "_type", "_copy", "_auto"];
                private _id = [_pick, _newId] select (_pick isEqualTo "");
                if ((trim _id) isEqualTo "") exitWith { ["Choisissez une campagne ou saisissez un identifiant.", "warn"] call comspec_sse_fnc_zeusNotify; };
                ["load", [_id, _name, _type, _copy, round _auto]] call comspec_sse_fnc_campaignOp;
            }
        ] call comspec_sse_fnc_zenDialog;
    };
    case "save": { ["save", ["sauvegarde Zeus"]] call comspec_sse_fnc_campaignOp; };
    case "reset": {
        [
            "Remettre à zéro ?",
            [["CHECKBOX", ["Confirmer", "Récurrente : tout redevient inconnu des joueurs. Temporaire : le bac à sable est vidé."], false]],
            { params ["_v"]; if (_v select 0) then { ["reset", []] call comspec_sse_fnc_campaignOp; }; }
        ] call comspec_sse_fnc_zenDialog;
    };
    case "end": { ["end", []] call comspec_sse_fnc_campaignOp; };
    case "delete": {
        if (_index isEqualTo []) exitWith { ["Aucune campagne sauvegardée.", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            "Supprimer une sauvegarde",
            [
                ["COMBO", "Campagne", [_ids select [1], _lbls select [1], 0]],
                ["CHECKBOX", "Confirmer la suppression définitive", false]
            ],
            { params ["_v"]; if (_v select 1) then { ["delete", [_v select 0]] call comspec_sse_fnc_campaignOp; }; }
        ] call comspec_sse_fnc_zenDialog;
    };
    case "export": {
        if (_index isEqualTo []) exitWith { ["Aucune campagne sauvegardée.", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            "Exporter (presse-papiers)",
            [["COMBO", ["Campagne", "Le texte copié s'importe sur un autre serveur ou dans une autre communauté."], [_ids select [1], _lbls select [1], ((_ids find (_cur getOrDefault ["id", ""])) - 1) max 0]]],
            { params ["_v"]; ["export", [_v select 0]] call comspec_sse_fnc_campaignOp; }
        ] call comspec_sse_fnc_zenDialog;
    };
    case "import": {
        [
            "Importer une campagne",
            [
                ["EDIT:MULTI", ["Texte exporté", "Collez le texte copié par « Exporter »."], [copyFromClipboard, {}, 6]],
                ["EDIT", ["Nouvel identifiant (vide = le sien)", "Pour ne pas écraser une campagne du même nom."], ""]
            ],
            { params ["_v"]; ["import", [_v select 0, _v select 1]] call comspec_sse_fnc_campaignOp; }
        ] call comspec_sse_fnc_zenDialog;
    };
    case "athenaPush": {
        if (_index isEqualTo []) exitWith { ["Aucune campagne sauvegardée.", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            "Envoyer à Athena",
            [["COMBO", ["Campagne", "La sauvegarde du serveur part dans Athena (Bureau SSE › Campagnes). Sauvegardez d'abord pour envoyer l'état actuel."], [_ids select [1], _lbls select [1], ((_ids find (_cur getOrDefault ["id", ""])) - 1) max 0]]],
            { params ["_v"]; ["push", [_v select 0]] call comspec_sse_fnc_campaignAthena; }
        ] call comspec_sse_fnc_zenDialog;
    };
    case "athenaPull": { ["list", []] call comspec_sse_fnc_campaignAthena; };
    default {
        // Traduit avant parseText (fn_tr ne traduit qu'une chaîne).
        hint parseText (([] call comspec_sse_fnc_campaignInfo) call comspec_sse_fnc_tr);
    };
};
true
