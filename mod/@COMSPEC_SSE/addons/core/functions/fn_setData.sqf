/*
    Écrit les données SSE sur une entité (public).
    [_entity, _data, _public] call comspec_sse_fnc_setData
*/
params [
    ["_entity", objNull, [objNull]],
    ["_data", [], [[]]],
    ["_public", true, [true]]
];

if (isNull _entity) exitWith { false };
if !(_data isEqualType []) exitWith { false };

private _uid = [_data, "uid", "?"] call comspec_sse_fnc_getPair;
private _prev = _entity getVariable ["comspec_sse_data", []];
private _sameUid = (_prev isEqualType [])
    && {([_prev, "uid", ""] call comspec_sse_fnc_getPair) isEqualTo _uid}
    && {_uid isNotEqualTo "?" && {_uid isNotEqualTo ""}};

_entity setVariable ["comspec_sse_data", _data, _public];
_entity setVariable ["comspec_sse_enabled", true, _public];

// Anti-spam journal : ne logger INFO que si nouvel uid / première écriture.
private _lastLog = _entity getVariable ["comspec_sse_setDataLogAt", -1e9];
if (!_sameUid || {(time - _lastLog) > 5}) then {
    [format ["setData %1 uid=%2", _entity, _uid]] call comspec_sse_fnc_log;
    _entity setVariable ["comspec_sse_setDataLogAt", time];
};

// Ne pas installer les menus ACE pendant generateData (même frame = pic de pile).
if (_entity getVariable ["comspec_sse_generating", false]) exitWith { true };
if (!hasInterface) exitWith { true };

// Menus déjà en place : pas de nouveau entityEnabled (évite courses ACE).
if (_entity getVariable ["comspec_sse_aceMenusInstalled", false]) exitWith { true };

// Différer l’install ACE hors de l’appelant.
if (!isNil "CBA_fnc_waitAndExecute") then {
    [{
        params ["_e"];
        if (isNull _e) exitWith {};
        if (_e getVariable ["comspec_sse_generating", false]) exitWith {};
        if (_e getVariable ["comspec_sse_aceMenusInstalled", false]) exitWith {};
        if (!isNil "CBA_fnc_localEvent") then {
            ["comspec_sse_entityEnabled", [_e]] call CBA_fnc_localEvent;
        } else {
            if (!isNil "comspec_sse_fnc_installEntityAceMenus") then {
                [_e] call comspec_sse_fnc_installEntityAceMenus;
            };
        };
    }, [_entity], 0.35] call CBA_fnc_waitAndExecute;
} else {
    if (!isNil "comspec_sse_fnc_installEntityAceMenus") then {
        [_entity] call comspec_sse_fnc_installEntityAceMenus;
    };
};

true
