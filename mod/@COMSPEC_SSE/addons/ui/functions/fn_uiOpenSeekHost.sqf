/*
    Ouvre le terminal SEEK (châssis PAA Overwatch) au lieu de l’overlay vert.
    [_target, _page, _screen] call comspec_sse_fnc_uiOpenSeekHost
*/
params [
    ["_target", objNull, [objNull]],
    ["_page", 0, [0]],
    ["_screen", "terminal", [""]]
];

if (!hasInterface) exitWith { false };
if (isNil "comspec_overwatch_connect_fnc_sseOpenTerminal") exitWith { false };

if (!isNull _target && {!isNil "comspec_sse_fnc_uiSetRecord"}) then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

_screen = toLower _screen;
missionNamespace setVariable ["comspec_sse_uiScreen", _screen];

private _apply = {
    params ["_page", "_screen"];
    if (!isNil "comspec_overwatch_connect_fnc_sseTerminalPage") then {
        [_page] call comspec_overwatch_connect_fnc_sseTerminalPage;
    };
    if (!isNil "comspec_sse_fnc_uiRefresh") then {
        [_screen] call comspec_sse_fnc_uiRefresh;
    };
};

private _open = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _open) then { _open = findDisplay 9991; };

if (!isNull _open) exitWith {
    [_page, _screen] call _apply;
    true
};

[_target, _page] call comspec_overwatch_connect_fnc_sseOpenTerminal;
[{
    params ["_page", "_screen"];
    if (!isNil "comspec_overwatch_connect_fnc_sseTerminalPage") then {
        [_page] call comspec_overwatch_connect_fnc_sseTerminalPage;
    };
    if (!isNil "comspec_sse_fnc_uiRefresh") then {
        [_screen] call comspec_sse_fnc_uiRefresh;
    };
}, [_page, _screen], 0.12] call CBA_fnc_waitAndExecute;
true
