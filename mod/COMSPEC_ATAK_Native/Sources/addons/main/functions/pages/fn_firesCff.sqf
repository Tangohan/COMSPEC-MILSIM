/*
    App CAS, onglet APPEL DE FEU : mise en page du clip (clip-motion, img/cff.jpg) en panneaux, aux couleurs sombres du téléphone.
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

// Couleurs : jetons du thème (fn_theme : sombre, jour, nuit JVN), jamais de couleur fixe. Encres vérifiées à 4,5 : AtakThemeContrastTest.
// Avant : texte foncé (onState) sur champs et boutons sombres, gris pâle sur panneaux, texte noir sur l'accent : illisible.
private _th = COMSPEC_ATAK_Th;
private _cPage = _th get "bg1";
private _cPanel = _th get "surface";
private _cHead = _th get "textDim";
private _cLine = _th get "border";
private _cWhite = _th get "text";
private _cBtn = _th get "surface2";
private _cField = _th get "field";
private _cInk = _th get "text";
private _cDim = _th get "textDim";
// Bouton actif ou principal : fond tonal d'accent, encre d'accent lisible dessus, libellé entre crochets (l'état ne tient pas qu'à la couleur).
private _cSel = _th get "accentSoftHi";
private _cOnSel = _th get "onAccentSoft";
private _cDark = _th get "surfaceAlt";
private _cValue = _th get "text";
private _cGrey = _th get "surface2";
private _cRed = _th get "alert";
private _cRedBg = [[_cRed select 0, _cRed select 1, _cRed select 2, 1], [(_cRed select 0) * 0.6, (_cRed select 1) * 0.6, (_cRed select 2) * 0.6, 1]] select (_th get "dark");
private _cOnRed = [_th get "onState", _th get "text"] select (_th get "dark");
private _cNote = _th get "warn";
private _cInfo = _th get "info";

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
    _c ctrlSetFontHeight (([_fh, _fs] select (_fh < 0)) max COMSPEC_ATAK_FontMin);
    _c ctrlCommit 0;
    _c
};
// Bouton : style "sel" (bleu, choisi), "off" (blanc), "yellow", "blue", "grey", "red", "flat" (petit, en-tête).
private _btn = {
    params ["_pos", "_txt", "_code", ["_style", "off"], ["_parent", controlNull], ["_tip", ""]];
    private _cols = switch (_style) do {
        case "sel";
        case "blue": { [_cSel, _cOnSel] };
        case "grey";
        case "yellow": { [_cGrey, _cInk] };
        case "red": { [_cRedBg, _cOnRed] };
        case "flat": { [_cBtn, _cInk] };
        default { [_cBtn, _cInk] };
    };
    private _b = ["COMSPEC_RscButton", _pos, _txt, _cols select 0, _cols select 1, _fs * 0.95, _parent] call _mk;
    // Choisi : crochets autour du libellé (forme + texte, pas seulement la couleur).
    if (_style isEqualTo "sel") then { _b ctrlSetText format ["[%1]", ctrlText _b]; };
    [_b, _fs * 0.95, true, 0.8] call comspec_atak_native_fnc_textFit;
    _b ctrlAddEventHandler ["ButtonClick", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b
};
private _label = {
    params ["_pos", "_txt", ["_parent", controlNull]];
    ["COMSPEC_RscText", _pos, _txt, [], _cHead, _fs * 0.9, _parent] call _mk
};
private _edit = {
    params ["_key", "_pos", "_val", ["_tip", ""]];
    private _e = ["COMSPEC_RscEdit", _pos, _val, _cField, _cInk, _font * 0.95] call _mk;
    if (_tip isNotEqualTo "") then { _e ctrlSetTooltip (_tip call comspec_atak_native_fnc_tr); };
    _form set [_key, _e];
    _e
};
// Valeur affichée seulement (champ grisé du clip).
private _ro = {
    params ["_pos", "_txt"];
    ["COMSPEC_RscText", _pos, _txt, _cPage, _cInk, _font * 0.95] call _mk
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
// Appui feu typé (fn_fireSupport, réglage CBA) : moyen choisi, pièces de ce moyen, batterie simulée si la mission l'autorise.
private _fsOn = ["on"] call comspec_atak_native_fnc_fireSupport;
private _fsTypes = if (_fsOn) then { ["types"] call comspec_atak_native_fnc_fireSupport } else { [] };
private _fsType = if (_fsOn) then { ["fsType", ""] call _v } else { "" };
if (_fsType isNotEqualTo "" && {(_fsTypes findIf { (_x get "key") isEqualTo _fsType }) < 0}) then { _fsType = ""; _f set ["fsType", ""]; };
private _fsT = ["type", _fsType] call comspec_atak_native_fnc_fireSupport;
private _fsSim = _fsType isNotEqualTo "" && {missionNamespace getVariable ["comspec_atak_native_fs_sim", false]};
private _guns = if (_fsOn) then { ["guns", _fsType] call comspec_atak_native_fnc_fireSupport } else { [] call comspec_atak_native_fnc_firesGuns };
private _gunId = ["gun", if ((count _guns) > 0) then { netId (_guns select 0) } else { ["MAN", "SIM"] select _fsSim }] call _v;
// Pièce absente de la liste (supprimée ou détruite : firesGuns ne garde que les vivantes) : retour à MAN, gardé dans l'état.
if (_gunId isNotEqualTo "MAN" && {!(_gunId isEqualTo "SIM" && {_fsSim})} && {(_guns findIf { netId _x isEqualTo _gunId }) < 0}) then { _gunId = "MAN"; _f set ["gun", _gunId]; _f deleteAt "solution"; };
// Pièce par défaut (première de la liste) gardée dans l'état : CALCULER et TIR lisent "gun", la liste ne déclenche pas LBSelChanged ici.
if !("gun" in _f) then { _f set ["gun", _gunId]; };
private _gunName = { params ["_g", "_i"]; _g getVariable ["COMSPEC_ATAK_GunName", format [("PIÈCE %1") call comspec_atak_native_fnc_tr, _i + 1]] };
private _gunLabel = switch (_gunId) do { case "MAN": { "MAN" }; case "SIM": { "SIMULÉE" }; default { [objectFromNetId _gunId, _guns findIf { netId _x isEqualTo _gunId }] call _gunName }; };
private _mode = ["mode", "POLAR"] call _v;
private _from = ["from", "ME"] call _v;
private _ammo = ["ammo", "HE"] call _v;
private _fsAmmo = _fsT getOrDefault ["ammo", ["HE", "SMOKE", "ILLUM"]];
if !(_ammo in _fsAmmo) then { _ammo = _fsAmmo select 0; _f set ["ammo", _ammo]; };
private _sheaf = ["sheaf", false] call _v;
private _sol = _f getOrDefault ["solution", createHashMap];
private _tgtGrid = _sol getOrDefault ["grid", ["tgtGrid", ""] call _v];
// Mission en cours : la solution peut avoir été effacée (pièce, munition, origine changées), la cible reste activeGrid.
private _stGrid = if (_f getOrDefault ["active", false]) then { _f getOrDefault ["activeGrid", _tgtGrid] } else { _tgtGrid };
// Cercles de la carte (fn_fireSupport "draw") : portées de la pièce choisie et distance de sécurité autour de la cible,
// calculées ici (à l'ouverture de l'écran), pas à chaque image.
_f deleteAt "fsRing";
_f deleteAt "fsDcRing";
if (_fsOn) then {
    private _g = if (_gunId in ["MAN", "SIM"]) then { objNull } else { objectFromNetId _gunId };
    private _rk = [_fsType, ["classify", _g] call comspec_atak_native_fnc_fireSupport] select (_fsType isEqualTo "" && {!isNull _g});
    private _c = if (isNull _g) then { [_sol getOrDefault ["gunPos", []], []] select (_gunId isEqualTo "SIM") } else { getPosATL _g };
    if ((count _c) >= 2) then {
        (["range", [_g, ["mag", [_g, _ammo]] call comspec_atak_native_fnc_fireSupport, _rk]] call comspec_atak_native_fnc_fireSupport) params ["_rMin", "_rMax"];
        if (_rMax > 0) then { _f set ["fsRing", [[_c select 0, _c select 1, 0], _rMin, _rMax, format [("PORTÉE %1–%2 M") call comspec_atak_native_fnc_tr, _rMin, _rMax]]]; };
    };
    private _tg = _f getOrDefault ["target", []];
    if ((count _tg) >= 2) then {
        private _dc = (["type", _rk] call comspec_atak_native_fnc_fireSupport) getOrDefault ["dc", 600];
        _f set ["fsDcRing", [[_tg select 0, _tg select 1, 0], _dc, format [("DANGER CLOSE %1 M") call comspec_atak_native_fnc_tr, _dc]]];
    };
};

// ---------- POSITION DE TIR ----------
private _drawFiring = {
    params ["_px", "_py", "_pw"];
    private _h = _rowH * 0.9 + _pad * 4 + _rowH * 3 + ([0, _rowH + _pad] select _fsOn);
    private _cy = [_px, _py, _pw, _h, "Position de tir"] call _panel;
    private _ix = _px + _pad;
    private _iw = _pw - 2 * _pad;
    // Appui feu typé : moyen d'appui (générique = toute pièce, comme sans le système), puis la liste ne garde que ses pièces.
    if (_fsOn) then {
        private _lw0 = _iw * 0.2;
        [[_ix, _cy, _lw0, _rowH], "MOYEN"] call _label;
        private _tc = ["COMSPEC_RscCombo", [_ix + _lw0, _cy, _iw - _lw0, _rowH], "", _cBtn, _cInk, _fs * 0.95] call _mk;
        _tc lbSetData [_tc lbAdd (("Appui générique (toute pièce)") call comspec_atak_native_fnc_tr), ""];
        { _tc lbSetData [_tc lbAdd (((format ["%1 · %2–%3 m · %4", (_x get "label") call comspec_atak_native_fnc_tr, _x get "min", _x get "max", (_x get "angle") call comspec_atak_native_fnc_tr])) call comspec_atak_native_fnc_tr), _x get "key"]; } forEach _fsTypes;
        _tc lbSetCurSel (((_fsTypes findIf { (_x get "key") isEqualTo _fsType }) + 1) max 0);
        _tc ctrlSetTooltip (("Moyen d'appui : portées, munitions, coups, délais et distance de sécurité propres au moyen") call comspec_atak_native_fnc_tr);
        _tc ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["fsType", _c lbData _i] call comspec_atak_native_fnc_firesAction; }];
        _cy = _cy + _rowH + _pad;
    };
    // Pièce : liste des pièces du camp (bleue) et MAN.
    private _cw = _iw * 0.62;
    private _combo = ["COMSPEC_RscCombo", [_ix, _cy, _cw, _rowH], "", _cBtn, _cInk, _fs * 0.95] call _mk;
    { _combo lbSetData [_combo lbAdd (((([_x, _forEachIndex] call _gunName) + " · " + ([_x, 8] call comspec_atak_native_fnc_gridRef))) call comspec_atak_native_fnc_tr), netId _x]; } forEach _guns;
    if ((count _guns) isEqualTo 0) then { _combo lbAdd (("Aucune pièce du camp") call comspec_atak_native_fnc_tr); _combo lbSetData [0, "MAN"]; };
    if (_fsSim) then { _combo lbSetData [_combo lbAdd (((format [("Batterie simulée · %1 (hors carte)") call comspec_atak_native_fnc_tr, (_fsT getOrDefault ["short", ""]) call comspec_atak_native_fnc_tr])) call comspec_atak_native_fnc_tr), "SIM"]; };
    private _ci = [_guns findIf { netId _x isEqualTo _gunId }, (lbSize _combo) - 1] select (_gunId isEqualTo "SIM" && {_fsSim});
    _combo lbSetCurSel (_ci max 0);
    _form set ["gun", _combo];
    _combo ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["gun", _c lbData _i] call comspec_atak_native_fnc_firesAction; }];
    [[_ix + _cw + _pad, _cy, _iw - _cw - _pad, _rowH], "MAN", { ["gun", "MAN"] call comspec_atak_native_fnc_firesAction; }, ["off", "sel"] select (_gunId isEqualTo "MAN"), controlNull, "Pièce manuelle : saisir sa grille"] call _btn;
    _cy = _cy + _rowH + _pad;
    [[_ix, _cy, _cw, _rowH], "PIÈCE À MA POSITION", { ["gunHere"] call comspec_atak_native_fnc_firesAction; }, "yellow"] call _btn;
    _cy = _cy + _rowH + _pad;
    // Grille et altitude de la pièce.
    private _gunPos = if (_gunId in ["MAN", "SIM"]) then { [] } else { getPosASL (objectFromNetId _gunId) };
    private _lw = _iw * 0.14;
    [[_ix, _cy, _lw, _rowH], "GRILLE"] call _label;
    switch (_gunId) do {
        case "MAN": { ["gunGrid", [_ix + _lw, _cy, _iw * 0.48, _rowH], ["gunGrid"] call _v, "GRILLE : position de la pièce, ex. 123 456"] call _edit; };
        // Batterie simulée (appui feu typé) : pas de position sur la carte.
        case "SIM": { [[_ix + _lw, _cy, _iw * 0.48, _rowH], "HORS CARTE"] call _ro; };
        default { [[_ix + _lw, _cy, _iw * 0.48, _rowH], [objectFromNetId _gunId, 10] call comspec_atak_native_fnc_gridRef] call _ro; };
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
    private _h = _rowH * 0.9 + _pad * (_nRows + 2) + _rowH * _nRows + _rowH * 1.9;
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
        ["obsGrid", [_ix + _u * 2.2, _cy, _u * 5, _rowH], ["obsGrid", [player, 10] call comspec_atak_native_fnc_gridRef] call _v, "OBS : grille de l'observateur, ex. 123 456"] call _edit;
        _cy = _cy + _rowH + _pad;
    };
    // AZ, DIST, CIBLE (polaire) ou grille de la cible.
    if (_mode isEqualTo "POLAR") then {
        [[_ix, _cy, _u * 1.2, _rowH], "AZ"] call _label;
        ["az", [_ix + _u * 1.2, _cy, _u * 2.4, _rowH], ["az", str round ((getDir player) * 6400 / 360)] call _v, "AZ : azimut de la cible en mils, ex. 1600"] call _edit;
        [[_ix + _u * 4, _cy, _u * 1.5, _rowH], "DIST"] call _label;
        ["dist", [_ix + _u * 5.5, _cy, _u * 2.2, _rowH], ["dist", "600"] call _v, "DIST : distance à la cible en mètres, ex. 600"] call _edit;
        [[_ix + _u * 8, _cy, _u * 1.1, _rowH], "CIBLE"] call _label;
        [[_ix + _u * 9.1, _cy, _u * 2.9, _rowH], [_tgtGrid, "—"] select (_tgtGrid isEqualTo "")] call _ro;
    } else {
        [[_ix, _cy, _u * 2.2, _rowH], "GRILLE"] call _label;
        ["tgtGrid", [_ix + _u * 2.2, _cy, _u * 5.5, _rowH], ["tgtGrid", _tgtGrid] call _v, "GRILLE : position de la cible, ex. 123 456"] call _edit;
    };
    _cy = _cy + _rowH + _pad;
    // ENNEMI, munition, nombre de coups.
    [[_ix, _cy, _u * 2.2, _rowH], "ENNEMI"] call _label;
    ["enemy", [_ix + _u * 2.2, _cy, _u * 3.3, _rowH], ["enemy", ""] call _v, "ENNEMI : description, ex. infanterie à découvert"] call _edit;
    {
        _x params ["_t", "_k", "_tip"];
        [[_ix + _u * (5.8 + _forEachIndex * 1.55), _cy, _u * 1.55, _rowH], _t, compile format ["['ammo', '%1'] call comspec_atak_native_fnc_firesAction;", _k], ["off", "sel"] select (_ammo isEqualTo _k), controlNull, _tip] call _btn;
    } forEach ([["HE", "HE", "Explosif"], ["ILM", "ILLUM", "Éclairant"], ["FUM", "SMOKE", "Fumigène"]] select { (_x select 1) in _fsAmmo });
    [[_ix + _u * 10.6, _cy, _u * 0.5, _rowH], "X"] call _label;
    ["rounds", [_ix + _u * 11.1, _cy, _u * 0.9, _rowH], ["rounds", "3"] call _v, "X : nombre de coups, ex. 3"] call _edit;
    _cy = _cy + _rowH + _pad;
    // Gerbe.
    [[_ix, _cy, _u * 1.7, _rowH], "OFF", { if ((call comspec_atak_native_fnc_firesState) getOrDefault ["sheaf", false]) then { ["sheaf"] call comspec_atak_native_fnc_firesAction; }; }, ["off", "sel"] select !_sheaf, controlNull, "Tous les coups au même point"] call _btn;
    [[_ix + _u * 1.7, _cy, _u * 2.4, _rowH], "GERBE", { if !((call comspec_atak_native_fnc_firesState) getOrDefault ["sheaf", false]) then { ["sheaf"] call comspec_atak_native_fnc_firesAction; }; }, ["off", "sel"] select _sheaf, controlNull, "Coups répartis autour de la cible"] call _btn;
    _cy = _cy + _rowH + _pad;
    // Aide : sens des champs, avec un exemple (Arma n'a pas de texte fantôme dans un champ).
    private _hc = ["COMSPEC_RscStructuredText", [_ix, _cy, _iw, _rowH * 1.9], "", [], [], _fs * 0.9] call _mk;
    _hc ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _th get "hexTextDim", (("AZ : azimut de la cible en mils, ex. 1600 · DIST : distance en mètres, ex. 600 · X : nombre de coups, ex. 3. GERBE répartit les coups autour de la cible, OFF les tire au même point.") call comspec_atak_native_fnc_tr) regexReplace ["[<>&]", ""]]; // i18n:brut
    _hc ctrlEnable false;
    _py + _h + _pad
};

// ---------- SOLUTION (bandeau sombre) ----------
private _drawSolution = {
    params ["_px", "_py", "_pw"];
    private _h = _rowH * ([3.6, 4.4] select _fsOn);
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
        ["COMSPEC_RscText", [_px + _pad + _forEachIndex * _cw, _py + _pad * 0.6, _cw, _fs], _t, [], _cDim, _fs * 0.85] call _mk;
        private _vc = ["COMSPEC_RscMono", [_px + _pad + _forEachIndex * _cw, _py + _pad * 0.6 + _fs, _cw, _rowH], "", [], _cValue, _font * 1.05] call _mk;
        _vc ctrlSetText ((_val) call comspec_atak_native_fnc_tr);
    } forEach _cells;
    // Note : intervalle vertical, dérive du vent, portée.
    private _note = if (_has) then {
        format ["VI %1%2M APPLIQUÉ · DÉRIVE VENT ~%3M%4", ["", "+"] select ((_sol getOrDefault ["vi", 0]) >= 0), round (_sol getOrDefault ["vi", 0]), round (_sol getOrDefault ["drift", 0]),
            [("  ·  HORS PORTÉE") call comspec_atak_native_fnc_tr, ""] select (_sol getOrDefault ["inRange", true])]
    } else { "Appuyez sur CALCULER après avoir renseigné la pièce et la cible : la solution de tir apparaît ici." };
    // Note sur plusieurs lignes (texte structuré : retour à la ligne automatique).
    private _noteC = ["COMSPEC_RscStructuredText", [_px + _pad, _py + _h - _rowH * 1.6, _pw - 2 * _pad, _rowH * 1.5], "", [], [], _fs * 0.9] call _mk;
    private _noteHex = [_th get "hexWarn", _th get "hexAlert"] select (_has && {!(_sol getOrDefault ["inRange", true])});
    _noteC ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _noteHex, ((_note) call comspec_atak_native_fnc_tr) regexReplace ["[<>&]", ""]]; // i18n:brut
    _noteC ctrlEnable false;
    // Appui feu typé : portée du moyen et distance de sécurité (ami le plus proche de la cible).
    if (_fsOn) then {
        private _fr = _sol getOrDefault ["friend", -1];
        private _dc = _sol getOrDefault ["dc", _fsT getOrDefault ["dc", 600]];
        private _close = _has && {_fr >= 0} && {_fr < _dc};
        private _rMax = _sol getOrDefault ["rMax", _fsT getOrDefault ["max", 0]];
        private _n2 = (if (_rMax > 0) then { format [("PORTÉE %1–%2 M") call comspec_atak_native_fnc_tr, _sol getOrDefault ["rMin", _fsT getOrDefault ["min", 0]], _rMax] + "  ·  " } else { "" })
            + (if (_close) then { format [("DANGER CLOSE : AMIS À %1 M (SÉCURITÉ %2 M)") call comspec_atak_native_fnc_tr, _fr, _dc] } else { format [("DISTANCE DE SÉCURITÉ %1 M") call comspec_atak_native_fnc_tr, _dc] })
            + (if (_fsType isEqualTo "") then { "" } else { "  ·  " + format [("%1 COUPS MAX") call comspec_atak_native_fnc_tr, _fsT get "rounds"] });
        private _c2 = ["COMSPEC_RscText", [_px + _pad, _py + _h - _rowH * 2.45, _pw - 2 * _pad, _rowH * 0.8], "", [], [_cInfo, _cRed] select _close, _fs * 0.85] call _mk;
        _c2 ctrlSetText _n2; // i18n:brut (morceaux déjà traduits)
    };
    _py + _h + _pad
};

// ---------- ÉTAT DE LA MISSION ----------
private _drawStatus = {
    params ["_px", "_py", "_pw"];
    private _lines = [
        _gunLabel,
        if (_gunId isEqualTo "MAN") then { format [("MANUEL %1") call comspec_atak_native_fnc_tr, [_stGrid, "—"] select (_stGrid isEqualTo "")] } else { format [("CIBLE %1") call comspec_atak_native_fnc_tr, [_stGrid, "—"] select (_stGrid isEqualTo "")] },
        if (_f getOrDefault ["active", false]) then { ("EN COURS") call comspec_atak_native_fnc_tr } else { [("NON PROGRAMMÉE") call comspec_atak_native_fnc_tr, ("PROGRAMMÉE") call comspec_atak_native_fnc_tr] select ((count _sol) > 0) },
        format [("%1 COUPS %2") call comspec_atak_native_fnc_tr, ["rounds", "3"] call _v, ["HE", "ILM", "FUM"] select ((["HE", "ILLUM", "SMOKE"] find _ammo) max 0)] + (["", (" · GERBE") call comspec_atak_native_fnc_tr] select _sheaf),
        ([] call comspec_atak_native_fnc_firesWind) select 0
    ];
    // Appui feu typé : moyen choisi et état de la mission de tir suivie (pièce IA, batterie simulée, servant joueur).
    if (_fsOn) then {
        _lines pushBack (if (_fsType isEqualTo "") then { ("APPUI GÉNÉRIQUE") call comspec_atak_native_fnc_tr } else { toUpper ((_fsT get "label") call comspec_atak_native_fnc_tr) });
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap]) getOrDefault [_f getOrDefault ["fsId", _f getOrDefault ["fsRecv", ""]], createHashMap];
        if ((count _e) > 0) then {
            private _stL = createHashMapFromArray [["NEW", "TRANSMISE"], ["ACK", "REÇUE"], ["EXEC", "EN PRÉPARATION"], ["SHOT", "COUPS PARTIS"], ["SPLASH", "SPLASH"], ["DONE", "TIR FINI"], ["FAIL", "IMPOSSIBLE"], ["EOM", "FIN DE MISSION"]];
            _lines pushBack format ["%1 · %2", ((_stL getOrDefault [_e getOrDefault ["state", ""], ""])) call comspec_atak_native_fnc_tr, (_e getOrDefault ["text", ""]) call comspec_atak_native_fnc_tr];
        };
    };
    private _h = _rowH * 0.9 + _pad * 2 + _fs * 1.25 * (count _lines);
    private _cy = [_px, _py, _pw, _h, "État de la mission"] call _panel;
    { (["COMSPEC_RscText", [_px + _pad, _cy + _forEachIndex * _fs * 1.25, [_pw * 0.7, _pw - 2 * _pad] select _fsOn, _fs * 1.25], "", [], _cInk, _fs * 0.92] call _mk) ctrlSetText ((_x) call comspec_atak_native_fnc_tr); } forEach _lines;
    _py + _h + _pad
};

// ---------- RÉGLAGE DU TIR (appui feu typé, mission en cours) ----------
private _drawAdjust = {
    params ["_px", "_py", "_pw"];
    if (!_fsOn || {!(_f getOrDefault ["active", false])} || {!("fsId" in _f)}) exitWith { _py };
    private _h = _rowH * 0.9 + _pad * 3 + _rowH * 2;
    private _cy = [_px, _py, _pw, _h, "Réglage du tir · axe observateur-cible"] call _panel;
    private _ix = _px + _pad;
    private _u = (_pw - 2 * _pad) / 12;
    private _step = _f getOrDefault ["adjStep", 100];
    {
        _x params ["_t", "_k", "_tip"];
        [[_ix + _u * 3 * _forEachIndex, _cy, _u * 2.9, _rowH], _t, compile format ["['adj', '%1'] call comspec_atak_native_fnc_firesAction;", _k], "off", controlNull, _tip] call _btn;
    } forEach [[format ["+ %1", _step], "ADD", "Allonger : plus loin sur l'axe, un coup de réglage"], [format ["– %1", _step], "DROP", "Raccourcir : plus près sur l'axe, un coup de réglage"],
        [format ["G %1", _step], "LEFT", "Gauche de l'axe, un coup de réglage"], [format ["D %1", _step], "RIGHT", "Droite de l'axe, un coup de réglage"]];
    _cy = _cy + _rowH + _pad;
    {
        [[_ix + _u * 2 * _forEachIndex, _cy, _u * 1.9, _rowH], format ["%1 M", _x], compile format ["['adjStep', %1] call comspec_atak_native_fnc_firesAction;", _x], ["off", "sel"] select (_step isEqualTo _x), controlNull, "Pas du réglage"] call _btn;
    } forEach [50, 100, 200];
    [[_ix + _u * 6.2, _cy, _u * 5.8, _rowH], "EFFICACE", { ["ffe"] call comspec_atak_native_fnc_firesAction; }, "yellow", controlNull, "Tir d'efficacité : tous les coups au point réglé"] call _btn;
    _py + _h + _pad
};

// ---------- JOURNAL ----------
private _drawLog = {
    params ["_px", "_py", "_pw", ["_hMin", 0]];
    private _msn = _f getOrDefault ["missions", []];
    private _n = (count _msn) min 8;
    private _h = (_rowH * 0.9 + _pad * 2 + ((_rowH * 0.95) * ([_n, 2.4] select (_n isEqualTo 0)))) max _hMin;
    private _cy = [_px, _py, _pw, _h, "Journal · toucher pour rappeler"] call _panel;
    [[_px + _pw - _pad - _pw * 0.26, _py + _rowH * 0.1, _pw * 0.26, _rowH * 0.7], "NOUVELLE MSN", { ["newMsn"] call comspec_atak_native_fnc_firesAction; }, "flat", controlNull, "Nouvelle mission : cible et solution remises à zéro"] call _btn;
    if (_n isEqualTo 0) then {
        private _e0 = ["COMSPEC_RscStructuredText", [_px + _pad, _cy, _pw - 2 * _pad, _rowH * 2.3], "", [], [], _fs * 0.9] call _mk;
        _e0 ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _th get "hexTextDim", (("Aucune mission enregistrée. Après CALCULER puis TIR, la mission apparaît ici : touchez-la pour la rappeler.") call comspec_atak_native_fnc_tr) regexReplace ["[<>&]", ""]]; // i18n:brut
        _e0 ctrlEnable false;
    };
    for "_i" from 0 to (_n - 1) do {
        private _idx = (count _msn) - 1 - _i;
        (_msn select _idx) params ["_t", "_g", "_a", "_r", "_st"];
        private _b = [[_px + _pad, _cy + _i * _rowH * 0.95, _pw - 2 * _pad, _rowH * 0.85], format ["%1  %2  %3 x%4  %5", _t, _g, _a, _r, _st], compile format ["['recall', %1] call comspec_atak_native_fnc_firesAction;", _idx], "off"] call _btn;
        _b ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
        [_b, (_fs * 0.95) max COMSPEC_ATAK_FontMin, true, 0.8] call comspec_atak_native_fnc_textFit;
        _b ctrlSetTooltip (("Rappeler cette mission (cible, munition, coups)") call comspec_atak_native_fnc_tr);
    };
    _py + _h + _pad
};

// Disposition.
private _y0 = _pad * 1.5;
if (_land) then {
    private _wL = (_gw - 3 * _pad) * 0.56;
    private _wR = (_gw - 3 * _pad) - _wL;
    private _xR = _pad * 2 + _wL;
    private _yl = [_pad, _y0, _wL] call _drawFiring;
    _yl = [_pad, _yl, _wL] call _drawTarget;
    _yl = [_pad, _yl, _wL] call _drawSolution;
    private _yr = [_xR, _y0, _wR] call _drawStatus;
    _yr = [_xR, _yr, _wR] call _drawAdjust;
    [_xR, _yr, _wR, (_yl - _yr - _pad) max 0] call _drawLog;
} else {
    private _w = _gw - 2 * _pad;
    private _y = [_pad, _y0, _w] call _drawFiring;
    _y = [_pad, _y, _w] call _drawTarget;
    _y = [_pad, _y, _w] call _drawSolution;
    _y = [_pad, _y, _w] call _drawStatus;
    _y = [_pad, _y, _w] call _drawAdjust;
    [_pad, _y, _w] call _drawLog;
};

// Barre du bas : CALCULER, FIN DE MISSION, TIR et l'état du tir.
private _by = _ry + _rh - _barH;
private _bw = _rw * ([0.16, 0.25] select !_land);
{
    _x params ["_t", "_code", "_style", "_tip"];
    private _b = ["COMSPEC_RscButton", [_rx + _pad + _forEachIndex * (_bw + _pad), _by + _pad * 0.4, _bw, _barH - _pad * 0.8], _t, [], [], _font * 0.9, _d displayCtrl 88531] call _mk;
    private _cols = switch (_style) do { case "blue": { [_cSel, _cOnSel] }; case "red": { [_cRedBg, _cOnRed] }; default { [_cGrey, _cInk] }; };
    _b ctrlSetBackgroundColor (_cols select 0);
    _b ctrlSetTextColor (_cols select 1);
    [_b, _font * 0.9, true, 0.8] call comspec_atak_native_fnc_textFit;
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
    if (_f getOrDefault ["active", false]) then { format [("%1 EN TIR · CIBLE %2") call comspec_atak_native_fnc_tr, _gunLabel, _stGrid] } else { "" }
};
if (_state isNotEqualTo "") then {
    private _st = ["COMSPEC_RscTextRight", [_rx + _pad * 4 + 3 * (_bw + _pad), _by, _rw - _pad * 5 - 3 * (_bw + _pad), _barH], "", [], [_cDim, _cRed] select (_hint select 1), _fs * 0.95, _d displayCtrl 88531] call _mk;
    _st ctrlSetText ((_state) call comspec_atak_native_fnc_tr);
    (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) pushBack _st;
};
true
