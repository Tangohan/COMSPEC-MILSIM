/*
    App Distances : pas, kilomètres à pied, en véhicule terrestre, en bateau, en vol et en saut (HALO, sous voile),
    temps à pied et heures de pilote par appareil, dessinés en tableau de bord :
      CETTE MISSION          distance totale en grand, pas, temps en mouvement, carrière, répartition par mode ;
      PAR MODE               une carte par mode (à pied en large : pas, temps, allure, cadence), part du total ;
      HEURES DE PILOTE       tableau par appareil, cette mission et carrière ;
      DERNIÈRES MISSIONS     barres empilées par mode des 10 dernières missions (mission en cours en couleur) ;
      MISSION ET CARRIÈRE    chaque mode, cette mission face au cumul, records d'une mission ;
      ATHENA                 dernier envoi du serveur au site.
    Mission : comptée par le serveur (fn_distanceServer, reçue toutes les 15 s dans COMSPEC_ATAK_DistMe).
    Carrière, dernières missions et records : carnet gardé sur ce PC (fn_distanceLog) ; le cumul de toutes les missions
    est aussi sur la fiche du membre (site Athena).
    Params (module, fn_pageRender) : [page, [x, y, largeur, hauteur]] (non utilisés : dessin dans la zone de contenu)
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _f = _l get "font";
private _pad = (_l get "pad") * 2;
// Carré : largeur = hauteur / ratio (COMSPEC_ATAK_Ratio = pixelH / pixelW) ; _fx = hauteur de police en unités x.
private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
private _fx = _f / _ratio;
// Le texte structuré a pour taille de base 0.024 * safeZoneH (classe COMSPEC_RscStructuredText) : les tailles
// demandées ci-dessous (en multiples de la police du téléphone) sont ramenées à cette base.
private _k = _f / (0.024 * safeZoneH);
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isEqualTo "fr";
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_DistBk" }) then { ["tick"] call comspec_atak_native_fnc_distanceLog; };
private _book = missionNamespace getVariable ["COMSPEC_ATAK_DistBk", createHashMap];
private _life = _book getOrDefault ["life", createHashMap];
private _lifePilot = _book getOrDefault ["pilot", createHashMap];
private _rec = _book getOrDefault ["rec", createHashMap];
private _missions = _book getOrDefault ["missions", []];
private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]];
(player getVariable ["COMSPEC_ATAK_DistMe", [[], []]]) params [["_pairs", []], ["_pilot", []]];
private _c = createHashMapFromArray _pairs;
private _get = { params ["_key"]; _c getOrDefault [_key, 0] };

// --- Couleurs ---
private _rgb = {
    params ["_h", ["_a", 1]];
    private _d = "0123456789abcdef";
    private _s = toLower (_h select [1]);
    private _v = [];
    for "_i" from 0 to 4 step 2 do { _v pushBack ((((_d find (_s select [_i, 1])) max 0) * 16 + ((_d find (_s select [_i + 1, 1])) max 0)) / 255); };
    _v + [_a]
};
private _cBg = [0.035, 0.045, 0.04, 1];
private _cCard = [0.07, 0.088, 0.079, 1];
private _cCard2 = [0.095, 0.116, 0.105, 1];
private _cTrack = [0.15, 0.18, 0.165, 1];
private _hText = "#eef4f0";
private _hDim = "#8a9a93";
private _hGold = "#f2c94c";
private _hAmber = "#f2ab33";
// Modes : clé, libellé, icône, couleur (fixes, distinctes de la couleur d'accent choisie).
private _modes = [
    ["foot", "À pied", "g_walk", "#5cc76b"],
    ["land", "Véhicule terrestre", "g_drive", "#e0a83a"],
    ["sea", "Bateau", "g_boat", "#4d9ffa"],
    ["air", "Vol", "g_flight", "#ad73f2"],
    ["halo", "Saut", "g_jump", "#2ec4b6"]
];
private _data = "\z\comspec_atak_native\addons\main\data\";

// --- Mise en forme ---
private _two = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
private _dec = { params ["_v", "_n"]; private _s = _v toFixed _n; if (_fr) then { _s = [_s, ".", ","] call CBA_fnc_replace; }; _s };
private _thou = {
    params ["_n"];
    private _s = str (round _n);
    private _o = "";
    private _j = 0;
    for "_i" from ((count _s) - 1) to 0 step -1 do { _o = (_s select [_i, 1]) + _o; _j = _j + 1; if (_j mod 3 isEqualTo 0 && {_i > 0}) then { _o = ([",", " "] select _fr) + _o; }; };
    _o
};
// Distance : [nombre, unité] (m sous 1 km, une décimale sous 100 km).
private _dist = {
    params ["_m"];
    switch (true) do {
        case (_m < 1000): { [str round _m, "m"] };
        case (_m < 100000): { [[_m / 1000, 1] call _dec, "km"] };
        default { [[_m / 1000] call _thou, "km"] };
    }
};
private _distS = { params ["_m"]; ([_m] call _dist) joinString " " };
private _dur = {
    params ["_sec"];
    private _m = floor (_sec / 60);
    switch (true) do { case (_sec < 1): { "—" }; case (_sec < 60): { format ["%1 s", round _sec] }; case (_m < 60): { format ["%1 min", _m] }; default { format ["%1 h %2", floor (_m / 60), [_m mod 60] call _two] }; }
};
private _dayS = { params ["_d"]; (_d splitString "-") params [["_yy", ""], ["_mm", ""], ["_dd", ""]]; format ["%1/%2", _dd, _mm] };
// Texte structuré : [texte, taille (× police du téléphone), couleur, gras, alignement]
// Texte brut (nom de mission, d'appareil, carnet) : &, < et > échappés ; entités déjà échappées gardées.
private _t = {
    params ["_s", ["_size", 1], ["_hex", ""], ["_bold", false], ["_align", ""]];
    _s = [str _s, _s] select (_s isEqualType "");
    _s = _s regexReplace ["&(?!(amp|lt|gt|quot|apos|#[0-9]+);)", "&amp;"];
    _s = [[_s, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace;
    format ["<t size='%1'%2%3%4>%5</t>", (_size * _k) toFixed 3, ["", format [" color='%1'", _hex]] select (_hex isNotEqualTo ""),
        ["", " font='COMSPEC_BarlowBold'"] select _bold, ["", format [" align='%1'", _align]] select (_align isNotEqualTo ""), _s]
};

// --- Briques de dessin (coordonnées de la zone de contenu, défilante) ---
private _box = { params ["_x", "_y", "_w", "_h", "_col"]; private _o = ["COMSPEC_RscText", [_x, _y, _w, _h]] call comspec_atak_native_fnc_pageCtrl; _o ctrlSetBackgroundColor _col; _o };
private _txt = { params ["_x", "_y", "_w", "_h", "_s"]; private _o = ["COMSPEC_RscStructuredText", [_x, _y, _w, _h]] call comspec_atak_native_fnc_pageCtrl; _o ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr); _o };
// Texte à la hauteur de son contenu : renvoie la hauteur occupée.
private _txtFit = {
    params ["_x", "_y", "_w", "_s"];
    private _o = [_x, _y, _w, _f * 2, _s] call _txt;
    private _h = ctrlTextHeight _o;
    _o ctrlSetPositionH _h; _o ctrlCommit 0;
    _h
};
private _ico = { params ["_x", "_y", "_s", "_name", "_hex"]; private _o = ["COMSPEC_RscPicture", [_x, _y, _s / _ratio, _s], _data + _name + ".paa"] call comspec_atak_native_fnc_pageCtrl; _o ctrlSetTextColor ([_hex] call _rgb); _o };
// Pastille d'icône : carré teinté à la couleur du mode, icône au centre.
private _tile = {
    params ["_x", "_y", "_s", "_name", "_hex", ["_a", 1]];
    [_x, _y, _s / _ratio, _s, [_hex, 0.16 * _a] call _rgb] call _box;
    private _i = [_x + _s * 0.2 / _ratio, _y + _s * 0.2, _s * 0.6, _name, _hex] call _ico;
    if (_a < 1) then { _i ctrlSetTextColor ([_hex, 0.45] call _rgb); };
};
private _click = {
    params ["_rect", "_code"];
    private _b = ["COMSPEC_RscButtonOverlay", _rect, ""] call comspec_atak_native_fnc_pageCtrl;
    _b setVariable ["comspec_click", _code];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_o"]; [{ [] call _this; }, _o getVariable ["comspec_click", {}]] call CBA_fnc_execNextFrame; }];
    _b
};
// Barre de progression : piste + remplissage (part de 0 à 1).
private _bar = {
    params ["_x", "_y", "_w", "_h", "_part", "_col"];
    [_x, _y, _w, _h, _cTrack] call _box;
    if (_part > 0) then { [_x, _y, (_w * (_part min 1)) max (pixelW * 2), _h, _col] call _box; };
};

private _x0 = _pad;
private _W = _bw - 0.014 - 2 * _pad;
private _gx = _fx * 0.6; // écart horizontal entre cartes
private _gy = _f * 0.6;  // écart vertical
private _wide = (_W / _fx) > 34; // paysage : deux colonnes dans le bandeau, trois cartes par rangée
private _y = _pad;
private _bg = [0, 0, _bw, _bh, _cBg] call _box;

// Titre de section : liseré d'accent, titre, précision à droite.
private _head = {
    params ["_title", ["_sub", ""]];
    _y = _y + _gy * 0.6;
    [_x0, _y + _f * 0.2, _fx * 0.22, _f * 1.05, _acc] call _box;
    [_x0 + _fx * 0.7, _y, _W * 0.6, _f * 1.5, [toUpper _title, 1.0, _hText, true] call _t] call _txt;
    if (_sub isNotEqualTo "") then { [_x0 + _W * 0.4, _y + _f * 0.2, _W * 0.6, _f * 1.3, [_sub, 0.72, _hDim, false, "right"] call _t] call _txt; };
    _y = _y + _f * 1.75;
};

// --- Compteurs de la mission ---
private _modeM = _modes apply { [format ["%1_m", _x select 0]] call _get };
private _total = 0; { _total = _total + _x; } forEach _modeM;
private _moving = 0; { _moving = _moving + ([format ["%1_s", _x select 0]] call _get); } forEach _modes;
private _steps = ["steps"] call _get;
private _lifeTotal = 0; { _lifeTotal = _lifeTotal + (_life getOrDefault [format ["%1_m", _x select 0], 0]); } forEach _modes;

// --- Avertissements ---
private _warn = [];
if !(missionNamespace getVariable ["comspec_atak_native_distance", true]) then { _warn pushBack "Le serveur a coupé le suivi des distances."; };
if (_pairs isEqualTo [] && {!isServer} && {!(missionNamespace getVariable ["COMSPEC_ATAK_PlanSrvOn", false])}) then { _warn pushBack "Le serveur n'a pas le mod ATAK natif : les distances ne sont pas comptées."; };
{
    [_x0, _y, _fx * 0.22, _f * 1.5, [_hAmber] call _rgb] call _box;
    private _h = [_x0 + _fx * 0.7, _y + _f * 0.15, _W - _fx * 0.9, [_x, 0.85, _hAmber] call _t] call _txtFit;
    _y = _y + (_h max (_f * 1.2)) + _gy;
} forEach _warn;

// ============ CETTE MISSION ============
private _heroH = _f * 8.6;
private _lw = [_W, _W * 0.56] select _wide;
[_x0, _y, _W, _heroH, _cCard] call _box;
[_x0, _y, _fx * 0.22, _heroH, _acc] call _box;
private _cx = _x0 + _fx * 1.2;
private _cw = _lw - _fx * 1.6;
[_cx, _y + _f * 0.55, _cw, _f * 1.2, (["CETTE MISSION", 0.75, _accHex, true] call _t) + ([format ["   %1", briefingName], 0.75, _hDim] call _t)] call _txt;
([_total] call _dist) params ["_tn", "_tu"];
[_cx, _y + _f * 1.55, _cw, _f * 3.8, ([_tn, 3.3, _hText, true] call _t) + ([" " + _tu, 1.5, _hDim] call _t)] call _txt;
// Trois chiffres clés sous le total.
private _kw = _cw / 3;
{
    _x params ["_lbl", "_val"];
    private _kx = _cx + _forEachIndex * _kw;
    if (_forEachIndex > 0) then { [_kx - _fx * 0.5, _y + _f * 5.6, pixelW * 2, _f * 2.2, _cTrack] call _box; };
    [_kx, _y + _f * 5.45, _kw - _fx * 0.6, _f * 2.6, ([_lbl, 0.68, _hDim, true] call _t) + "<br/>" + ([_val, 1.2, _hText, true] call _t)] call _txt;
} forEach [
    ["PAS", [_steps] call _thou],
    ["EN MOUVEMENT", [_moving] call _dur],
    ["CARRIÈRE", [_lifeTotal] call _distS]
];
// Répartition par mode : barre empilée + légende (à droite en paysage, carte à part en portrait).
private _split = {
    params ["_rx", "_ry", "_rw", "_cols"];
    [_rx, _ry, _rw, _f * 1.2, ["RÉPARTITION PAR MODE", 0.68, _hDim, true] call _t] call _txt;
    private _sy = _ry + _f * 1.35;
    private _sh = _f * 0.62;
    [_rx, _sy, _rw, _sh, _cTrack] call _box;
    if (_total > 0) then {
        private _sx = _rx;
        {
            private _m = _modeM select _forEachIndex;
            if (_m > 0) then {
                private _ww = _rw * _m / (_total max 1);
                [_sx, _sy, _ww, _sh, [_x select 3] call _rgb] call _box;
                _sx = _sx + _ww;
            };
        } forEach _modes;
    };
    private _ly = _sy + _sh + _f * 0.45;
    private _lcw = _rw / _cols;
    private _rows = ceil ((count _modes) / _cols);
    {
        _x params ["", "_lbl", "", "_hex"];
        private _m = _modeM select _forEachIndex;
        private _lx = _rx + (floor (_forEachIndex / _rows)) * _lcw;
        private _lyy = _ly + (_forEachIndex mod _rows) * _f * 1.1;
        [_lx, _lyy + _f * 0.3, _f * 0.5 / _ratio, _f * 0.5, [_hex, [0.35, 1] select (_m > 0)] call _rgb] call _box;
        [_lx + _fx * 0.9, _lyy, _lcw * 0.55, _f * 1.15, [_lbl, 0.82, [_hDim, _hText] select (_m > 0)] call _t] call _txt;
        [_lx + _lcw * 0.45, _lyy, _lcw * 0.55 - _fx * 0.6, _f * 1.15,
            ([[_m] call _distS, 0.82, [_hDim, _hText] select (_m > 0), true, "right"] call _t) + (["  " + str ([0, round (100 * _m / (_total max 1))] select (_total > 0)) + " %", 0.72, _hDim, false, "right"] call _t)] call _txt;
    } forEach _modes;
    _ly + _rows * _f * 1.1
};
if (_wide) then {
    private _rx = _x0 + _lw + _fx * 0.4;
    [_rx - _fx * 0.8, _y + _f * 0.8, pixelW * 2, _heroH - _f * 1.6, _cTrack] call _box;
    [_rx, _y + _f * 0.55, _W - _lw - _fx * 1.2, 1] call _split;
    _y = _y + _heroH + _gy;
} else {
    _y = _y + _heroH + _gy;
    private _sc = [_x0, _y, _W, _f * 6.6, _cCard] call _box;
    private _ey = [_x0 + _fx * 0.8, _y + _f * 0.5, _W - _fx * 1.6, 1] call _split;
    private _sh = (_ey - _y) + _f * 0.5;
    _sc ctrlSetPositionH _sh; _sc ctrlCommit 0;
    _y = _y + _sh + _gy;
};

// ============ PAR MODE ============
["Par mode", "part de la distance de cette mission"] call _head;
private _cols = [2, 3] select _wide;
private _cwm = (_W - _gx * (_cols - 1)) / _cols;
private _ch = _f * 7.4;
private _rowsUsed = 0;
{
    _x params ["_key", "_lbl", "_icon", "_hex"];
    private _m = _modeM select _forEachIndex;
    private _sec = [format ["%1_s", _key]] call _get;
    private _on = _m > 0;
    // À pied : deux colonnes en tête ; les autres remplissent la grille à la suite.
    private _cell = [_forEachIndex + 1, 0] select (_forEachIndex isEqualTo 0);
    private _col = _cell mod _cols;
    private _row = floor (_cell / _cols);
    private _span = [1, 2] select (_key isEqualTo "foot");
    private _cx = _x0 + _col * (_cwm + _gx);
    private _cy = _y + _row * (_ch + _gy);
    private _cw = _cwm * _span + _gx * (_span - 1);
    _rowsUsed = _rowsUsed max (_row + 1);
    [_cx, _cy, _cw, _ch, _cCard] call _box;
    [_cx, _cy, _cw, pixelH * 3, [_hex, [0.25, 0.9] select _on] call _rgb] call _box;
    [_cx + _fx * 0.7, _cy + _f * 0.7, _f * 1.9, _icon, _hex, [0.5, 1] select _on] call _tile;
    private _hx = _cx + _fx * 0.7 + _f * 1.9 / _ratio + _fx * 0.6;
    [_hx, _cy + _f * 0.62, _cwm - (_hx - _cx) - _fx * 0.4, _f * 2.2,
        ([toUpper _lbl, 0.85, [_hDim, _hText] select _on, true] call _t) + "<br/>" + ([[_sec] call _dur, 0.72, _hDim] call _t)] call _txt;
    ([_m] call _dist) params ["_n", "_u"];
    private _vw = _cwm - _fx * 1.4;
    [_cx + _fx * 0.7, _cy + _f * 2.95, _vw, _f * 2.8, ([_n, 2.2, [_hDim, _hText] select _on, true] call _t) + ([" " + _u, 1.0, _hDim] call _t)] call _txt;
    private _meta = switch (_key) do {
        case "foot": { "" };
        case "halo": { format ["%1 saut(s)", round (["halo_jumps"] call _get)] };
        default { if (_sec >= 30) then { format ["Vitesse moyenne %1 km/h", round (3.6 * _m / _sec)] } else { "Pas encore cette mission" } };
    };
    if (_meta isNotEqualTo "") then { [_cx + _fx * 0.7, _cy + _f * 5.05, _vw, _f * 1.2, [_meta, 0.75, _hDim] call _t] call _txt; };
    // Part du total de la mission.
    private _part = _m / (_total max 1);
    [_cx + _fx * 0.7, _cy + _ch - _f * 0.95, _vw - _fx * 2.6, _f * 0.3, _part, [_hex] call _rgb] call _bar;
    [_cx + _fx * 0.7 + _vw - _fx * 2.4, _cy + _ch - _f * 1.4, _fx * 2.4, _f * 1.2, [str (round (100 * _part)) + " %", 0.75, [_hDim, _hex] select _on, true, "right"] call _t] call _txt;
    // À pied, partie droite : pas, temps de marche, allure et cadence.
    if (_key isEqualTo "foot") then {
        private _sx = _cx + _cwm + _gx * 0.5;
        private _sw = (_cw - _cwm - _gx * 0.5 - _fx * 0.7) / 2;
        [_sx - _fx * 0.3, _cy + _f * 0.8, pixelW * 2, _ch - _f * 1.6, _cTrack] call _box;
        private _pace = if (_m >= 50 && {_sec >= 30}) then { private _p = round (_sec / (_m / 1000)) min 5999; format ["%1:%2 /km", floor (_p / 60), [_p mod 60] call _two] } else { "—" };
        private _cad = if (_sec >= 30) then { format ["%1 pas/min", round (_steps / (_sec / 60))] } else { "—" };
        {
            _x params ["_sl", "_sv"];
            [_sx + (_forEachIndex mod 2) * _sw, _cy + _f * 0.75 + (floor (_forEachIndex / 2)) * _f * 3.0, _sw - _fx * 0.4, _f * 2.8,
                ([_sl, 0.68, _hDim, true] call _t) + "<br/>" + ([_sv, 1.3, [_hDim, _hText] select _on, true] call _t)] call _txt;
        } forEach [
            ["PAS", [_steps] call _thou],
            ["TEMPS DE MARCHE", [_sec] call _dur],
            ["ALLURE MOYENNE", _pace],
            ["CADENCE", _cad]
        ];
        [_sx, _cy + _ch - _f * 1.35, _sw * 2, _f * 1.2, [["Pas estimés d'après l'allure", "Pas estimés d'après l'allure (rien à la nage ni en rampant)"] select _wide, 0.65, _hDim] call _t] call _txt;
    };
} forEach _modes;
_y = _y + _rowsUsed * (_ch + _gy);

// ============ HEURES DE PILOTE ============
["Heures de pilote", "aux commandes, moteur allumé"] call _head;
// Appareils de cette mission et de la carrière, par nom (le carnet regroupe par nom affiché).
private _pRows = createHashMap;
{
    _x params ["_cls", "_label", "_kind", "_ps", "_pm"];
    private _name = [_label, _cls] select (_label isEqualTo "");
    (_pRows getOrDefault [_name, [_name, _kind, 0, 0, 0]]) params ["", "", "_s0", "_m0", "_l0"];
    _pRows set [_name, [_name, _kind, _s0 + _ps, _m0 + _pm, _l0]];
} forEach _pilot;
{
    (_lifePilot get _x) params ["_name", "_kind", "_ls"];
    (_pRows getOrDefault [_name, [_name, _kind, 0, 0, 0]]) params ["", "", "_s0", "_m0"];
    _pRows set [_name, [_name, _kind, _s0, _m0, _ls]];
} forEach _lifePilot;
private _pl = [values _pRows, [], { -((_x select 4) max (_x select 2)) }, "ASCEND"] call BIS_fnc_sortBy;
_pl = _pl select [0, 8];
if (_pl isEqualTo []) then {
    [_x0, _y, _W, _f * 3.4, _cCard] call _box;
    [_x0 + _fx * 0.8, _y + _f * 0.75, _f * 1.9, "g_flight", "#ad73f2", 0.5] call _tile;
    [_x0 + _fx * 0.8 + _f * 1.9 / _ratio + _fx * 0.8, _y + _f * 0.6, _W - _fx * 5, _f * 2.4,
        (["Pas encore d'heures de pilote.", 0.9, _hText, true] call _t) + "<br/>" + (["Elles se comptent aux commandes d'un hélicoptère ou d'un avion, moteur allumé.", 0.75, _hDim] call _t)] call _txt;
    _y = _y + _f * 3.4 + _gy;
} else {
    private _rh = _f * 2.1;
    private _maxS = 1; { _maxS = _maxS max (_x select 4) max (_x select 2); } forEach _pl;
    // Colonnes : appareil | cette mission | carrière | distance (mission)
    private _cxs = [_x0 + _fx * 0.8, _x0 + _W * 0.46, _x0 + _W * 0.64, _x0 + _W * 0.80];
    private _cws = [_W * 0.44 - _fx * 0.8, _W * 0.17, _W * 0.15, _W * 0.20 - _fx * 0.8];
    [_x0, _y, _W, _f * 1.5, _cCard2] call _box;
    {
        [_cxs select _forEachIndex, _y + _f * 0.15, _cws select _forEachIndex, _f * 1.2, [_x, 0.68, _hDim, true, ["left", "right"] select (_forEachIndex > 0)] call _t] call _txt;
    } forEach ["APPAREIL", "CETTE MISSION", "CARRIÈRE", "DISTANCE"];
    _y = _y + _f * 1.5;
    {
        _x params ["_name", "_kind", "_ps", "_pm", "_ls"];
        private _ry = _y + _forEachIndex * _rh;
        [_x0, _ry, _W, _rh, [_cCard, [0.082, 0.1, 0.091, 1]] select (_forEachIndex mod 2 isEqualTo 1)] call _box;
        [_cxs select 0, _ry + _f * 0.4, _f * 1.25, ["d_heli", "qs_airplane"] select (_kind isEqualTo "plane"), "#ad73f2"] call _ico;
        [(_cxs select 0) + _f * 1.25 / _ratio + _fx * 0.5, _ry + _f * 0.35, (_cws select 0) - _fx * 2, _f * 1.3, [_name, 0.9, _hText, true] call _t] call _txt;
        // Temps de la carrière en barre fine sous le nom (le plus volé = pleine largeur).
        [(_cxs select 0) + _f * 1.25 / _ratio + _fx * 0.5, _ry + _rh - _f * 0.45, (_cws select 0) - _fx * 2, _f * 0.16, (_ls max _ps) / _maxS, ["#ad73f2"] call _rgb] call _bar;
        [_cxs select 1, _ry + _f * 0.45, _cws select 1, _f * 1.3, [[_ps] call _dur, 0.9, [_hDim, _hText] select (_ps > 0), true, "right"] call _t] call _txt;
        [_cxs select 2, _ry + _f * 0.45, _cws select 2, _f * 1.3, [[_ls max _ps] call _dur, 0.9, "#c9b6f5", true, "right"] call _t] call _txt;
        [_cxs select 3, _ry + _f * 0.45, _cws select 3, _f * 1.3, [[[_pm] call _distS, "—"] select (_pm < 1), 0.9, _hDim, false, "right"] call _t] call _txt;
    } forEach _pl;
    _y = _y + (count _pl) * _rh + _gy;
};

// ============ DERNIÈRES MISSIONS ============
private _last = _missions select [((count _missions) - ([6, 10] select _wide)) max 0];
["Dernières missions", "distance par mission, couleurs des modes"] call _head;
private _chH = _f * 9.4;
[_x0, _y, _W, _chH, _cCard] call _box;
if ((count _last) < 1) then {
    [_x0 + _fx * 0.8, _y + _f * 3.4, _W - _fx * 1.6, _f * 2.6, ["Le graphique se remplit au fil des missions jouées avec ce profil.", 0.85, _hDim, false, "center"] call _t] call _txt;
} else {
    private _mOf = { params ["_e"]; private _h = createHashMapFromArray (_e select 4); _modes apply { _h getOrDefault [format ["%1_m", _x select 0], 0] } };
    private _maxM = 1; { private _s = 0; { _s = _s + _x; } forEach ([_x] call _mOf); _maxM = _maxM max _s; } forEach _last;
    private _ax = _x0 + _fx * 0.9;
    private _aw = _W - _fx * 4.6;
    private _ay = _y + _f * 1.6;
    private _ah = _f * 5.4;
    // Lignes de repère (moitié, maximum) et leur valeur à droite.
    {
        private _ly = _ay + _ah * (1 - _x);
        [_ax, _ly, _aw, pixelH * 2, [[0.16, 0.19, 0.17, 1], [0.24, 0.28, 0.26, 1]] select (_x isEqualTo 0)] call _box;
        if (_x > 0) then { [_ax + _aw + _fx * 0.3, _ly - _f * 0.55, _fx * 3.4, _f * 1.1, [[_maxM * _x] call _distS, 0.62, _hDim] call _t] call _txt; };
    } forEach [0, 0.5, 1];
    private _slots = (count _last) max 6;
    private _sw = _aw / _slots;
    private _bw2 = _sw * 0.56;
    {
        private _e = _x;
        private _mm = [_e] call _mOf;
        private _sum = 0; { _sum = _sum + _x; } forEach _mm;
        private _cur = (_e select 0) isEqualTo _mk;
        private _bx2 = _ax + _forEachIndex * _sw + (_sw - _bw2) / 2;
        private _yy = _ay + _ah;
        {
            if (_x > 0) then {
                private _hh = (_ah * _x / _maxM) max (pixelH * 2);
                _yy = _yy - _hh;
                [_bx2, _yy, _bw2, _hh, [(_modes select _forEachIndex) select 3, [0.55, 1] select _cur] call _rgb] call _box;
            };
        } forEach _mm;
        // Valeur au-dessus de la barre, date (ou EN COURS) dessous.
        [_ax + _forEachIndex * _sw, _yy - _f * 1.15, _sw, _f * 1.1, [[_sum] call _distS, 0.6, [_hDim, _hText] select _cur, _cur, "center"] call _t] call _txt;
        [_ax + _forEachIndex * _sw, _ay + _ah + _f * 0.2, _sw, _f * 1.1, [[[_e select 3] call _dayS, "EN COURS"] select _cur, 0.62, [_hDim, _accHex] select _cur, _cur, "center"] call _t] call _txt;
        [_ax + _forEachIndex * _sw, _ay + _ah + _f * 1.1, _sw, _f * 1.1, [_e select 1, 0.58, _hDim, false, "center"] call _t] call _txt;
    } forEach _last;
};
_y = _y + _chH + _gy;

// ============ MISSION ET CARRIÈRE ============
private _nMis = _life getOrDefault ["missions", count _missions];
["Mission et carrière", format ["%1 mission(s) sur ce PC", _nMis]] call _head;
private _rh = _f * 2.0;
private _rowsMC = [["total", "Total", "app_distance", _accHex, _total, _lifeTotal]] + (_modes apply {
    _x params ["_key", "_lbl", "_icon", "_hex"];
    [_key, _lbl, _icon, _hex, [format ["%1_m", _key]] call _get, _life getOrDefault [format ["%1_m", _key], 0]]
});
_rowsMC pushBack ["steps", "Pas", "g_steps", "#5cc76b", _steps, _life getOrDefault ["steps", 0]];
private _mcx = [_x0 + _fx * 0.8, _x0 + _W * 0.36, _x0 + _W * 0.52, _x0 + _W * 0.70];
[_x0, _y, _W, _f * 1.5, _cCard2] call _box;
[_mcx select 1, _y + _f * 0.15, _W * 0.15, _f * 1.2, ["CETTE MISSION", 0.68, _hDim, true, "right"] call _t] call _txt;
[_mcx select 2, _y + _f * 0.15, _W * 0.17, _f * 1.2, ["CARRIÈRE", 0.68, _hDim, true, "right"] call _t] call _txt;
[_mcx select 3, _y + _f * 0.15, _W * 0.29, _f * 1.2, [["PART", "PART DE LA CARRIÈRE"] select _wide, 0.68, _hDim, true] call _t] call _txt;
_y = _y + _f * 1.5;
{
    _x params ["_key", "_lbl", "_icon", "_hex", "_mv", "_lv"];
    _lv = _lv max _mv;
    private _ry = _y + _forEachIndex * _rh;
    private _isTot = _key isEqualTo "total";
    [_x0, _ry, _W, _rh, [[_cCard, [0.082, 0.1, 0.091, 1]] select (_forEachIndex mod 2 isEqualTo 1), _cCard2] select _isTot] call _box;
    [_mcx select 0, _ry + _f * 0.4, _f * 1.2, _icon, _hex] call _ico;
    [(_mcx select 0) + _f * 1.2 / _ratio + _fx * 0.5, _ry + _f * 0.35, _W * 0.3, _f * 1.3, [_lbl, 0.88, _hText, _isTot] call _t] call _txt;
    private _fmt = [{ [_this] call _distS }, { [_this] call _thou }] select (_key isEqualTo "steps");
    [_mcx select 1, _ry + _f * 0.35, _W * 0.15, _f * 1.3, [_mv call _fmt, 0.88, [_hDim, _hText] select (_mv > 0), true, "right"] call _t] call _txt;
    [_mcx select 2, _ry + _f * 0.35, _W * 0.17, _f * 1.3, [_lv call _fmt, 0.88, _hText, _isTot, "right"] call _t] call _txt;
    private _part = _mv / (_lv max 1);
    [_mcx select 3, _ry + _f * 0.82, _W * 0.22, _f * 0.32, _part, [_hex] call _rgb] call _bar;
    [(_mcx select 3) + _W * 0.22, _ry + _f * 0.4, _W * 0.08 - _fx * 0.8, _f * 1.2, [str (round (100 * _part)) + " %", 0.72, _hDim, false, "right"] call _t] call _txt;
} forEach _rowsMC;
_y = _y + (count _rowsMC) * _rh + _gy;

// ============ RECORDS ============
["Records d'une mission", "battus en direct pendant la mission"] call _head;
private _recs = [
    ["total_m", "Plus longue mission", "m"], ["foot_m", "Plus long à pied", "m"], ["steps", "Plus de pas", "n"],
    ["land_m", "Plus long en véhicule", "m"], ["air_m", "Plus long en vol", "m"], ["halo_jumps", "Plus de sauts", "n"]
];
private _rcols = [2, 3] select _wide;
private _rw = (_W - _gx * (_rcols - 1)) / _rcols;
private _rhh = _f * 4.3;
{
    _x params ["_key", "_lbl", "_unit"];
    (_rec getOrDefault [_key, [_key, 0, "", "", ""]]) params ["", "_v", "_mis", "_day", ["_rmk", ""]];
    private _rx = _x0 + (_forEachIndex mod _rcols) * (_rw + _gx);
    private _ry = _y + (floor (_forEachIndex / _rcols)) * (_rhh + _gy);
    private _has = _v > 0;
    private _new = _has && {_rmk isEqualTo _mk};
    [_rx, _ry, _rw, _rhh, _cCard] call _box;
    [_rx + _fx * 0.7, _ry + _f * 0.75, _f * 1.5, "g_laurel", [_hDim, _hGold] select _has] call _ico;
    private _tx = _rx + _fx * 0.7 + _f * 1.5 / _ratio + _fx * 0.7;
    private _tw = _rw - (_tx - _rx) - _fx * 0.5;
    [_tx, _ry + _f * 0.45, _tw - ([0, _fx * 4.4] select (_new && _wide)), _f * 1.2, [toUpper _lbl, 0.66, _hDim, true] call _t] call _txt;
    private _vs = if (!_has) then { "—" } else { [[_v] call _distS, [_v] call _thou] select (_unit isEqualTo "n") };
    [_tx, _ry + _f * 1.45, _tw, _f * 1.8, [_vs, 1.45, [_hDim, _hText] select _has, true] call _t] call _txt;
    [_tx, _ry + _f * 3.0, _tw, _f * 1.1, [[[format ["%1 · %2", _mis, [_day] call _dayS], "cette mission"] select _new, "pas encore"] select !_has, 0.62, _hDim] call _t] call _txt;
    if (_new) then {
        private _pw = _fx * 4.2;
        // Badge en haut à droite ; en portrait (cartes étroites), à la place de la ligne du bas.
        private _py = _ry + _f * ([3.0, 0.45] select _wide);
        [_rx + _rw - _pw - _fx * 0.5, _py, _pw, _f * 1.0, [_hGold, 0.18] call _rgb] call _box;
        [_rx + _rw - _pw - _fx * 0.5, _py, _pw, _f * 1.0, ["NOUVEAU", 0.6, _hGold, true, "center"] call _t] call _txt;
    };
} forEach _recs;
_y = _y + (ceil ((count _recs) / _rcols)) * (_rhh + _gy);

// ============ ATHENA ============
// Serveur sans COMSPEC Link relié : ce téléphone compte et envoie lui-même (relais de fn_distanceServer, mode "self").
private _relay = !(missionNamespace getVariable ["COMSPEC_ATAK_SrvPlayOK", false]);
(missionNamespace getVariable [["COMSPEC_ATAK_DistSrv", "COMSPEC_ATAK_DistSrvSelf"] select _relay, [true, -1]]) params ["_ok", "_at"];
private _ah2 = _f * 3.6;
[_x0, _y + _gy * 0.6, _W, _ah2, _cCard] call _box;
_y = _y + _gy * 0.6;
private _stHex = [[_hAmber, "#5cc76b"] select _ok, _hDim] select (_at < 0);
[_x0 + _fx * 0.8, _y + _f * 0.95, _f * 0.55 / _ratio, _f * 0.55, [_stHex] call _rgb] call _box;
private _st = if (_at < 0) then { "Pas encore envoyé" } else { format [["Envoyé il y a %1 · en échec, renvoyé au prochain envoi", "Envoyé il y a %1"] select _ok, [serverTime - _at] call _dur] };
private _bwBtn = _fx * 7.5;
[_x0 + _fx * 1.8, _y + _f * 0.45, _W - _bwBtn - _fx * 3, _f * 3,
    (["ATHENA", 0.72, _hDim, true] call _t) + "<br/>" + ([_st, 0.88, _hText, true] call _t) + "<br/>" +
    ([["Envoyé par le serveur toutes les 10 min.", "Envoyé par ce téléphone toutes les 10 min (le serveur n'est pas relié à Athena)."] select _relay, 0.66, _hDim] call _t) + " " + (["Le cumul de toutes vos missions est sur votre fiche du site.", 0.66, _hDim] call _t)] call _txt;
private _btn = [_x0 + _W - _bwBtn - _fx * 0.8, _y + _f * 0.9, _bwBtn, _f * 1.8];
[_btn select 0, _btn select 1, _btn select 2, _btn select 3, [_accHex, 0.16] call _rgb] call _box;
[_btn select 0, (_btn select 1) + _f * 0.3, _btn select 2, _f * 1.3, ["ACTUALISER", 0.8, _accHex, true, "center"] call _t] call _txt;
[_btn, { ["tick"] call comspec_atak_native_fnc_distanceLog; ['DISTANCE'] call comspec_atak_native_fnc_pageRender; }] call _click;
_y = _y + _ah2 + _pad;

// Fond étendu à toute la hauteur dessinée, pour défiler jusqu'en bas.
_bg ctrlSetPosition [0, 0, _bw, _y max _bh];
_bg ctrlCommit 0;
true
