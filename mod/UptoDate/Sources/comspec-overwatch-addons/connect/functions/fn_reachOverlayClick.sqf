/*
    Clic carte : si un contact récent est sous le curseur, affiche sa zone de déplacement.
    Params : [_world, _mapCtrl, _xC, _yC]  → true si un contact a été pris.
*/
params [["_world", []], ["_mapCtrl", controlNull], ["_xC", -1], ["_yC", -1]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith { false };

private _rows = missionNamespace getVariable ["COMSPEC_ReachCache", []];
if (!(_rows isEqualType []) || {(count _rows) == 0}) exitWith { false };

private _useScreen = !isNull _mapCtrl && {_xC >= 0} && {_yC >= 0};
private _best = [];
private _bestD = if (_useScreen) then { 0.04 } else { 80 };
{
    if (!(_x isEqualType []) || {(count _x) < 6}) then { continue };
    if (_x select 3) then { continue };
    private _wx = _x select 4;
    private _wy = _x select 5;
    if ((abs _wx) < 1 && {(abs _wy) < 1}) then { continue };
    private _d = if (_useScreen) then {
        private _scr = _mapCtrl ctrlMapWorldToScreen [_wx, _wy];
        [_xC, _yC] distance2D _scr
    } else {
        [_wx, _wy, 0] distance2D _world
    };
    if (_d < _bestD) then {
        _bestD = _d;
        _best = _x;
    };
} forEach _rows;

if ((count _best) < 1) exitWith { false };
[_best, true] call comspec_overwatch_connect_fnc_reachOverlaySelect
