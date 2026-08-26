/*
    Installe une action ACE self-interact **une seule fois** (classe CAManBase).
    addActionToObject sur le joueur s’empile à chaque Respawn / ré-init : ACE
    recopie l’ancienne liste, puis on en rajoutait une. D’où le dump vertical.

    - Toujours retirer une copie objet résiduelle (nouvelle unité).
    - addActionToClass une fois par session (clé path + id).
    - Normalise le tableau à 11 cases (index 10 = modificateur).
*/
params [
    ["_action", [], [[]]],
    ["_path", ["ACE_SelfActions"], [[]]]
];

if (!hasInterface) exitWith { false };
if (isNil "ace_interact_menu_fnc_addActionToClass" && {isNil "ace_interact_menu_fnc_addActionToObject"}) exitWith {
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
if (!isNull player && {!isNil "ace_interact_menu_fnc_removeActionFromObject"}) then {
    [player, 1, _path, _actionId] call ace_interact_menu_fnc_removeActionFromObject;
};

private _installed = missionNamespace getVariable ["COMSPEC_ACESelfActions", []];
if (!(_installed isEqualType [])) then { _installed = []; };
private _already = false;
{
    if ((_x isEqualType []) && {(count _x) >= 2} && {(_x select 0) isEqualTo _path} && {(_x select 1) isEqualTo _actionId}) exitWith {
        _already = true;
    };
} forEach _installed;

if (_already) exitWith {
    ["DEBUG", "ACE", format ["Action déjà installée (classe) : %1", _actionId]] call comspec_overwatch_connect_fnc_log;
    true
};

private _ok = false;
if (!isNil "ace_interact_menu_fnc_addActionToClass") then {
    ["CAManBase", 1, _path, _a, true] call ace_interact_menu_fnc_addActionToClass;
    _ok = true;
} else {
    if (isNull player) then {
        ["aceAddSelfAction", "Joueur null — action non ajoutée", _action, "ACE", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
    } else {
        [player, 1, _path, _a] call ace_interact_menu_fnc_addActionToObject;
        _ok = true;
    };
};
if (!_ok) exitWith { false };

_installed pushBack [_path, _actionId];
missionNamespace setVariable ["COMSPEC_ACESelfActions", _installed, false];
["DEBUG", "ACE", format ["Action installée : %1", _actionId]] call comspec_overwatch_connect_fnc_log;
true
