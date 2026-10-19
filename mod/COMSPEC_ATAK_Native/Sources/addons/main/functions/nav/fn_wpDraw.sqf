/*
    Itinéraire des points de passage sur la carte (fn_mapOnDraw, à chaque image) :
    - tronçons pleins et épais à la couleur de l'étape d'arrivée, chevron de sens au milieu, distance et azimut du
      tronçon quand on zoome ; en navigation, tronçons passés estompés, tronçon actif et trait de cap depuis moi en vert ;
    - chaque étape : symbole de son type, « numéro. nom » cerné de noir ; étape active : anneau qui pulse ; étape
      modifiée dans la fiche : anneau blanc ;
    - mode POINTS DE PASSAGE : point choisi d'un clic droit (anneau, symbole du type, trait depuis la dernière étape ou,
      pour un déplacement, depuis l'étape modifiée) et trait de la dernière étape au curseur.
    Params : [contrôle carte, échelle des symboles, échelle des textes, position du curseur]
*/
params ["_map", "_sc", "_ts", ["_cursor", []]];
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _pts = _w getOrDefault ["pts", []];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _wpMode = (_state getOrDefault ["mapMode", "SELECT"]) isEqualTo "WP";
private _anchor = _state getOrDefault ["wpAnchor", []];
if ((count _pts) isEqualTo 0 && {!_wpMode}) exitWith {};
private _font = COMSPEC_ATAK_FontSet select 1;
private _clear = "#(argb,8,8,3)color(0,0,0,0)";
private _ring = "\A3\ui_f\data\map\markers\military\circle_CA.paa";
// Mètres par pixel écran : épaisseur des traits et décalages constants à l'écran, quel que soit le zoom.
private _c0 = _map ctrlMapScreenToWorld [0.5, 0.5];
private _mpp = (_c0 distance2D (_map ctrlMapScreenToWorld [0.5 + pixelW, 0.5])) max 0.01;
private _legs = (ctrlMapScale _map) < 0.18;
private _nav = _w getOrDefault ["nav", false];
private _idx = _w getOrDefault ["idx", 0];
private _sel = [-1, _state getOrDefault ["wpSel", -1]] select _wpMode;
if (_sel >= count _pts) then { _sel = -1; };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
// Trait épais : liseré sombre puis trois traits de couleur côte à côte.
private _thick = {
    params ["_a", "_b", "_c"];
    private _d = (_b vectorDiff _a); _d set [2, 0];
    private _len = vectorMagnitude _d;
    if (_len < 0.01) exitWith {};
    private _n = [-(_d select 1) / _len, (_d select 0) / _len, 0];
    private _shade = [0, 0, 0, 0.55 * (_c select 3)];
    { _map drawLine [_a vectorAdd (_n vectorMultiply (_x * _mpp)), _b vectorAdd (_n vectorMultiply (_x * _mpp)), _shade]; } forEach [-2.2, 2.2];
    { _map drawLine [_a vectorAdd (_n vectorMultiply (_x * _mpp)), _b vectorAdd (_n vectorMultiply (_x * _mpp)), _c]; } forEach [-1.1, 0, 1.1];
};
private _dash = {
    params ["_a", "_b", "_c"];
    private _n = ((round ((_a distance2D _b) / (_mpp * 10))) max 1) min 80;
    for "_k" from 0 to (_n - 1) step 2 do { _map drawLine [_a vectorAdd ((_b vectorDiff _a) vectorMultiply (_k / _n)), _a vectorAdd ((_b vectorDiff _a) vectorMultiply ((_k + 1) / _n)), _c]; };
};
// Couleur et icône de chaque type, lues une fois par image (pas une fois par étape).
private _tmap = createHashMap;
{ _tmap set [_x select 0, [_x select 3, ["icon", _x select 0] call comspec_atak_native_fnc_wpAction]]; } forEach (["types"] call comspec_atak_native_fnc_wpAction);
private _tOf = { params ["_k"]; _tmap getOrDefault [_k, _tmap get "WP"] };
private _colOf = { params ["_p"]; +(([_p param [2, "WP"]] call _tOf) select 0) };

// 1. Tronçons.
for "_i" from 1 to ((count _pts) - 1) do {
    private _a = ((_pts select (_i - 1)) select 0) vectorMultiply 1; _a set [2, 0];
    private _b = ((_pts select _i) select 0) vectorMultiply 1; _b set [2, 0];
    private _c = [_pts select _i] call _colOf;
    if (_nav) then {
        if (_i < _idx) then { _c = [0.55, 0.60, 0.57, 0.35]; };
        if (_i isEqualTo _idx) then { _c = [0.36, 0.85, 0.42, 1]; };
    };
    [_a, _b, _c] call _thick;
    // Chevron de sens au milieu du tronçon (assez long à l'écran pour qu'il ne cache rien).
    private _len = _a distance2D _b;
    if ((_len / _mpp) > 70) then {
        private _mid = (_a vectorAdd _b) vectorMultiply 0.5;
        private _u = (_b vectorDiff _a) vectorMultiply (1 / _len);
        _map drawArrow [_mid vectorAdd (_u vectorMultiply (-9 * _mpp)), _mid vectorAdd (_u vectorMultiply (9 * _mpp)), [1, 1, 1, 0.9 * (_c select 3)]];
        if (_legs && {(_len / _mpp) > 140}) then {
            private _n = [-(_u select 1), _u select 0, 0];
            _map drawIcon [_clear, [1, 1, 1, 0.9 * ((_c select 3) max 0.6)], _mid vectorAdd (_n vectorMultiply (10 * _mpp)), 0, 0, 0,
                format ["%1 · %2°", [_len] call _fmt, round (_a getDir _b)], 2, 0.022 * _ts, _font, "center"];
        };
    };
};
// Cap depuis moi vers l'étape active.
if (_nav && {(count _pts) > 0}) then {
    private _me = getPosATL vehicle player; _me set [2, 0];
    [_me, ((_pts select (_idx min ((count _pts) - 1))) select 0), [0.36, 0.85, 0.42, 0.9]] call _dash;
};

// 2. Mode POINTS DE PASSAGE : trait de la dernière étape au curseur, point choisi d'un clic droit.
if (_wpMode) then {
    private _last = if ((count _pts) > 0) then { (_pts select ((count _pts) - 1)) select 0 } else { getPosATL vehicle player };
    if (_sel < 0 && {(count _anchor) < 2} && {(count _cursor) >= 2}) then {
        [_last, [_cursor select 0, _cursor select 1, 0], [1, 1, 1, 0.45]] call _dash;
    };
    if ((count _anchor) >= 2) then {
        private _ap = [_anchor select 0, _anchor select 1, 0];
        private _from = if (_sel >= 0) then { (_pts select _sel) select 0 } else { _last };
        private _k = if (_sel >= 0) then { (_pts select _sel) param [2, "WP"] } else { _state getOrDefault ["wpType", "WP"] };
        private _c = ([_k] call _tOf) select 0;
        [_from, _ap, [_c select 0, _c select 1, _c select 2, 0.8]] call _dash;
        private _pulse = 0.55 + 0.45 * (abs sin (diag_tickTime * 180));
        _map drawIcon [_ring, [_c select 0, _c select 1, _c select 2, _pulse], _ap, 36 * _sc, 36 * _sc, 0, "", 0];
        _map drawIcon [([_k] call _tOf) select 1, [1, 1, 1, 0.8], _ap, 22 * _sc, 22 * _sc, 0,
            "  " + ((["NOUVELLE ÉTAPE · VALIDER", "NOUVEAU POINT · DÉPLACER ICI"] select (_sel >= 0)) call comspec_atak_native_fnc_tr), 2, 0.026 * _ts, _font, "right"];
    };
};

// 3. Étapes (dessinées après les traits : jamais recouvertes).
{
    _x params ["_p", "_n"];
    _n = [_n] call comspec_atak_native_fnc_markerAutoName; // FIN <-> END selon la langue
    private _c = [_x] call _colOf;
    private _passed = _nav && {_forEachIndex < _idx};
    if (_passed) then { _c set [3, 0.45]; };
    if (_nav && {_forEachIndex isEqualTo _idx}) then {
        private _pulse = 0.5 + 0.5 * (abs sin (diag_tickTime * 150));
        _map drawIcon [_ring, [0.36, 0.85, 0.42, _pulse], _p, 40 * _sc, 40 * _sc, 0, "", 0];
    };
    if (_forEachIndex isEqualTo _sel) then { _map drawIcon [_ring, [1, 1, 1, 1], _p, 38 * _sc, 38 * _sc, 0, "", 0]; };
    // Halo sombre sous le symbole : lisible sur la forêt comme sur le sable.
    private _ic = ([_x param [2, "WP"]] call _tOf) select 1;
    _map drawIcon [_ic, [0, 0, 0, 0.6 * (_c select 3)], _p, 30 * _sc, 30 * _sc, 0, "", 0];
    _map drawIcon [_ic, _c, _p, 24 * _sc, 24 * _sc, 0,
        format [" %1. %2", _forEachIndex + 1, _n], 2, 0.03 * _ts, _font, "right"];
} forEach _pts;
