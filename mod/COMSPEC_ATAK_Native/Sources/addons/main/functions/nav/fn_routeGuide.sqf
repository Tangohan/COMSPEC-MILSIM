/*
    Guidage GPS (appelé chaque seconde) : avance sur l'itinéraire, prochaine manœuvre, distance et heure d'arrivée,
    recalcul si l'on sort de la route, arrivée. Renvoie [] sans itinéraire, sinon
    [texte manœuvre, distance avant manœuvre (m), icône (nav_*), reste (m), durée (s), libellé].
*/
private _r = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
if ((count _r) isEqualTo 0) exitWith { [] };
private _pts = _r get "pts"; private _cum = _r get "cum";
private _me = getPosATL vehicle player;
private _dest = _r get "dest";
if ((_me distance2D _dest) < 30) exitWith {
    ["SUCCESS", format ["Vous êtes arrivé : %1", _r get "label"], 6, 50] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
    missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMap];
    []
};
// Point le plus proche sur les segments à venir (fenêtre de 25 segments).
private _idx = _r get "idx";
private _best = 1e9; private _bi = _idx; private _bproj = _me;
for "_i" from _idx to (((count _pts) - 2) min (_idx + 25)) do {
    private _a = _pts select _i; private _b = _pts select (_i + 1);
    private _ab = (_b vectorDiff _a); _ab set [2, 0];
    private _l2 = (_ab vectorDotProduct _ab) max 0.01;
    private _am = _me vectorDiff _a; _am set [2, 0];
    private _t = ((_am vectorDotProduct _ab) / _l2) max 0 min 1;
    private _p = _a vectorAdd (_ab vectorMultiply _t);
    private _d = _me distance2D _p;
    if (_d < _best) then { _best = _d; _bi = _i; _bproj = _p; };
};
_r set ["idx", _bi];
_r set ["proj", _bproj];
// Hors itinéraire : recalcul (au plus toutes les 12 s).
if (_best > 60) then { _r set ["off", (_r get "off") + 1]; } else { _r set ["off", 0]; };
if ((_r get "off") >= 4 && {time - (_r get "at") > 12}) then {
    _r set ["off", 0];
    ["INFO", "Hors itinéraire : recalcul…", 3, 20] call comspec_atak_native_fnc_notify;
    [_dest, _r get "label", true] spawn comspec_atak_native_fnc_routeCompute;
};
private _done = (_cum select _bi) + ((_pts select _bi) distance2D _bproj);
private _left = ((_r get "total") - _done) max 0;
private _next = ((_r get "turns") select { (_x select 0) > _bi }) param [0, []];
private _txt = ""; private _icon = "nav_arrive"; private _dn = _left;
if ((count _next) > 0) then {
    _next params ["_ti", "_ang"];
    _dn = ((_cum select _ti) - _done) max 0;
    private _r = _ang > 0;
    switch (true) do {
        case ((abs _ang) > 135): { _txt = "Faites demi-tour"; _icon = "nav_uturn"; };
        case ((abs _ang) > 60): { _txt = ["Tournez à gauche", "Tournez à droite"] select _r; _icon = ["nav_left", "nav_right"] select _r; };
        default { _txt = ["Serrez à gauche", "Serrez à droite"] select _r; _icon = ["nav_slight_left", "nav_slight_right"] select _r; };
    };
    // Loin du prochain virage : « continuez ».
    if (_dn > 800) then { _txt = format ["Continuez, puis %1", toLower _txt]; };
} else {
    _txt = "Continuez jusqu'à destination";
};
// Vitesse : réelle si l'on roule, sinon allure type (à pied 5 km/h, véhicule 45 km/h).
private _v = vectorMagnitude velocity vehicle player;
private _vRef = [1.4, 12.5] select ((vehicle player) isNotEqualTo player);
private _eta = _left / ((_v max (_vRef * 0.5)) min (_vRef * 3) max 0.5);
[_txt, _dn, _icon, _left, _eta, _r get "label"]
