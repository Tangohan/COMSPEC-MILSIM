/*
    Noms des lieux (villes, villages, lieux-dits, sites) sur la carte du téléphone : une seule source de noms. La carte Arma
    n'en écrit plus aucun (COMSPEC_RscMapAtak : densité de noms nulle, couleur transparente, taille minuscule ; avant, le
    moteur écrivait encore certains noms à côté des nôtres, « GRAVIA » doublé de « Gravia »). Nos noms suivent la taille en
    pixels, le fond et l'anti-chevauchement de toutes les étiquettes (fn_mapLbl) et se masquent par catégorie (CALQUES,
    Réglages > Carte ou menu CBA « ATAK · Carte ») :
      villes      NameCityCapital, NameCity ;
      villages    NameVillage ;
      lieux-dits  NameLocal, NameMarine (baies, lagunes) ;
      sites       Airport, et lieux-dits au nom militaire (base, camp, caserne, armée, aérodrome...).
    Style (réglage CBA comspec_atak_native_map_place_style) :
      TACTICAL  capitales grasses détourées, taille selon le type (par défaut) ;
      TAG       plaque sombre, losange à la couleur du type ;
      QUIET     petit texte clair détouré, casse d'origine ;
      CLASSIC   couleur et casse de la carte Arma d'avant ;
      SMALL     petites capitales claires en monospace au-dessus d'un point turquoise (TACTICAL prend ce rendu sur le fond ARDOISE).
    Niveau de détail (fn_mapLbl) : villes seulement en vue lointaine, villages en vue moyenne, lieux-dits et sites en vue
    rapprochée. Les moins prioritaires des étiquettes : un nom qui toucherait une unité, un repère, une autre étiquette, le
    bandeau ou le bord n'est pas écrit (centré sur le lieu, sinon juste dessous ou dessus) ; noms coupés proprement au-delà
    de 28 caractères ; 60 au plus.
    Params : [carte, taille des textes, facteur écran, échelle de la carte]
*/
params ["_map", "_ts", "_kt", "_scale"];
private _LF = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
if !(_LF getOrDefault ["lay", false]) exitWith {};
private _show = [
    missionNamespace getVariable ["comspec_atak_native_map_show_cities", true],
    missionNamespace getVariable ["comspec_atak_native_map_show_villages", true],
    missionNamespace getVariable ["comspec_atak_native_map_show_locals", true],
    missionNamespace getVariable ["comspec_atak_native_map_show_sites", true]
];
if !(true in _show) exitWith {};
private _lod = _LF get "lod";
private _maxCat = [0, 1, 3] select _lod;

// Liste des lieux de la carte, faite une fois par carte : [position, texte, catégorie 0..3, couleur Arma].
private _all = missionNamespace getVariable ["COMSPEC_ATAK_PlaceList", []];
private _wk = worldName + "|2";
if ((_all param [0, ""]) isNotEqualTo _wk) then {
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
        _list pushBack [locationPosition _x, ["cut", [_t, 28]] call comspec_atak_native_fnc_mapLbl, _cat, _col];
    } forEach nearestLocations [[worldSize / 2, worldSize / 2], keys _cats, worldSize * 1.5];
    // Plus importants d'abord : ils passent devant les autres quand deux noms se chevauchent.
    _list = [0, 1, 2, 3] apply { private _c = _x; _list select { (_x select 2) isEqualTo _c } };
    _all = [_wk, (_list select 0) + (_list select 1) + (_list select 2) + (_list select 3)];
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
private _style = toUpper (missionNamespace getVariable ["comspec_atak_native_map_place_style", "tactical"]);
private _palP = [] call comspec_atak_native_fnc_mapColors;
if (_style isEqualTo "TACTICAL" && {_palP get "slate"}) then { _style = "SMALL"; };
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _fB = COMSPEC_ATAK_FontSet select 1;
private _fR = COMSPEC_ATAK_FontSet select 0;
// Taille en pixels réels par catégorie (fn_mapLbl : ville, village, lieu-dit, site).
private _lfs = _LF get "fs";
private _sizes = ["p0", "p1", "p2", "p3"] apply { _lfs get _x };
private _cols = [[0.98, 0.98, 0.97, 1], [0.93, 0.94, 0.91, 1], [0.86, 0.84, 0.77, 1], [0.98, 0.80, 0.45, 1]];
private _tagCols = [[0.95, 0.95, 0.95, 1], [0.70, 0.80, 0.75, 1], [0.80, 0.70, 0.50, 1], [0.95, 0.67, 0.20, 1]];
private _tagBg = _palP get "lblBg";
// Fond des noms (fn_mapPrefs « nameBg ») ; fond clair : texte sombre sans contour.
private _nbg = _LF get "bg";
private _nbgLight = _LF get "light";
private _nbgTxt = [0.04, 0.05, 0.05, 1];
private _smallCols = [[0.80, 0.94, 0.90, 1], [0.72, 0.88, 0.84, 1], [0.62, 0.78, 0.75, 1], [0.98, 0.74, 0.42, 1]];
private _pw = 4 * pixelW;
private _ph = 2 * pixelH;
private _n = 0;
{
    if (_n >= 60) then { break };
    _x params ["_pos", "_txt", "_cat", "_acol"];
    if (_cat > _maxCat) then { break };
    if !(_show select _cat) then { continue };
    _pos params ["_px", "_py"];
    if (_px < _wx0 || {_px > _wx1} || {_py < _wy0} || {_py > _wy1}) then { continue };
    private _fs = _sizes select _cat;
    private _small = _style isEqualTo "SMALL";
    private _tag = _style isEqualTo "TAG";
    private _font = [[_fB, _fR] select (_style in ["QUIET", "CLASSIC"]), "EtelkaMonospacePro"] select _small;
    private _t = [toUpper _txt, _txt] select (_style in ["QUIET", "CLASSIC"]);
    private _w = ["w", [_t, _font, _fs]] call comspec_atak_native_fnc_mapLbl;
    private _bg = (!_tag) && {_nbg isNotEqualTo []};
    private _pr = [];
    private _bw = _w + ([0, _pw * 2] select _bg);
    private _bh = _fs + ([0, _ph * 2] select _bg);
    private _slots = [8, 2, 3];
    private _hpx = 0;
    switch (_style) do {
        case "TAG": {
            private _dw = _fs * 0.9 * pixelW / pixelH;
            _bw = _w + _dw + _pw * 2;
            _bh = _fs * 1.3;
            _pr pushBack ["R", 0, 0, _bw, _bh, _tagBg, _fill];
            _pr pushBack ["Q", _pw + _dw * 0.45, _bh / 2, _fs * 0.22, 45, _tagCols select _cat];
            _pr pushBack ["T", _pw + _dw, _bh / 2, _t, [0.96, 0.95, 0.92, 1], _fs, _font, 0, "right"];
        };
        case "SMALL": {
            // Petites capitales au-dessus d'un point turquoise posé sur le lieu.
            private _ds = 9 * (_fs / pixelH) / 15;
            _hpx = _ds / 2;
            _slots = [9];
            if (_bg) then { _pr pushBack ["R", 0, 0, _bw, _bh, _nbg, _fill]; };
            _pr pushBack ["E", _bw / 2, _bh + _hpx * pixelH, _hpx * pixelH, [0.30, 0.85, 0.78, 1]];
            _pr pushBack ["T", _bw / 2, _bh / 2, _t, [_smallCols select _cat, _nbgTxt] select _nbgLight, _fs, _font, [1, 0] select _bg, "center"];
        };
        default {
            if (_bg) then { _pr pushBack ["R", 0, 0, _bw, _bh, _nbg, _fill]; };
            private _c = switch (_style) do {
                case "QUIET": { [[0.93, 0.93, 0.90, 0.85], _nbgTxt] select _nbgLight };
                case "CLASSIC": { _acol };
                default { [_cols select _cat, _nbgTxt] select _nbgLight };
            };
            _pr pushBack ["T", _bw / 2, _bh / 2, _t, _c, _fs, _font, [[2, 0] select _nbgLight, 0] select (_style isEqualTo "CLASSIC"), "center"];
        };
    };
    ["add", [6 + _cat * 0.1, [_px, _py], "", _hpx, _hpx, [_bw, _bh, _pr], false, _slots]] call comspec_atak_native_fnc_mapLbl;
    _n = _n + 1;
} forEach _places;
