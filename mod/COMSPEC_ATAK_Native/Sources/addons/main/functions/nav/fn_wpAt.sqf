/*
    Étape de l'itinéraire sous un point de l'écran (la plus proche, à quelques pixels près).
    Params : [contrôle carte, position écran [x, y]] -> index de l'étape ou -1
*/
params ["_map", "_screen"];
if (isNull _map) exitWith { -1 };
private _best = -1;
private _bestD = pixelW * 16 * ((uiNamespace getVariable ["COMSPEC_ATAK_MapIs", 1.25]) max 1);
{
    private _d = (_map ctrlMapWorldToScreen (_x select 0)) distance2D _screen;
    if (_d < _bestD) then { _bestD = _d; _best = _forEachIndex; };
} forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []]);
_best
