/*
    Clic sur le tableau des liens : choisit le nœud le plus proche du curseur.
*/
params ["_ctrl", "_button", "_mx", "_my"];
if (_button isNotEqualTo 0) exitWith {};
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
private _best = "";
private _bd = 0.03;
{
    private _sp = _ctrl ctrlMapWorldToScreen (_y get "pos");
    private _d = _sp distance2D [_mx, _my];
    if (_d < _bd) then { _bd = _d; _best = _x; };
} forEach (_st getOrDefault ["nodes", createHashMap]);
_st set ["sel", _best];
[_best] call comspec_sse_fnc_chartDetail;
