/*
    Calque Relief sur la carte (appelé depuis mapOnDraw) : une case pleine par point de la grille calculée
    par comspec_atak_native_fnc_reliefAction. Mode VISIBILITÉ : vert vu, rouge caché par le relief, orange caché par
    un bâtiment ou la végétation ; mode ALTITUDE : dégradé. Observateur marqué (œil), secteur de vision tracé.
    Params : [contrôle carte]
*/
params ["_map"];
if !(missionNamespace getVariable ["COMSPEC_ATAK_ViewshedShow", true]) exitWith {};
private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
if ((count _vs) < 9) exitWith {};
_vs params ["", "_cell", "_cells", "", "", "", "", "_best"];
private _alt = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["rlMode", "VIS"]) isEqualTo "ALT";
private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
private _a = _cell / 2;
private _green = [0.2, 0.85, 0.3, 0.3];
private _red = [0.9, 0.2, 0.15, 0.3];
private _orange = [1, 0.6, 0.1, 0.3];
// Cases hors écran ignorées : on borne d'après les coins visibles de la carte.
(ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
private _x0 = ((_tl select 0) min (_br select 0)) - _cell;
private _x1 = ((_tl select 0) max (_br select 0)) + _cell;
private _y0 = ((_tl select 1) min (_br select 1)) - _cell;
private _y1 = ((_tl select 1) max (_br select 1)) + _cell;
{
    _x params ["_p", "_seen", "", "_col", ["_obj", false]];
    if ((_p select 0) < _x0 || {(_p select 0) > _x1} || {(_p select 1) < _y0} || {(_p select 1) > _y1}) then { continue };
    _map drawRectangle [_p, _a, _a, 0, [[[_red, _orange] select _obj, _green] select _seen, _col] select _alt, _tex];
} forEach _cells;
if (_best >= 0) then {
    private _bp = (_cells select _best) select 0;
    _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa", [0.95, 0.55, 0.2, 1], _bp, 18, 18, 0, format ["%1 m", round (_bp select 2)], 1, 0.03 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), "RobotoCondensedBold", "right"];
};
// Observateur et secteur (calculs depuis un point, un équipier ou un drone).
if ((count _vs) > 11) then {
    _vs params ["", "", "", "_rad", "", "", "", "", "", "_eye", "_sector", "_who"];
    private _ts = uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1];
    if (_sector isNotEqualTo []) then {
        _sector params ["_dir", "_fov"];
        private _l = _dir - _fov / 2;
        private _r = _dir + _fov / 2;
        _map drawLine [_eye, _eye getPos [_rad, _l], [0.95, 0.95, 0.95, 0.8]];
        _map drawLine [_eye, _eye getPos [_rad, _r], [0.95, 0.95, 0.95, 0.8]];
        for "_g" from _l to (_r - 5) step 5 do { _map drawLine [_eye getPos [_rad, _g], _eye getPos [_rad, (_g + 5) min _r], [0.95, 0.95, 0.95, 0.8]]; };
    } else {
        _map drawEllipse [_eye, _rad, _rad, 0, [0.95, 0.95, 0.95, 0.6], ""];
    };
    _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [0.95, 0.95, 0.95, 1], _eye, 14, 14, 0, format ["%1 · %2 m", _who, round ((_eye select 2) - ((getTerrainHeightASL _eye) max 0))], 1, 0.026 * _ts, "RobotoCondensedBold", "right"];
};
