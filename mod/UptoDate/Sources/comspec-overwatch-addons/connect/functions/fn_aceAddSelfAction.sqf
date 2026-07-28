/*
    Ajoute une action ACE self-interact de façon isolée.
    - Copie le tableau action (+_) pour éviter la mutation partagée (reco ACE)
    - Ignore si joueur / API ACE absents
    - Journalise les échecs liés à Overwatch
    Retour: true si ajoutée
*/
params [
    ["_action", [], [[]]],
    ["_path", ["ACE_SelfActions"], [[]]]
];

if (!hasInterface) exitWith { false };
if (isNull player) exitWith {
    ["aceAddSelfAction", "Joueur null — action non ajoutée", _action, "ACE", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
    false
};
if (isNil "ace_interact_menu_fnc_addActionToObject") exitWith {
    ["aceAddSelfAction", "API ACE interact_menu absente", nil, "ACE", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
    false
};
if (_action isEqualTo []) exitWith {
    ["aceAddSelfAction", "Action vide", _path, "ACE", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
    false
};

private _actionId = "";
if ((count _action) > 0 && {(_action select 0) isEqualType ""}) then {
    _actionId = _action select 0;
};

[player, 1, _path, +_action] call ace_interact_menu_fnc_addActionToObject;
["DEBUG", "ACE", format ["Action installée : %1", if (_actionId isEqualTo "") then { str _path } else { _actionId }]] call comspec_overwatch_connect_fnc_log;
true
