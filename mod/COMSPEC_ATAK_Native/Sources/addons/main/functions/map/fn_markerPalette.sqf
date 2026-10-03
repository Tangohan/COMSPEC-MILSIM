/*
    Palette du mode MARQUEUR (au-dessus de la carte) :
    - camp : ENNEMI, AMI, NEUTRE, INCONNU ou TACTIQUE (repères d'ordre : objectif, danger, LZ…) ;
    - grille d'icônes du type (infanterie, blindé, artillerie, PC, médical…) ;
    - couleur (AUTO = couleur du camp) et taille ;
    - AUTRES : tous les marqueurs Arma et des mods, en liste ;
    - « éditer après la pose » pour saisir titre et description tout de suite.
    Params : [[x, y, w, h] zone disponible]
*/
params ["_rect"];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_hMax"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _pad = _l get "pad";
private _ratio = pixelH / pixelW;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
private _redraw = { [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
uiNamespace setVariable ["COMSPEC_ATAK_MarkerPick", {
    params ["_key", "_val"];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _s set [_key, _val];
    if (_key isEqualTo "markerAff") then { _s set ["markerType", ""]; };
    if (_key isEqualTo "markerLibCat") then { _s set ["markerType", ""]; };
    [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
([] call comspec_atak_native_fnc_markerPaletteData) params ["_affs", "_typesByAff"];
private _aff = _s getOrDefault ["markerAff", "o"];
private _types = _typesByAff getOrDefault [_aff, []];
private _type = _s getOrDefault ["markerType", ""];
if ((_types findIf { (_x select 0) isEqualTo _type }) < 0 && {!isClass (configFile >> "CfgMarkers" >> _type)}) then { _type = (_types param [0, ["mil_dot"]]) select 0; _s set ["markerType", _type]; };
private _affColor = (_affs select ((_affs findIf { (_x select 0) isEqualTo _aff }) max 0)) select 3;
// Couleur trop sombre (noir des repères tactiques) : icônes en blanc dans la palette pour rester lisibles.
private _tint = { params ["_c"]; if (((_c select 0) + (_c select 1) + (_c select 2)) < 0.6) then { [0.92, 0.92, 0.92, 1] } else { _c } };
private _rh = _fs * 1.55;
private _y = _y0;
private _bg = ["COMSPEC_RscMapPanel", [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, _hMax]] call _mk;

// 1. Camp
private _n = count _affs;
private _cw = _w / _n;
{
    _x params ["_key", "_label", "_cls", "_rgba"];
    private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _cw, _y, _cw - _pad / 3, _rh], _label] call _mk;
    _b ctrlSetFontHeight (_fs * 0.85);
    if (_key isEqualTo _aff) then { _b ctrlSetBackgroundColor [(_rgba select 0) * 0.85, (_rgba select 1) * 0.85, (_rgba select 2) * 0.85, 1]; _b ctrlSetTextColor [1, 1, 1, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerAff', '%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _key]];
} forEach _affs;
_y = _y + _rh + _pad;

// 2. Types (icônes). Onglet TOUS : choix de la catégorie et pages.
private _cell = _fs * 2.1;
if (_aff isEqualTo "lib") then {
    private _cats = [];
    { _cats pushBackUnique (_x select 2); } forEach _types;
    private _cat = _s getOrDefault ["markerLibCat", _cats param [0, ""]];
    if !(_cat in _cats) then { _cat = _cats param [0, ""]; };
    private _all = +_types;
    _types = _types select { (_x select 2) isEqualTo _cat };
    private _cc = ["COMSPEC_RscCombo", [_x0, _y, _w * 0.62, _rh]] call _mk;
    _cc ctrlSetFontHeight (_fs * 0.85);
    {
        private _dn = getText (configFile >> "CfgMarkerClasses" >> _x >> "displayName");
        private _c = _x;
        private _i = _cc lbAdd format ["%1 (%2)", [_dn, _x] select (_dn isEqualTo ""), { (_x select 2) isEqualTo _c } count _all];
        _cc lbSetData [_i, _x];
        if (_x isEqualTo _cat) then { _cc lbSetCurSel _i; };
    } forEach _cats;
    _cc ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["markerLibPage", 0]; ['markerLibCat', _c lbData _i] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick'); }];
    // Pages : autant d'icônes que la palette en montre sur 4 lignes.
    private _perRow = floor (_w / (_cell / _ratio + _pad / 2)) max 1;
    private _per = _perRow * 4;
    private _pages = ceil ((count _types) / _per) max 1;
    private _page = (_s getOrDefault ["markerLibPage", 0]) min (_pages - 1);
    _types = _types select [_page * _per, _per];
    private _pw = (_w * 0.38 - _pad) / 3;
    private _px = _x0 + _w * 0.62 + _pad;
    private _prev = ["COMSPEC_RscButton", [_px, _y, _pw, _rh], "‹"] call _mk;
    _prev ctrlAddEventHandler ["ButtonClick", compile format ["['markerLibPage', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", (_page - 1) max 0]];
    private _pt = ["COMSPEC_RscTextCenter", [_px + _pw, _y, _pw, _rh], format ["%1/%2", _page + 1, _pages]] call _mk;
    _pt ctrlSetFontHeight (_fs * 0.85);
    private _next = ["COMSPEC_RscButton", [_px + 2 * _pw, _y, _pw, _rh], "›"] call _mk;
    _next ctrlAddEventHandler ["ButtonClick", compile format ["['markerLibPage', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", (_page + 1) min (_pages - 1)]];
    _y = _y + _rh + _pad / 2;
};
private _cellW = _cell / _ratio;
private _cols = floor (_w / (_cellW + _pad / 2)) max 1;
private _colorKey = _s getOrDefault ["markerColor", "AUTO"];
private _iconRgba = if (_colorKey isEqualTo "AUTO") then { _affColor } else {
    private _c = (getArray (configFile >> "CfgMarkerColors" >> _colorKey >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
    [_affColor, _c] select ((count _c) isEqualTo 4)
};
{
    _x params ["_cls", "_label"];
    private _cx = _x0 + (_forEachIndex mod _cols) * (_cellW + _pad / 2);
    private _cy = _y + (floor (_forEachIndex / _cols)) * (_cell + _pad / 2);
    private _sel = _cls isEqualTo _type;
    private _cb = ["COMSPEC_RscText", [_cx, _cy, _cellW, _cell]] call _mk;
    _cb ctrlSetBackgroundColor ([[0.08, 0.10, 0.09, 0.9], [0.36, 0.78, 0.42, 0.55]] select _sel);
    private _ic = ["COMSPEC_RscIcon", [_cx + _cellW * 0.12, _cy + _cell * 0.12, _cellW * 0.76, _cell * 0.76], getText (configFile >> "CfgMarkers" >> _cls >> "icon")] call _mk;
    _ic ctrlSetTextColor ([_iconRgba] call _tint);
    private _b = ["COMSPEC_RscButtonOverlay", [_cx, _cy, _cellW, _cell]] call _mk;
    _b ctrlSetTooltip _label;
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerType', '%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _cls]];
} forEach _types;
_y = _y + (ceil ((count _types) / _cols)) * (_cell + _pad / 2) + _pad / 2;
private _typeLabel = ((_types select ((_types findIf { (_x select 0) isEqualTo _type }) max 0)) param [1, getText (configFile >> "CfgMarkers" >> _type >> "name")]);
private _lab = ["COMSPEC_RscText", [_x0, _y, _w, _rh], format ["Choisi : %1", _typeLabel]] call _mk;
_lab ctrlSetFontHeight (_fs * 0.9);
_y = _y + _rh;

// 3. Couleur
private _swatches = [["AUTO", "AUTO"], ["ROUGE", "ColorRed"], ["BLEU", "ColorBlue"], ["VERT", "ColorGreen"], ["JAUNE", "ColorYellow"], ["ORANGE", "ColorOrange"], ["NOIR", "ColorBlack"], ["BLANC", "ColorWhite"]];
private _sw = _w / (count _swatches);
{
    _x params ["_t", "_cls"];
    private _rgba = if (_cls isEqualTo "AUTO") then { _affColor } else { (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } } };
    private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _sw, _y, _sw - _pad / 3, _rh], ["", "●"] select (_cls isEqualTo _colorKey)] call _mk;
    if ((count _rgba) isEqualTo 4) then { _b ctrlSetBackgroundColor _rgba; };
    if (_cls isEqualTo "AUTO") then { _b ctrlSetText (["AUTO", "● AUTO"] select (_colorKey isEqualTo "AUTO")); _b ctrlSetFontHeight (_fs * 0.8); };
    _b ctrlSetTextColor ([[1, 1, 1, 1], [0, 0, 0, 1]] select (_cls in ["ColorWhite", "ColorYellow"]));
    _b ctrlSetTooltip _t;
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerColor', '%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _cls]];
} forEach _swatches;
_y = _y + _rh + _pad / 2;

// 4. Taille, édition après pose, autres marqueurs
private _size = _s getOrDefault ["markerSize", 1];
private _opts = [["S", 0.7], ["M", 1], ["L", 1.4], ["XL", 2]];
private _ow = _w * 0.07;
{
    _x params ["_t", "_v"];
    private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _ow, _y, _ow - _pad / 3, _rh], _t] call _mk;
    _b ctrlSetFontHeight (_fs * 0.85);
    if (_v isEqualTo _size) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['markerSize', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick');", _v]];
} forEach _opts;
private _edit = _s getOrDefault ["markerEditAfter", false];
private _eb = ["COMSPEC_RscButton", [_x0 + 4 * _ow + _pad, _y, _w * 0.3, _rh], ["○ ÉDITER APRÈS", "● ÉDITER APRÈS"] select _edit] call _mk;
_eb ctrlSetFontHeight (_fs * 0.85);
if (_edit) then { _eb ctrlSetTextColor [0.36, 0.78, 0.42, 1]; };
_eb ctrlSetTooltip "Ouvrir l'éditeur (titre, description) juste après la pose";
_eb ctrlAddEventHandler ["ButtonClick", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; ['markerEditAfter', !(_s getOrDefault ["markerEditAfter", false])] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick'); }];
// Tous les marqueurs chargés (Arma et mods) en liste.
private _cx = _x0 + 4 * _ow + _w * 0.3 + _pad * 2;
private _combo = ["COMSPEC_RscCombo", [_cx, _y, (_x0 + _w) - _cx, _rh]] call _mk;
_combo ctrlSetFontHeight (_fs * 0.85);
_combo lbAdd "Autres marqueurs…";
_combo lbSetData [0, ""];
private _sel = 0;
{
    _x params ["_name", "_cls", "_icon"];
    private _i = _combo lbAdd _name;
    _combo lbSetData [_i, _cls];
    _combo lbSetPicture [_i, _icon];
    if (_cls isEqualTo _type && {(_types findIf { (_x select 0) isEqualTo _cls }) < 0}) then { _sel = _i; };
} forEach (([] call comspec_atak_native_fnc_markerCatalog) select 0);
_combo lbSetCurSel _sel;
_combo ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; private _d = _c lbData _i; if (_d isNotEqualTo "") then { ['markerType', _d] call (uiNamespace getVariable 'COMSPEC_ATAK_MarkerPick'); }; }];
_y = _y + _rh + _pad;
_bg ctrlSetPosition [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, (_y - _y0) + _pad];
_bg ctrlCommit 0;
_y
