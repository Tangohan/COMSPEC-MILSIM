/*
    Dessin carte (Draw) : mesure, itinéraire, laser JTAC, aéronefs.
    Pas de texte EachFrame — uniquement des traits / icônes.
*/
params ["_map"];
if (isNull _map) exitWith {};

private _a = missionNamespace getVariable ["COMSPEC_MapMeasureA", []];
if ((_a isEqualType []) && {(count _a) >= 2}) then {
    private _cur = _map ctrlMapScreenToWorld getMousePosition;
    if ((count _cur) >= 2) then {
        _map drawLine [_a, _cur, [0.95, 0.82, 0.22, 0.9]];
    };
};

private _pts = missionNamespace getVariable ["COMSPEC_MapRoutePts", []];
if ((_pts isEqualType []) && {(count _pts) >= 2}) then {
    private _i = 1;
    while { _i < (count _pts) } do {
        _map drawLine [_pts select (_i - 1), _pts select _i, [0.37, 0.78, 0.95, 0.88]];
        _i = _i + 1;
    };
};

private _lt = laserTarget player;
if (isNull _lt) then {
    _lt = player getVariable ["ace_laser_target", objNull];
};
if (!isNull _lt) then {
    _map drawLine [getPosATL player, getPosATL _lt, [1, 0.15, 0.12, 0.85]];
    missionNamespace setVariable ["COMSPEC_LaserSeenAt", time, false];
};

private _filter = missionNamespace getVariable ["COMSPEC_MapFilter", "ALL"];
private _layers = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
private _showAir = (_filter in ["ALL", "AIR", "FRIENDLY"]) && {_layers getOrDefault ["cas", true]};
private _air = missionNamespace getVariable ["COMSPEC_MapAir", []];
if (_showAir && {_air isEqualType []}) then {
    {
        _x params ["_cs", "_pos", "_alt", "_st"];
        if (!(_pos isEqualType []) || {(count _pos) < 2}) then { continue };
        _map drawIcon [
            "\A3\ui_f\data\map\markers\nato\b_air.paa",
            [0.37, 0.78, 0.95, 1],
            _pos,
            22,
            22,
            0,
            format ["%1  %2 m  %3", _cs, _alt, _st],
            1,
            0.04,
            "RobotoCondensed",
            "right"
        ];
    } forEach _air;
};
