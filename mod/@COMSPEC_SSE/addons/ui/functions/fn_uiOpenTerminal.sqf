/*
    Ouvre le terminal SSE (hub).
    [_entity] call comspec_sse_fnc_uiOpenTerminal
*/
params [
    ["_entity", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };

if (isNull _entity) then {
    private _cursor = cursorObject;
    if (!isNull _cursor && {!isNil {[_cursor] call comspec_sse_fnc_getData}}) then {
        _entity = _cursor;
    };
};

if (!isNull _entity) then {
    [_entity] call comspec_sse_fnc_uiSetRecord;
};

if (!isNil "comspec_overwatch_connect_fnc_sseOpenTerminal") exitWith {
    [_entity, 0, "terminal"] call comspec_sse_fnc_uiOpenSeekHost
};

if !(createDialog "COMSPEC_SSE_TerminalDialog") exitWith {
    hint "Impossible d'ouvrir le terminal SSE.";
    false
};
true
