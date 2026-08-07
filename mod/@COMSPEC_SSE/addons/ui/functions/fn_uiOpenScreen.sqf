/*
    Navigation entre écrans UI SSE.
    [_screen] call comspec_sse_fnc_uiOpenScreen
*/
params [
    ["_screen", "terminal", [""]]
];

closeDialog 0;

private _map = createHashMapFromArray [
    ["terminal", "COMSPEC_SSE_TerminalDialog"],
    ["digital", "COMSPEC_SSE_DigitalDialog"],
    ["site", "COMSPEC_SSE_SiteDialog"],
    ["graph", "COMSPEC_SSE_GraphDialog"],
    ["evidence", "COMSPEC_SSE_EvidenceDialog"],
    ["mission", "COMSPEC_SSE_MissionIntelDialog"],
    ["zeus", "COMSPEC_SSE_ZeusControlDialog"],
    ["seek", "SEEK"]
];

private _dlg = _map getOrDefault [toLower _screen, "COMSPEC_SSE_TerminalDialog"];

if (_dlg == "SEEK") exitWith {
    private _rec = [] call comspec_sse_fnc_uiGetRecord;
    if (isNull _rec) then { _rec = cursorObject; };
    if (isNull _rec) then { _rec = player; };
    [_rec] call comspec_sse_fnc_openSeek;
    true
};

missionNamespace setVariable ["comspec_sse_uiScreen", toLower _screen];
createDialog _dlg;
true
