/*
    Traits et dessins sur la carte, posés comme marqueurs polyligne partagés sur le canal courant (et envoyés au web).
    LINE : clic A puis clic B. DRAW : maintenir le clic gauche et dessiner, relâcher pour poser : le tracé est lissé
    et allégé (fn_strokeSimplify), fermé si l'option FERMÉ est cochée.
    Style choisi dans la barre du dessin (fn_strokeBar, profil) : couleur COMSPEC_ATAK_DrawColor, épaisseur
    COMSPEC_ATAK_DrawWidth (1-3), flèche COMSPEC_ATAK_DrawArrow, forme fermée COMSPEC_ATAK_DrawClosed.
    L'épaisseur et la flèche partent avec le trait (variable publique COMSPEC_ATAK_SK_<nom>, dessinée par fn_strokeDraw).
    Params : ["DOWN" | "MOVE" | "UP", position monde] ; ["UNDO"] défait mon dernier trait ; ["CLEARMINE"] efface les miens.
*/
params ["_event", ["_pos", []]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mode = _s getOrDefault ["mapMode", "SELECT"];
private _stroke = _s getOrDefault ["drawStroke", []];
private _mine = { (missionNamespace getVariable ["COMSPEC_ATAK_StrokeMine", []]) select { (markerShape _x) isNotEqualTo "" } };
private _commit = {
    params ["_pts", ["_closed", false]];
    if ((count _pts) < 2) exitWith { "" };
    private _flat = [];
    { _flat append [(_x select 0), (_x select 1)]; } forEach (_pts select [0, 200]);
    private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
    private _channel = ["channel"] call comspec_atak_native_fnc_markerPlace;
    missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
    // Overwatch (EH MarkerCreated) enverrait sinon au web un point « mil_dot » avant que la polyligne existe :
    // ses EH sont muets le temps de poser le trait, fn_markerWeb l'envoie ensuite en entier.
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1];
    private _m = ["create", [format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _channel], _pts select 0, _channel]] call comspec_atak_native_fnc_markerNet;
    if (_m isNotEqualTo "") then {
        _m setMarkerShapeLocal "POLYLINE";
        _m setMarkerPolylineLocal _flat;
        _m setMarkerColor (profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"]);
    };
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1) max 0];
    if (_m isEqualTo "") exitWith { ["WARNING", "Trait refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify; "" };
    private _w = (profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2]) max 1 min 3;
    private _arrow = (profileNamespace getVariable ["COMSPEC_ATAK_DrawArrow", false]) && {!_closed};
    // Style visible de tous (JIP compris) : un trait fin sans flèche n'en a pas besoin.
    if (_w > 1 || _arrow) then {
        missionNamespace setVariable [format ["COMSPEC_ATAK_SK_%1", _m regexReplace ["[^A-Za-z0-9]", "_"]], [_w, _arrow, _closed], true];
    };
    private _list = missionNamespace getVariable ["COMSPEC_ATAK_StrokeMine", []];
    _list pushBack _m;
    missionNamespace setVariable ["COMSPEC_ATAK_StrokeMine", _list select [((count _list) - 50) max 0]];
    missionNamespace setVariable ["COMSPEC_ATAK_SkScan", -1];
    [_m] call comspec_atak_native_fnc_markerWeb;
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
        private _map = ([] call comspec_atak_native_fnc_mapCtrl);
        // Un point tous les ~3 pixels : le lissage au relâchement garde la forme sans les tremblements.
        if (((_map ctrlMapWorldToScreen (_stroke select -1)) distance2D (_map ctrlMapWorldToScreen _pos)) > pixelW * 3) then { _stroke pushBack _pos; };
        true
    };
    case "UP": {
        if !(_mode isEqualTo "DRAW" && {_s getOrDefault ["drawing", false]}) exitWith { false };
        _s set ["drawing", false];
        private _map = ([] call comspec_atak_native_fnc_mapCtrl);
        private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
        private _closed = profileNamespace getVariable ["COMSPEC_ATAK_DrawClosed", false];
        private _pts = [_stroke, (_mpx * 1.5) max 0.2, true, _closed && {(count _stroke) > 3}, 150] call comspec_atak_native_fnc_strokeSimplify;
        // Simple clic sans tracé : rien n'est posé.
        if ((count _pts) >= 2 && {((_pts select 0) distance2D (_pts select -1)) > _mpx * 3 || {(count _pts) > 2}}) then { [_pts, _closed] call _commit; };
        _s set ["drawStroke", []];
        true
    };
    case "UNDO": {
        private _l = call _mine;
        if ((count _l) isEqualTo 0) exitWith { ["INFO", "Aucun trait à défaire", 2, 10] call comspec_atak_native_fnc_notify; false };
        [_l select -1] call comspec_atak_native_fnc_markerDelete;
        missionNamespace setVariable ["COMSPEC_ATAK_StrokeMine", _l select [0, (count _l) - 1]];
        true
    };
    case "CLEARMINE": {
        private _l = call _mine;
        { deleteMarker _x; [_x, true] call comspec_atak_native_fnc_markerWeb; } forEach _l;
        missionNamespace setVariable ["COMSPEC_ATAK_StrokeMine", []];
        ["INFO", format ["%1 trait(s) effacé(s)", count _l], 2, 10] call comspec_atak_native_fnc_notify;
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        true
    };
    default { false };
}
