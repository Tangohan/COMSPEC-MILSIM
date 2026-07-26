/*
    Recentre / applique l’échelle de la carte native tablette.
*/
private _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
if (isNull _display) then { _display = findDisplay 9974; };
if (isNull _display) exitWith {};

private _map = _display displayCtrl 9410;
if (isNull _map) exitWith {};
if !(missionNamespace getVariable ["COMSPEC_WebBrowser_MapVisible", false]) exitWith {};

private _zoom = missionNamespace getVariable ["COMSPEC_WebBrowser_MapZoom", 1];
_zoom = (_zoom max 0.25) min 8;
private _scale = ((0.12 / _zoom) max 0.001) min 1.0;

private _center = getPos player;
private _units = missionNamespace getVariable ["COMSPEC_WebBrowser_MapUnits", []];
{
    if ((_x isEqualType []) && {(count _x) >= 5}) then {
        _x params ["_cs", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0]];
        if (_isSelf && {!(_wx isEqualTo 0 && {_wy isEqualTo 0})}) then {
            _center = [_wx, _wy, 0];
        };
    };
} forEach _units;

_map ctrlMapAnimAdd [0, _scale, _center];
ctrlMapAnimCommit _map;
missionNamespace setVariable ["COMSPEC_WebBrowser_MapAutoCenter", true];
