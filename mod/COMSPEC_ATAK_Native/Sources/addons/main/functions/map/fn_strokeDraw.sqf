/*
    Dessin libre de la carte (fn_mapOnDraw) : traits joueurs épais / fléchés, et tracé en cours.
    Arma dessine les polylignes sur un pixel ; les traits posés avec une épaisseur ou une flèche (variable publique
    COMSPEC_ATAK_SK_<nom> = [épaisseur, flèche, fermé]) sont repassés ici, à leur couleur, identiques pour tous.
    La liste des traits stylés est refaite toutes les 2 s (pas de parcours de tous les marqueurs à chaque image).
    Params : [carte, position du curseur (monde)]
*/
params ["_map", ["_cursor", []]];
private _now = diag_tickTime;
private _list = uiNamespace getVariable ["COMSPEC_ATAK_SkList", []];
if (_now > (uiNamespace getVariable ["COMSPEC_ATAK_SkScan", -1])) then {
    uiNamespace setVariable ["COMSPEC_ATAK_SkScan", _now + 2];
    _list = [];
    {
        if ((markerShape _x) isEqualTo "POLYLINE") then {
            private _st = missionNamespace getVariable [format ["COMSPEC_ATAK_SK_%1", _x regexReplace ["[^A-Za-z0-9]", "_"]], []];
            if ((count _st) >= 2) then { _list pushBack [_x, _st]; };
        };
    } forEach allMapMarkers;
    uiNamespace setVariable ["COMSPEC_ATAK_SkList", _list];
};
private _colCache = uiNamespace getVariable "COMSPEC_ATAK_SkColors";
if (isNil "_colCache") then { _colCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_SkColors", _colCache]; };
private _rgbaOf = {
    params ["_cls"];
    private _c = _colCache getOrDefault [_cls, []];
    if ((count _c) isEqualTo 0) then {
        _c = (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
        if ((count _c) < 4) then { _c = [0.9, 0.1, 0.1, 1]; };
        _colCache set [_cls, _c];
    };
    +_c
};
private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
// Rectangle visible (marge de 30 px pour l'épaisseur et la flèche) : traits hors de la vue sautés.
(ctrlPosition _map) params ["_vcx", "_vcy", "_vcw", "_vch"];
private _vtl = _map ctrlMapScreenToWorld [_vcx, _vcy];
private _vbr = _map ctrlMapScreenToWorld [_vcx + _vcw, _vcy + _vch];
private _vmg = 30 * _mpx;
private _vx0 = ((_vtl select 0) min (_vbr select 0)) - _vmg;
private _vx1 = ((_vtl select 0) max (_vbr select 0)) + _vmg;
private _vy0 = ((_vtl select 1) min (_vbr select 1)) - _vmg;
private _vy1 = ((_vtl select 1) max (_vbr select 1)) + _vmg;
// Points et boîte englobante de chaque trait gardés tant que sa polyligne ne change pas (avant : liste refaite à chaque image).
private _geo = uiNamespace getVariable ["COMSPEC_ATAK_SkGeo", createHashMap];
if ((count _geo) > 400) then { _geo = createHashMap; };
uiNamespace setVariable ["COMSPEC_ATAK_SkGeo", _geo];
{
    _x params ["_m", "_st"];
    if ((markerShape _m) isEqualTo "" || {(markerAlpha _m) <= 0}) then { continue };
    _st params [["_w", 2], ["_arrow", false]];
    private _flat = markerPolyline _m;
    private _g = _geo getOrDefault [_m, []];
    if ((_g param [0, []]) isNotEqualTo _flat) then {
        private _pts = [];
        private _bx0 = 1e9; private _bx1 = -1e9; private _by0 = 1e9; private _by1 = -1e9;
        for "_i" from 0 to ((count _flat) - 2) step 2 do {
            private _px = _flat select _i; private _py = _flat select (_i + 1);
            _pts pushBack [_px, _py, 0];
            _bx0 = _bx0 min _px; _bx1 = _bx1 max _px; _by0 = _by0 min _py; _by1 = _by1 max _py;
        };
        _g = [_flat, _pts, [_bx0, _bx1, _by0, _by1]];
        _geo set [_m, _g];
    };
    _g params ["", "_pts", "_bb"];
    if ((_bb select 1) < _vx0 || {(_bb select 0) > _vx1} || {(_bb select 3) < _vy0} || {(_bb select 2) > _vy1}) then { continue };
    private _c = [markerColor _m] call _rgbaOf;
    _c set [3, (_c select 3) * (markerAlpha _m)];
    ["line", _map, [_pts, _c, (_w max 1 min 3) * 1.3, false, _arrow, false, _mpx]] call comspec_atak_native_fnc_planGfx;
} forEach _list;

// Tracé en cours (trait A-B suivant le curseur, ou dessin à main levée) au style choisi.
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _stroke = +(_state getOrDefault ["drawStroke", []]);
if ((count _stroke) > 0) then {
    if ((_state getOrDefault ["mapMode", ""]) isEqualTo "LINE" && {(count _cursor) >= 2}) then { _stroke pushBack [_cursor select 0, _cursor select 1, 0]; };
    private _c = [profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"]] call _rgbaOf;
    private _closed = (profileNamespace getVariable ["COMSPEC_ATAK_DrawClosed", false]) && {(_state getOrDefault ["mapMode", ""]) isEqualTo "DRAW"};
    if (_closed && {(count _stroke) > 2}) then {
        // Fermeture montrée en pointillé fin jusqu'au point de départ.
        _map drawLine [_stroke select -1, _stroke select 0, [_c select 0, _c select 1, _c select 2, 0.45]];
    };
    ["line", _map, [_stroke apply { [_x select 0, _x select 1, 0] }, _c, ((profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2]) max 1 min 3) * 1.3, false,
        (profileNamespace getVariable ["COMSPEC_ATAK_DrawArrow", false]) && !_closed, false, _mpx]] call comspec_atak_native_fnc_planGfx;
};
