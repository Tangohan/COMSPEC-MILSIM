/*
    Carte façon ATAK : la carte occupe l'écran, les informations flottent par-dessus.
    - Haut gauche : boussole (aiguille = cap du joueur).
    - Bas gauche : bandeau d'information en deux cases (curseur : grille, distance, azimut, MGRS, altitude ; moi : cap,
      indicatif, équipe de feu, grille), boutons « Outils carte » et « RECHERCHER »
      (IMEI, numéro, balise GPS, indicatif : fn_mapSearch, panneau en haut à gauche, fiche du résultat en bas).
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
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
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
private _search = uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap];
private _searchOpen = _interactive && {_search getOrDefault ["open", false]} && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0};
if (((["COMSPEC_ATAK_Compass", true, "native_compass"] call comspec_atak_native_fnc_pref) select 0) && {!(_interactive && {_s getOrDefault ["mapToolsOpen", false]})} && {!_searchOpen}) then {
    // Style choisi dans Réglages > Carte : CLASSIC (rose fixe, aiguille qui tourne, défaut), CARD (cadran
    // qui tourne, le haut reste l'avant), ARROW (aiguille seule), TAPE (bandeau de cap gradué).
    private _cStyle = profileNamespace getVariable ["COMSPEC_ATAK_CompassStyle", "CLASSIC"];
    private _ch = (_mh * 0.24) min (_font * 3.6);
    if (_cStyle isEqualTo "ARROW") then { _ch = _ch * 0.7; };
    private _cw = _ch / _ratio;
    if (_cStyle isEqualTo "TAPE") then { _ch = _fs * 1.5; _cw = ((_font * 3.6 / _ratio) * 1.9) min (_mw * 0.5); };
    switch (_cStyle) do {
        case "CARD": {
            private _card = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_card.paa"] call _mk;
            _card ctrlSetTextColor [1, 1, 1, 1];
            private _lub = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_lubber.paa"] call _mk;
            _lub ctrlSetTextColor [1, 1, 1, 1];
            _ov set ["compassCard", _card];
        };
        case "TAPE": {
            private _tape = ["COMSPEC_RscStructuredText", [_bx + _pad, _by + _pad, _cw, _ch]] call _mk;
            _tape ctrlSetBackgroundColor [0.02, 0.025, 0.03, 0.78];
            _ov set ["compassTape", _tape];
        };
        case "ARROW": {
            private _needle = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_needle.paa"] call _mk;
            _needle ctrlSetTextColor [1, 1, 1, 1];
            _ov set ["needle", _needle];
        };
        default {
            private _ring = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_ring.paa"] call _mk;
            _ring ctrlSetTextColor [1, 1, 1, 1];
            private _needle = ["COMSPEC_RscIcon", [_bx + _pad, _by + _pad, _cw, _ch], _dir + "compass_needle.paa"] call _mk;
            _needle ctrlSetTextColor [1, 1, 1, 1];
            _ov set ["needle", _needle];
        };
    };
    // Cap sous la rose, façon ATAK : « 42° NE » en chasse fixe grasse sur une pastille sombre (le bandeau l'affiche déjà).
    if (_cStyle isEqualTo "TAPE") exitWith {};
    private _hd = ["COMSPEC_RscTextCenter", [_bx + _pad, _by + _pad + _ch + pixelH * 2, _cw max (_font * 3.6 * 0.7 / _ratio), _fs * 1.15]] call _mk;
    _hd ctrlSetFont "EtelkaMonospaceProBold";
    _hd ctrlSetFontHeight (_fs * 0.9);
    _hd ctrlSetBackgroundColor [0.02, 0.025, 0.03, 0.78];
    _hd ctrlSetTextColor [0.95, 0.97, 0.96, 1];
    _ov set ["heading", _hd];
};

// Bandeau d'information (bas gauche) : une seule pastille, deux cases séparées d'un filet, même hauteur, liseré vert
// à gauche. Case 1 : curseur (en main) ou ma position (porté) ; case 2 : mon cap, mon indicatif et mon équipe de feu
// (couleur de l'anneau autour de mon symbole). Ligne 1 = valeur principale en grand, ligne 2 = détails en petit gris
// (fn_mapOverlayUpdate). Largeur calée sur le texte (avant : bandeau étiré jusqu'à la carte « moi », à moitié vide)
// et rectangle gardé pour que les noms des repères et des lieux ne passent plus dessous (fn_mapMarkerText).
// Police = petite police × taille des textes de carte (Réglages > Carte).
private _mini = _l get "mini";
private _mts = ((profileNamespace getVariable ["COMSPEC_ATAK_MapTextScale", 1.25]) max 0.8) min 2.2;
private _panFs = _fs * ([0.78, 0.7] select _mini) * _mts;
private _panW = ((((_mw * 0.34) min (_font * 9 / _ratio)) * ([1, 0.75] select _mini)) * (_mts max 1)) min (_mw * ([0.44, 0.62] select _mini));
private _panIn = _pad * 0.6;
private _panLine = _panFs * 1.18;
// En main, la barre d'outils occupe le bord droit : le bandeau s'arrête avant.
private _toolCol = [0, ((((_mh - 2 * _pad) / 7) min (_font * 2.1)) / _ratio) + _pad] select _interactive;
// Largeur d'un caractère à chasse fixe (taille 1) et marges intérieures d'une case.
private _chW = _panFs * 0.6 / _ratio;
private _cellIn = _panIn / _ratio * 1.2;
private _avail = (_mw - 2 * _pad - _toolCol) max (_panW * 1.2);
// Case 2 : « 317° NO » en grand, « TA1 · BLEU · 1519 1729 » en petit (sans grille en porté : la case 1 la donne).
private _meW = ((([18, 13] select _mini) * _chW) + _cellIn * 2) min (_avail * 0.45);
// Case 1 : « CURSEUR 1732 1750  2135 m · 085° E » (taille 1,2) ; trop étroite : distance et azimut en ligne 2,
// MGRS et altitude en ligne 3.
private _curNeed = ([36, 30] select _mini) * 1.2 * _chW + _cellIn * 2;
private _curW = (_avail - _meW) min (_curNeed max (_panW * 0.9));
private _curWrap = _curW < (_curNeed * 0.92);
private _lines = [2.15, 3.1] select _curWrap;
private _panH = _panLine * _lines + _panIn * 2;
private _py0 = _by + _mh - _panH - _pad;
private _px0 = _bx + _pad;
private _stripW = _curW + _meW;
private _bg = ["COMSPEC_RscText", [_px0, _py0, _stripW, _panH]] call _mk;
_bg ctrlSetBackgroundColor [0.02, 0.025, 0.022, 0.74];
private _edge = ["COMSPEC_RscText", [_px0, _py0, pixelW * 3, _panH]] call _mk;
_edge ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9];
private _sep = ["COMSPEC_RscText", [_px0 + _curW, _py0 + _panIn, pixelW, _panH - _panIn * 2]] call _mk;
_sep ctrlSetBackgroundColor [0.55, 0.62, 0.58, 0.35];
private _cell = {
    params ["_x0", "_w"];
    private _t = ["COMSPEC_RscStructuredText", [_x0 + _cellIn, _py0 + _panIn * 0.7, _w - _cellIn * 1.6, _panH - _panIn * 1.2]] call _mk;
    _t ctrlSetFontHeight ((_panFs) max COMSPEC_ATAK_FontMin);
    _t
};
private _cur = [_px0 + pixelW * 3, _curW - pixelW * 3] call _cell;
private _me = [_px0 + _curW, _meW] call _cell;
_ov set ["cursor", _cur];
_ov set ["cursorWrap", _curWrap];
_ov set ["me", _me];
// Rectangles de l'interface posée sur la carte, relatifs au coin de la carte, avec sa taille : fn_mapHudRects les
// rend aux noms des repères, des lieux et aux cartouches, qui ne passent plus dessous.
private _hud = [[_px0 - _bx, _py0 - _by, _px0 + _stripW - _bx, _py0 + _panH - _by]];
private _hdC = _ov getOrDefault ["heading", controlNull];
if (!isNull _hdC) then { (ctrlPosition _hdC) params ["_hx", "_hy", "_hw", "_hh"]; _hud pushBack [_hx - _bx, _hy - _by, _hx + _hw - _bx, _hy + _hh - _by]; };
uiNamespace setVariable ["COMSPEC_ATAK_MapHudRects", [[_mw, _mh], _hud]];

if (_interactive) then {
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
    _tlb ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
    _tlb ctrlSetFontHeight _fs;
    _tlb ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _toolsOpen);
    private _tb = ["COMSPEC_RscButtonOverlay", [_bx + _pad, _tby, _tbw, _th]] call _mk;
    _tb ctrlSetTooltip (("Outils carte") call comspec_atak_native_fnc_tr);
    _tb ctrlAddEventHandler ["ButtonClick", {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["mapToolsOpen", !(_s getOrDefault ["mapToolsOpen", false])];
        (uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap]) set ["open", false];
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }];
    _ov set ["tool_menu", _tb];
    // Bouton « RECHERCHER » à côté : localiser un IMEI, un numéro, une balise GPS ou un indicatif (fn_mapSearch).
    private _sx0 = _bx + _pad + _tbw + _pad / 2;
    private _sbw = (_tw + _fs * 5.8 / _ratio) min _panW;
    private _sbg = ["COMSPEC_RscMapPanel", [_sx0, _tby, _sbw, _th]] call _mk;
    if (_searchOpen) then { _sbg ctrlSetBackgroundColor [0.10, 0.20, 0.12, 0.92]; };
    private _sic = ["COMSPEC_RscIcon", [_sx0, _tby, _tw, _th], _dir + "app_osint.paa"] call _mk;
    _sic ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _searchOpen);
    private _slb = ["COMSPEC_RscText", [_sx0 + _tw, _tby, _sbw - _tw, _th], "RECHERCHER"] call _mk;
    _slb ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
    _slb ctrlSetFontHeight _fs;
    _slb ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _searchOpen);
    private _sbt = ["COMSPEC_RscButtonOverlay", [_sx0, _tby, _sbw, _th]] call _mk;
    _sbt ctrlSetTooltip (("Rechercher et localiser : IMEI, numéro de téléphone, balise GPS, indicatif ou nom") call comspec_atak_native_fnc_tr);
    _sbt ctrlAddEventHandler ["ButtonClick", { ["toggle"] call comspec_atak_native_fnc_mapSearch; }];
    _ov set ["tool_search", _sbt];

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
            ["SEARCH", "app_osint", "Rechercher (IMEI, numéro, balise)"],
            ["LOS", "map_los", "Ligne de vue"],
            ["IPTGT", "app_jtac", "JTAC : IP → cible (cap magnétique)"],
            ["CLEAR", "map_clear", "Tout effacer"]
        ];
        // Drone appairé et en état : ses ordres se donnent aussi depuis la carte.
        private _drn = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        if (!isNull _drn && {alive _drn}) then { _items insert [0, [["DRONE", "app_drone", "Drone : ordres sur la carte"]]]; };
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

    // Barre d'outils à droite (taille choisie dans Réglages > Carte : petite, normale, grande)
    private _tbScale = profileNamespace getVariable ["COMSPEC_ATAK_MapToolbarScale", 1];
    if !(_tbScale isEqualType 0) then { _tbScale = 1; };
    private _ihMax = (_mh - 2 * _pad) / 7;
    // Normale : comme avant. Grande : jamais plus haute que la carte ne le permet.
    private _ih = (((_ihMax min (_font * 2.1)) * _tbScale) min _ihMax);
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
    // Drone appairé : bascule carte / vue drone plein écran / vue incrustée.
    if (alive (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) then {
        _tools pushBack ["droneview", "app_drone", "Vue drone : carte, plein écran ou incrustée", { ["view"] call comspec_atak_native_fnc_droneAction; }];
    };
    private _ty0 = _by + _pad + _ih * 0.5;
    ["COMSPEC_RscMapPanel", [_tx - _pad / 2, _ty0 - _pad / 2, _iw + _pad, _ih * (count _tools) + _pad]] call _mk;
    {
        _x params ["_key", "_icon", "_tip", "_code"];
        private _b = ["COMSPEC_RscIconButton", [_tx, _ty0 + _forEachIndex * _ih, _iw, _ih * 0.92], _dir + _icon + ".paa"] call _mk;
        _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
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
        // Pas de place sous la barre d'outils (drone appairé, petit écran) : l'œil passe à sa gauche, en haut.
        if (!_open && {_sy + _sh > _by + _mh - _pad}) then { _sx = _tx - _iw - _pad; _sy = _ty0 - _pad / 2; };
        private _sbg = ["COMSPEC_RscMapPanel", [_sx, _sy, _sw, _sh]] call _mk;
        if (_open) then { _sbg ctrlSetBackgroundColor [0.10, 0.20, 0.12, 0.92]; };
        private _sic = ["COMSPEC_RscIcon", [_sx + (_si * 0.15), _sy + (_sh * 0.15), _si * 0.7, _sh * 0.7], _dir + "app_intel.paa"] call _mk;
        _sic ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.36, 0.78, 0.42, 1]] select _open);
        if (_open) then {
            private _slb = ["COMSPEC_RscStructuredText", [_sx + _si, _sy + _sh * 0.2, _sw - _si, _sh * 0.8]] call _mk;
            _slb ctrlSetStructuredText parseText (("<t font='COMSPEC_BarlowBold' size='0.85' color='#5cc76b'>SITUATION</t><t size='0.85' color='#8a9a93' align='right'>REPLIER</t>") call comspec_atak_native_fnc_tr);
        };
        private _sb = ["COMSPEC_RscButtonOverlay", [_sx, _sy, _sw, _sh]] call _mk;
        _sb ctrlSetTooltip (((["Afficher le panneau SITUATION (unités, contacts, sélection)", "Replier le panneau SITUATION"] select _open)) call comspec_atak_native_fnc_tr);
        _sb ctrlAddEventHandler ["ButtonClick", { [] call comspec_atak_native_fnc_inspToggle; }];
        _ov set ["insp_toggle", _sb];
        // Calques : bascule d'un calque (lignes du volet ci-dessous), avec ce qu'il montre une fois allumé.
        if (_open) then {
            uiNamespace setVariable ["COMSPEC_ATAK_LayerToggle", {
                params ["_ns", "_var", "_def"];
                // Réglage CBA côté joueur (noms sur la carte) : enregistré par CBA, le même que dans le menu CBA.
                if (_ns isEqualTo "C") exitWith {
                    [_var, !(missionNamespace getVariable [_var, _def]), false, "client"] call CBA_settings_fnc_set;
                    [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
                };
                private _n = [profileNamespace, missionNamespace] select (_ns isEqualTo "M");
                _n setVariable [_var, !(_n getVariable [_var, _def])];
                if (_ns isEqualTo "P") then { saveProfileNamespace; };
                // Les filtres d'unités (alliés, ennemis) sont appliqués à la collecte : on la relance.
                if (_var in ["COMSPEC_ATAK_LayerFriends", "COMSPEC_ATAK_ShowHostile"]) then { [] call comspec_atak_native_fnc_localDataRefresh; };
                if (_var isEqualTo "COMSPEC_ATAK_SigintLayer") then { [] spawn comspec_atak_native_fnc_sigintPoll; };
                // Calque allumé : on dit ce qu'il montre, ou pourquoi il n'y a rien à voir (sans donnée, le calque semblait en panne).
                private _on = _n getVariable [_var, _def];
                if (_on) then {
                    private _msg = switch (_var) do {
                        case "COMSPEC_ATAK_LayerFriends": { format ["Alliés : %1 unité(s) amie(s) sur la carte", { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" } count values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap])] };
                        case "COMSPEC_ATAK_ZonesLayer": {
                            private _z = (count (missionNamespace getVariable ["COMSPEC_DangerZones", []])) + (count (missionNamespace getVariable ["COMSPEC_RoleplayZones", []])) + (count ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["zones", []]));
                            ["Zones Athena : aucune zone reçue pour cette carte", format ["Zones Athena : %1 zone(s)", _z]] select (_z > 0)
                        };
                        case "COMSPEC_ATAK_SigintLayer": {
                            private _e = count (uiNamespace getVariable ["COMSPEC_ATAK_Sigint", []]);
                            [["SIGINT : Athena non joignable, aucun émetteur estimé", "SIGINT : aucun émetteur estimé pour l'instant (relèvements des relais sur Athena)"] select ([] call comspec_atak_native_fnc_bridge), format ["SIGINT : %1 émetteur(s) estimé(s)", _e]] select (_e > 0)
                        };
                        case "COMSPEC_ATAK_LayerEw": {
                            private _e = (count (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []])) + (count (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []])) + (count (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]));
                            ["Guerre élec. : aucun relèvement, brouilleur ni géolocalisation (app EW)", format ["Guerre élec. : %1 élément(s)", _e]] select (_e > 0)
                        };
                        case "COMSPEC_ATAK_LayerLogi": {
                            private _e = { (_x getOrDefault ["status", ""]) in ["DEMANDEE", "VALIDEE", "EN_ROUTE"] } count values (missionNamespace getVariable ["COMSPEC_ATAK_LogiReqs", createHashMap]);
                            ["Logistique : aucune demande ouverte (app Logistique)", format ["Logistique : %1 demande(s) ouverte(s)", _e]] select (_e > 0)
                        };
                        case "COMSPEC_ATAK_ViewshedShow": {
                            // Rien de calculé : on lance le champ de vision depuis moi plutôt que d'afficher un calque vide.
                            if ((count (missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []])) < 9) then {
                                ["compute"] call comspec_atak_native_fnc_reliefAction;
                                "Relief : calcul du champ de vision depuis votre position (clic droit sur la carte : depuis un point)"
                            } else { "Relief : champ de vision affiché" };
                        };
                        case "COMSPEC_ATAK_MeshOnMap": {
                            private _rl = ["list"] call comspec_atak_native_fnc_relayNet;
                            private _ok = { _x select 6 } count _rl;
                            if (_ok > 0 && {(count (missionNamespace getVariable ["COMSPEC_ATAK_Coverage", []])) < 8}) then { ["coverage"] call comspec_atak_native_fnc_relayNet; };
                            [format ["Wave Relay : %1 relais / passerelle(s), calcul de la couverture", _ok], "Wave Relay : aucun relais ni passerelle de véhicule dans votre camp, seuls les liens entre téléphones s'affichent"] select (_ok isEqualTo 0)
                        };
                        case "COMSPEC_ATAK_RelaysLayer": {
                            private _rl = ["list"] call comspec_atak_native_fnc_relayNet;
                            [format ["Relais : %1 relais / passerelle(s) dans votre camp, %2 en service", count _rl, { _x select 6 } count _rl], "Relais : aucun relais ni passerelle dans votre camp"] select (_rl isEqualTo [])
                        };
                        default { "" };
                    };
                    if (_msg isNotEqualTo "") then { ["INFO", _msg, 4, 20] call comspec_atak_native_fnc_notify; };
                };
                [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }];
            // Sections repliées (profil) : clé ajoutée ou retirée au clic sur l'en-tête, page redessinée.
            uiNamespace setVariable ["COMSPEC_ATAK_MapPanelFold", {
                params ["_k"];
                private _f = profileNamespace getVariable ["COMSPEC_ATAK_MapPanelFolds", []];
                if !(_f isEqualType []) then { _f = []; };
                if (_k in _f) then { _f = _f - [_k]; } else { _f pushBack _k; };
                profileNamespace setVariable ["COMSPEC_ATAK_MapPanelFolds", _f];
                saveProfileNamespace;
                ["MAP"] call comspec_atak_native_fnc_pageRender;
            }];
            private _folds = profileNamespace getVariable ["COMSPEC_ATAK_MapPanelFolds", []];
            if !(_folds isEqualType []) then { _folds = []; };
            // Vue CONFIGURATION (bouton en tête du volet) : panneau élargi, toutes les sections dépliées, chaque calque
            // expliqué. Les sections repliées du profil sont gardées pour le retour au volet normal.
            private _full = missionNamespace getVariable ["COMSPEC_ATAK_InspFull", false];
            if (_full) then { _folds = []; };
            private _foldCode = { format ["[%1] call (uiNamespace getVariable 'COMSPEC_ATAK_MapPanelFold');", str _this] };
            // Volet défilant sous le bandeau, en sections repliables (fn_formRender) : situation, fond de carte, calques
            // par thème avec leur compteur. Une ligne par calque sur toute la largeur, libellé entier (il passe à la ligne
            // au besoin) et interrupteur OUI / NON à droite : les deux colonnes coupaient les libellés (« Noms des rep »).
            private _rows = [["buttons", [[["CONFIGURATION", "FERMER LA CONFIGURATION"] select _full, "['full'] call comspec_atak_native_fnc_inspToggle;", _full, true,
                ["Tout déplier : fond de carte et calques, avec ce que montre chaque calque", "Revenir au volet normal à côté de la carte"] select _full]]]];
            private _sel = _s getOrDefault ["selectedEntity", createHashMap];
            private _units = values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
            private _fr = { (_x getOrDefault ["affiliation", ""]) isEqualTo "friend" } count _units;
            private _ho = { (_x getOrDefault ["affiliation", ""]) isEqualTo "hostile" } count _units;
            private _sitOpen = !("sit" in _folds);
            // Résumés d'en-tête courts (le volet est étroit) : alliés en bleu · contacts (en rouge s'il y en a), ou l'indicatif.
            _rows pushBack ["fold", ["Situation", "Unité sélectionnée"] select ((count _sel) > 0),
                [format ["<t color='#47b3ff'>%1</t> · <t color='%3'>%2</t>", _fr, _ho, ["#c9d4ce", "#e5483a"] select (_ho > 0)], _sel getOrDefault ["callsign", "CONTACT"]] select ((count _sel) > 0),
                _sitOpen, "sit" call _foldCode];
            // Texte du résumé ou de la fiche : rafraîchi chaque seconde par fn_inspectorUpdate dans ce même contrôle.
            uiNamespace setVariable ["COMSPEC_ATAK_InspTextCtrl", controlNull];
            uiNamespace setVariable ["COMSPEC_ATAK_InspSelKey", ["", _sel getOrDefault ["callsign", "CONTACT"]] select ((count _sel) > 0)];
            if (_sitOpen) then { _rows pushBack ["text", [true] call comspec_atak_native_fnc_inspectorUpdate, [0.06, 0.075, 0.068, 0.96], "COMSPEC_ATAK_InspTextCtrl"]; };
            // Fond de carte : boutons sur deux rangées au-delà de trois (TOPO CLAIR SOMBRE / NUIT SATELLITE HYBRIDE, PHOTO en plus).
            private _style = [] call comspec_atak_native_fnc_mapStyle;
            private _styles = [["TOPO", "TOPO"], ["CLAIR", "LIGHT"], ["SOMBRE", "DARK"], ["NUIT", "NIGHT"], ["CLASSIQUE", "SAT"], ["HYBRIDE", "HYBRID"]];
            if ((["info"] call comspec_atak_native_fnc_photoMap) isNotEqualTo []) then { _styles pushBack ["SATELLITE", "PHOTO"]; };
            private _bgOpen = !("bg" in _folds);
            _rows pushBack ["fold", "Fond de carte", (_styles select ((_styles findIf { (_x select 1) isEqualTo _style }) max 0)) select 0, _bgOpen, "bg" call _foldCode];
            if (_bgOpen) then {
                private _opts = _styles apply {
                    _x params ["_t", "_k"];
                    [_t, format ["[%1] call comspec_atak_native_fnc_mapStyle; ['MAP'] call comspec_atak_native_fnc_pageRender;", str _k], _k isEqualTo _style, "Fond de carte (aussi dans Réglages > Carte)"]
                };
                private _per = [count _opts, ceil ((count _opts) / 2)] select ((count _opts) > 3);
                for "_i" from 0 to ((count _opts) - 1) step _per do { _rows pushBack ["segment", "", _opts select [_i, _per]]; };
            };
            // Calques par thème : [clé, titre, [[libellé, espace (P profil, M mission, C réglage CBA du joueur), variable,
            // défaut, clé communauté (verrou Athena) ou "", explication affichée en vue CONFIGURATION]...]]
            private _groups = [
                ["forces", "Forces", [
                    ["Alliés", "P", "COMSPEC_ATAK_LayerFriends", true, "", "Unités amies (joueurs et IA) sur la carte"]
                ]],
                ["places", "Repères et lieux", [
                    ["Coordonnées des marqueurs", "P", "COMSPEC_ATAK_MarkerTags", true, "native_marker_tags", "Grille sous chaque marqueur posé"],
                    ["Noms des marqueurs", "C", "comspec_atak_native_map_show_marker_text", true, "", "Texte des marqueurs (aussi dans le menu CBA)"],
                    ["Villes", "C", "comspec_atak_native_map_show_cities", true, "", "Noms des villes du terrain"],
                    ["Villages", "C", "comspec_atak_native_map_show_villages", true, "", "Noms des villages du terrain"],
                    ["Lieux-dits", "C", "comspec_atak_native_map_show_locals", true, "", "Noms des collines, bois et lieux-dits"],
                    ["Sites", "C", "comspec_atak_native_map_show_sites", true, "", "Sites remarquables (usines, aéroports...)"]
                ]],
                ["intel", "Renseignement", [
                    ["Zones Athena", "P", "COMSPEC_ATAK_ZonesLayer", true, "", "Zones dangereuses et de jeu envoyées par Athena"],
                    ["SIGINT", "P", "COMSPEC_ATAK_SigintLayer", true, "", "Émetteurs ennemis estimés par les relais (Athena)"],
                    ["Guerre électronique", "P", "COMSPEC_ATAK_LayerEw", true, "", "Relèvements, brouilleurs et géolocalisations (app EW)"]
                ]],
                ["net", "Réseau et logistique", [
                    ["Relais", "P", "COMSPEC_ATAK_RelaysLayer", true, "", "Relais radio et passerelles de véhicule de votre camp"],
                    ["Wave Relay", "P", "COMSPEC_ATAK_MeshOnMap", false, "", "Liens entre téléphones et couverture du réseau maillé"],
                    ["Logistique", "P", "COMSPEC_ATAK_LayerLogi", true, "", "Demandes de ravitaillement ouvertes (app Logistique)"]
                ]],
                ["relief", "Relief", [
                    ["Champ de vision", "M", "COMSPEC_ATAK_ViewshedShow", true, "", "Ce qui est visible depuis vous (ou un point choisi)"],
                    ["Altitudes (heatmap)", "P", "COMSPEC_ATAK_LayerHeat", false, "", "Couleurs selon l'altitude du terrain"]
                ]]
            ];
            _rows pushBack ["title", "Calques"];
            {
                _x params ["_key", "_title", "_items"];
                // État de chaque calque : valeur du joueur, ou celle imposée par la communauté (Contrôle serveur).
                private _st = _items apply {
                    _x params ["", "_ns", "_var", "_def", "_tenant"];
                    private _on = ([profileNamespace, missionNamespace] select (_ns in ["M", "C"])) getVariable [_var, _def];
                    private _locked = false;
                    if (_tenant isNotEqualTo "") then { ([_var, _def, _tenant] call comspec_atak_native_fnc_pref) params ["_v", "_lk"]; _on = _v; _locked = _lk; };
                    [_on, _locked]
                };
                private _gOpen = !(_key in _folds);
                _rows pushBack ["fold", _title, format ["%1/%2", { _x select 0 } count _st, count _items], _gOpen, _key call _foldCode];
                if (_gOpen) then {
                    {
                        _x params ["_lbl", "_ns", "_var", "_def", "", ["_help", ""]];
                        (_st select _forEachIndex) params ["_on", "_locked"];
                        _rows pushBack ["switch", _lbl, _on, format ["[%1, %2, %3] call (uiNamespace getVariable 'COMSPEC_ATAK_LayerToggle');", str _ns, str _var, _def], ["", _help] select _full, _locked];
                    } forEach _items;
                };
            } forEach _groups;
            private _fy = _sy + _sh + _pad / 3;
            private _grp = [_rows, [_sx, _fy, _sw, (_by + _bh - _pad / 2) - _fy], false, [0, 0, 0, 0]] call comspec_atak_native_fnc_formRender;
            // Défilement gardé d'un rendu à l'autre (calque basculé, section repliée) : fn_pageRender relève les groupes nommés.
            private _sg = uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []];
            _sg pushBack ["mapPanel", _grp];
            uiNamespace setVariable ["COMSPEC_ATAK_ScrollGroups", _sg];
            private _v = (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["scrollGroups", createHashMap]) getOrDefault ["mapPanel", []]) param [0, -1];
            if (_v > 0) then {
                _grp ctrlSetScrollValues [_v, -1];
                [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_grp, _v]] call CBA_fnc_execNextFrame;
            };
        };
    };

    // Consigne de l'outil actif
    private _mode = _s getOrDefault ["mapMode", "SELECT"];
    private _hintText = switch (_mode) do {
        // MARQUEUR : la palette dit elle-même comment poser (clic gauche, ou clic droit puis VALIDER) et se ferme par ✕.
        case "MARKER": { "" };
        case "MEASURE": { "MESURE : clic A puis B" };
        case "HOUSES": { "BÂTIMENTS : clic pour numéroter autour" };
        case "HEIGHT": { "HAUTEUR : clic pour relever l'altitude" };
        case "FLAT": { "TERRAIN PLAT : clic pour chercher, clic sur une LZ = marqueur" };
        case "LOS": { "LIGNE DE VUE : clic sur la cible" };
        case "IPTGT": { ["JTAC : clic sur l'IP", "JTAC : clic sur la cible", "JTAC : clic pour un nouvel IP"] select ((count (_s getOrDefault ["jtacTool", []])) min 2) };
        case "ROUTE": { "GPS : clic sur la destination" };
        // POINTS DE PASSAGE : la fiche d'étape dit elle-même comment poser et se ferme par ✕ (fn_wpCard).
        case "WP": { "" };
        case "LINE": { "TRAIT : clic A puis clic B" };
        case "DRAW": { "DESSIN : maintenir le clic gauche (lissé au relâchement)" };
        case "DRONE": { switch (_s getOrDefault ["droneMapSub", "GOTO"]) do {
            case "ROUTE": { format ["DRONE : clic pour ajouter un point de route (%1)", count (_s getOrDefault ["droneMapPts", []])] };
            case "ZONE": { [["DRONE : clic au centre de la zone", "DRONE : clic au bord pour le rayon"], ["DRONE : clic sur un coin de la zone", "DRONE : clic sur le coin opposé"]] select ((_s getOrDefault ["droneZoneKind", "LOITER"]) isEqualTo "HUNT") select ((count (_s getOrDefault ["droneMapPts", []])) min 1) };
            default { "DRONE : clic = aller ici" };
        } };
        default { "" };
    };
    if ((count (_s getOrDefault ["markerEdit", createHashMap])) > 0) then { _hintText = ""; };
    if (_hintText isNotEqualTo "") then {
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        private _hint = ["COMSPEC_RscChip", [_hx, _by + _pad, (_tx - _hx - _pad) max 0, _fs * 1.5], _hintText + " · clic droit : quitter"] call _mk;
        _hint ctrlSetFontHeight _fs;
    };
    if (_mode in ["LINE", "DRAW"]) then {
        // Couleur, épaisseur, flèche, forme fermée, défaire (fn_strokeBar).
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        [[_hx, _by + _pad * 2 + _fs * 1.5, (_tx - _hx - _pad * 2) max (_font * 6 / _ratio)]] call comspec_atak_native_fnc_strokeBar;
    };
    // Outil DRONE : barre d'ordres sous la consigne (drone appairé et en vie).
    if (_mode isEqualTo "DRONE" && {alive (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])}) then {
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        ["mapBar", [_hx, _by + _pad * 2 + _fs * 1.5, (_tx - _hx - _pad * 2) max (_font * 6 / _ratio)]] call comspec_atak_native_fnc_droneAction;
    };
    // Fiche des points de passage : nom, type, partage, VALIDER ; ou l'étape cliquée (renommer, déplacer, supprimer).
    if (_mode isEqualTo "WP" && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        [[_hx, _by + _pad * 2 + _fs * 1.5, (_tx - _hx - _pad * 2) max (_font * 6 / _ratio), _mh * 0.7]] call comspec_atak_native_fnc_wpCard;
    };
    // Palette masquée pendant l'édition d'un marqueur : un seul panneau à la fois.
    if (_mode isEqualTo "MARKER" && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
        // Palette complète : camp, type (icônes), couleur, taille, autres marqueurs.
        private _hx = _bx + _pad * 2 + ((_mh * 0.24) min (_font * 3.6)) / _ratio;
        [[_hx, _by + _pad * 2 + _fs * 1.5, (_tx - _hx - _pad * 2) max (_font * 6 / _ratio), _mh * 0.7]] call comspec_atak_native_fnc_markerPalette;
    };
};
// Recherche (fn_mapSearch) : panneau en haut à gauche, fiche du résultat en bas au centre (au-dessus du panneau curseur).
if (_searchOpen) then {
    private _sw = ((_mw * 0.46) max (_font * 11 / _ratio)) min (_mw - 2 * _pad);
    ["panel", [_bx + _pad, _by + _pad, _sw, (_mh * 0.72) min (_mh - _panH - _fs * 4)]] call comspec_atak_native_fnc_mapSearch;
};
if (_interactive && {(count (_search getOrDefault ["hit", createHashMap])) > 0} && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
    private _cw = ((_mw * 0.5) max (_font * 11 / _ratio)) min (_mw - 2 * _pad);
    private _chh = _fs * 4.9;
    // Fiche du marqueur sélectionné (plus bas) : la fiche du résultat passe au-dessus.
    private _selUp = [0, _fs * 6.2 + _pad * 4] select ((_s getOrDefault ["selectedMarker", ""]) isNotEqualTo "");
    ["card", [_bx + (_mw - _cw) / 2, _by + _mh - _panH - _pad * 2 - _fs * 2 - _chh - _selUp, _cw, _chh]] call comspec_atak_native_fnc_mapSearch;
} else {
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearchCard", controlNull];
    // Outil JTAC IP → cible (fn_jtacTool) : fiche du calcul au même endroit que celle de la recherche.
    if (_interactive && {(count (_s getOrDefault ["jtacTool", []])) >= 2} && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
        private _cw = ((_mw * 0.5) max (_font * 11 / _ratio)) min (_mw - 2 * _pad);
        private _chh = _fs * 7.6;
        private _selUp = [0, _fs * 6.2 + _pad * 4] select ((_s getOrDefault ["selectedMarker", ""]) isNotEqualTo "");
        ["card", [_bx + (_mw - _cw) / 2, _by + _mh - _panH - _pad * 2 - _fs * 2 - _chh - _selUp, _cw, _chh]] call comspec_atak_native_fnc_jtacTool;
    };
};
// Marqueur sélectionné (clic gauche sur une étiquette) : fiche au-dessus du panneau curseur. Liseré et pastille de la
// couleur du marqueur, titre, nature (alerte, joueur, mission), grille · distance · azimut, note, puis une rangée
// d'actions qui ne chevauche jamais le texte : Y ALLER, CENTRER, MODIFIER (marqueur de joueur), SUPPRIMER / EFFACER.
private _sel = _s getOrDefault ["selectedMarker", ""];
if (_interactive && {_sel isNotEqualTo ""} && {(markerShape _sel) isNotEqualTo ""} && {(count (_s getOrDefault ["markerEdit", createHashMap])) isEqualTo 0}) then {
    private _note = ((missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap]) getOrDefault [_sel, ["", ""]]) select 0;
    private _kind = [_sel] call comspec_atak_native_fnc_markerDeletable;
    private _pw = ((_mw * 0.46) max (_font * 12 / _ratio)) min (_mw - 2 * _pad);
    private _px = _bx + (_mw - _pw) / 2;
    private _lineH = _fs * 1.05;
    private _btnH = _fs * 1.5;
    private _in = _pad * 0.8;
    private _ph = _in + _fs * 1.3 + _lineH + ([0, _lineH] select (_note isNotEqualTo "")) + _in * 0.6 + _btnH + _in;
    private _py = _by + _mh - _panH - _pad * 2 - _ph;
    // Couleur du marqueur (CfgMarkerColors), claire par défaut (marqueur sans couleur ou noir sur fond sombre).
    private _rgba = (getArray (configFile >> "CfgMarkerColors" >> (markerColor _sel) >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
    if ((count _rgba) isNotEqualTo 4 || {((_rgba select 0) + (_rgba select 1) + (_rgba select 2)) < 0.3}) then { _rgba = [0.86, 0.91, 0.88, 1]; };
    _rgba = [_rgba select 0, _rgba select 1, _rgba select 2, 1];
    { private _o = ["COMSPEC_RscText", [_px + pixelW * _x, _py + pixelH * _x, _pw, _ph]] call _mk; _o ctrlSetBackgroundColor [0, 0, 0, 0.22]; } forEach [2, 4];
    private _brd = ["COMSPEC_RscText", [_px - pixelW, _py - pixelH, _pw + pixelW * 2, _ph + pixelH * 2]] call _mk;
    _brd ctrlSetBackgroundColor [0.30, 0.36, 0.32, 0.9];
    private _cbg = ["COMSPEC_RscText", [_px, _py, _pw, _ph]] call _mk;
    _cbg ctrlSetBackgroundColor [0.035, 0.042, 0.038, 0.97];
    private _edge = ["COMSPEC_RscText", [_px, _py, pixelW * 4, _ph]] call _mk;
    _edge ctrlSetBackgroundColor _rgba;
    // Pastille : l'icône du marqueur dans sa couleur.
    private _tH = _fs * 1.3 + _lineH;
    private _tW = _tH / _ratio;
    private _tx0 = _px + pixelW * 4 + _in / _ratio * 0.6;
    private _chip = ["COMSPEC_RscText", [_tx0, _py + _in, _tW, _tH]] call _mk;
    _chip ctrlSetBackgroundColor [_rgba select 0, _rgba select 1, _rgba select 2, 0.16];
    private _ic = ["COMSPEC_RscIcon", [_tx0 + _tW * 0.15, _py + _in + _tH * 0.15, _tW * 0.7, _tH * 0.7], getText (configFile >> "CfgMarkers" >> (markerType _sel) >> "icon")] call _mk;
    _ic ctrlSetTextColor _rgba;
    // Fermer (en haut à droite).
    private _cw = _fs * 1.2;
    private _cx = _px + _pw - _cw / _ratio - _in / _ratio * 0.6;
    private _cl = ["COMSPEC_RscIcon", [_cx, _py + _in, _cw / _ratio, _cw], "\z\comspec_atak_native\addons\main\data\ui_close.paa"] call _mk;
    _cl ctrlSetTextColor [0.55, 0.62, 0.58, 1];
    private _clb = ["COMSPEC_RscButtonOverlay", [_cx, _py + _in, _cw / _ratio, _cw]] call _mk;
    _clb ctrlSetTooltip (("Fermer la fiche") call comspec_atak_native_fnc_tr);
    _clb ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["selectedMarker", ""]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    // Titre, nature, position ; note s'il y en a une.
    private _mp = getMarkerPos _sel;
    private _dd = player distance2D _mp;
    private _kindTxt = ["MISSION", "JOUEUR", "ALERTE"] select _kind;
    private _kindCol = ["#8a9a93", "#5cc76b", "#f2a33a"] select _kind;
    private _txX = _tx0 + _tW + _in / _ratio * 0.6;
    private _txt = ["COMSPEC_RscStructuredText", [_txX, _py + _in - _fs * 0.1, _cx - _txX - _in / _ratio * 0.4, _tH + ([0, _lineH] select (_note isNotEqualTo "")) + _fs * 0.2]] call _mk;
    _txt ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='1.15' color='#e6efe9'>%1</t>  <t size='0.72' color='%2'>%3</t><br/><t font='EtelkaMonospacePro' size='0.82' color='#8a9a93'>%4 · %5 · %6°</t>%7",
        ([([markerText _sel] call comspec_atak_native_fnc_markerAutoName), "(sans titre)"] select ((markerText _sel) isEqualTo "")) regexReplace ["[<>&]", ""], _kindCol, _kindTxt,
        [_mp, 8] call comspec_atak_native_fnc_gridRef, [format ["%1 m", round _dd], format ["%1 km", (_dd / 1000) toFixed 1]] select (_dd >= 1000), round (player getDir _mp),
        ["", format ["<br/><t size='0.85' color='#c9d3cd'>%1</t>", (_note regexReplace ["[<>&]", ""]) select [0, 90]]] select (_note isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
    _txt ctrlSetFontHeight _fs;
    // Actions : une rangée en bas de la fiche.
    private _acts = [
        ["Y ALLER", "COMSPEC_RscButtonPrimary", { private _m = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]; private _p = getMarkerPos _m; [[_p select 0, _p select 1, 0], [([markerText _m] call comspec_atak_native_fnc_markerAutoName), "Marqueur"] select ((markerText _m) isEqualTo "")] spawn comspec_atak_native_fnc_routeCompute; }],
        ["CENTRER", "COMSPEC_RscButton", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["mapFollow", false]; [getMarkerPos (_s getOrDefault ["selectedMarker", ""])] call comspec_atak_native_fnc_mapCenter; }]
    ];
    if (_kind isEqualTo 1) then {
        _acts pushBack ["MODIFIER", "COMSPEC_RscButton", { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]] call comspec_atak_native_fnc_markerEditOpen; }];
    };
    if (_kind > 0) then {
        _acts pushBack [["", "SUPPRIMER", "EFFACER"] select _kind, "COMSPEC_RscButton", { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""]] call comspec_atak_native_fnc_markerDelete; }];
    };
    private _ay = _py + _ph - _in - _btnH;
    private _ax0 = _px + pixelW * 4 + _in / _ratio * 0.6;
    private _aw = ((_px + _pw - _in / _ratio * 0.6) - _ax0 - _pad * 0.5 * ((count _acts) - 1)) / (count _acts);
    {
        _x params ["_lbl", "_cls", "_code"];
        private _b = [_cls, [_ax0 + _forEachIndex * (_aw + _pad * 0.5), _ay, _aw, _btnH], _lbl] call _mk;
        _b ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        if (_lbl in ["SUPPRIMER", "EFFACER"]) then { _b ctrlSetTextColor [0.95, 0.42, 0.36, 1]; };
        _b ctrlAddEventHandler ["ButtonClick", _code];
    } forEach _acts;
    if (_kind > 0) then {
        _txt ctrlSetTooltip ((["", "Double clic : modifier · Suppr : effacer", "Suppr : effacer cette alerte de la carte"] select _kind) call comspec_atak_native_fnc_tr);
    };
};

// Éditeur de marqueur. La carte est figée pendant l'édition : sinon elle garde la molette (zoom)
// et le formulaire ne défile pas.
private _editing = _interactive && {(count (_s getOrDefault ["markerEdit", createHashMap])) > 0};
([] call comspec_atak_native_fnc_mapCtrl) ctrlEnable !_editing;
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
    // Au-dessus du bandeau d'information (il passait dessus quand le bandeau dépassait le centre).
    private _py = _by + _mh - _panH - _pad * 2 - _ah;
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
        _stop ctrlSetFontHeight (((_fs * 0.8) min (_gh * 0.26)) max COMSPEC_ATAK_FontMin);
        _stop ctrlSetBackgroundColor [0.75, 0.18, 0.15, 1];
        _stop ctrlAddEventHandler ["ButtonClick", { missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMap]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    };
    uiNamespace setVariable ["COMSPEC_ATAK_RouteBanner", [_g1, _g2, _g3]];
    [] call comspec_atak_native_fnc_routeBanner;
};

// Vue drone (nacelle FLIR) : à la place de la carte ou incrustée, en grand comme en miniature.
private _dv = _s getOrDefault ["droneView", "OFF"];
private _drv = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
if (_dv isNotEqualTo "OFF" && {isNull _drv || {!alive _drv}}) then { _s set ["droneView", "OFF"]; _dv = "OFF"; };
if (_dv isNotEqualTo "OFF") then {
    ["camEnsure"] call comspec_atak_native_fnc_droneAction;
    private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_droneAction;", str _a, str _v] };
    if (_dv isEqualTo "FULL") then {
        // La carte disparaît derrière un fond noir ; l'image garde son 16/9 (bandes noires en haut et en bas).
        private _cover = ["COMSPEC_RscText", [_bx, _by, _mw, _mh]] call _mk;
        _cover ctrlSetBackgroundColor [0, 0, 0, 1];
        private _bh2 = [0, _fs * 1.45] select _interactive;
        private _vw = _mw;
        private _vh = _vw * 0.5625 * _ratio;
        if (_vh > (_mh - _bh2 - _pad)) then { _vh = _mh - _bh2 - _pad; _vw = _vh / 0.5625 / _ratio; };
        private _vx = _bx + (_mw - _vw) / 2;
        private _vy = _by + (((_mh - _bh2 - _vh) / 2) max 0);
        ["build", [_vx, _vy, _vw, _vh], "FULL", false] call comspec_atak_native_fnc_droneFlir;
        if (_interactive) then {
            private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
            private _fsrc = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedSrc", "DRONE"];
            private _fu = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedUnit", player];
            if (isNull _fu) then { _fu = player; };
            private _mates = [player] + ((units group player) select { alive _x && {_x isNotEqualTo player} && {(_x distance player) < 3000} });
            private _fn = _mates select (((_mates find _fu) + 1) mod (count _mates));
            // Libellés : ce que fait l'appui, en clair ; détail en infobulle. Groupes : cible, caméra, affichage.
            // [libellé, code, état ("" | "on" vert | "red"), aide, largeur relative]
            private _btns = [
                ["MARQUER LA CIBLE", ["lock"] call _act, "red", "Marque la cible sous le réticule (touche « Marquer la cible ») : LOCK suivi sur la carte. Clic sur l'image : la caméra vise ce point", 1.4],
                ["OUBLIER LES CIBLES", { ["release", ""] call comspec_atak_native_fnc_lockAction; }, "", "Retire le marquage de toutes les cibles marquées (LOCK)", 1.4],
                ["|"],
                [format ["VISION : %1", ["JOUR", "NUIT", "THERM."] select (missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0])], ["vision"] call _act, "", "Change le mode de la caméra : jour → nuit (intensification de lumière) → thermique", 1.3],
                ["ZOOM x1", ["zoom", 1] call _act, ["", "on"] select (_zoom isEqualTo 1), "Zoom de la caméra : x1 (champ large)", 0.8],
                ["x2", ["zoom", 2] call _act, ["", "on"] select (_zoom isEqualTo 2), "Zoom de la caméra : x2", 0.5],
                ["x4", ["zoom", 4] call _act, ["", "on"] select (_zoom isEqualTo 4), "Zoom de la caméra : x4", 0.5],
                ["CAMÉRA DEVANT", ["camAim", ""] call _act, "", "Remet la caméra du drone droit devant", 1.2],
                ["|"],
                // Vidéo affichée : drone → caméra du téléphone → caméra casque (ici seulement : l'app Drone montre toujours le drone).
                [["VIDÉO DU DRONE", "VIDÉO DU TÉLÉPHONE", "VIDÉO DU CASQUE"] select ((["DRONE", "PHONE", "HELMET"] find _fsrc) max 0), ["feedSrc", ["PHONE", "HELMET", "DRONE"] select ((["DRONE", "PHONE", "HELMET"] find _fsrc) max 0)] call _act, ["on", ""] select (_fsrc isEqualTo "DRONE"), "Choisit la vidéo affichée : drone → caméra du téléphone (devant vous) → caméra casque", 1.5]
            ] + ([[], [
                [format ["CASQUE DE %1", [name _fu, "MOI"] select (_fu isEqualTo player)], compile format ["['feedUnit', %1] call comspec_atak_native_fnc_droneAction;", str ([netId _fn, getPlayerUID _fn] select (isPlayer _fn))], "", "Porteur de la caméra casque : vous, puis les équipiers du groupe à moins de 3 km (appui suivant)", 1.4]
            ]] select (_fsrc isEqualTo "HELMET")) + [
                ["|"],
                ["PETITE FENÊTRE", ["view", "INSET"] call _act, "", "Réduit la vue du drone dans un coin de la carte", 1.2],
                ["RETOUR À LA CARTE", ["view", "OFF"] call _act, "", "Ferme la vue du drone et revient à la carte", 1.4]
            ];
            _s set ["backSub", ["MAP", ["view", "OFF"] call _act]];
            // Largeur de chaque bouton d'après son texte affiché (traduit) : chaque libellé tient en entier. Si la
            // rangée déborde, la police baisse pour tous ; s'il reste de la place, elle est partagée entre les boutons.
            private _gap = _pad * 0.8;
            private _fh0 = (_fs * 0.7) max COMSPEC_ATAK_FontMin;
            private _padX = _fs * 0.9 / _ratio;
            private _made = [];
            {
                if ((count _x) isEqualTo 1) then { continue; };
                _x params ["_lbl", "_code", "_state", "_tip"];
                private _b = ["COMSPEC_RscButton", [_bx, _by + _mh - _bh2 - _pad / 2, _mw, _bh2], _lbl] call _mk;
                _b ctrlSetFontHeight _fh0;
                _b ctrlSetBackgroundColor (switch (_state) do { case "on": { [0.36, 0.78, 0.42, 0.9] }; case "red": { [0.75, 0.18, 0.15, 0.95] }; default { [0.08, 0.1, 0.09, 0.9] }; });
                _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
                _b ctrlAddEventHandler ["ButtonClick", _code];
                _made pushBack [_b, ctrlTextWidth _b, _forEachIndex];
            } forEach _btns;
            private _avail = _mw - _pad * 2 - _gap * ({ (count _x) isEqualTo 1 } count _btns) - (_pad / 3) * (count _made);
            private _tsum = 0;
            { _tsum = _tsum + (_x select 1); } forEach _made;
            private _k = ((_avail - _padX * (count _made)) / (_tsum max 0.001)) min 1;
            private _fh = (_fh0 * _k) max COMSPEC_ATAK_FontMin;
            private _extra = ((_avail - (_tsum * _k + _padX * (count _made))) max 0) / ((count _made) max 1);
            private _byb = _by + _mh - _bh2 - _pad / 2;
            private _xb = _bx + _pad;
            private _last = -1;
            {
                _x params ["_b", "_tw", "_i"];
                // Séparateur « | » entre ce bouton et le précédent : un écart.
                if (_last >= 0 && {(_btns select [_last + 1, _i - _last - 1]) findIf { (count _x) isEqualTo 1 } >= 0}) then { _xb = _xb + _gap; };
                private _w = _tw * _k + _padX + _extra;
                _b ctrlSetFontHeight _fh;
                _b ctrlSetPosition [_xb, _byb, _w, _bh2];
                _b ctrlCommit 0;
                // Police plancher atteinte : dernier recours, on réduit encore ce seul bouton.
                private _f = _fh;
                while { (ctrlTextWidth _b) > _w * 0.96 && {_f > _fh * 0.6} } do { _f = _f * 0.92; _b ctrlSetFontHeight _f; };
                _xb = _xb + _w + _pad / 3;
                _last = _i;
            } forEach _made;
        };
    } else {
        // Incrustée : coin haut droit (à gauche de la barre d'outils en main). Téléphone en petit format : vignette
        // (retour de TTRD, 19/10 : l'incrustation prenait la moitié de l'écran), AGRANDIR la passe à la place de la carte.
        private _iw2 = _mw * ([0.42, 0.36] select _mini);
        private _ih2 = _iw2 * 0.5625 * _ratio;
        private _ix = _bx + _mw - _iw2 - _pad - ([0, ((((_mh - 2 * _pad) / 7) min (_font * 2.1)) / _ratio) + _pad] select _interactive);
        private _iy = _by + _pad;
        ["build", [_ix, _iy, _iw2, _ih2], "INSET", false] call comspec_atak_native_fnc_droneFlir;
        if (_interactive) then {
            private _bh3 = _fs * 1.2;
            private _b3w = _iw2 / 3;
            {
                _x params ["_lbl", "_code", "_col", "_tip"];
                private _b = ["COMSPEC_RscButton", [_ix + _forEachIndex * _b3w, _iy + _ih2, _b3w - pixelW * 2, _bh3], _lbl] call _mk;
                private _f = (_fs * 0.66) max COMSPEC_ATAK_FontMin;
                _b ctrlSetFontHeight _f;
                while { (ctrlTextWidth _b) > (_b3w - pixelW * 2) * 0.92 && {_f > _fs * 0.4} } do { _f = _f * 0.92; _b ctrlSetFontHeight _f; };
                _b ctrlSetBackgroundColor _col;
                _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
                _b ctrlAddEventHandler ["ButtonClick", _code];
            } forEach [
                ["MARQUER", ["lock"] call _act, [0.75, 0.18, 0.15, 0.95], "Marque la cible sous le réticule (LOCK suivi sur la carte)"],
                [["GRANDE VUE", "AGRANDIR"] select _mini, ["view", "FULL"] call _act, [0.08, 0.1, 0.09, 0.9], "La vue du drone remplace la carte"],
                [["FERMER LA VUE", "FERMER"] select _mini, ["view", "OFF"] call _act, [0.08, 0.1, 0.09, 0.9], "Ferme la vue du drone"]
            ];
        };
    };
};

// Caméra d'un équipier (BFT > piste > VOIR CAMÉRA, ou clic droit sur un allié) : petite fenêtre en haut à gauche de la
// carte, à droite de la boussole, avec le flux du Live cam (caméra poitrine, cible de rendu comspec_livecam).
// pageClear coupe le Live cam à chaque dessin : la caméra est relancée ici à chaque dessin de la carte.
private _camId = _s getOrDefault ["mapCam", ""];
if (_camId isNotEqualTo "") then {
    private _cu = objectFromNetId _camId;
    if (isNull _cu || {!alive _cu} || {!([_cu] call comspec_atak_native_fnc_hasDevice)}) exitWith {
        _s set ["mapCam", ""];
        ["WARNING", "Caméra de l'équipier : flux perdu", 3, 30] call comspec_atak_native_fnc_notify;
    };
    // Caméra de téléphone non partagée avec moi (fn_camShare) : pas de flux.
    if !(["can", _cu] call comspec_atak_native_fnc_camShare) exitWith {
        _s set ["mapCam", ""];
        ["WARNING", "Caméra non partagée : demandez à l'équipier de vous la partager (app Vidéo live > PARTAGER)", 4, 30] call comspec_atak_native_fnc_notify;
    };
    private _mini = _l get "mini";
    private _cmode = _s getOrDefault ["livecamMode", 0];
    [_cu, _cmode] call comspec_atak_native_fnc_livecamStart;
    private _cw2 = _mw * ([0.3, 0.46] select _mini);
    private _ch2 = _cw2 * 0.5625 * _ratio;
    private _cx2 = _bx + _pad * 2 + ((((_mh * 0.24) min (_font * 3.6)) / _ratio) * ([1, 0] select _mini));
    private _cy2 = _by + _pad;
    private _cbg = ["COMSPEC_RscText", [_cx2 - pixelW, _cy2 - pixelH, _cw2 + pixelW * 2, _ch2 + pixelH * 2]] call _mk;
    _cbg ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9];
    private _cblack = ["COMSPEC_RscText", [_cx2, _cy2, _cw2, _ch2]] call _mk;
    _cblack ctrlSetBackgroundColor [0, 0, 0, 1];
    ["COMSPEC_RscPhone", [_cx2, _cy2, _cw2, _ch2], "#(argb,1024,1024,1)r2t(comspec_livecam,1.7778)"] call _mk;
    // Incrustation (indicatif, grille, distance, cap) : tenue à jour chaque seconde par la boucle du Live cam.
    private _cosd = ["COMSPEC_RscStructuredText", [_cx2 + pixelW * 4, _cy2 + pixelH * 3, _cw2 - pixelW * 8, _fs * 3.2]] call _mk;
    _cosd ctrlSetFontHeight ((_fs * ([0.85, 0.7] select _mini)) max COMSPEC_ATAK_FontMin);
    _cosd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#e5483a'>● LIVE</t>  <t font='COMSPEC_BarlowBold'>%1</t>",
        [_cu, true] call comspec_atak_native_fnc_unitCallsign]) call comspec_atak_native_fnc_tr);
    uiNamespace setVariable ["COMSPEC_ATAK_LivecamOsd", _cosd];
    if (_interactive) then {
        private _bh4 = _fs * 1.2;
        private _b4w = _cw2 / 3;
        {
            _x params ["_lbl", "_code", "_tip"];
            private _b = ["COMSPEC_RscButton", [_cx2 + _forEachIndex * _b4w, _cy2 + _ch2 + pixelH * 2, _b4w - pixelW * 2, _bh4], _lbl] call _mk;
            _b ctrlSetFontHeight ((_fs * 0.66) max COMSPEC_ATAK_FontMin);
            _b ctrlSetBackgroundColor [0.08, 0.1, 0.09, 0.92];
            _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
            _b ctrlAddEventHandler ["ButtonClick", _code];
        } forEach [
            [["JOUR", "VISION NOCT.", "THERMIQUE"] select _cmode, {
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                _s set ["livecamMode", ((_s getOrDefault ["livecamMode", 0]) + 1) mod 3];
                [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }, "Change le mode de la caméra : jour → vision nocturne → thermique"],
            ["GRAND", {
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                _s set ["livecamTarget", _s getOrDefault ["mapCam", ""]];
                _s set ["mapCam", ""];
                [{ ["LIVECAM"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
            }, "Ouvre la caméra de l'équipier en grand (app Vidéo live)"],
            ["FERMER", {
                (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapCam", ""];
                [] call comspec_atak_native_fnc_livecamStop;
                [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }, "Ferme la caméra de l'équipier"]
        ];
    };
};

// Menu contextuel du clic droit (PING / MARQUEUR / SUPPRIMER), par-dessus tout le reste.
if (_interactive) then { ["render"] call comspec_atak_native_fnc_mapCtxMenu; };
uiNamespace setVariable ["COMSPEC_ATAK_MapOverlay", _ov];

if !(_s getOrDefault ["mapCentered", false]) then {
    _s set ["mapCentered", true];
    [player, uiNamespace getVariable ["COMSPEC_ATAK_MapScale", 0.08]] call comspec_atak_native_fnc_mapCenter;
};
[] call comspec_atak_native_fnc_mapOverlayUpdate;
if !(_l get "mini") then { [] call comspec_atak_native_fnc_inspectorUpdate; };
[] call comspec_atak_native_fnc_mapUnfocus;
true
