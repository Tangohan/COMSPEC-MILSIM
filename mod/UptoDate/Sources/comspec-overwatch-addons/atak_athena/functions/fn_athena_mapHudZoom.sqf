/*
    Zoom carte ATAK Enhanced : facteur < 1 rapproche, > 1 eloigne.
    Centre conserve sur le milieu du rectangle carte.
*/
params [["_factor", 1, [0]]];

if (!hasInterface) exitWith {};
if (_factor <= 0) exitWith {};

private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _disp) exitWith {};

private _mapCtrl = controlNull;
if (!isNil "cTab_fnc_getSettings" && {!isNil "cTab_fnc_getFromPairs"}) then {
    private _mapName = ["cTab_Android_dlg", "mapType"] call cTab_fnc_getSettings;
    private _mapTypes = ["cTab_Android_dlg", "mapTypes"] call cTab_fnc_getSettings;
    private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
    if (_mapIdc isEqualType 0) then { _mapCtrl = _disp displayCtrl _mapIdc; };
};
if (isNull _mapCtrl) then { _mapCtrl = _disp displayCtrl 1201; };
if (isNull _mapCtrl) exitWith {};

(ctrlPosition _mapCtrl) params ["_mx", "_my", "_mw", "_mh"];
private _center = _mapCtrl ctrlMapScreenToWorld [_mx + (_mw / 2), _my + (_mh / 2)];
private _scale = ctrlMapScale _mapCtrl;
private _next = ((_scale * _factor) max 0.001) min 1;
_mapCtrl ctrlMapAnimAdd [0.16, _next, _center];
ctrlMapAnimCommit _mapCtrl;
