/*
    Compte rendu after-action SSE.
    [_center, _radius] call comspec_sse_fnc_afterActionReport
*/
params [
    ["_center", objNull, [objNull, []]],
    ["_radius", 150, [0]]
];

private _list = [_center, _radius] call comspec_sse_fnc_listSiteEntities;
private _found = count _list;
private _exploited = { (_x getOrDefault ["level", "NONE"]) in ["DETAILED", "FUSION"] } count _list;
private _partial = { (_x getOrDefault ["level", "NONE"]) in ["TACTICAL", "FIELD"] } count _list;
private _untouched = _found - _exploited - _partial;
private _pct = [_center, _radius] call comspec_sse_fnc_siteCompleteness;
private _logical = count ([] call comspec_sse_fnc_listLogicalEntities);
private _hist = count (missionNamespace getVariable ["comspec_sse_actionHistory", []]);

private _lines = [
    "=== AFTER ACTION SSE ===",
    format ["Complétude site : %1%%", _pct],
    format ["Éléments SSE : %1", _found],
    format ["Exploités (DETAILED+) : %1", _exploited],
    format ["Partiels : %1", _partial],
    format ["Non touchés : %1", _untouched],
    format ["Entités logiques : %1", _logical],
    format ["Actions opérateurs : %1", _hist],
    format ["Découvertes indexées : %1", count (keys (missionNamespace getVariable ["comspec_sse_discoveryStates", createHashMap]))]
];

hint (_lines joinString endl);
_lines
