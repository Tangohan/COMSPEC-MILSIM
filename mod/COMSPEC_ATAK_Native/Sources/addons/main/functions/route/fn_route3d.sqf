/*
    Vue 3D des routes (option « Vue 3D » d'une route, réglage client comspec_atak_native_route_3d) : trait posé sur le
    terrain et repère de chaque étape dans le monde. 2.9.38 : seulement JVN allumée (currentVisionMode 1, même règle
    que l'affichage ECOTI) et seulement la route suivie, à 3D cochée, à partir de l'étape visée (étapes atteintes
    retirées tout de suite : fn_routeAction rappelle cette fonction à chaque étape, à l'arrêt et à la fin du suivi).
    Sans JVN, sans suivi ou sans 3D : aucun événement Draw3D (coût nul).
    Appelée une fois par seconde (fn_routeAction "tick") : si une route a changé ou après 20 m parcourus, prépare les morceaux à moins de 1,5 km du joueur (découpés pour
    suivre le relief, 400 au plus) et les repères, puis pose ou retire l'événement Draw3D. À chaque image, Draw3D ne fait
    que tracer la liste préparée (aucun calcul de route ni de terrain).
    Params : [] (préparation) | ["draw"] (Draw3D)
*/
params [["_op", ""]];
if (_op isEqualTo "draw") exitWith {
    (missionNamespace getVariable ["COMSPEC_ATAK_Rte3d", [[], []]]) params ["_segs", "_tags"];
    { drawLine3D _x; } forEach _segs;
    private _me = positionCameraToWorld [0, 0, 0];
    {
        _x params ["_p", "_ic", "_c", "_t"];
        private _d = _me distance _p;
        private _a = ((1.4 - _d / 1500) max 0.35) min 1;
        drawIcon3D [_ic, [_c select 0, _c select 1, _c select 2, (_c select 3) * _a], _p, 0.7, 0.7, 0, format ["%1  %2", _t, [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000)], 2, 0.032, "RobotoCondensed", "center", false];
    } forEach _tags;
};
private _fpano = [] call comspec_atak_native_fnc_routeEcoti;
private _ok = missionNamespace getVariable ["comspec_atak_native_route_3d", true];
private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
private _fid = _f getOrDefault ["id", ""];
private _list = if (_ok && {_fid isNotEqualTo ""} && {(currentVisionMode player) isEqualTo 1} && {!visibleMap} && {[player] call comspec_atak_native_fnc_hasDevice} && {alive player}) then {
    private _r = ["get", _fid] call comspec_atak_native_fnc_routeAction;
    [[], [_r]] select ((count _r) > 0 && {_r get "d3"} && {(count (_r get "steps")) > 0})
} else { [] };
private _eh = missionNamespace getVariable ["COMSPEC_ATAK_Rte3dEh", -1];
if ((count _list) isEqualTo 0) exitWith {
    if (_eh >= 0) then { removeMissionEventHandler ["Draw3D", _eh]; missionNamespace setVariable ["COMSPEC_ATAK_Rte3dEh", -1]; };
    missionNamespace setVariable ["COMSPEC_ATAK_Rte3d", [[], []]];
    missionNamespace setVariable ["COMSPEC_ATAK_Rte3dKey", []];
};
private _me = getPosATL vehicle player;
// Liste refaite seulement si une route a changé (révision, couleur, étape visée) ou après 20 m parcourus.
private _key = [_list apply { [_x get "id", _x get "rev", _x get "color"] }, _f getOrDefault ["id", ""], _f getOrDefault ["idx", -1], _fpano];
private _last = missionNamespace getVariable ["COMSPEC_ATAK_Rte3dKey", []];
if (_eh >= 0 && {(_last param [0, []]) isEqualTo _key} && {(_me distance2D (_last param [1, [-1e6, -1e6, 0]])) < 20}) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_Rte3dKey", [_key, _me]];
private _near = 1500;
private _segs = [];
private _tags = [];
private _agl = { params ["_p", ["_h", 1.2]]; [_p select 0, _p select 1, _h] };
{
    private _r = _x;
    private _col = ["rgba", _r get "color"] call comspec_atak_native_fnc_routeAction;
    private _air = (_r get "kind") isEqualTo "AIR";
    private _fi = _f getOrDefault ["idx", 0];
    // Trait restant : de ma position à l'étape visée, puis les étapes suivantes (tracé par les routes non repris).
    private _pts = [[_me select 0, _me select 1, 0]] + (((_r get "steps") select [_fi]) apply { [(_x select 0) select 0, (_x select 0) select 1, 0] });
    // Trait : morceaux de 30 m au plus près du joueur, posés 1,2 m au-dessus du sol (aérien : 2 m, pour rester lisible).
    for "_i" from 1 to ((count _pts) - 1) do {
        if ((count _segs) >= 400) exitWith {};
        private _a = _pts select (_i - 1); private _b = _pts select _i;
        private _len = _a distance2D _b;
        // Segment loin de moi (ses deux bouts et son milieu) : sauté.
        if ((_me distance2D _a) > _near && {(_me distance2D _b) > _near} && {(_me distance2D ((_a vectorAdd _b) vectorMultiply 0.5)) > _near}) then { continue };
        private _n = ((ceil (_len / 30)) max 1) min 60;
        private _prev = [_a, [1.2, 2] select _air] call _agl;
        for "_k" from 1 to _n do {
            private _q = [(_a vectorAdd ((_b vectorDiff _a) vectorMultiply (_k / _n))), [1.2, 2] select _air] call _agl;
            if ((_me distance2D _q) < _near) then { _segs pushBack [_prev, _q, _col]; };
            _prev = _q;
        };
    };
    {
        _x params ["_p", "_k", "_n"];
        // F-PANO ECOTI affiche déjà les étapes (fn_routeEcoti) : ici le trait seul.
        if (_fpano || {_forEachIndex < _fi} || {(_me distance2D _p) > 4000}) then { continue };
        private _c = [_col, [0.36, 0.85, 0.42, 1]] select (_forEachIndex isEqualTo _fi);
        _tags pushBack [[_p, 3] call _agl, ["icon", _k] call comspec_atak_native_fnc_routeAction, _c, format ["%1. %2", _forEachIndex + 1, [_n] call comspec_atak_native_fnc_markerAutoName]];
    } forEach (_r get "steps");
} forEach _list;
missionNamespace setVariable ["COMSPEC_ATAK_Rte3d", [_segs, _tags select [0, 60]]];
if (_eh < 0) then {
    missionNamespace setVariable ["COMSPEC_ATAK_Rte3dEh", addMissionEventHandler ["Draw3D", { ["draw"] call comspec_atak_native_fnc_route3d; }]];
};
