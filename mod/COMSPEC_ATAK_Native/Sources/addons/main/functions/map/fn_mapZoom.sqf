/*
    Zoom de la carte du téléphone. Params : [facteur (0.5 = avant, 2 = arrière)]
    En main : autour du centre visible. Porté (mini) : autour du joueur, la carte continue à le suivre.
    Le zoom est mémorisé pour la prochaine ouverture de la carte.
    Progressif comme la molette : l'échelle est interpolée image par image (courbe douce, 0,28 s) au lieu d'un saut d'un cran
    entier ; des appuis rapprochés s'additionnent (la cible repart de la cible en cours) et le dernier arrête l'animation précédente.
    Un seul maître de la vue pendant le zoom (« ça déplace la visu vers un point puis recentre sur moi », mini) :
    avant, le centre était pris une fois au départ, le décalage venait d'un comspec_lastAim jamais mis à jour par le zoom
    (faux dès le 2e cran rapproché), le suivi image par image (fn_mapOnDraw) et le recentrage de fn_statusUpdate (échelle
    changée) lançaient leurs propres ctrlMapAnimAdd pendant l'animation, et fn_mapCenter recalait à la fin avec ce décalage
    périmé : la carte filait vers un autre point puis le suivi la ramenait sur moi.
    Désormais : à chaque image, centre recalculé (ma position du moment, l'unité suivie, ou le centre visible fixe sans suivi),
    cible calculée par fn_mapZoomAim avec un décalage normalisé par l'échelle (gardé d'un cran à l'autre), comspec_lastAim
    tenu à jour, et comspec_zoomUntil fait taire le suivi et fn_statusUpdate jusqu'à la fin ; plus de recentrage final.
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
(ctrlPosition _m) params ["_x", "_y", "_w", "_h"];
private _vis = _m ctrlMapScreenToWorld [_x + _w / 2, _y + _h / 2];
// Centre tenu pendant le zoom : moi (porté ou « Suivre ma position »), l'unité suivie, ou le centre visible (fixe).
private _mode = "VIS";
if (!(_s getOrDefault ["interactive", false]) || {_s getOrDefault ["mapFollow", false]}) then {
    _mode = "ME";
} else {
    if ((_s getOrDefault ["mapFollowId", ""]) isNotEqualTo "") then { _mode = "UNIT"; };
};
private _z0 = (ctrlMapScale _m) max 0.0001;
// Décalage centre interne / centre visible, ramené à l'échelle 1. Zoom déjà en cours : on garde le sien (le dernier point
// visé n'est peut-être pas encore affiché, le remesurer donnerait un écart faux) ; sinon on le mesure sur le dernier recentrage.
private _offN = _m getVariable ["comspec_zoomOffN", []];
if (diag_tickTime >= (_m getVariable ["comspec_zoomUntil", -1]) || {(count _offN) isNotEqualTo 2}) then {
    private _last = _m getVariable ["comspec_lastAim", []];
    _offN = [0, 0];
    if ((count _last) isEqualTo 2) then { _offN = [((_last select 0) - (_vis select 0)) / _z0, ((_last select 1) - (_vis select 1)) / _z0]; };
};
private _tok = (uiNamespace getVariable ["COMSPEC_ATAK_MapZoomTok", 0]) + 1;
uiNamespace setVariable ["COMSPEC_ATAK_MapZoomTok", _tok];
// Déjà à la cible (borne atteinte) : rien à animer.
if (abs (_z - _z0) < (_z0 * 0.01)) exitWith { true };
_m setVariable ["comspec_zoomOffN", _offN];
_m setVariable ["comspec_zoomUntil", _now + 0.4];
// Un recentrage de fn_mapCenter encore en cours ne doit pas disputer l'échelle à l'animation.
_m setVariable ["comspec_centerToken", (_m getVariable ["comspec_centerToken", 0]) + 1];
[{
    params ["_a", "_pfh"];
    _a params ["_m", "_vis", "_offN", "_z0", "_z", "_mode", "_t0", "_tok"];
    if (isNull _m || {(uiNamespace getVariable ["COMSPEC_ATAK_MapZoomTok", 0]) isNotEqualTo _tok}) exitWith { [_pfh] call CBA_fnc_removePerFrameHandler; };
    // Centre de cette image : position du moment (le joueur avance pendant les 0,28 s), pas celle du départ.
    private _c = _vis;
    switch (_mode) do {
        case "ME": { private _p = [] call comspec_atak_native_fnc_gpsPos; _c = [_p select 0, _p select 1]; };
        case "UNIT": {
            private _u = (((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["mapFollowId", ""], createHashMap]) getOrDefault ["position", []];
            if ((count _u) >= 2) then { _c = [_u select 0, _u select 1]; };
        };
    };
    private _t = ((diag_tickTime - _t0) / 0.28) min 1;
    ([_z0, _z, _t, _c, _offN] call comspec_atak_native_fnc_mapZoomAim) params ["_zi", "_aim"];
    // Seul ordre de la vue pendant le zoom ; fn_mapOnDraw et fn_mapCenter repartent de ce point visé.
    _m setVariable ["comspec_lastAim", _aim];
    _m setVariable ["comspec_zoomUntil", diag_tickTime + 0.1];
    _m ctrlMapAnimAdd [0, _zi, _aim];
    ctrlMapAnimCommit _m;
    if (_t >= 1) then {
        [_pfh] call CBA_fnc_removePerFrameHandler;
        // Fin avec suivi : pas de recentrage (il visait avec l'ancien décalage et faisait le « puis recentre sur moi »). Le
        // suivi reprend tout seul depuis comspec_lastAim, exact ; fn_statusUpdate ne relance pas de centrage pour ce zoom.
        uiNamespace setVariable ["COMSPEC_ATAK_FollowLast", [0, 0, _z]];
        _m setVariable ["comspec_zoomUntil", -1];
        // Sans suivi : vise le même point que la dernière image (décalage à jour), donc pas de saut ; ses corrections
        // rattrapent seulement un reste d'écart (premier zoom d'une carte jamais centrée).
        if (_mode isEqualTo "VIS") then { [_vis, _z] call comspec_atak_native_fnc_mapCenter; };
    };
}, 0, [_m, _vis, _offN, _z0, _z, _mode, _now, _tok]] call CBA_fnc_addPerFrameHandler;
true
