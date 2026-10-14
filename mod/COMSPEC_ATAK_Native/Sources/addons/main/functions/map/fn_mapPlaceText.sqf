/*
    Noms des lieux (villes, villages, lieux-dits, sites) sur la carte du téléphone, dessinés par l'ATAK : la carte Arma
    n'écrit plus les noms (sizeExNames minuscule dans COMSPEC_RscMapAtak). Chaque catégorie se masque à part (CALQUES,
    Réglages > Carte ou menu CBA « ATAK · Carte ») :
      villes      NameCityCapital, NameCity ;
      villages    NameVillage ;
      lieux-dits  NameLocal, NameMarine ;
      sites       Airport, et lieux-dits au nom militaire (base, camp, caserne, armée, aérodrome...).
    Style (réglage CBA comspec_atak_native_map_place_style) :
      TACTICAL  capitales grasses détourées, taille selon le type (par défaut) ;
      TAG       plaque sombre, losange à la couleur du type ;
      QUIET     petit texte clair détouré, casse d'origine ;
      CLASSIC   couleur et casse de la carte Arma d'avant.
    Désencombrement : villages à partir d'un zoom moyen, lieux-dits et sites en zoom rapproché ; un nom qui chevauche
    une étiquette de repère (fn_mapMarkerText, image précédente) ou un nom plus important n'est pas écrit.
    Params : [carte, taille des textes, facteur écran, échelle de la carte]
*/
params ["_map", "_ts", "_kt", "_scale"];
private _show = [
    missionNamespace getVariable ["comspec_atak_native_map_show_cities", true],
    missionNamespace getVariable ["comspec_atak_native_map_show_villages", true],
    missionNamespace getVariable ["comspec_atak_native_map_show_locals", true],
    missionNamespace getVariable ["comspec_atak_native_map_show_sites", true]
];
if !(true in _show) exitWith {};
// Zoom : villes toujours, villages sous 0,25, lieux-dits et sites sous 0,08.
private _maxCat = [[0, 1] select (_scale < 0.25), 3] select (_scale < 0.08);

// Liste des lieux de la carte, faite une fois par carte : [position, texte, catégorie 0..3, couleur Arma].
private _all = missionNamespace getVariable ["COMSPEC_ATAK_PlaceList", []];
if ((_all param [0, ""]) isNotEqualTo worldName) then {
    private _cats = createHashMapFromArray [["NameCityCapital", 0], ["NameCity", 0], ["NameVillage", 1], ["NameLocal", 2], ["NameMarine", 2], ["Airport", 3]];
    private _mil = ["base", "camp", "military", "militaire", "armée", "armee", "caserne", "airfield", "aérodrome", "aerodrome", "airbase", "fob", "outpost", "checkpoint", "barracks", "army"];
    private _list = [];
    {
        private _t = text _x;
        if (_t isEqualTo "") then { continue };
        private _ty = type _x;
        private _cat = _cats getOrDefault [_ty, 2];
        if (_cat isEqualTo 2 && {private _lt = toLower _t; (_mil findIf { (_lt find _x) >= 0 }) >= 0}) then { _cat = 3; };
        private _col = (getArray (configFile >> "CfgLocationTypes" >> _ty >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
        if ((count _col) < 4) then { _col = [0, 0, 0, 1]; };
        _list pushBack [locationPosition _x, _t, _cat, _col];
    } forEach nearestLocations [[worldSize / 2, worldSize / 2], keys _cats, worldSize * 1.5];
    // Plus importants d'abord : ils passent devant les autres quand deux noms se chevauchent.
    _list = [0, 1, 2, 3] apply { private _c = _x; _list select { (_x select 2) isEqualTo _c } };
    _all = [worldName, (_list select 0) + (_list select 1) + (_list select 2) + (_list select 3)];
    missionNamespace setVariable ["COMSPEC_ATAK_PlaceList", _all];
};
private _places = _all select 1;
if ((count _places) isEqualTo 0) exitWith {};

(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _tl = _map ctrlMapScreenToWorld [_mx, _my];
private _br = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh];
private _wx0 = (_tl select 0) min (_br select 0);
private _wx1 = (_tl select 0) max (_br select 0);
private _wy0 = (_tl select 1) min (_br select 1);
private _wy1 = (_tl select 1) max (_br select 1);
private _mpx = (_wx1 - _wx0) / _mw;
private _mpy = (_wy1 - _wy0) / _mh;
if (_mpx <= 0 || {_mpy <= 0}) exitWith {};
private _style = toUpper (missionNamespace getVariable ["comspec_atak_native_map_place_style", "tactical"]);
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _fB = COMSPEC_ATAK_FontSet select 1;
private _fR = COMSPEC_ATAK_FontSet select 0;
private _k = _ts * _kt;
// Taille, police, couleur par catégorie (ville, village, lieu-dit, site).
private _sizes = switch (_style) do {
    case "QUIET": { [0.026, 0.022, 0.019, 0.019] };
    case "TAG": { [0.023, 0.021, 0.019, 0.019] };
    case "CLASSIC": { [0.034, 0.028, 0.025, 0.025] };
    default { [0.031, 0.025, 0.020, 0.020] };
} apply { _x * _k };
private _cols = [[0.98, 0.98, 0.97, 1], [0.93, 0.94, 0.91, 1], [0.86, 0.84, 0.77, 1], [0.98, 0.80, 0.45, 1]];
private _tagCols = [[0.95, 0.95, 0.95, 1], [0.70, 0.80, 0.75, 1], [0.80, 0.70, 0.50, 1], [0.95, 0.67, 0.20, 1]];
private _toW = { params ["_sx", "_sy"]; private _w = _map ctrlMapScreenToWorld [_sx, _sy]; [_w select 0, _w select 1, 0] };
private _placed = +(uiNamespace getVariable ["COMSPEC_ATAK_LblRects", []]);
private _hit = { params ["_r"]; (_placed findIf { (_r select 0) < (_x select 2) && {(_r select 2) > (_x select 0)} && {(_r select 1) < (_x select 3)} && {(_r select 3) > (_x select 1)} }) >= 0 };
private _n = 0;
{
    if (_n >= 60) then { break };
    _x params ["_pos", "_txt", "_cat", "_acol"];
    if (_cat > _maxCat) then { break };
    if !(_show select _cat) then { continue };
    _pos params ["_px", "_py"];
    if (_px < _wx0 || {_px > _wx1} || {_py < _wy0} || {_py > _wy1}) then { continue };
    private _sp = _map ctrlMapWorldToScreen _pos;
    _sp params ["_sx", "_sy"];
    private _fs = _sizes select _cat;
    private _font = [_fB, _fR] select (_style in ["QUIET", "CLASSIC"] || {_cat >= 2 && {_style isEqualTo "TACTICAL"}});
    private _t = [toUpper _txt, _txt] select (_style in ["QUIET", "CLASSIC"]);
    private _w = [_t, _font, _fs] call comspec_atak_native_fnc_mapTextW;
    private _tag = _style isEqualTo "TAG";
    private _dw = [0, _fs * 0.9 * pixelW / pixelH] select _tag;
    private _pad = [0, 4 * pixelW] select _tag;
    private _bw = _w + _dw + _pad * 2;
    private _bh = _fs * ([1.0, 1.35] select _tag);
    private _r = [_sx - _bw / 2, _sy - _bh / 2, _sx + _bw / 2, _sy + _bh / 2];
    if ([_r] call _hit) then { continue };
    _placed pushBack _r;
    switch (_style) do {
        case "TAG": {
            _r params ["_x0", "_y0", "_x1", "_y1"];
            _map drawRectangle [[_sx, _sy] call _toW, (_bw / 2) * _mpx, (_bh / 2) * _mpy, 0, [0.045, 0.055, 0.05, 0.8], _fill];
            private _d = _fs * 0.22;
            _map drawRectangle [[_x0 + _pad + _dw * 0.45, _sy] call _toW, _d * _mpy, _d * _mpy, 45, _tagCols select _cat, _fill];
            _map drawIcon [_none, [0.96, 0.95, 0.92, 1], [_x0 + _pad + _dw, _sy] call _toW, 0, 0, 0, _t, 0, _fs, _font, "right"];
        };
        case "QUIET": {
            _map drawIcon [_none, [0.93, 0.93, 0.90, 0.85], _pos, 0, 0, 0, _t, 2, _fs, _font, "center"];
        };
        case "CLASSIC": {
            _map drawIcon [_none, _acol, _pos, 0, 0, 0, _t, 0, _fs, _font, "center"];
        };
        default {
            _map drawIcon [_none, _cols select _cat, _pos, 0, 0, 0, _t, 2, _fs, _font, "center"];
        };
    };
    _n = _n + 1;
} forEach _places;
