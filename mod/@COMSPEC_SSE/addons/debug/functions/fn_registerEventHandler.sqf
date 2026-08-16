/*
    Enregistre un EventHandler avec détection de doublons.

    Params:
      0: id logique (STRING) ex. COMSPEC_SSE_REQUEST_OP
      1: kind (STRING) CBA | MISSION | OBJECT | ACE
      2: event name (STRING|NUMBER)
      3: code (CODE)
      4: target (OBJECT, optional — OBJECT EH)
*/
params [
    ["_id", "", [""]],
    ["_kind", "CBA", [""]],
    ["_event", "", ["", 0]],
    ["_code", {}, [{}]],
    ["_target", objNull, [objNull]]
];

_kind = toUpper _kind;
if (_id isEqualTo "") then {
    _id = format ["ANON_%1_%2", _kind, _event];
};

private _reg = missionNamespace getVariable ["COMSPEC_DEBUG_EH_REGISTRY", createHashMap];
if (!(_reg isEqualType createHashMap)) then { _reg = createHashMap; };
private _count = (_reg getOrDefault [_id, 0]) + 1;
_reg set [_id, _count];
missionNamespace setVariable ["COMSPEC_DEBUG_EH_REGISTRY", _reg];

if (_count > 1) then {
    ["WARN", "EH", "DUPLICATE", format ["id=%1 registered=%2 kind=%3 event=%4", _id, _count, _kind, _event]] call comspec_debug_fnc_log;
} else {
    ["DEBUG", "EH", "REGISTER", format ["id=%1 kind=%2 event=%3", _id, _kind, _event]] call comspec_debug_fnc_log;
};

private _handle = -1;
switch (_kind) do {
    case "CBA": {
        if (!isNil "CBA_fnc_addEventHandler") then {
            _handle = [_event, _code] call CBA_fnc_addEventHandler;
        };
    };
    case "MISSION": {
        _handle = addMissionEventHandler [_event, _code];
    };
    case "OBJECT": {
        if (!isNull _target) then {
            _handle = _target addEventHandler [_event, _code];
        };
    };
    case "ACE": {
        if (!isNil "ace_common_fnc_addEventHandler") then {
            _handle = [_event, _code] call ace_common_fnc_addEventHandler;
        };
    };
    default {
        ["WARN", "EH", "UNKNOWN", format ["kind=%1 id=%2", _kind, _id]] call comspec_debug_fnc_log;
    };
};

_handle
