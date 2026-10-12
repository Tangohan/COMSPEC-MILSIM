/*
    Ouvre le tableau des liens.
    [_zeus] call comspec_sse_fnc_chartOpen
      _zeus : true = tout le réseau, ce que les joueurs ignorent en grisé, bouton RÉVÉLER
*/
params [["_zeus", false, [false]]];
if (!hasInterface) exitWith { false };
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
if (!(_st isEqualType createHashMap)) then { _st = createHashMap; };
_st set ["zeus", _zeus];
if (!("mode" in _st)) then { _st set ["mode", "graph"]; };
if (!("filter" in _st)) then { _st set ["filter", "all"]; };
if (!("confirmed" in _st)) then { _st set ["confirmed", false]; };
_st set ["sel", ""];
uiNamespace setVariable ["comspec_sse_chartState", _st];
createDialog "COMSPEC_SSE_ChartDialog";
true
