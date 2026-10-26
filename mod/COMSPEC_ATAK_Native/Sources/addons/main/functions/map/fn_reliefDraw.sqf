/*
    Calque Relief sur la carte (appelé depuis mapOnDraw) : une case pleine par point de la grille calculée
    par comspec_atak_native_fnc_reliefAction. Mode VISIBILITÉ : vert vu, rouge caché (orange : caché par un
    bâtiment ou la végétation) ; observateur et secteur tracés ; mode ALTITUDE : heatmap
    (altitude absolue, ou par rapport à moi selon rlHeat). Puis les bâtiments vus (vert) et cachés (rouge pâle).
    Params : [contrôle carte]
*/
params ["_map"];
if !(missionNamespace getVariable ["COMSPEC_ATAK_ViewshedShow", true]) exitWith {};
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Coins visibles de la carte : cases et bâtiments hors écran ignorés.
(ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
private _vx0 = (_tl select 0) min (_br select 0);
private _vx1 = (_tl select 0) max (_br select 0);
private _vy0 = (_tl select 1) min (_br select 1);
private _vy1 = (_tl select 1) max (_br select 1);
// Bâtiments vus : contour du bâtiment, plein vert s'il est vu. Position, demi-tailles et orientation calculées une fois
// par calcul de relief (avant : boundingBoxReal, getPosASL, getDir de 300 bâtiments à chaque image).
private _bld = missionNamespace getVariable ["COMSPEC_ATAK_ReliefBld", []];
if ((count _bld) > 2) then {
    private _geo = uiNamespace getVariable ["COMSPEC_ATAK_ReliefBldGeo", []];
    if ((_geo param [0, []]) isNotEqualTo [_bld select 0, _bld param [3, 0], count (_bld select 2)]) then {
        private _list = [];
        {
            _x params ["_b", "_seen"];
            if (isNull _b) then { continue };
            (boundingBoxReal _b) params ["_p1", "_p2"];
            _list pushBack [_b, _seen, getPosASL _b, ((_p2 select 0) - (_p1 select 0)) / 2, ((_p2 select 1) - (_p1 select 1)) / 2, getDir _b];
        } forEach (_bld select 2);
        _geo = [[_bld select 0, _bld param [3, 0], count (_bld select 2)], _list];
        uiNamespace setVariable ["COMSPEC_ATAK_ReliefBldGeo", _geo];
    };
    {
        _x params ["_b", "_seen", "_bp", "_rw", "_rh", "_bd"];
        if (isNull _b) then { continue };
        private _bm = _rw max _rh;
        if ((_bp select 0) + _bm < _vx0 || {(_bp select 0) - _bm > _vx1} || {(_bp select 1) + _bm < _vy0} || {(_bp select 1) - _bm > _vy1}) then { continue };
        if (_seen) then {
            _map drawRectangle [_bp, _rw, _rh, _bd, [0.25, 0.90, 0.40, 0.55], "#(rgb,8,8,3)color(1,1,1,1)"];
            _map drawRectangle [_bp, _rw, _rh, _bd, [0.25, 0.90, 0.40, 1], ""];
        } else {
            _map drawRectangle [_bp, _rw, _rh, _bd, [0.90, 0.25, 0.20, 0.45], ""];
        };
    } forEach (_geo select 1);
};
private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
if ((count _vs) < 9) exitWith {};
_vs params ["", "_cell", "_cells", "", "", "", "", "_best"];
private _alt = (_st getOrDefault ["rlMode", "VIS"]) isEqualTo "ALT";
// Heatmap : couleur absolue (4e champ) ou par rapport à moi (5e champ, calculs de cette version).
private _ci = [3, 4] select ((_st getOrDefault ["rlHeat", "ABS"]) isEqualTo "REL");
private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
private _a = _cell / 2;
private _green = [0.2, 0.85, 0.3, 0.3];
private _red = [0.9, 0.2, 0.15, 0.3];
private _orange = [1, 0.6, 0.1, 0.3];
// Cases hors écran ignorées : on borne d'après les coins visibles de la carte.
private _x0 = _vx0 - _cell;
private _x1 = _vx1 + _cell;
private _y0 = _vy0 - _cell;
private _y1 = _vy1 + _cell;
{
    _x params ["_p", "_seen"];
    private _col = _x param [_ci, []];
    if ((count _col) < 4) then { _col = _x select 3; };
    if ((_p select 0) < _x0 || {(_p select 0) > _x1} || {(_p select 1) < _y0} || {(_p select 1) > _y1}) then { continue };
    _map drawRectangle [_p, _a, _a, 0, [[[_red, _orange] select (_x param [5, false]), _green] select _seen, _col] select _alt, _tex];
} forEach _cells;
if (_best >= 0) then {
    private _bp = (_cells select _best) select 0;
    _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa", [0.95, 0.55, 0.2, 1], _bp, 18, 18, 0, format ["%1 m", round (_bp select 2)], 1, 0.03 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), (COMSPEC_ATAK_FontSet select 1), "right"];
};
// Observateur et secteur (calculs depuis un point, un équipier ou un drone).
if ((count _vs) > 13) then {
    _vs params ["", "", "", "_rad", "", "", "", "", "", "", "", "_eye", "_sector", "_who"];
    if (_sector isNotEqualTo []) then {
        _sector params ["_dir", "_fov"];
        private _l = _dir - _fov / 2;
        private _r = _dir + _fov / 2;
        _map drawLine [_eye, _eye getPos [_rad, _l], [0.95, 0.95, 0.95, 0.8]];
        _map drawLine [_eye, _eye getPos [_rad, _r], [0.95, 0.95, 0.95, 0.8]];
        for "_g" from _l to (_r - 5) step 5 do { _map drawLine [_eye getPos [_rad, _g], _eye getPos [_rad, (_g + 5) min _r], [0.95, 0.95, 0.95, 0.8]]; };
    };
    if (_who isNotEqualTo "Moi") then {
        _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [0.95, 0.95, 0.95, 1], _eye, 14, 14, 0, format ["%1 · %2 m", _who call comspec_atak_native_fnc_tr, round ((_eye select 2) - ((getTerrainHeightASL _eye) max 0))], 1, 0.026 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), (COMSPEC_ATAK_FontSet select 1), "right"];
    };
};
