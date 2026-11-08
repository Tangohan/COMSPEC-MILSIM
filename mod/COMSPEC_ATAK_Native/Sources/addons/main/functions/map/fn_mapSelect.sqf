/* Clic gauche sur la carte selon l'outil actif (Ctrl = ping, Maj = marqueur, Alt = mesure). */
params ["_world",["_shift",false],["_ctrl",false],["_alt",false],["_map",controlNull]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _tool = _s getOrDefault ["mapMode","SELECT"];
if (_ctrl) then {_tool = "PING"};
if (_shift) then {_tool = "MARKER"};
if (_alt) then {_tool = "MEASURE"};
_world = [_world select 0, _world select 1, 0];
switch (_tool) do {
    // Ping partagé avec mon camp (même envoi que le menu du clic droit).
    case "PING": { ["send", _world] call comspec_atak_native_fnc_mapPing; };
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
    case "WP": { ["add", _world] call comspec_atak_native_fnc_wpAction; };
    // JTAC : IP puis cible (cap magnétique, distance, altitude, fn_jtacTool).
    case "IPTGT": { ["click", _world] call comspec_atak_native_fnc_jtacTool; };
    case "ROUTE": {
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        ["INFO", "GPS : calcul de l'itinéraire…", 2, 20] call comspec_atak_native_fnc_notify;
        [[_world select 0, _world select 1, 0], format ["Point %1", [[_world select 0, _world select 1, 0], 6] call comspec_atak_native_fnc_gridRef]] spawn comspec_atak_native_fnc_routeCompute;
    };
    default {
        private _best = createHashMap;
        private _dist = 1e10;
        // Tolérance en pixels écran autour du symbole (comme fn_markerAt) : avant, 150 m fixes (tout l'écran en zoom
        // rapproché, quelques pixels en zoom éloigné). Sans contrôle carte : 150 m comme avant.
        private _scr = [];
        private _tol = 150;
        if (!isNull _map) then {
            _scr = _map ctrlMapWorldToScreen _world;
            _tol = pixelW * 18 * (uiNamespace getVariable ["COMSPEC_ATAK_MapIs", 1]);
        };
        {
            private _e = _y;
            private _p = _e getOrDefault ["position",[]];
            if (count _p >= 2) then {
                private _v = if ((count _scr) > 0) then { (_map ctrlMapWorldToScreen _p) distance2D _scr } else { _p distance2D _world };
                if (_v < _dist && {_v < _tol}) then { _dist = _v; _best = _e; };
            };
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["units",createHashMap]);
        _s set ["selectedEntity",_best];
        // La fiche flottante (fn_unitCard), aussi en petit format : la carte ne change ni de taille ni de rendu. Le panneau
        // SITUATION reste ouvert par son onglet et garde la fiche longue.
        ["mapCard"] call comspec_atak_native_fnc_unitCard;
        [] call comspec_atak_native_fnc_inspectorUpdate;
    };
};
true
