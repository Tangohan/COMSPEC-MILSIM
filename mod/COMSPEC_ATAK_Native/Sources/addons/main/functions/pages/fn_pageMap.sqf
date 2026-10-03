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
// (masquée tant que le menu Outils est ouvert : le menu prend toute la hauteur à gauche)
if (((["COMSPEC_ATAK_Compass", true, "native_compass"] call comspec_atak_native_fnc_pref) select 0) && {!(_interactive && {_s getOrDefault ["mapToolsOpen", false]})}) then {
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
// Texte fixé à la petite police (sinon le texte structuré prend la taille par défaut, trop grosse en mini).
private _mini = _l get "mini";
private _panFs = _fs * ([0.85, 0.7] select _mini);
private _panW = ((_mw * 0.34) min (_font * 9 / _ratio)) * ([1, 0.75] select _mini);
private _panH = _panFs * ([3.6, 2.5] select _mini);
private _me = ["COMSPEC_RscMapPanel", [_bx + _mw - _panW - _pad, _by + _mh - _panH - _pad, _panW, _panH]] call _mk;
_me ctrlSetFontHeight _panFs;
_ov set ["me", _me];

if (_interactive) then {
    // Panneau curseur (bas gauche) + bouton outils
    private _cur = ["COMSPEC_RscMapPanel", [_bx + _pad, _by + _mh - _panH - _pad, _panW, _panH]] call _mk;
    _cur ctrlSetFontHeight _panFs;
    _ov set ["cursor", _cur];
    // Bouton « OUTILS » bien visible (icône + libellé) : ouvre le menu des outils carte.
    private _th = _fs * 1.9;
    private _tw = _th / _ratio;
    private _toolsOpen = _s getOrDefault ["mapToolsOpen", false];
    private _tby = _by + _mh - _panH - _pad * 2 - _th;
    private _tbw = (_tw + _fs * 4 / _ratio) min _panW;
    private _tbg = ["COMSPEC_RscMapPanel", [_bx + _pad, _tby, _tbw, _th]] call _mk;
    if (_toolsOpen) then { _tbg ctrlSetBackgroundColor [0.10, 0.20, 0.12, 0.92]; };
    private _tic = ["COMSPEC_RscIcon", [_bx + _pad, _tby, _tw, _th], _dir + "map_tools.paa"] call _mk;
    _tic ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _toolsOpen);
    private _tlb = ["COMSPEC_RscText", [_bx + _pad + _tw, _tby, _tbw - _tw, _th], "OUTILS"] call _mk;
    _tlb ctrlSetFont "RobotoCondensedBold";
    _tlb ctrlSetFontHeight _fs;
    _tlb ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _toolsOpen);
    private _tb = ["COMSPEC_RscButtonOverlay", [_bx + _pad, _tby, _tbw, _th]] call _mk;
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
            ["ROUTE", "map_route", "GPS : itinéraire"],
            ["WP", "app_waypoints", "Points de passage"],
            ["LINE", "map_measure", "Tracer un trait"],
            ["DRAW", "map_labels", "Dessin libre"],
            ["DISTANCE", "map_distance", "Distance"],
            ["MEASURE", "map_measure", "Mesure A-B"],
            ["HOUSES", "map_house", "Marquer bâtiments"],
            ["HEIGHT", "map_height", "Hauteur"],
            ["COMPASS", "map_compass", "Boussole"],
            ["GRID", "map_grid", format ["Grille %1 chiffres", profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]]],
            ["FLAT", "map_flat", "Terrain plat"],
            ["ZONES", "map_grid", "Zones tactiques"],
            ["SIGINT", "map_los", "Goniométrie (émetteurs)"],
            ["LOS", "map_los", "Ligne de vue"],
            ["CLEAR", "map_clear", "Tout effacer"]
        ];
        private _rh = _fs * 1.55;
        private _mwid = _panW;
        // Autant de lignes que la hauteur le permet, puis une seconde colonne.
        private _bottom = _tby - _pad;
        private _perCol = (floor ((_bottom - _by - _pad) / _rh)) max 1;
        private _cols = ceil ((count _items) / _perCol);
        private _rows = ceil ((count _items) / _cols);
        private _my0 = _bottom - _rh * _rows;
        private _bg = ["COMSPEC_RscMapPanel", [_bx + _pad, _my0 - _pad / 2, _mwid * _cols + _pad / 2 * (_cols - 1), _rh * _rows + _pad / 2]] call _mk;
        _bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.97];
        private _mode = _s getOrDefault ["mapMode", "SELECT"];
        {
            _x params ["_key", "_icon", "_label"];
            private _col = floor (_forEachIndex / _rows);
            private _y0 = _my0 + (_forEachIndex mod _rows) * _rh;
            private _bx = _bx + _col * (_mwid + _pad / 2);
            private _active = switch (_key) do {
                case "DISTANCE": { _s getOrDefault ["mapDistance", true] };
                case "COMPASS": { (["COMSPEC_ATAK_Compass", true, "native_compass"] call comspec_atak_native_fnc_pref) select 0 };
                case "GRID": { (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) > 6 };
                case "ZONES": { profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true] };
                case "SIGINT": { profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true] };
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
        ["labels", "map_labels", "Afficher / masquer les indicatifs", { if ((["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 1) exitWith { ["INFO", "Réglage imposé par votre communauté", 3, 20] call comspec_atak_native_fnc_notify; }; profileNamespace setVariable ["COMSPEC_ATAK_Labels", !(profileNamespace getVariable ["COMSPEC_ATAK_Labels", true])]; [] call comspec_atak_native_fnc_mapOverlayUpdate; }]
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

    // Panneau SITUATION : bouton pour le replier (en plein écran) ou le rouvrir.
    // Replié (par défaut) : onglet icône + libellé + nombre de contacts sous la barre d'outils, même style
    // que OUTILS. Ouvert : bandeau « REPLIER » en tête du panneau.
    if !(_l get "mini") then {
        private _open = missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false];
        private _sh = _fs * 1.9;
        private _si = _sh / _ratio;
        private _n = count ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
        // Replié : simple icône œil, comme les outils ; ouvert : bandeau REPLIER en tête du panneau.
        private _sw = if (_open) then { (_l get "inspW") - _pad } else { _iw };
        private _sx = if (_open) then { _bx + _bw - (_l get "inspW") + _pad / 2 } else { _tx + _iw + _pad / 2 - _sw };
        private _sy = if (_open) then { _by + _pad / 2 } else { _ty0 + _ih * (count _tools) + _pad * 1.5 };
        if (!_open) then { _sh = _ih; _si = _iw; };
        private _sbg = ["COMSPEC_RscMapPanel", [_sx, _sy, _sw, _sh]] call _mk;
        if (_open) then { _sbg ctrlSetBackgroundColor [0.10, 0.20, 0.12, 0.92]; };
        private _sic = ["COMSPEC_RscIcon", [_sx + (_si * 0.15), _sy + (_sh * 0.15), _si * 0.7, _sh * 0.7], _dir + "app_intel.paa"] call _mk;
        _sic ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _open);
        if (_open) then {
            private _slb = ["COMSPEC_RscStructuredText", [_sx + _si, _sy + _sh * 0.2, _sw - _si, _sh * 0.8]] call _mk;
            _slb ctrlSetStructuredText parseText "<t font='RobotoCondensedBold' size='0.85' color='#5cc76b'>SITUATION</t><t size='0.85' color='#8a9a93' align='right'>REPLIER</t>";
        };
        private _sb = ["COMSPEC_RscButtonOverlay", [_sx, _sy, _sw, _sh]] call _mk;
        _sb ctrlSetTooltip (["Afficher le panneau SITUATION (unités, contacts, sélection)", "Replier le panneau SITUATION"] select _open);
        _sb ctrlAddEventHandler ["ButtonClick", { [] call comspec_atak_native_fnc_inspToggle; }];
        _ov set ["insp_toggle", _sb];
        // Calques : boutons à bascule en bas du panneau SITUATION ouvert (deux colonnes).
        if (_open) then {
            uiNamespace setVariable ["COMSPEC_ATAK_LayerToggle", {
                params ["_ns", "_var", "_def"];
                private _n = [profileNamespace, missionNamespace] select (_ns isEqualTo "M");
                _n setVariable [_var, !(_n getVariable [_var, _def])];
                if (_ns isEqualTo "P") then { saveProfileNamespace; };
                // Les filtres d'unités (alliés, ennemis) sont appliqués à la collecte : on la relance.
                if (_var in ["COMSPEC_ATAK_LayerFriends", "COMSPEC_ATAK_ShowHostile"]) then { [] call comspec_atak_native_fnc_localDataRefresh; };
                if (_var isEqualTo "COMSPEC_ATAK_SigintLayer") then { [] spawn comspec_atak_native_fnc_sigintPoll; };
                [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }];
            private _layers = [
                ["Alliés", "P", "COMSPEC_ATAK_LayerFriends", true],
                ["Ennemis repérés", "P", "COMSPEC_ATAK_ShowHostile", false],
                ["Cartouches", "P", "COMSPEC_ATAK_MarkerTags", true],
                ["Zones Athena", "P", "COMSPEC_ATAK_ZonesLayer", true],
                ["SIGINT", "P", "COMSPEC_ATAK_SigintLayer", true],
                ["Guerre élec.", "P", "COMSPEC_ATAK_LayerEw", true],
                ["Logistique", "P", "COMSPEC_ATAK_LayerLogi", true],
                ["Relief", "M", "COMSPEC_ATAK_ViewshedShow", true],
                ["Wave Relay", "P", "COMSPEC_ATAK_MeshOnMap", false],
                ["Heatmap", "P", "COMSPEC_ATAK_LayerHeat", false],
                ["Carte nuit", "P", "COMSPEC_ATAK_LayerNight", false]
            ];
            private _lw = ((_l get "inspW") - _pad * 2.5) / 2;
            private _lh = _fs * 1.45;
            private _rowsN = ceil ((count _layers) / 2);
            private _ly0 = _by + _bh - _pad - _rowsN * (_lh + _pad / 3);
            private _lt = ["COMSPEC_RscStructuredText", [_sx, _ly0 - _fs * 1.3, _sw, _fs * 1.2]] call _mk;
            _lt ctrlSetStructuredText parseText "<t font='RobotoCondensedBold' size='0.8' color='#5cc76b'>CALQUES</t>";
            uiNamespace setVariable ["COMSPEC_ATAK_InspTextBottom", _ly0 - _fs * 1.4];
            private _sep = ["COMSPEC_RscText", [_sx, _ly0 - _fs * 1.45, _sw, pixelH]] call _mk;
            _sep ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.35];
            {
                _x params ["_lbl", "_ns", "_var", "_def"];
                private _on = ([profileNamespace, missionNamespace] select (_ns isEqualTo "M")) getVariable [_var, _def];
                private _col = _forEachIndex mod 2;
                private _row = floor (_forEachIndex / 2);
                // Bouton à bascule : fond et pastille verts quand le calque est affiché, gris sinon.
                private _bx0 = _sx + _col * (_lw + _pad / 2);
                private _by0 = _ly0 + _row * (_lh + _pad / 3);
                private _b = ["COMSPEC_RscButton", [_bx0, _by0, _lw, _lh], _lbl] call _mk;
                _b ctrlSetFontHeight (_fs * 0.78);
                _b ctrlSetBackgroundColor ([[0.08, 0.10, 0.09, 0.95], [0.10, 0.30, 0.16, 0.95]] select _on);
                _b ctrlSetTextColor ([[0.6, 0.65, 0.62, 1], [0.92, 0.97, 0.93, 1]] select _on);
                private _dotH = _lh * 0.34;
                private _dot = ["COMSPEC_RscText", [_bx0 + _lh * 0.3 * _ratio, _by0 + (_lh - _dotH) / 2, _dotH * pixelH / pixelW, _dotH]] call _mk;
                _dot ctrlSetBackgroundColor ([[0.30, 0.34, 0.32, 1], [0.36, 0.78, 0.42, 1]] select _on);
                _b ctrlAddEventHandler ["ButtonClick", compile format ["[%1, %2, %3] call (uiNamespace getVariable 'COMSPEC_ATAK_LayerToggle');", str _ns, str _var, _def]];
            } forEach _layers;
        };
    };

    // Consigne de l'outil actif
    private _mode = _s getOrDefault ["mapMode", "SELECT"];
    private _hintText = switch (_mode) do {
        case "MARKER": { "MARQUEUR : clic pour poser" };
        case "MEASURE": { "MESURE : clic A puis B" };
        case "HOUSES": { "BÂTIMENTS : clic pour numéroter autour" };
        case "HEIGHT": { "HAUTEUR : clic pour relever l'altitude" };
        case "FLAT": { "TERRAIN PLAT : clic pour chercher, clic sur une LZ = marqueur" };
        case "LOS": { "LIGNE DE VUE : clic sur la cible" };
        case "ROUTE": { "GPS : clic sur la destination" };
        case "WP": { "POINTS DE PASSAGE : clic pour ajouter une étape" };
        case "LINE": { "TRAIT : clic A puis clic B" };
        case "DRAW": { "DESSIN : maintenir le clic gauche" };
        default { "" };
    };
    if ((count (_s getOrDefault ["markerEdit", createHashMap])) > 0) then { _hintText = ""; };
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
    // Palette masquée pendant l'édition d'un marqueur : un seul panneau à la fois.
    if (_mode isEqualTo "MARKER" && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
        // Palette complète : camp, type (icônes), couleur, taille, autres marqueurs.
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        [[_hx, _by + _pad * 2 + _fs * 1.5, (_tx - _hx - _pad * 2) max (_font * 6 / _ratio), _mh * 0.7]] call comspec_atak_native_fnc_markerPalette;
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
    private _go = ["COMSPEC_RscButtonPrimary", [_px + _pw - _pad - _fs * 5 / _ratio * 0.6, _py + _pad / 2, _fs * 5 / _ratio * 0.6, _fs * 1.4], "Y ALLER"] call _mk;
    _go ctrlSetFontHeight (_fs * 0.85);
    _go ctrlAddEventHandler ["ButtonClick", { private _m = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]; private _p = getMarkerPos _m; [[_p select 0, _p select 1, 0], [markerText _m, "Marqueur"] select ((markerText _m) isEqualTo "")] spawn comspec_atak_native_fnc_routeCompute; }];
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

// Éditeur de marqueur. La carte est figée pendant l'édition : sinon elle garde la molette (zoom)
// et le formulaire ne défile pas.
private _editing = _interactive && {(count (_s getOrDefault ["markerEdit", createHashMap])) > 0};
((([] call comspec_atak_native_fnc_display)) displayCtrl 88530) ctrlEnable !_editing;
if (_editing) then {
    private _ew = [_mw * 0.42, _mw] select (_l get "mini");
    [[_bx + _mw - _ew, _by, _ew, _bh]] call comspec_atak_native_fnc_markerEditor;
};

// Points de passage en navigation : flèche de cap (relative à mon cap) et distance, en bas au centre, aussi en mini.
private _wpn = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
if (_wpn getOrDefault ["nav", false] && {(count (_wpn getOrDefault ["pts", []])) > 0}) then {
    private _ah = _font * 2.4;
    private _aw = _ah / _ratio;
    private _pw = _aw + _font * 5.5 / _ratio;
    private _px = _bx + (_mw - _pw) / 2;
    private _py = _by + _mh - _ah - _pad * ([1, 6] select (_interactive && {!(_l get "mini")}));
    private _abg = ["COMSPEC_RscMapPanel", [_px, _py, _pw, _ah]] call _mk;
    _abg ctrlSetBackgroundColor [0.03, 0.04, 0.035, 0.88];
    private _arr = ["COMSPEC_RscIcon", [_px, _py, _aw, _ah], _dir + "nav_straight.paa"] call _mk;
    _arr ctrlSetTextColor [0.36, 0.85, 0.42, 1];
    private _atx = ["COMSPEC_RscStructuredText", [_px + _aw, _py + _ah * 0.1, _pw - _aw, _ah * 0.9]] call _mk;
    _ov set ["wpArrow", _arr];
    _ov set ["wpText", _atx];
};

// GPS : bandeau de guidage en haut de la carte (mis à jour chaque seconde par fn_routeBanner).
if ((count (missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap])) > 0) then {
    private _gw = (_mw * 0.62) max (_font * 9 / _ratio) min (_mw - 2 * _pad);
    private _gx = _bx + (_mw - _gw) / 2;
    private _gy = _by + _pad + ([0, _fs * 1.8] select ((_s getOrDefault ["mapMode", "SELECT"]) isNotEqualTo "SELECT"));
    private _gh = _font * 2.6;
    private _gbg = ["COMSPEC_RscMapPanel", [_gx, _gy, _gw, _gh * 0.62]] call _mk;
    _gbg ctrlSetBackgroundColor [0.10, 0.45, 0.25, 0.95];
    private _gi = _gh * 0.52;
    private _g3 = ["COMSPEC_RscIcon", [_gx + _pad / 2, _gy + _gh * 0.05, _gi / _ratio, _gi], ""] call _mk;
    _g3 ctrlSetTextColor [1, 1, 1, 1];
    private _g1 = ["COMSPEC_RscStructuredText", [_gx + _pad + _gi / _ratio, _gy + _gh * 0.03, _gw - _pad * 1.5 - _gi / _ratio, _gh * 0.6]] call _mk;
    private _g2 = ["COMSPEC_RscMapPanel", [_gx, _gy + _gh * 0.62, _gw, _gh * 0.38]] call _mk;
    _g2 ctrlSetBackgroundColor [0.03, 0.04, 0.035, 0.92];
    private _stop = controlNull;
    if (_interactive) then {
        private _sw = _fs * 5.5 / _ratio * 0.6;
        _stop = ["COMSPEC_RscButton", [_gx + _gw - _sw - _pad / 2, _gy + _gh * 0.62 + _gh * 0.04, _sw, _gh * 0.3], "ARRÊTER"] call _mk;
        _stop ctrlSetFontHeight ((_fs * 0.8) min (_gh * 0.26));
        _stop ctrlSetBackgroundColor [0.75, 0.18, 0.15, 1];
        _stop ctrlAddEventHandler ["ButtonClick", { missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMap]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    };
    uiNamespace setVariable ["COMSPEC_ATAK_RouteBanner", [_g1, _g2, _g3]];
    [] call comspec_atak_native_fnc_routeBanner;
};

uiNamespace setVariable ["COMSPEC_ATAK_MapOverlay", _ov];

if !(_s getOrDefault ["mapCentered", false]) then {
    _s set ["mapCentered", true];
    [player, uiNamespace getVariable ["COMSPEC_ATAK_MapScale", 0.08]] call comspec_atak_native_fnc_mapCenter;
};
[] call comspec_atak_native_fnc_mapOverlayUpdate;
if !(_l get "mini") then { [] call comspec_atak_native_fnc_inspectorUpdate; };
[] call comspec_atak_native_fnc_mapUnfocus;
true
