/* Clic gauche sur la carte selon l'outil actif (Ctrl = ping, Maj = marqueur, Alt = mesure). */
params ["_world",["_shift",false],["_ctrl",false],["_alt",false]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _tool = _s getOrDefault ["mapMode","SELECT"];
if (_ctrl) then {_tool = "PING"};
if (_shift) then {_tool = "MARKER"};
if (_alt) then {_tool = "MEASURE"};
_world = [_world select 0, _world select 1, 0];
switch (_tool) do {
    case "PING": {
        private _events = (uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["events",[]];
        _events pushBack [_world,diag_tickTime];
        ["TACTICAL",format ["PING %1",[_world] call comspec_atak_native_fnc_gridRef],3,40] call comspec_atak_native_fnc_notify;
    };
    case "MARKER": { [_world] call comspec_atak_native_fnc_markerDrop; };
    case "MEASURE": {
        private _m = _s getOrDefault ["mapMeasure",[]];
        _s set ["mapMeasure", [[_world], (_m + [_world])] select ((count _m) isEqualTo 1)];
        [true] call comspec_atak_native_fnc_mapOverlayUpdate;
    };
    case "HOUSES";
    case "HEIGHT";
    case "FLAT";
    case "LOS": { [_tool, _world] call comspec_atak_native_fnc_mapToolRun; };
    case "ROUTE": {
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        ["INFO", "GPS : calcul de l'itinéraire…", 2, 20] call comspec_atak_native_fnc_notify;
        [[_world select 0, _world select 1, 0], format ["%1", [[_world select 0, _world select 1, 0], 6] call comspec_atak_native_fnc_gridRef]] spawn comspec_atak_native_fnc_routeCompute;
    };
    default {
        private _best = createHashMap;
        private _dist = 1e10;
        {
            private _e = _y;
            private _p = _e getOrDefault ["position",[]];
            if (count _p >= 2) then {
                private _v = _p distance2D _world;
                if (_v < _dist && {_v < 150}) then { _dist = _v; _best = _e; };
            };
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["units",createHashMap]);
        _s set ["selectedEntity",_best];
        [] call comspec_atak_native_fnc_inspectorUpdate;
    };
};
true
