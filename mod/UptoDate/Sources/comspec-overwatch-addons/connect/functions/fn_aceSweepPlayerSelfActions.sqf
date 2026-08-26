/*
    Retire les copies d’actions Overwatch collées sur l’unité (addActionToObject).
    Les actions de classe (CAManBase) restent — c’est elles qui survivent au Respawn.
    À appeler après Respawn : ACE recopie parfois l’ancienne liste objet sur la nouvelle unité.
*/
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };
if (isNil "ace_interact_menu_fnc_removeActionFromObject") exitWith { false };

private _installed = missionNamespace getVariable ["COMSPEC_ACESelfActions", []];
if (!(_installed isEqualType []) || {_installed isEqualTo []}) exitWith { true };

{
    if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
    _x params ["_path", "_actionId"];
    if (!(_path isEqualType []) || {!(_actionId isEqualType "")}) then { continue };
    [player, 1, _path, _actionId] call ace_interact_menu_fnc_removeActionFromObject;
} forEach _installed;

true
