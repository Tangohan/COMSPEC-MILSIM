/*
    Ajoute une action ACE self-interact de façon isolée.
    Normalise le tableau à 11 cases (index 10 = modificateur) — ACE
    collectActiveActionTree plante sinon sur « select 10 ».
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

private _a = [_action] call comspec_overwatch_connect_fnc_acePadAction;
if (_a isEqualTo []) exitWith {
    ["aceAddSelfAction", "Action ACE mal formée", _path, "ACE", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
    false
};

private _actionId = _a select 0;
[player, 1, _path, _a] call ace_interact_menu_fnc_addActionToObject;
["DEBUG", "ACE", format ["Action installée : %1", _actionId]] call comspec_overwatch_connect_fnc_log;
true
