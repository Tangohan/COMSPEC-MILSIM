/*
    Ouvre le terminal SSE (hub).
    [_entity] call comspec_sse_fnc_uiOpenTerminal
*/
params [
    ["_entity", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };

if (isNull _entity) then {
    // Si curseur sur une cible SSE, l'utiliser
    private _cursor = cursorObject;
    if (!isNull _cursor && {!isNil {[_cursor] call comspec_sse_fnc_getData}}) then {
        _entity = _cursor;
    };
};

if (!isNull _entity) then {
    [_entity] call comspec_sse_fnc_uiSetRecord;
};

if !(createDialog "COMSPEC_SSE_TerminalDialog") exitWith {
    hint "Impossible d'ouvrir le terminal SSE.";
    false
};
true
