/*
    App CAS, onglet APPEL DE FEU : écran du clip (clip-motion, img/cff.jpg), clair, en panneaux.
    Paysage : à gauche POSITION DE TIR, CIBLE et la solution ; à droite ÉTAT DE LA MISSION et JOURNAL.
    Portrait : les mêmes panneaux l'un sous l'autre (défilement). En bas : CALCULER, FIN DE MISSION, TIR.
    Champs gardés par fn_firesSave (clés gun, gunGrid, tgtGrid, az, dist, obsGrid, enemy, rounds) ; actions : fn_firesAction.
    Params : [rectangle de la zone sous les onglets (x, y, largeur, hauteur)]
*/
params ["_rect"];
disableSerialization;
_rect params ["_rx", "_ry", "_rw", "_rh"];
private _d = [] call comspec_atak_native_fnc_display;
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 1.6;
private _f = [] call comspec_atak_native_fnc_firesState;
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Form", _form];
private _v = { params ["_k", ["_def", ""]]; _f getOrDefault [_k, _def] };

// Couleurs du clip.
private _cPage = [0.84, 0.86, 0.88, 1];
private _cPanel = [0.97, 0.975, 0.98, 1];
private _cHead = [0.30, 0.33, 0.37, 1];
private _cLine = [0.80, 0.82, 0.85, 1];
private _cBlue = [0.49, 0.69, 0.88, 1];
private _cWhite = [1, 1, 1, 1];
private _cInk = [0.16, 0.18, 0.21, 1];
private _cDim = [0.55, 0.58, 0.62, 1];
private _cYellow = [0.93, 0.83, 0.50, 1];
private _cDark = [0.33, 0.38, 0.43, 1];
private _cValue = [0.86, 0.95, 0.62, 1];
private _cGrey = [0.62, 0.66, 0.70, 1];
private _cRed = [0.89, 0.47, 0.40, 1];

private _land = (_rw / pixelW) > (_rh / pixelH) * 1.15;
private _rowH = _font * 1.45;
private _barH = _font * 1.7;

// Fond de l'app, puis zone défilante au-dessus de la barre du bas.
private _bg = ["COMSPEC_RscText", _rect] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor _cPage;
private _grp = ["COMSPEC_RscControlsGroup", [_rx, _ry, _rw, _rh - _barH - _pad]] call comspec_atak_native_fnc_pageCtrl;
private _gw = _rw - 0.012;

private _mk = {
    params ["_cls", "_pos", ["_txt", ""], ["_bgc", []], ["_fg", []], ["_fh", -1], ["_parent", controlNull]];
    if (isNull _parent) then { _parent = _grp; };
    private _c = _d ctrlCreate [_cls, -1, _parent];
    _c ctrlSetPosition _pos;
    if (_txt isNotEqualTo "") then { _c ctrlSetText ([(_txt) call comspec_atak_native_fnc_tr, _txt] select ((_cls find "Edit") >= 0)); }; // i18n:brut
    if (_bgc isNotEqualTo []) then { _c ctrlSetBackgroundColor _bgc; };
    if (_fg isNotEqualTo []) then { _c ctrlSetTextColor _fg; };
    _c ctrlSetFontHeight ([_fh, _fs] select (_fh < 0));
    _c ctrlCommit 0;
    _c
};
// Bouton : style "sel" (bleu, choisi), "off" (blanc), "yellow", "blue", "grey", "red", "flat" (petit, en-tête).
private _btn = {
    params ["_pos", "_txt", "_code", ["_style", "off"], ["_parent", controlNull], ["_tip", ""]];
    private _cols = switch (_style) do {
        case "sel": { [_cBlue, _cWhite] };
        case "yellow": { [_cYellow, _cInk] };
        case "blue": { [_cBlue, _cWhite] };
        case "grey": { [_cGrey, _cWhite] };
        case "red": { [_cRed, _cWhite] };
        case "flat": { [_cWhite, _cDim] };
        default { [_cWhite, _cInk] };
    };
    private _b = ["COMSPEC_RscButton", _pos, _txt, _cols select 0, _cols select 1, _fs * 0.95, _parent] call _mk;
    _b ctrlAddEventHandler ["ButtonClick", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b
};
private _label = {
    params ["_pos", "_txt", ["_parent", controlNull]];
    ["COMSPEC_RscText", _pos, _txt, [], _cHead, _fs * 0.9, _parent] call _mk
};
private _edit = {
    params ["_key", "_pos", "_val"];
    private _e = ["COMSPEC_RscEdit", _pos, _val, _cWhite, _cInk, _font * 0.95] call _mk;
    _form set [_key, _e];
    _e
};
// Valeur affichée seulement (champ grisé du clip).
private _ro = {
    params ["_pos", "_txt"];
    ["COMSPEC_RscText", _pos, _txt, [0.94, 0.945, 0.95, 1], _cDim, _font * 0.95] call _mk
};
// Panneau titré : fond, liseré, titre ; renvoie le y du contenu.
private _panel = {
    params ["_px", "_py", "_pw", "_h", "_title"];
    ["COMSPEC_RscText", [_px, _py, _pw, _h], "", _cLine] call _mk;
    ["COMSPEC_RscText", [_px + pixelW, _py + pixelH, _pw - 2 * pixelW, _h - 2 * pixelH], "", _cPanel] call _mk;
    ["COMSPEC_RscText", [_px + _pad, _py, _pw - 2 * _pad, _rowH * 0.9], toUpper _title, [], _cHead, _fs * 0.92] call _mk;
    ["COMSPEC_RscText", [_px, _py + _rowH * 0.9, _pw, pixelH], "", _cLine] call _mk;
    _py + _rowH * 0.9 + _pad
};

// Données.
private _guns = [] call comspec_atak_native_fnc_firesGuns;
private _gunId = ["gun", if ((count _guns) > 0) then { netId (_guns select 0) } else { "MAN" }] call _v;
if (_gunId isNotEqualTo "MAN" && {isNull (objectFromNetId _gunId)}) then { _gunId = "MAN"; };
private _gunName = { params ["_g", "_i"]; _g getVariable ["COMSPEC_ATAK_GunName", format [("PIÈCE %1") call comspec_atak_native_fnc_tr, _i + 1]] };
private _gunLabel = if (_gunId isEqualTo "MAN") then { "MAN" } else { [objectFromNetId _gunId, _guns findIf { netId _x isEqualTo _gunId }] call _gunName };
private _mode = ["mode", "POLAR"] call _v;
private _from = ["from", "ME"] call _v;
private _ammo = ["ammo", "HE"] call _v;
private _sheaf = ["sheaf", false] call _v;
private _sol = _f getOrDefault ["solution", createHashMap];
private _tgtGrid = _sol getOrDefault ["grid", ["tgtGrid", ""] call _v];

// ---------- POSITION DE TIR ----------
private _drawFiring = {
    params ["_px", "_py", "_pw"];
    private _h = _rowH * 0.9 + _pad * 4 + _rowH * 3;
    private _cy = [_px, _py, _pw, _h, "Position de tir"] call _panel;
    private _ix = _px + _pad;
    private _iw = _pw - 2 * _pad;
    // Pièce : liste des pièces du camp (bleue) et MAN.
    private _cw = _iw * 0.62;
    private _combo = ["COMSPEC_RscCombo", [_ix, _cy, _cw, _rowH], "", _cBlue, _cWhite, _fs * 0.95] call _mk;
    { _combo lbSetData [_combo lbAdd (((([_x, _forEachIndex] call _gunName) + " · " + ([_x, 8] call comspec_atak_native_fnc_gridRef))) call comspec_atak_native_fnc_tr), netId _x]; } forEach _guns;
    if ((count _guns) isEqualTo 0) then { _combo lbAdd (("Aucune pièce du camp") call comspec_atak_native_fnc_tr); _combo lbSetData [0, "MAN"]; };
    private _ci = _guns findIf { netId _x isEqualTo _gunId };
    _combo lbSetCurSel (_ci max 0);
    _form set ["gun", _combo];
    _combo ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["gun", _c lbData _i] call comspec_atak_native_fnc_firesAction; }];
    [[_ix + _cw + _pad, _cy, _iw - _cw - _pad, _rowH], "MAN", { ["gun", "MAN"] call comspec_atak_native_fnc_firesAction; }, ["off", "sel"] select (_gunId isEqualTo "MAN"), controlNull, "Pièce manuelle : saisir sa grille"] call _btn;
    _cy = _cy + _rowH + _pad;
    [[_ix, _cy, _cw, _rowH], "PIÈCE À MA POSITION", { ["gunHere"] call comspec_atak_native_fnc_firesAction; }, "yellow"] call _btn;
    _cy = _cy + _rowH + _pad;
    // Grille et altitude de la pièce.
    private _gunPos = if (_gunId isEqualTo "MAN") then { [] } else { getPosASL (objectFromNetId _gunId) };
    private _lw = _iw * 0.14;
    [[_ix, _cy, _lw, _rowH], "GRILLE"] call _label;
    if (_gunId isEqualTo "MAN") then {
        ["gunGrid", [_ix + _lw, _cy, _iw * 0.48, _rowH], ["gunGrid"] call _v] call _edit;
    } else {
        [[_ix + _lw, _cy, _iw * 0.48, _rowH], [objectFromNetId _gunId, 10] call comspec_atak_native_fnc_gridRef] call _ro;
    };
    [[_ix + _iw * 0.66, _cy, _lw * 0.7, _rowH], "ALT"] call _label;
    private _alt = if ((count _gunPos) > 2) then { str round (_gunPos select 2) } else { _sol getOrDefault ["gunAlt", "—"] };
    [[_ix + _iw * 0.76, _cy, _iw * 0.24, _rowH], _alt] call _ro;
    _py + _h + _pad
};

// ---------- CIBLE ----------
private _drawTarget = {
    params ["_px", "_py", "_pw"];
    private _nRows = 4 + ([0, 1] select (_mode isEqualTo "POLAR" && {_from isEqualTo "OBS"}));
    private _h = _rowH * 0.9 + _pad * (_nRows + 2) + _rowH * _nRows;
    private _cy = [_px, _py, _pw, _h, "Cible"] call _panel;
    [[_px + _pw - _pad - _pw * 0.17, _py + _rowH * 0.1, _pw * 0.17, _rowH * 0.7], "EFFACER", { ["clearTgt"] call comspec_atak_native_fnc_firesAction; }, "flat", controlNull, "Vide la cible et la solution"] call _btn;
    private _ix = _px + _pad;
    private _iw = _pw - 2 * _pad;
    private _u = _iw / 12;
    // GRILLE / POLAIRE, DEPUIS OBSERVATEUR / MA POS.
    [[_ix, _cy, _u * 2.2, _rowH], "GRILLE", { ["mode", "GRID"] call comspec_atak_native_fnc_firesAction; }, ["off", "sel"] select (_mode isEqualTo "GRID")] call _btn;
    [[_ix + _u * 2.2, _cy, _u * 2.2, _rowH], "POLAIRE", { ["mode", "POLAR"] call comspec_atak_native_fnc_firesAction; }, ["off", "sel"] select (_mode isEqualTo "POLAR")] call _btn;
    if (_mode isEqualTo "POLAR") then {
        [[_ix + _u * 4.7, _cy, _u * 1.6, _rowH], "DEPUIS"] call _label;
        [[_ix + _u * 6.3, _cy, _u * 3, _rowH], "OBSERVATEUR", { ["from", "OBS"] call comspec_atak_native_fnc_firesAction; }, ["off", "sel"] select (_from isEqualTo "OBS"), controlNull, "Azimut et distance depuis la grille d'un observateur"] call _btn;
        [[_ix + _u * 9.3, _cy, _u * 2.7, _rowH], "MA POS", { ["from", "ME"] call comspec_atak_native_fnc_firesAction; }, ["off", "sel"] select (_from isEqualTo "ME"), controlNull, "Azimut et distance depuis ma position"] call _btn;
    } else {
        [[_ix + _u * 4.7, _cy, _u * 3.5, _rowH], "SUR LA CARTE", { ["pick", "cff"] call comspec_atak_native_fnc_firesAction; }, "off", controlNull, "Le prochain clic sur la carte devient la cible"] call _btn;
    };
    _cy = _cy + _rowH + _pad;
    if (_mode isEqualTo "POLAR" && {_from isEqualTo "OBS"}) then {
        [[_ix, _cy, _u * 2.2, _rowH], "OBS"] call _label;
        ["obsGrid", [_ix + _u * 2.2, _cy, _u * 5, _rowH], ["obsGrid", [player, 10] call comspec_atak_native_fnc_gridRef] call _v] call _edit;
        _cy = _cy + _rowH + _pad;
    };
    // AZ, DIST, CIBLE (polaire) ou grille de la cible.
    if (_mode isEqualTo "POLAR") then {
        [[_ix, _cy, _u * 1.2, _rowH], "AZ"] call _label;
        ["az", [_ix + _u * 1.2, _cy, _u * 2.4, _rowH], ["az", str round ((getDir player) * 6400 / 360)] call _v] call _edit;
        [[_ix + _u * 4, _cy, _u * 1.5, _rowH], "DIST"] call _label;
        ["dist", [_ix + _u * 5.5, _cy, _u * 2.2, _rowH], ["dist", "600"] call _v] call _edit;
        [[_ix + _u * 8, _cy, _u * 1.1, _rowH], "CIBLE"] call _label;
        [[_ix + _u * 9.1, _cy, _u * 2.9, _rowH], [_tgtGrid, "—"] select (_tgtGrid isEqualTo "")] call _ro;
    } else {
        [[_ix, _cy, _u * 2.2, _rowH], "GRILLE"] call _label;
        ["tgtGrid", [_ix + _u * 2.2, _cy, _u * 5.5, _rowH], ["tgtGrid", _tgtGrid] call _v] call _edit;
    };
    _cy = _cy + _rowH + _pad;
    // ENNEMI, munition, nombre de coups.
    [[_ix, _cy, _u * 2.2, _rowH], "ENNEMI"] call _label;
    ["enemy", [_ix + _u * 2.2, _cy, _u * 3.3, _rowH], ["enemy", ""] call _v] call _edit;
    {
        _x params ["_t", "_k", "_tip"];
        [[_ix + _u * (5.8 + _forEachIndex * 1.55), _cy, _u * 1.55, _rowH], _t, compile format ["['ammo', '%1'] call comspec_atak_native_fnc_firesAction;", _k], ["off", "sel"] select (_ammo isEqualTo _k), controlNull, _tip] call _btn;
    } forEach [["HE", "HE", "Explosif"], ["ILM", "ILLUM", "Éclairant"], ["FUM", "SMOKE", "Fumigène"]];
    [[_ix + _u * 10.6, _cy, _u * 0.5, _rowH], "X"] call _label;
    ["rounds", [_ix + _u * 11.1, _cy, _u * 0.9, _rowH], ["rounds", "3"] call _v] call _edit;
    _cy = _cy + _rowH + _pad;
    // Gerbe.
    [[_ix, _cy, _u * 1.7, _rowH], "OFF", { if ((call comspec_atak_native_fnc_firesState) getOrDefault ["sheaf", false]) then { ["sheaf"] call comspec_atak_native_fnc_firesAction; }; }, ["off", "sel"] select !_sheaf, controlNull, "Tous les coups au même point"] call _btn;
    [[_ix + _u * 1.7, _cy, _u * 2.4, _rowH], "GERBE", { if !((call comspec_atak_native_fnc_firesState) getOrDefault ["sheaf", false]) then { ["sheaf"] call comspec_atak_native_fnc_firesAction; }; }, ["off", "sel"] select _sheaf, controlNull, "Coups répartis autour de la cible"] call _btn;
    _py + _h + _pad
};

// ---------- SOLUTION (bandeau sombre) ----------
private _drawSolution = {
    params ["_px", "_py", "_pw"];
    private _h = _rowH * 2.6;
    ["COMSPEC_RscText", [_px, _py, _pw, _h], "", _cDark] call _mk;
    private _has = (count _sol) > 0;
    private _num = { params ["_k", "_fmt"]; if (!_has || {(_sol getOrDefault [_k, -1]) isEqualTo -1}) exitWith { "—" }; format [_fmt, _sol get _k] };
    private _cells = [
        ["UNITÉ", [_gunLabel, "—"] select (!_has)],
        ["CHARGE", ["charge", "%1"] call _num],
        ["DÉRIVE", if (_has) then { [round (_sol getOrDefault ["mils", 0]), 4] call CBA_fnc_formatNumber } else { "—" }],
        ["ÉLÉVATION", if (_has && {(_sol getOrDefault ["elev", -1]) >= 0}) then { [round (_sol get "elev"), 4] call CBA_fnc_formatNumber } else { "—" }],
        ["DURÉE", if (_has && {(_sol getOrDefault ["eta", -1]) > 0}) then { format ["%1s", (_sol get "eta") toFixed 1] } else { "—" }],
        ["FLÈCHE", if (_has && {(_sol getOrDefault ["maxOrd", -1]) > 0}) then { format ["%1M", round (_sol get "maxOrd")] } else { "—" }]
    ];
    private _cw = (_pw - 2 * _pad) / 6;
    {
        _x params ["_t", "_val"];
        ["COMSPEC_RscText", [_px + _pad + _forEachIndex * _cw, _py + _pad * 0.6, _cw, _fs], _t, [], [0.82, 0.85, 0.88, 1], _fs * 0.72] call _mk;
        private _vc = ["COMSPEC_RscLabel", [_px + _pad + _forEachIndex * _cw, _py + _pad * 0.6 + _fs, _cw, _rowH], "", [], _cValue, _font * 1.05] call _mk;
        _vc ctrlSetText ((_val) call comspec_atak_native_fnc_tr);
    } forEach _cells;
    // Note : intervalle vertical, dérive du vent, portée.
    private _note = if (_has) then {
        format ["VI %1%2M APPLIQUÉ · DÉRIVE VENT ~%3M%4", ["", "+"] select ((_sol getOrDefault ["vi", 0]) >= 0), round (_sol getOrDefault ["vi", 0]), round (_sol getOrDefault ["drift", 0]),
            [("  ·  HORS PORTÉE") call comspec_atak_native_fnc_tr, ""] select (_sol getOrDefault ["inRange", true])]
    } else { "CALCULER pour obtenir la solution de tir" };
    ["COMSPEC_RscText", [_px + _pad, _py + _h - _rowH * 0.85, _pw - 2 * _pad, _rowH * 0.8], _note, [], [[0.95, 0.85, 0.40, 1], _cRed] select (_has && {!(_sol getOrDefault ["inRange", true])}), _fs * 0.85] call _mk;
    _py + _h + _pad
};

// ---------- ÉTAT DE LA MISSION ----------
private _drawStatus = {
    params ["_px", "_py", "_pw"];
    private _lines = [
        _gunLabel,
        if (_gunId isEqualTo "MAN") then { format [("MANUEL %1") call comspec_atak_native_fnc_tr, [_tgtGrid, "—"] select (_tgtGrid isEqualTo "")] } else { format [("CIBLE %1") call comspec_atak_native_fnc_tr, [_tgtGrid, "—"] select (_tgtGrid isEqualTo "")] },
        [("NON PROGRAMMÉE") call comspec_atak_native_fnc_tr, [("PROGRAMMÉE") call comspec_atak_native_fnc_tr, ("EN COURS") call comspec_atak_native_fnc_tr] select (_f getOrDefault ["active", false])] select ((count _sol) > 0),
        format [("%1 COUPS %2") call comspec_atak_native_fnc_tr, ["rounds", "3"] call _v, ["HE", "ILM", "FUM"] select (["HE", "ILLUM", "SMOKE"] find _ammo max 0)] + (["", (" · GERBE") call comspec_atak_native_fnc_tr] select _sheaf),
        ([] call comspec_atak_native_fnc_firesWind) select 0
    ];
    private _h = _rowH * 0.9 + _pad * 2 + _fs * 1.25 * (count _lines);
    private _cy = [_px, _py, _pw, _h, "État de la mission"] call _panel;
    { (["COMSPEC_RscText", [_px + _pad, _cy + _forEachIndex * _fs * 1.25, _pw * 0.7, _fs * 1.25], "", [], _cInk, _fs * 0.92] call _mk) ctrlSetText ((_x) call comspec_atak_native_fnc_tr); } forEach _lines;
    _py + _h + _pad
};

// ---------- JOURNAL ----------
private _drawLog = {
    params ["_px", "_py", "_pw", ["_hMin", 0]];
    private _msn = _f getOrDefault ["missions", []];
    private _n = (count _msn) min 8;
    private _h = (_rowH * 0.9 + _pad * 2 + ((_rowH * 0.95) * (_n max 1))) max _hMin;
    private _cy = [_px, _py, _pw, _h, "Journal · toucher pour rappeler"] call _panel;
    [[_px + _pw - _pad - _pw * 0.26, _py + _rowH * 0.1, _pw * 0.26, _rowH * 0.7], "NOUVELLE MSN", { ["newMsn"] call comspec_atak_native_fnc_firesAction; }, "flat", controlNull, "Nouvelle mission : cible et solution remises à zéro"] call _btn;
    if (_n isEqualTo 0) then {
        ["COMSPEC_RscText", [_px + _pad, _cy, _pw - 2 * _pad, _rowH], "AUCUNE MISSION ENREGISTRÉE", [], _cDim, _fs * 0.9] call _mk;
    };
    for "_i" from 0 to (_n - 1) do {
        private _idx = (count _msn) - 1 - _i;
        (_msn select _idx) params ["_t", "_g", "_a", "_r", "_st"];
        private _b = [[_px + _pad, _cy + _i * _rowH * 0.95, _pw - 2 * _pad, _rowH * 0.85], format ["%1  %2  %3 x%4  %5", _t, _g, _a, _r, _st], compile format ["['recall', %1] call comspec_atak_native_fnc_firesAction;", _idx], "off"] call _btn;
        _b ctrlSetFontHeight (_fs * 0.85);
        _b ctrlSetTooltip (("Rappeler cette mission (cible, munition, coups)") call comspec_atak_native_fnc_tr);
    };
    _py + _h + _pad
};

// Disposition.
private _y0 = _pad;
if (_land) then {
    private _wL = (_gw - 3 * _pad) * 0.56;
    private _wR = (_gw - 3 * _pad) - _wL;
    private _xR = _pad * 2 + _wL;
    private _yl = [_pad, _y0, _wL] call _drawFiring;
    _yl = [_pad, _yl, _wL] call _drawTarget;
    _yl = [_pad, _yl, _wL] call _drawSolution;
    private _yr = [_xR, _y0, _wR] call _drawStatus;
    [_xR, _yr, _wR, (_yl - _yr - _pad) max 0] call _drawLog;
} else {
    private _w = _gw - 2 * _pad;
    private _y = [_pad, _y0, _w] call _drawFiring;
    _y = [_pad, _y, _w] call _drawTarget;
    _y = [_pad, _y, _w] call _drawSolution;
    _y = [_pad, _y, _w] call _drawStatus;
    [_pad, _y, _w] call _drawLog;
};

// Barre du bas : CALCULER, FIN DE MISSION, TIR et l'état du tir.
private _by = _ry + _rh - _barH;
private _bw = _rw * ([0.16, 0.25] select !_land);
{
    _x params ["_t", "_code", "_style", "_tip"];
    private _b = ["COMSPEC_RscButton", [_rx + _pad + _forEachIndex * (_bw + _pad), _by + _pad * 0.4, _bw, _barH - _pad * 0.8], _t, [], [], _font * 0.9, _d displayCtrl 88531] call _mk;
    private _cols = switch (_style) do { case "blue": { [_cBlue, _cWhite] }; case "red": { [_cRed, _cWhite] }; default { [_cGrey, _cWhite] }; };
    _b ctrlSetBackgroundColor (_cols select 0);
    _b ctrlSetTextColor (_cols select 1);
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) pushBack _b;
} forEach [
    ["CALCULER", { ["compute"] call comspec_atak_native_fnc_firesAction; }, "blue", "Calcule la solution de tir (dérive, élévation, charge, durée de vol)"],
    ["FIN DE MISSION", { ["eom"] call comspec_atak_native_fnc_firesAction; }, "grey", "Transmet la fin de mission"],
    ["TIR", { ["shot"] call comspec_atak_native_fnc_firesAction; }, "red", "Déclenche le tir (pièce IA) ou envoie la mission au servant"]
];
private _hint = _f getOrDefault ["hint", ["", false]];
private _state = if ((_hint select 0) isNotEqualTo "") then { (_hint select 0) call comspec_atak_native_fnc_tr } else {
    if (_f getOrDefault ["active", false]) then { format [("%1 EN TIR · CIBLE %2") call comspec_atak_native_fnc_tr, _gunLabel, _tgtGrid] } else { "" }
};
if (_state isNotEqualTo "") then {
    private _st = ["COMSPEC_RscTextRight", [_rx + _pad * 4 + 3 * (_bw + _pad), _by, _rw - _pad * 5 - 3 * (_bw + _pad), _barH], "", [], [[0.30, 0.33, 0.37, 1], _cRed] select (_hint select 1), _fs * 0.95, _d displayCtrl 88531] call _mk;
    _st ctrlSetText ((_state) call comspec_atak_native_fnc_tr);
    (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) pushBack _st;
};
true
