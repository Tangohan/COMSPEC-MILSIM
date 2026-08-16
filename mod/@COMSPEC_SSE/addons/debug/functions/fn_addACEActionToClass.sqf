/*
    Wrapper ACE addActionToClass avec registre, détection doublons et classes dangereuses.

    Params:
      0: class (STRING)
      1: type (NUMBER) 0=actions 1=self
      2: path (ARRAY)
      3: action (ARRAY from createAction)
      4: inheritance (BOOL)
      5: source (STRING, optional)
*/
params [
    ["_class", "", [""]],
    ["_type", 0, [0]],
    ["_path", [], [[]]],
    ["_action", [], [[]]],
    ["_inheritance", true, [true]],
    ["_source", "unknown", [""]]
];

private _actionId = if ((count _action) > 0) then { str (_action select 0) } else { "?" };
private _pathStr = _path joinString "/";
private _key = format ["%1|%2|%3", _class, _pathStr, _actionId];

["DEBUG", "ACE", "REGISTER", format [
    "source=%1 class=%2 path=%3 action=%4 inheritance=%5",
    _source, _class, _pathStr, _actionId, _inheritance
]] call comspec_debug_fnc_log;

private _dangerous = ["Thing", "All", "AllVehicles", "ThingX", "Entity"];
if (_inheritance && {_class in _dangerous}) then {
    ["CRITICAL", "ACE", "INHERITANCE", format [
        "Dangerous broad inheritance detected class=%1 inheritance=%2 source=%3",
        _class, _inheritance, _source
    ]] call comspec_debug_fnc_log;

    if (missionNamespace getVariable ["COMSPEC_DEBUG_BLOCK_DANGEROUS_ACE", true]) exitWith {
        ["CRITICAL", "ACE", "BLOCKED", format [
            "Registration blocked class=%1 source=%2",
            _class, _source
        ]] call comspec_debug_fnc_log;
        false
    };
};

private _reg = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_ACTIONS", createHashMap];
if (!(_reg isEqualType createHashMap)) then { _reg = createHashMap; };

private _count = _reg getOrDefault [_key, 0];
_count = _count + 1;
_reg set [_key, _count];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_ACTIONS", _reg];

private _dup = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_DUPLICATES", 0];
if (_count > 1) then {
    _dup = _dup + 1;
    missionNamespace setVariable ["COMSPEC_DEBUG_ACE_DUPLICATES", _dup];
    ["WARN", "ACE", "DUPLICATE", format [
        "Action déjà enregistrée class=%1 action=%2 count=%3 source=%4",
        _class, _actionId, _count, _source
    ]] call comspec_debug_fnc_log;
};

private _byClass = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_BY_CLASS", createHashMap];
if (!(_byClass isEqualType createHashMap)) then { _byClass = createHashMap; };
_byClass set [_class, (_byClass getOrDefault [_class, 0]) + 1];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_BY_CLASS", _byClass];

if (_inheritance) then {
    private _inh = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERITED", 0];
    missionNamespace setVariable ["COMSPEC_DEBUG_ACE_INHERITED", _inh + 1];
};

private _total = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_TOTAL", 0];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_TOTAL", _total + 1];

if (isNil "ace_interact_menu_fnc_addActionToClass") exitWith {
    ["ERROR", "ACE", "MISSING", "ace_interact_menu_fnc_addActionToClass indisponible"] call comspec_debug_fnc_log;
    false
};

private _ok = true;
private _start = diag_tickTime;
try {
    [_class, _type, _path, _action, _inheritance] call ace_interact_menu_fnc_addActionToClass;
} catch {
    _ok = false;
    ["ERROR", "ACE", "EXCEPTION", format [
        "function=addACEActionToClass exception=%1 context=class=%2 action=%3 source=%4",
        _exception, _class, _actionId, _source
    ]] call comspec_debug_fnc_log;
};
[_start, format ["addACEActionToClass %1/%2", _class, _actionId], 0.05] call comspec_debug_fnc_perfWarn;

_ok
