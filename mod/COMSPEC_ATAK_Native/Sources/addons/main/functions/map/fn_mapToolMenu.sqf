/* Menu « Outils carte » : une table unique (["table"]) décrit chaque entrée, le menu, les touches et la barre de droite
   la relisent. Les outils à clic restent actifs jusqu'au clic droit, Échap ou ✕.
   Une entrée : [id, groupe, libellé court, info-bulle, icône, type, raccourci, condition d'affichage]
     type « outil »   : mode de la carte (mapMode), actif jusqu'à la sortie ; surligné dans le menu et la barre ;
          « bascule » : calque ou réglage d'affichage, pastille d'état ; le menu reste ouvert ;
          « action »  : effet immédiat (recherche, QR code, tout effacer en deux appuis).
     raccourci : "1" à "9" puis "0" (touches du haut du clavier), "⇧1"… (Maj + chiffre), "" sans touche.
   ["table"] / ["visible"] -> la table entière / les entrées dont la condition est vraie, dans l'ordre du menu.
   ["groups"]   -> [[groupe, titre]] dans l'ordre du menu.
   ["numbered"] -> ids des touches 1 à 9 puis 0, dans l'ordre (mêmes numéros dans le menu et au clavier).
   ["key", n] / ["key2", n] -> touche n (1 à 9, 10 = 0) seule / avec Maj ; false si la carte n'est pas l'app ouverte, en main.
   ["esc"]      -> Échap : ferme le menu, sinon quitte l'outil actif ; false si rien à fermer.
   ["close"]    -> ferme le menu (clic sur la carte hors du panneau, second appui sur OUTILS).
   ["active", id] -> l'entrée est-elle allumée (outil en cours ou bascule active).
   ["build", [x droite, y haut, y bas, largeur carte, hauteur carte, police, petite police, ratio, marge]] -> dessine le
   menu (à l'ouverture, jamais par image) ; ["filter"] relit le champ de recherche et redessine les lignes seulement.
   Autre id -> l'entrée de la table (ou MARKER / PING de la barre). */
params ["_key", ["_n", 0]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _always = { true };
private _table = [
    ["ROUTE", "ALLER", "GPS : itinéraire", "Itinéraire GPS jusqu'au point cliqué sur la carte", "map_route", "outil", "1", _always],
    ["WP", "ALLER", "Points de passage", "Poser et modifier une série de points de passage", "app_waypoints", "outil", "2", _always],
    ["SEARCH", "ALLER", "Rechercher", "Rechercher et localiser : IMEI, numéro de téléphone, balise GPS, indicatif ou nom", "app_osint", "action", "3", _always],
    ["MEASURE", "MESURER", "Mesure A-B", "Distance et azimut entre deux points cliqués", "map_measure", "outil", "4", _always],
    ["HEIGHT", "MESURER", "Hauteur", "Altitude du point cliqué", "map_height", "outil", "5", _always],
    ["LOS", "MESURER", "Ligne de vue", "Ligne de vue de ma position jusqu'au point cliqué", "map_los", "outil", "6", _always],
    ["DISTANCE", "MESURER", "Distance au curseur", "Distance et cap de ma position jusqu'au curseur", "map_distance", "bascule", "⇧2", _always],
    ["FLAT", "PLANIFIER", "Terrain plat (LZ)", "Chercher un terrain plat autour du point cliqué ; clic sur une LZ = marqueur", "map_flat", "outil", "7", _always],
    ["IPTGT", "PLANIFIER", "JTAC : IP → cible", "IP puis cible : cap magnétique, distance et altitude", "app_jtac", "outil", "8", _always],
    ["DRONE", "PLANIFIER", "Ordres au drone", "Drone appairé : aller ici, point de route ou zone, d'un clic sur la carte", "app_drone", "outil", "", { alive (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]) }],
    ["LINE", "DESSINER", "Tracer un trait", "Clic A, Ctrl + clic pour une étape, clic B", "map_measure", "outil", "9", _always],
    ["DRAW", "DESSINER", "Dessin libre", "Maintenir le clic gauche et dessiner ; lissé au relâchement", "map_labels", "outil", "0", _always],
    ["HOUSES", "DESSINER", "Marquer bâtiments", "Numéroter les bâtiments autour du point cliqué", "map_house", "outil", "⇧1", _always],
    ["COMPASS", "AFFICHER", "Boussole", "Afficher la boussole sur la carte", "map_compass", "bascule", "⇧3", _always],
    ["GRID", "AFFICHER", "Grille %1 chiffres", "Précision des coordonnées : 6, 8 puis 10 chiffres", "map_grid", "bascule", "⇧4", _always],
    ["ZONES", "AFFICHER", "Zones tactiques", "Afficher les zones reçues d'Athena", "map_grid", "bascule", "⇧5", _always],
    ["SIGINT", "AFFICHER", "Goniométrie (émetteurs)", "Afficher les relèvements des émetteurs détectés", "map_los", "bascule", "⇧6", _always],
    ["LABELS", "AFFICHER", "Indicatifs des unités", "Afficher les indicatifs à côté des unités", "map_labels", "bascule", "⇧7", _always],
    ["PHONE", "PARTAGER", "Sur mon téléphone", "La carte de la partie sur votre vrai téléphone, par un QR code", "app_athena", "action", "⇧8", _always],
    ["CLEAR", "PARTAGER", "Tout effacer", "Efface mesures, bâtiments, hauteurs, terrains plats, lignes de vue et calcul JTAC (deux appuis)", "map_clear", "action", "", _always]
];
private _groups = [["ALLER", "ALLER"], ["MESURER", "MESURER"], ["PLANIFIER", "PLANIFIER"], ["DESSINER", "DESSINER"], ["AFFICHER", "AFFICHER"], ["PARTAGER", "PARTAGER / DONNÉES"]];
private _keys = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "0"];
private _onMap = { (_s getOrDefault ["activePage", ""]) isEqualTo "MAP" && {_s getOrDefault ["interactive", false]} };
private _render = { [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
// Entrée allumée : outil en cours, ou bascule active.
private _isOn = {
    params ["_id"];
    switch (_id) do {
        case "DISTANCE": { _s getOrDefault ["mapDistance", true] };
        case "COMPASS": { (["COMSPEC_ATAK_Compass", true, "native_compass"] call comspec_atak_native_fnc_pref) select 0 };
        case "GRID": { (profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]) > 6 };
        case "ZONES": { profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true] };
        case "SIGINT": { profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true] };
        case "LABELS": { (["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 0 };
        default { (_s getOrDefault ["mapMode", "SELECT"]) isEqualTo _id };
    }
};
if (_key isEqualTo "table") exitWith { _table };
if (_key isEqualTo "groups") exitWith { _groups };
if (_key isEqualTo "visible") exitWith { _table select { call (_x select 7) } };
if (_key isEqualTo "numbered") exitWith { _keys apply { private _k = _x; private _i = _table findIf { (_x select 6) isEqualTo _k }; [(_table select _i) select 0, ""] select (_i < 0) } };
if (_key isEqualTo "active") exitWith { [_n] call _isOn };
if (_key in ["key", "key2"]) exitWith {
    if !(call _onMap) exitWith { false };
    if (_n < 1 || {_n > 10}) exitWith { false };
    private _k = (["", "⇧"] select (_key isEqualTo "key2")) + (_keys select (_n - 1));
    private _i = _table findIf { (_x select 6) isEqualTo _k && {call (_x select 7)} };
    if (_i < 0) exitWith { false };
    [{ [_this] call comspec_atak_native_fnc_mapToolMenu; }, (_table select _i) select 0] call comspec_atak_native_fnc_nextFrame;
    true
};
if (_key isEqualTo "close") exitWith {
    if !(_s getOrDefault ["mapToolsOpen", false]) exitWith { false };
    _s set ["mapToolsOpen", false];
    _s set ["mapToolsRect", []];
    call _render;
    true
};
if (_key isEqualTo "esc") exitWith {
    if !(call _onMap) exitWith { false };
    if (_s getOrDefault ["mapToolsOpen", false]) exitWith { ["close"] call comspec_atak_native_fnc_mapToolMenu };
    if ((_s getOrDefault ["mapMode", "SELECT"]) in ["SELECT", "MARKER", "WP"]) exitWith { false };
    _s set ["drawStroke", []];
    ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
    call _render;
    true
};

if (_key in ["build", "filter"]) exitWith {
    disableSerialization;
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d) exitWith { false };
    private _ui = uiNamespace getVariable ["COMSPEC_ATAK_MapToolsUi", createHashMap];
    if (_key isEqualTo "build") then {
        _ui = createHashMap;
        uiNamespace setVariable ["COMSPEC_ATAK_MapToolsUi", _ui];
        _n params ["_right", "_top", "_bottom", "_mw", "_mh", "_font", "_fs", "_ratio", "_pad"];
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
        private _vis = _table select { call (_x select 7) };
        // Paysage : 3 colonnes au plus, sur 60 % de la largeur ; portrait : 1 ou 2 colonnes, sur 90 %, avec défilement.
        private _land = (_mw / pixelW) > (_mh / pixelH) * 1.1;
        private _rh = _fs * 1.6;
        private _ih = _rh * 0.62;
        // Largeur d'une colonne : le libellé le plus long à la taille du menu, plus l'icône, la pastille et la touche.
        private _lw = 0;
        { _lw = _lw max ([(format [_x select 2, profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]]) call comspec_atak_native_fnc_tr, COMSPEC_ATAK_FontSet select 0, _fs] call comspec_atak_native_fnc_mapTextW); } forEach _vis;
        private _fixed = _pad * 2.5 + _ih / _ratio + _rh * 1.1 / _ratio + _fs * 1.6 / _ratio;
        private _colW = _lw + _fixed;
        private _maxW = _mw * ([0.9, 0.6] select _land);
        private _cols = [2, 3] select _land;
        while { _cols > 1 && {_cols * _colW > _maxW} } do { _cols = _cols - 1; };
        // Une seule colonne encore trop large : la colonne prend la place permise, la police du libellé se réduit.
        private _lfs = _fs;
        if (_colW > _maxW) then { _lfs = ((_fs * (_maxW - _fixed) / (_lw max 0.001)) max (COMSPEC_ATAK_FontMin max (_fs * 0.75))) min _fs; _colW = _maxW; };
        private _pw = _cols * _colW + _pad;
        private _hh = _fs * 1.7;
        private _x0 = _right - _pw;
        // Fond, titre, recherche (plus de 12 entrées), fermeture.
        private _bg = ["COMSPEC_RscMapPanel", [_x0, _top, _pw, _bottom - _top]] call _mk;
        _bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.96];
        private _ttl = ["COMSPEC_RscText", [_x0 + _pad, _top, _fs * 6 / _ratio, _hh], "OUTILS"] call _mk;
        _ttl ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _ttl ctrlSetFontHeight _fs;
        _ttl ctrlSetTextColor (COMSPEC_ATAK_Th get "mapText");
        private _xw = _hh / _ratio;
        private _xb = ["COMSPEC_RscButton", [_x0 + _pw - _xw, _top, _xw, _hh], "✕"] call _mk;
        _xb ctrlSetFontHeight _fs;
        _xb ctrlSetTooltip (("Fermer le menu (Échap)") call comspec_atak_native_fnc_tr);
        _xb ctrlAddEventHandler ["ButtonClick", { ["close"] call comspec_atak_native_fnc_mapToolMenu; }];
        if ((count _vis) > 12) then {
            private _ex = _x0 + _pad + _fs * 6 / _ratio;
            private _ed = ["COMSPEC_RscEdit", [_ex, _top + _hh * 0.14, (_x0 + _pw - _xw - _pad / 2) - _ex, _hh * 0.72]] call _mk;
            _ed ctrlSetFontHeight (_fs * 0.9);
            _ed ctrlSetText (_s getOrDefault ["mapToolsFilter", ""]);
            _ed ctrlSetTooltip (("Chercher un outil") call comspec_atak_native_fnc_tr);
            _ed ctrlAddEventHandler ["KeyUp", { ["filter"] call comspec_atak_native_fnc_mapToolMenu; false }];
            // Entrée : la première entrée trouvée.
            _ed ctrlAddEventHandler ["KeyDown", {
                params ["", "_k"];
                if !(_k in [28, 156]) exitWith { false };
                private _first = (uiNamespace getVariable ["COMSPEC_ATAK_MapToolsUi", createHashMap]) getOrDefault ["first", ""];
                if (_first isNotEqualTo "") then { [{ [_this] call comspec_atak_native_fnc_mapToolMenu; }, _first] call comspec_atak_native_fnc_nextFrame; };
                true
            }];
            _ui set ["edit", _ed];
        };
        private _grp = ["COMSPEC_RscControlsGroup", [_x0, _top + _hh, _pw, _bottom - _top - _hh]] call _mk;
        _ui set ["grp", _grp];
        _ui set ["geom", [_cols, _colW, _rh, _ih, _lfs, _fs, _ratio, _pad, _pw, _bottom - _top - _hh]];
        // Zone du menu : un clic de la carte dedans n'agit pas sur la carte (fn_mapMouseButtonUp).
        _s set ["mapToolsRect", [_x0, _top, _pw, _bottom - _top]];
    };
    private _grp = _ui getOrDefault ["grp", controlNull];
    if (isNull _grp) exitWith { false };
    if (_key isEqualTo "filter") then {
        _s set ["mapToolsFilter", ctrlText (_ui getOrDefault ["edit", controlNull])];
        { if ((ctrlParentControlsGroup _x) isEqualTo _grp) then { ctrlDelete _x; }; } forEach (allControls _d);
    };
    (_ui get "geom") params ["_cols", "_colW", "_rh", "_ih", "_lfs", "_fs", "_ratio", "_pad", "_pw", "_gh"];
    private _dir = "\z\comspec_atak_native\addons\main\data\";
    private _mk = {
        params ["_class", "_pos", ["_text", ""]];
        private _c = _d ctrlCreate [_class, -1, _grp];
        [_c, _class] call comspec_atak_native_fnc_fontApply;
        _c ctrlSetPosition _pos;
        if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
        _c ctrlCommit 0;
        _c
    };
    private _vis = _table select { call (_x select 7) };
    private _q = toLower (trim (_s getOrDefault ["mapToolsFilter", ""]));
    private _mode = _s getOrDefault ["mapMode", "SELECT"];
    private _armed = (diag_tickTime - (_s getOrDefault ["mapClearArm", -10])) < 3;
    // Une ligne d'entrée, coordonnées dans le groupe.
    private _row = {
        params ["_e", "_rx", "_ry"];
        _e params ["_id", "", "_label", "_tip", "_icon", "_type", "_hot"];
        private _on = [_id] call _isOn;
        private _w = _colW - _pad / 2;
        private _danger = _id isEqualTo "CLEAR";
        private _txtCol = [COMSPEC_ATAK_Th get "mapText", COMSPEC_ATAK_Th get "mapOk"] select (_on && {_type isEqualTo "outil"});
        if (_danger) then { _txtCol = ((COMSPEC_ATAK_Th get "mapAlert") select [0, 3]) + [0.85]; };
        // Outil en cours : fond et filet à gauche, comme la pastille de l'outil sur la carte.
        if (_on && {_type isEqualTo "outil"}) then {
            private _hl = ["COMSPEC_RscText", [_rx, _ry, _w, _rh]] call _mk;
            _hl ctrlSetBackgroundColor (((COMSPEC_ATAK_Th get "accent") select [0, 3]) + [0.18]);
            private _eg = ["COMSPEC_RscText", [_rx, _ry, pixelW * 3, _rh]] call _mk;
            _eg ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "mapOk");
        };
        if (_danger && {_armed}) then {
            private _hl = ["COMSPEC_RscText", [_rx, _ry, _w, _rh]] call _mk;
            _hl ctrlSetBackgroundColor (((COMSPEC_ATAK_Th get "mapAlert") select [0, 3]) + [0.22]);
        };
        // Icônes toutes à la même taille.
        private _ic = ["COMSPEC_RscIcon", [_rx + _pad / 2, _ry + (_rh - _ih) / 2, _ih / _ratio, _ih], _dir + _icon + ".paa"] call _mk;
        _ic ctrlSetTextColor _txtCol;
        private _lx = _rx + _pad + _ih / _ratio;
        private _hotW = _fs * 1.6 / _ratio;
        private _pillW = [0, _rh * 1.1 / _ratio] select (_type isEqualTo "bascule");
        private _txt = [(format [_label, profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]]) call comspec_atak_native_fnc_tr, ("Confirmer ? (3 s)") call comspec_atak_native_fnc_tr] select (_danger && {_armed});
        private _t = ["COMSPEC_RscText", [_lx, _ry, (_w - (_lx - _rx) - _pillW - _hotW) max 0, _rh]] call _mk;
        _t ctrlSetText _txt;
        _t ctrlSetFontHeight _lfs;
        _t ctrlSetTextColor _txtCol;
        // Bascule : pastille d'état (allumée = verte, curseur à droite), jamais confondue avec un outil.
        if (_type isEqualTo "bascule") then {
            private _px = _rx + _w - _hotW - _pillW;
            private _ph = _rh * 0.42;
            private _py = _ry + (_rh - _ph) / 2;
            private _pb = ["COMSPEC_RscText", [_px, _py, _pillW * 0.85, _ph]] call _mk;
            _pb ctrlSetBackgroundColor ([[0.55, 0.58, 0.56, 0.35], ((COMSPEC_ATAK_Th get "mapOk") select [0, 3]) + [0.85]] select _on);
            private _kw = _ph * 0.8 / _ratio;
            private _kn = ["COMSPEC_RscText", [[_px + pixelW * 2, _px + _pillW * 0.85 - _kw - pixelW * 2] select _on, _py + _ph * 0.1, _kw, _ph * 0.8]] call _mk;
            _kn ctrlSetBackgroundColor [0.93, 0.95, 0.94, 0.95];
        };
        if (_hot isNotEqualTo "") then {
            private _hk = ["COMSPEC_RscText", [_rx + _w - _hotW, _ry, _hotW, _rh]] call _mk;
            _hk ctrlSetText _hot;
            _hk ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
            _hk ctrlSetTextColor (COMSPEC_ATAK_Th get "mapDim");
        };
        private _b = ["COMSPEC_RscButtonOverlay", [_rx, _ry, _w, _rh]] call _mk;
        _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
        _b setVariable ["key", _id];
        _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "key"] call comspec_atak_native_fnc_mapToolMenu; }];
    };
    // Titre de rubrique : le nom du groupe et un filet, à la hauteur d'une ligne (rubrique du menu, pas un surtitre).
    private _head = {
        params ["_title", "_rx", "_ry", "_w"];
        private _h = ["COMSPEC_RscText", [_rx + _pad / 2, _ry + _rh * 0.2, _w - _pad, _rh * 0.8], _title] call _mk;
        _h ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _h ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
        _h ctrlSetTextColor (COMSPEC_ATAK_Th get "mapDim");
        private _ln = ["COMSPEC_RscText", [_rx + _pad / 2, _ry + _rh - pixelH, _w - _pad, pixelH]] call _mk;
        _ln ctrlSetBackgroundColor (((COMSPEC_ATAK_Th get "accent") select [0, 3]) + [0.25]);
    };
    private _x0 = _pad / 2;
    private _y = 0;
    _ui set ["first", ""];
    if (_q isNotEqualTo "") then {
        // Recherche : les entrées trouvées (libellé, info-bulle ou rubrique), sans rubriques, en colonnes.
        private _hits = _vis select {
            private _e = _x;
            ((toLower ((format [_e select 2, ""]) call comspec_atak_native_fnc_tr)) find _q) >= 0
                || {((toLower ((_e select 3) call comspec_atak_native_fnc_tr)) find _q) >= 0}
                || {((toLower (_e select 1)) find _q) >= 0}
        };
        if ((count _hits) isEqualTo 0) exitWith {
            private _no = ["COMSPEC_RscText", [_x0, 0, _pw - _pad, _rh], "Aucun outil trouvé"] call _mk;
            _no ctrlSetFontHeight _fs;
            _no ctrlSetTextColor (COMSPEC_ATAK_Th get "mapDim");
        };
        _ui set ["first", (_hits select 0) select 0];
        { [_x, _x0 + (_forEachIndex mod _cols) * _colW, (floor (_forEachIndex / _cols)) * _rh] call _row; } forEach _hits;
    } else {
        // Les trois derniers outils utilisés, en tête, sur une rangée.
        private _rec = ((profileNamespace getVariable ["COMSPEC_ATAK_MapToolRecent", []]) apply { private _r = _x; _vis findIf { (_x select 0) isEqualTo _r } }) select { _x >= 0 };
        if ((count _rec) > 0) then {
            ["RÉCENTS", _x0, 0, _pw - _pad] call _head;
            _y = _rh;
            { [_vis select _x, _x0 + (_forEachIndex mod _cols) * _colW, _y + (floor (_forEachIndex / _cols)) * _rh] call _row; } forEach (_rec select [0, 3]);
            _y = _y + (ceil (((count _rec) min 3) / _cols)) * _rh + _rh * 0.3;
        };
        // Rubriques réparties en colonnes équilibrées, sans couper une rubrique ; titres alignés en haut de chaque colonne.
        private _blocks = (_groups apply { private _g = _x select 0; [_x select 1, _vis select { (_x select 1) isEqualTo _g }] }) select { (count (_x select 1)) > 0 };
        private _total = 0;
        { _total = _total + 1 + (count (_x select 1)); } forEach _blocks;
        private _target = _total / _cols;
        private _col = 0;
        private _used = 0;
        private _y0 = _y;
        private _maxY = _y;
        {
            _x params ["_title", "_items"];
            private _hgt = 1 + (count _items);
            if (_used > 0 && {_col < (_cols - 1)} && {(_used + _hgt / 2) > _target}) then { _col = _col + 1; _used = 0; };
            private _cx = _x0 + _col * _colW;
            private _cy = _y0 + _used * _rh;
            [_title, _cx, _cy, _colW] call _head;
            {
                // Action destructive : séparée des autres par un filet, en bas de sa rubrique.
                if ((_x select 0) isEqualTo "CLEAR") then {
                    private _sp = ["COMSPEC_RscText", [_cx + _pad / 2, _cy + (_forEachIndex + 1) * _rh + _rh * 0.12, _colW - _pad, pixelH]] call _mk;
                    _sp ctrlSetBackgroundColor (((COMSPEC_ATAK_Th get "mapAlert") select [0, 3]) + [0.35]);
                    _used = _used + 0.25;
                    _cy = _cy + _rh * 0.25;
                };
                [_x, _cx, _cy + (_forEachIndex + 1) * _rh] call _row;
            } forEach _items;
            _used = _used + _hgt;
            _maxY = _maxY max (_y0 + _used * _rh);
        } forEach _blocks;
        _y = _maxY;
    };
    true
};

// Entrées utilisées : les trois dernières en tête du menu (tout sauf « Tout effacer »).
if (_key isNotEqualTo "CLEAR" && {(_table findIf { (_x select 0) isEqualTo _key }) >= 0}) then {
    private _rec = (profileNamespace getVariable ["COMSPEC_ATAK_MapToolRecent", []]) - [_key];
    profileNamespace setVariable ["COMSPEC_ATAK_MapToolRecent", ([_key] + _rec) select [0, 3]];
};
switch (_key) do {
    case "DISTANCE": { _s set ["mapDistance", !(_s getOrDefault ["mapDistance", true])]; };
    case "COMPASS": { if ((["COMSPEC_ATAK_Compass", true, "native_compass"] call comspec_atak_native_fnc_pref) select 1) then { ["INFO", "Réglage imposé par votre communauté", 3, 20] call comspec_atak_native_fnc_notify; } else { profileNamespace setVariable ["COMSPEC_ATAK_Compass", !(profileNamespace getVariable ["COMSPEC_ATAK_Compass", true])]; }; };
    case "ZONES": {
        private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true]);
        profileNamespace setVariable ["COMSPEC_ATAK_ZonesLayer", _on];
        // Marqueurs COMSPEC_TZ_<id> d'Overwatch : jamais touchés ici, fn_mapGameMarkers et fn_mapMarkerText les sautent à l'affichage.
        private _n = (count (missionNamespace getVariable ["COMSPEC_DangerZones", []])) + (count (missionNamespace getVariable ["COMSPEC_RoleplayZones", []]));
        ["INFO", if (_on) then { [format ["Zones tactiques affichées (%1)", _n], "Zones tactiques affichées : aucune zone reçue d'Athena pour cette carte"] select (_n isEqualTo 0) } else { format ["Zones tactiques masquées (%1)", _n] }, 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "SEARCH": { _s set ["mapToolsOpen", false]; ["open"] call comspec_atak_native_fnc_mapSearch; };
    case "LABELS": {
        if ((["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 1) exitWith { ["INFO", "Réglage imposé par votre communauté", 3, 20] call comspec_atak_native_fnc_notify; };
        profileNamespace setVariable ["COMSPEC_ATAK_Labels", !(profileNamespace getVariable ["COMSPEC_ATAK_Labels", true])];
    };
    // « Sur mon téléphone » (2.9.39) : la carte de la partie sur le vrai téléphone, par un QR code (fn_mobileQr).
    case "PHONE": {
        _s set ["mapToolsOpen", false];
        [{ ["open", "carte"] call comspec_atak_native_fnc_mobileQr; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "SIGINT": { profileNamespace setVariable ["COMSPEC_ATAK_SigintLayer", !(profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true])]; [] spawn comspec_atak_native_fnc_sigintPoll; };
    case "GRID": {
        private _d = profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6];
        profileNamespace setVariable ["COMSPEC_ATAK_GridDigits", [8, 10, 6] select (([6, 8, 10] find _d) max 0)];
    };
    // Tout effacer : deux appuis. Le premier arme « Confirmer ? » 3 s (comme TOUT EFFACER de l'app Plan), le second efface.
    case "CLEAR": {
        if ((diag_tickTime - (_s getOrDefault ["mapClearArm", -10])) >= 3) then {
            _s set ["mapClearArm", diag_tickTime];
            // Passé les 3 s sans second appui : le libellé redevient « Tout effacer ».
            [{
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                if ((diag_tickTime - (_s getOrDefault ["mapClearArm", -10])) >= 2.9 && {_s getOrDefault ["mapToolsOpen", false]}) then { ["MAP"] call comspec_atak_native_fnc_pageRender; };
            }, [], 3.1] call comspec_atak_native_fnc_waitAndExec;
        } else {
            _s set ["mapClearArm", -10];
            ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
            { _s set [_x, []]; } forEach ["mapMeasure", "mapHouses", "mapHeights", "mapFlat", "mapFlatTxt", "mapLos", "jtacTool"];
            ["INFO", "Mesures et relevés effacés", 2, 10] call comspec_atak_native_fnc_notify;
        };
    };
    default {
        private _cur = _s getOrDefault ["mapMode", "SELECT"];
        [["SELECT", _key] select (_cur isNotEqualTo _key)] call comspec_atak_native_fnc_mapToolSet;
        if (_key isNotEqualTo "MARKER") then { _s set ["mapToolsOpen", false]; };
    };
};
call _render;
true
