/*
    Carte façon ATAK : la carte occupe l'écran, les informations flottent par-dessus.
    - Haut gauche : boussole (aiguille = cap du joueur).
    - Bas gauche : panneau curseur (grille, altitude, distance, azimut) et bouton « Outils carte ».
    - Bas droite : carte « moi » (indicatif, cap, grille).
    - Droite (en main) : centrer, suivre, zoom, marqueur, libellés.
    - Bas (réglage « Barre de données sous la carte ») : vitesse, altitude, cap, grille, heure.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _mw = _bw - (_l get "inspW");
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _ratio = pixelH / pixelW;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _l get "interactive";
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _ov = createHashMap;
private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };

// Barre de données (réglage roleplay du téléphone)
private _dataBar = [] call comspec_atak_native_fnc_dataBarEnabled;
private _barH = [0, _fs * 1.5] select _dataBar;
private _mh = _bh - _barH;
if (_dataBar) then {
    private _bar = ["COMSPEC_RscMapPanel", [_bx, _by + _mh, _mw, _barH]] call _mk;
    _ov set ["databar", _bar];
};

// Boussole
if (profileNamespace getVariable ["COMSPEC_ATAK_Compass", true]) then {
    private _ch = (_mh * 0.24) min (_font * 3.6);
    private _cw = _ch / _ratio;
    private _ring = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_ring.paa"] call _mk;
    _ring ctrlSetTextColor [1, 1, 1, 1];
    private _needle = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_needle.paa"] call _mk;
    _needle ctrlSetTextColor [1, 1, 1, 1];
    private _hd = ["COMSPEC_RscTextCenter", [_bx + _pad, _by + _pad + _ch, _cw, _fs * 1.2]] call _mk;
    _hd ctrlSetFontHeight _fs;
    _ov set ["needle", _needle];
    _ov set ["heading", _hd];
};

// Carte « moi » (bas droite)
private _panW = (_mw * 0.34) min (_font * 9 / _ratio);
private _panH = _fs * 3.4;
private _me = ["COMSPEC_RscMapPanel", [_bx + _mw - _panW - _pad, _by + _mh - _panH - _pad, _panW, _panH]] call _mk;
_ov set ["me", _me];

if (_interactive) then {
    // Panneau curseur (bas gauche) + bouton outils
    private _cur = ["COMSPEC_RscMapPanel", [_bx + _pad, _by + _mh - _panH - _pad, _panW, _panH]] call _mk;
    _ov set ["cursor", _cur];
    private _th = _fs * 1.9;
    private _tw = _th / _ratio;
    private _tb = ["COMSPEC_RscIconButton", [_bx + _pad, _by + _mh - _panH - _pad * 2 - _th, _tw, _th], _dir + "map_tools.paa"] call _mk;
    _tb ctrlSetTooltip "Outils carte";
    _tb ctrlAddEventHandler ["ButtonClick", {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["mapToolsOpen", !(_s getOrDefault ["mapToolsOpen", false])];
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }];
    _ov set ["tool_menu", _tb];

    // Menu des outils carte, au-dessus du bouton
    if (_s getOrDefault ["mapToolsOpen", false]) then {
        private _items = [
            ["LINE", "map_measure", "Tracer un trait"],
            ["DRAW", "map_labels", "Dessin libre"],
            ["DISTANCE", "map_distance", "Distance"],
            ["MEASURE", "map_measure", "Mesure A-B"],
            ["HOUSES", "map_house", "Marquer bâtiments"],
            ["HEIGHT", "map_height", "Hauteur"],
            ["COMPASS", "map_compass", "Boussole"],
            ["GRID", "map_grid", format ["Grille %1 chiffres", profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]]],
            ["FLAT", "map_flat", "Terrain plat"],
            ["LOS", "map_los", "Ligne de vue"],
            ["CLEAR", "map_clear", "Tout effacer"]
        ];
        private _rh = _fs * 1.55;
        private _mwid = _panW;
        private _my0 = _by + _mh - _panH - _pad * 3 - _th - _rh * (count _items);
        private _bg = ["COMSPEC_RscMapPanel", [_bx + _pad, _my0, _mwid, _rh * (count _items)]] call _mk;
        private _mode = _s getOrDefault ["mapMode", "SELECT"];
        {
            _x params ["_key", "_icon", "_label"];
            private _y0 = _my0 + _forEachIndex * _rh;
            private _active = switch (_key) do {
                case "DISTANCE": { _s getOrDefault ["mapDistance", true] };
                case "COMPASS": { profileNamespace getVariable ["COMSPEC_ATAK_Compass", true] };
                case "GRID": { (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) > 6 };
                default { _mode isEqualTo _key };
            };
            private _ic = ["COMSPEC_RscIcon", [_bx + _pad * 1.5, _y0 + _rh * 0.15, _rh * 0.7 / _ratio, _rh * 0.7], _dir + _icon + ".paa"] call _mk;
            _ic ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _active);
            private _t = ["COMSPEC_RscText", [_bx + _pad * 2.5 + _rh * 0.7 / _ratio, _y0, _mwid - _pad * 2 - _rh * 0.7 / _ratio, _rh], _label] call _mk;
            _t ctrlSetFontHeight _fs;
            _t ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _active);
            private _b = ["COMSPEC_RscButtonOverlay", [_bx + _pad, _y0, _mwid, _rh]] call _mk;
            _b setVariable ["key", _key];
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "key"] call comspec_atak_native_fnc_mapToolMenu; }];
        } forEach _items;
    };

    // Barre d'outils à droite
    private _ih = ((_mh - 2 * _pad) / 7) min (_font * 2.1);
    private _iw = _ih / _ratio;
    private _tx = _bx + _mw - _iw - _pad;
    private _tools = [
        ["center", "map_center", "Centrer sur moi", { [player, 0.05] call comspec_atak_native_fnc_mapCenter; }],
        ["follow", "map_follow", "Suivre ma position", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["mapFollow", !(_s getOrDefault ["mapFollow", false])]; if (_s get "mapFollow") then { [player] call comspec_atak_native_fnc_mapCenter; }; [] call comspec_atak_native_fnc_mapOverlayUpdate; }],
        ["zoomin", "map_zoomin", "Zoom avant", { [0.5] call comspec_atak_native_fnc_mapZoom; }],
        ["zoomout", "map_zoomout", "Zoom arrière", { [2] call comspec_atak_native_fnc_mapZoom; }],
        ["MARKER", "map_marker", "Poser un marqueur (clic sur la carte)", { ["MARKER"] call comspec_atak_native_fnc_mapToolMenu; }],
        ["labels", "map_labels", "Afficher / masquer les indicatifs", { profileNamespace setVariable ["COMSPEC_ATAK_Labels", !(profileNamespace getVariable ["COMSPEC_ATAK_Labels", true])]; [] call comspec_atak_native_fnc_mapOverlayUpdate; }]
    ];
    private _ty0 = _by + _pad + _ih * 0.5;
    ["COMSPEC_RscMapPanel", [_tx - _pad / 2, _ty0 - _pad / 2, _iw + _pad, _ih * (count _tools) + _pad]] call _mk;
    {
        _x params ["_key", "_icon", "_tip", "_code"];
        private _b = ["COMSPEC_RscIconButton", [_tx, _ty0 + _forEachIndex * _ih, _iw, _ih * 0.92], _dir + _icon + ".paa"] call _mk;
        _b ctrlSetTooltip _tip;
        _b ctrlAddEventHandler ["ButtonClick", _code];
        _ov set ["tool_" + _key, _b];
    } forEach _tools;

    // Consigne de l'outil actif
    private _mode = _s getOrDefault ["mapMode", "SELECT"];
    private _hintText = switch (_mode) do {
        case "MARKER": { "MARQUEUR : clic pour poser" };
        case "MEASURE": { "MESURE : clic A puis B" };
        case "HOUSES": { "BÂTIMENTS : clic pour numéroter autour" };
        case "HEIGHT": { "HAUTEUR : clic pour relever l'altitude" };
        case "FLAT": { "TERRAIN PLAT : clic pour chercher autour" };
        case "LOS": { "LIGNE DE VUE : clic sur la cible" };
        case "LINE": { "TRAIT : clic A puis clic B" };
        case "DRAW": { "DESSIN : maintenir le clic gauche" };
        default { "" };
    };
    if (_hintText isNotEqualTo "") then {
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        private _hint = ["COMSPEC_RscChip", [_hx, _by + _pad, (_tx - _hx - _pad) max 0, _fs * 1.5], _hintText + " · clic droit : quitter"] call _mk;
        _hint ctrlSetFontHeight _fs;
    };
    if (_mode in ["LINE", "DRAW"]) then {
        private _cur = profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"];
        private _cols = [["ColorRed", [0.9, 0.1, 0.1, 1]], ["ColorBlue", [0.1, 0.3, 0.9, 1]], ["ColorGreen", [0.1, 0.7, 0.2, 1]], ["ColorYellow", [0.95, 0.85, 0.1, 1]], ["ColorOrange", [0.95, 0.5, 0.1, 1]], ["ColorBlack", [0.05, 0.05, 0.05, 1]], ["ColorWhite", [1, 1, 1, 1]]];
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        private _cw = _fs * 1.6 / _ratio;
        {
            _x params ["_cls", "_rgb"];
            private _c = ["COMSPEC_RscButton", [_hx + _forEachIndex * (_cw + _pad / 2), _by + _pad * 1.5 + _fs * 1.5, _cw, _fs * 1.6], ["", "●"] select (_cls isEqualTo _cur)] call _mk;
            _c ctrlSetBackgroundColor _rgb;
            _c ctrlSetTextColor ([[1, 1, 1, 1], [0, 0, 0, 1]] select (_cls in ["ColorWhite", "ColorYellow"]));
            _c setVariable ["cls", _cls];
            _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; profileNamespace setVariable ["COMSPEC_ATAK_DrawColor", _c getVariable "cls"]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
        } forEach _cols;
    };
    if (_mode isEqualTo "MARKER") then {
        private _cur = _s getOrDefault ["markerKind", "ENI"];
        private _kinds = ["ENI", "AMI", "OBJ", "DNG", "PT"];
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        private _cw = ((_tx - _hx - _pad) / (count _kinds)) min (_font * 3.4 / _ratio);
        {
            private _c = ["COMSPEC_RscButton", [_hx + _forEachIndex * (_cw + _pad / 2), _by + _pad * 1.5 + _fs * 1.5, _cw, _fs * 1.6], _x] call _mk;
            _c ctrlSetFontHeight _fs;
            if (_x isEqualTo _cur) then { _c ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9]; _c ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
            _c setVariable ["kind", _x];
            _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["markerKind", _c getVariable "kind"]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
        } forEach _kinds;
    };
};
// Marqueur sélectionné : titre, description et actions (en main).
private _sel = _s getOrDefault ["selectedMarker", ""];
if (_interactive && {_sel isNotEqualTo ""} && {(markerShape _sel) isNotEqualTo ""} && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
    private _note = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap]) getOrDefault [_sel, ["", ""]];
    private _pw = (_mw * 0.5) max (_font * 10 / _ratio) min (_mw - 2 * _pad);
    private _px = _bx + (_mw - _pw) / 2;
    private _ph = _fs * 4.6;
    private _py = _by + _mh - _ph - _pad;
    private _info = ["COMSPEC_RscMapPanel", [_px, _py, _pw, _ph]] call _mk;
    _info ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' color='#5cc76b'>%1</t>  <t color='#8a9a93' size='0.85'>%2</t><br/><t size='0.85'>%3</t>",
        [markerText _sel, "(sans titre)"] select ((markerText _sel) isEqualTo ""), [getMarkerPos _sel] call comspec_atak_native_fnc_gridRef,
        [_note select 0, "Double clic : modifier · Suppr : effacer"] select ((_note select 0) isEqualTo "")];
    private _own = (_sel find "_USER_DEFINED") isEqualTo 0;
    if (_own) then {
        private _bw2 = (_pw - _pad * 3) / 2;
        private _b1 = ["COMSPEC_RscButton", [_px + _pad, _py + _ph - _fs * 1.6 - _pad / 2, _bw2, _fs * 1.5], "MODIFIER"] call _mk;
        _b1 ctrlSetFontHeight _fs;
        _b1 ctrlAddEventHandler ["ButtonClick", { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]] call comspec_atak_native_fnc_markerEditOpen; }];
        private _b2 = ["COMSPEC_RscButton", [_px + _pad * 2 + _bw2, _py + _ph - _fs * 1.6 - _pad / 2, _bw2, _fs * 1.5], "SUPPRIMER"] call _mk;
        _b2 ctrlSetFontHeight _fs;
        _b2 ctrlAddEventHandler ["ButtonClick", { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]] call comspec_atak_native_fnc_markerDelete; }];
    };
};

// Éditeur de marqueur
if (_interactive && {(count (_s getOrDefault ["markerEdit", createHashMap])) > 0}) then {
    private _ew = [_mw * 0.42, _mw] select (_l get "mini");
    [[_bx + _mw - _ew, _by, _ew, _bh]] call comspec_atak_native_fnc_markerEditor;
};

uiNamespace setVariable ["COMSPEC_ATAK_MapOverlay", _ov];

if !(_s getOrDefault ["mapCentered", false]) then {
    _s set ["mapCentered", true];
    [player, 0.08] call comspec_atak_native_fnc_mapCenter;
};
[] call comspec_atak_native_fnc_mapOverlayUpdate;
if !(_l get "mini") then { [] call comspec_atak_native_fnc_inspectorUpdate; };
true
