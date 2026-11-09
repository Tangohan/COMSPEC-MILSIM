/*
    Zoom de la carte du téléphone. Params : [facteur (0.5 = avant, 2 = arrière)]
    En main : autour du centre visible. Porté (mini) : autour du joueur, la carte continue à le suivre.
    Le zoom est mémorisé pour la prochaine ouverture de la carte.
    Progressif comme la molette : l'échelle est interpolée image par image (courbe douce, 0,28 s) au lieu d'un saut d'un cran
    entier ; des appuis rapprochés s'additionnent (la cible repart de la cible en cours) et le dernier arrête l'animation précédente.
    Le centre est tenu image par image (même décalage qu'à fn_mapCenter), puis fn_mapCenter recale une fois à la fin.
*/
params [["_factor", 0.5]];
disableSerialization;
private _m = ([] call comspec_atak_native_fnc_mapCtrl);
if (isNull _m) exitWith { false };
private _now = diag_tickTime;
// Appuis rapprochés (moins de 0,6 s) : la cible en cours sert de base, pas l'échelle de l'instant.
private _base = uiNamespace getVariable ["COMSPEC_ATAK_MapZoomTarget", -1];
if (_base <= 0 || {(_now - (uiNamespace getVariable ["COMSPEC_ATAK_MapZoomTargetAt", -9])) > 0.6}) then { _base = ctrlMapScale _m; };
private _z = ((_base * _factor) max 0.001) min 1;
uiNamespace setVariable ["COMSPEC_ATAK_MapScale", _z];
uiNamespace setVariable ["COMSPEC_ATAK_MapZoomTarget", _z];
uiNamespace setVariable ["COMSPEC_ATAK_MapZoomTargetAt", _now];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Centre tenu pendant le zoom : moi, l'unité suivie, ou le centre visible.
private _fwP = (((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [_s getOrDefault ["mapFollowId", ""], createHashMap]) getOrDefault ["position", []];
(ctrlPosition _m) params ["_x", "_y", "_w", "_h"];
private _vis = _m ctrlMapScreenToWorld [_x + _w / 2, _y + _h / 2];
private _mode = "VIS";
private _c = _vis;
if (!(_s getOrDefault ["interactive", false]) || {_s getOrDefault ["mapFollow", false]}) then {
    _mode = "ME";
    private _p = [] call comspec_atak_native_fnc_gpsPos;
    _c = [_p select 0, _p select 1];
} else {
    if ((count _fwP) >= 2) then { _mode = "UNIT"; _c = [_fwP select 0, _fwP select 1]; };
};
private _last = _m getVariable ["comspec_lastAim", []];
private _off = [0, 0];
if ((count _last) isEqualTo 2) then { _off = [(_last select 0) - (_vis select 0), (_last select 1) - (_vis select 1)]; };
private _z0 = (ctrlMapScale _m) max 0.0001;
private _tok = (uiNamespace getVariable ["COMSPEC_ATAK_MapZoomTok", 0]) + 1;
uiNamespace setVariable ["COMSPEC_ATAK_MapZoomTok", _tok];
// Déjà à la cible (borne atteinte) : rien à animer.
if (abs (_z - _z0) < (_z0 * 0.01)) exitWith { true };
// Un recentrage de fn_mapCenter encore en cours ne doit pas disputer l'échelle à l'animation.
_m setVariable ["comspec_centerToken", (_m getVariable ["comspec_centerToken", 0]) + 1];
[{
    params ["_a", "_pfh"];
    _a params ["_m", "_c", "_off", "_z0", "_z", "_mode", "_t0", "_tok"];
    if (isNull _m || {(uiNamespace getVariable ["COMSPEC_ATAK_MapZoomTok", 0]) isNotEqualTo _tok}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
    private _t = ((diag_tickTime - _t0) / 0.28) min 1;
    // Courbe douce (démarrage et arrivée progressifs), échelle interpolée en géométrique : même rapport à chaque image.
    private _k = _t * _t * (3 - 2 * _t);
    private _zi = _z0 * ((_z / _z0) ^ _k);
    private _aim = [(_c select 0) + (_off select 0) * (_zi / _z0), (_c select 1) + (_off select 1) * (_zi / _z0)];
    _m ctrlMapAnimAdd [0, _zi, _aim];
    ctrlMapAnimCommit _m;
    if (_t >= 1) then {
        [_pfh] call CBA_fnc_removePerFrameHandler;
        // Fin : recalage d'après la position réellement affichée (fn_mapCenter), comme avant.
        switch (_mode) do {
            case "ME": { [player, _z] call comspec_atak_native_fnc_mapCenter };
            case "UNIT": { [_c, _z, true] call comspec_atak_native_fnc_mapCenter };
            default { [_c, _z] call comspec_atak_native_fnc_mapCenter };
        };
    };
}, 0, [_m, _c, _off, _z0, _z, _mode, _now, _tok]] call CBA_fnc_addPerFrameHandler;
true
