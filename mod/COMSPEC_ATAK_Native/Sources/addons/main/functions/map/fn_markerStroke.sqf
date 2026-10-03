/*
    Traits et dessins sur la carte, posés comme marqueurs polyligne partagés sur le canal courant.
    LINE : clic A puis clic B. DRAW : maintenir le clic gauche et dessiner, relâcher pour poser.
    Params : ["DOWN" | "MOVE" | "UP", position monde]
*/
params ["_event", ["_pos", []]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mode = _s getOrDefault ["mapMode", "SELECT"];
private _stroke = _s getOrDefault ["drawStroke", []];
private _commit = {
    params ["_pts"];
    if ((count _pts) < 2) exitWith { "" };
    private _flat = [];
    { _flat append [(_x select 0), (_x select 1)]; } forEach (_pts select [0, 300]);
    private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
    private _m = createMarker [format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, currentChannel], _pts select 0, currentChannel, player];
    if (_m isEqualTo "") exitWith { ["WARNING", "Trait refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify; "" };
    _m setMarkerShapeLocal "POLYLINE";
    _m setMarkerPolylineLocal _flat;
    _m setMarkerColor (profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"]);
    _m
};
switch (_event) do {
    case "DOWN": {
        if (_mode isEqualTo "LINE") exitWith {
            if ((count _stroke) isEqualTo 0) then { _s set ["drawStroke", [_pos]]; } else { [[_stroke select 0, _pos]] call _commit; _s set ["drawStroke", []]; };
            true
        };
        if (_mode isEqualTo "DRAW") exitWith { _s set ["drawStroke", [_pos]]; _s set ["drawing", true]; true };
        false
    };
    case "MOVE": {
        if !(_mode isEqualTo "DRAW" && {_s getOrDefault ["drawing", false]}) exitWith { false };
        private _map = ([] call comspec_atak_native_fnc_display) displayCtrl 88530;
        // Un point tous les ~6 pixels pour garder un trait lisse sans le surcharger.
        if (((_map ctrlMapWorldToScreen (_stroke select -1)) distance2D (_map ctrlMapWorldToScreen _pos)) > pixelW * 6) then { _stroke pushBack _pos; };
        true
    };
    case "UP": {
        if !(_mode isEqualTo "DRAW" && {_s getOrDefault ["drawing", false]}) exitWith { false };
        _s set ["drawing", false];
        [_stroke] call _commit;
        _s set ["drawStroke", []];
        true
    };
    default { false };
}
