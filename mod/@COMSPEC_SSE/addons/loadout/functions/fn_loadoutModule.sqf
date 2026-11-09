/*
    Module « Caisse de kits (Athena) » (Zeus et Eden, config.cpp) : l'objet visé devient une caisse de kits.
      Eden : objets synchronisés, sinon l'objet le plus proche à 3 m du module ; nom et tenue des attributs du module.
      Zeus : module posé sur un objet ; formulaire à la pose (nom, tenue collée, ou ma tenue actuelle).
    Création sur le serveur (fn_loadoutTake « crate », réservé aux Zeus et administrateurs en appel distant).
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = _ctx get "targets";
if (_targets isEqualTo []) then {
    _targets = (nearestObjects [_ctx get "pos", ["ThingX", "Static", "LandVehicle", "Air", "Ship"], 3]) select { !(_x isKindOf "Logic") && {!(_x isKindOf "CAManBase")} } select [0, 1];
};
private _send = {
    params ["_objs", "_name", "_text"];
    { if (isServer) then { ["crate", [_x, _name, _text]] call comspec_sse_fnc_loadoutTake; } else { ["crate", [_x, _name, _text]] remoteExecCall ["comspec_sse_fnc_loadoutTake", 2]; }; } forEach _objs;
};
if (_ctx get "zeus") then {
    if (_targets isEqualTo []) exitWith {
        ["Caisse de kits : posez le module sur un objet (caisse, véhicule, table…).", "WARNING"] call comspec_sse_fnc_notify;
    };
    [
        "Caisse de kits",
        [
            ["EDIT", "Nom de la tenue", "Facultatif : nom montré au joueur.", ""],
            ["EDIT", "Tenue (JSON d'Athena)", "Facultatif : tenue collée depuis Athena ou « Copier ma tenue pour Athena ». Vide : kits Athena des joueurs.", ""],
            ["CHECK", "Ma tenue actuelle", "La caisse propose la tenue que porte votre personnage (remplace le texte collé).", false]
        ],
        {
            params ["_values", "_args"];
            _values params ["_name", "_text", "_mine"];
            if (_mine) then { _text = [player, false] call comspec_sse_fnc_loadoutExport; };
            [_args select 0, _name, _text] call (_args select 1);
        },
        [_targets, _send],
        format ["Objet : %1. Sans tenue, chaque joueur y prend son kit Athena (paquet d'identité).", getText (configOf (_targets select 0) >> "displayName")]
    ] call comspec_sse_fnc_uiForm;
} else {
    if (_targets isEqualTo []) exitWith { diag_log text "[COMSPEC SSE] Équipement : module Caisse de kits sans objet synchronisé ni objet à 3 m."; };
    [_targets, _logic getVariable ["KitName", ""], _logic getVariable ["KitText", ""]] call _send;
};
if (_ctx get "zeus") then { deleteVehicle _logic; };
true
