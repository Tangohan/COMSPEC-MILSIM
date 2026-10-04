/*
    Carte : drone appairé (position, mode, ligne vers la cible d'une frappe). Appelé depuis fn_mapOnDraw.
    Params : [contrôle carte]
*/
params ["_map"];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
if (isNull _d || {!alive _d}) exitWith {};
// Sans liaison, la carte garde la dernière position reçue.
private _linked = missionNamespace getVariable ["COMSPEC_ATAK_DroneLinked", true];
private _pos = if (_linked) then { getPosASL _d } else { missionNamespace getVariable ["COMSPEC_ATAK_DroneLastPos", getPosASL _d] };
if (_linked) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneLastPos", _pos]; };
private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _c = [[0.2, 0.9, 0.95, 1], [0.95, 0.3, 0.2, 1]] select (_m isEqualTo "STRIKE");
if (_m isEqualTo "STRIKE") then {
    private _t = _d getVariable ["COMSPEC_DroneTgt", []];
    if ((count _t) >= 2) then {
        _map drawLine [_pos, _t, _c];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", _c, _t, 22, 22, 0, "", 0];
    };
};
_map drawIcon ["\A3\ui_f\data\map\vehicleicons\iconHelicopter_ca.paa", [_c, [0.6, 0.6, 0.6, 0.8]] select !_linked, _pos, 24, 24, getDir _d,
    format ["Drone %1 m%2", round ((getPosATL _d) select 2), ["", " (hors liaison)"] select !_linked], 1, 0.03, "RobotoCondensedBold", "right"];
