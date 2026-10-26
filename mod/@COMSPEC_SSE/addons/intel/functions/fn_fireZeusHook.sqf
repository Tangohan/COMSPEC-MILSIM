params [
    ["_intelId", "", [""]],
    ["_entity", objNull, [objNull]],
    ["_datum", createHashMap, [createHashMap]]
];
if (_intelId == "") exitWith { false };
// Hooks propres à chaque machine (registerZeusHook) : à enregistrer côté serveur (initServer.sqf). Un poste qui
// exploite sans hook local passe la main au serveur (intel/XEH_postInit).
private _hooks = missionNamespace getVariable ["comspec_sse_zeusHooks", createHashMap];
private _hook = _hooks getOrDefault [_intelId, nil];
if (isNil "_hook") then {
    private _hid = _datum getOrDefault ["hookId", ""];
    _hook = _hooks getOrDefault [_hid, nil];
};
if (isNil "_hook") exitWith {
    if (!isServer) then { ["comspec_sse_fireZeusHook", [_intelId, _entity, _datum]] call CBA_fnc_serverEvent; };
    false
};

if (_hook isEqualType {}) then {
    [_entity, _datum, _intelId] call _hook;
} else {
    if (_hook isEqualType []) then {
        _hook params [["_code", {}, [{}]], ["_args", [], [[]]]];
        [_entity, _datum, _intelId, _args] call _code;
    };
};
["SSE_HookFired", [_intelId, _entity]] call comspec_sse_fnc_emitEvent;
true
