/*
    Display + IDC selon l’hôte : terminal SEEK (PAA) si ouvert, sinon overlay historique.
    [_screen] call comspec_sse_fnc_uiDisplayCtx
    → [display, idcSummary, idcList, idcExtra, isSeek]
*/
params [
    ["_screen", "terminal", [""]]
];

_screen = toLower _screen;

private _seek = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _seek) then { _seek = findDisplay 9991; };
if (!isNull _seek) exitWith {
    [_seek, 9580, 9581, 9582, true]
};

private _legacy = switch (_screen) do {
    case "digital": { [93250, 93253, 93254, 93252] };
    case "site": { [93300, 93310, 93311, 93312] };
    case "graph": { [93350, 93361, 93360, 93361] };
    case "evidence": { [93400, 93411, 93410, 93411] };
    case "mission": { [93450, 93452, 93453, 93452] };
    default { [93200, 93211, 93212, 93213] };
};
_legacy params ["_idd", "_sum", "_list", "_extra"];
[findDisplay _idd, _sum, _list, _extra, false]
