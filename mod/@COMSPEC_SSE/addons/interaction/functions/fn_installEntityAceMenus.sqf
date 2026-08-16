/*
    Installe UNE racine ACE SSE sur l’entité.
    Les enfants (bio / digital / Athena) viennent de insertChildren dynamiques —
    plus d’addActionToObject répétés → plus de doublons Biométrie / SEEK / etc.
*/
params [
    ["_entity", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };
if (isNull _entity) exitWith { false };
if (_entity getVariable ["comspec_sse_generating", false]) exitWith { false };
if (isNil "ace_interact_menu_fnc_createAction" || {isNil "ace_interact_menu_fnc_addActionToObject"}) exitWith { false };

// Verrou exclusif immédiat (anti course setData / entityEnabled / pending bio).
if (_entity getVariable ["comspec_sse_aceMenusInstalled", false]) exitWith { true };
if (_entity getVariable ["comspec_sse_aceInstalling", false]) exitWith { true };
_entity setVariable ["comspec_sse_aceInstalling", true];

private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (!(_cache isEqualType createHashMap) || {count _cache == 0}) exitWith {
    _entity setVariable ["comspec_sse_aceInstalling", false];
    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            [_e] call comspec_sse_fnc_installEntityAceMenus;
        }, [_entity], 0.5] call CBA_fnc_waitAndExecute;
    };
    false
};

private _isPerson = _entity isKindOf "CAManBase";
private _rootKey = if (_isPerson) then { "personRoot" } else { "objectRoot" };
private _root = _cache getOrDefault [_rootKey, []];
if (_root isEqualTo []) exitWith {
    _entity setVariable ["comspec_sse_aceInstalling", false];
    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            [_e] call comspec_sse_fnc_installEntityAceMenus;
        }, [_entity], 0.5] call CBA_fnc_waitAndExecute;
    };
    false
};

// Une seule addAction : la racine (enfants = insertChildren du cache).
_entity setVariable ["comspec_sse_aceMenusInstalled", true];
_entity setVariable ["comspec_sse_aceChildrenQueued", true];
_entity setVariable ["comspec_sse_aceBioQueued", true];
_entity setVariable ["comspec_sse_aceBioInstalled", true];
_entity setVariable ["comspec_sse_aceDigQueued", true];
_entity setVariable ["comspec_sse_aceDigInstalled", true];
[_entity, 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToObject;

if !(_entity getVariable ["comspec_sse_aceReadyFired", false]) then {
    _entity setVariable ["comspec_sse_aceReadyFired", true];
    if (!isNil "CBA_fnc_localEvent") then {
        ["comspec_sse_entityAceReady", [_entity]] call CBA_fnc_localEvent;
    };
};

_entity setVariable ["comspec_sse_aceInstalling", false];
true
