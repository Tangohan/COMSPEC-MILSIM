/*
    App Plan : tout ce qui entoure la carte, redessiné seul à chaque clic (la carte de fn_pagePlan n'est pas recréée,
    elle garde sa vue).
      - Barre du haut : ☰ OUTILS (palette), couches CAMP / BROUILLON / SALLE (où vont les nouveaux éléments),
        ↶ ANNULER, ↷ RÉTABLIR, EXPORTER ▾, PLANS, ? AIDE.
      - Palette d'outils (par-dessus la gauche de la carte) : onglets ZONES / LIGNES / POINTS, symboles APP-6A avec
        leur nom complet.
      - Barre du bas, selon l'état : tracé (consigne, couleur, épaisseur, tirets, flèche, crayon, texte, TERMINER,
        ANNULER), sélection (texte, description, couleur, DUPLIQUER, NET. ⇄, SUPPRIMER) ou repos (résumé, TUTORIEL,
        TOUT EFFACER).
      - Menus : EXPORTER, SALLE (créer, rejoindre par code, quitter) et clic droit sur la carte.
      - Aide (touche H) et tutoriel : fn_planHelp, dessinés par-dessus le reste.
    Params : ["build"] | ["key", paramètres de KeyDown] -> touche gardée
    Contrôles absolus sur le display (créés après la carte : dessinés devant elle), suivis dans
    uiNamespace COMSPEC_ATAK_PlanChrome (et COMSPEC_ATAK_PageControls : un changement d'app les efface).
*/
params [["_mode", "build"], ["_arg", []]];
disableSerialization;
private _ui = ["ui"] call comspec_atak_native_fnc_planAction;
if (_mode isEqualTo "key") exitWith {
    _arg params ["_disp", "_key", ["_shift", false], ["_ctrl", false]];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "PLAN" || {(_ui get "view") isNotEqualTo "MAP"}) exitWith { false };
    if !(_s getOrDefault ["interactive", false]) exitWith { false };
    private _f = focusedCtrl _disp;
    // Saisie dans un champ : Entrée valide le champ, le reste est à la saisie (fn_keyBlock garde les lettres).
    if (!isNull _f && {(ctrlType _f) isEqualTo 2}) exitWith {
        if (_key in [28, 156]) exitWith { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; true };
        _arg call comspec_atak_native_fnc_keyBlock
    };
    private _menuOpen = (_ui get "menu") isNotEqualTo "" || {(count (_ui get "ctx")) > 0};
    switch (true) do {
        case (_key isEqualTo 0x23 && {!_ctrl}): { ["help"] call comspec_atak_native_fnc_planAction; true };
        case (_ctrl && {_key isEqualTo 0x2C}): { ["undo"] call comspec_atak_native_fnc_planAction; true };
        case (_ctrl && {_key isEqualTo 0x15 || {_shift && {_key isEqualTo 0x2C}}}): { ["redo"] call comspec_atak_native_fnc_planAction; true };
        case (_ctrl && {_key isEqualTo 0x20}): { ["dup"] call comspec_atak_native_fnc_planAction; true };
        case (_key isEqualTo 0xD3 && {(_ui get "sel") isNotEqualTo ""}): { ["delete"] call comspec_atak_native_fnc_planAction; true };
        case (_key in [28, 156] && {(count (_ui get "draft")) > 0}): { ["finish"] call comspec_atak_native_fnc_planAction; true };
        case (_key isEqualTo 0x01 && {(_ui get "tuto") >= 0}): { ["tutoEnd"] call comspec_atak_native_fnc_planAction; true };
        case (_key isEqualTo 0x01 && {_menuOpen || {_ui get "help"}}): {
            _ui set ["menu", ""]; _ui set ["ctx", createHashMap]; _ui set ["help", false]; ["chrome"] call comspec_atak_native_fnc_planAction; true
        };
        case (_key isEqualTo 0x01 && {(count (_ui get "draft")) > 0 || {(_ui get "tool") isNotEqualTo ""}}): { ["cancel"] call comspec_atak_native_fnc_planAction; true };
        case (_key isEqualTo 0x01 && {(_ui get "sel") isNotEqualTo ""}): { ["select", ["", ""]] call comspec_atak_native_fnc_planAction; true };
        default { false };
    }
};
if (_mode isNotEqualTo "build") exitWith { false };

{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PlanChrome", []]);
uiNamespace setVariable ["COMSPEC_ATAK_PlanChrome", []];
private _lay = uiNamespace getVariable ["COMSPEC_ATAK_PlanLayout", createHashMap];
if ((count _lay) isEqualTo 0) exitWith { false };
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _ctrls = uiNamespace getVariable ["COMSPEC_ATAK_PlanCtrls", createHashMap];
private _map = _ctrls getOrDefault ["map", controlNull];
if (isNull _map) exitWith { false };
(_lay get "body") params ["_bx", "_by", "_bw", "_bh"];
(_lay get "map") params ["_mx", "_my", "_mw", "_mh"];
private _rowH = _lay get "rowH";
private _gap = _lay get "gap";
private _pad = _lay get "pad";
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _kinds = _cat get "kinds";
private _colors = _cat get "colors";
private _room = ["roomId"] call comspec_atak_native_fnc_planAction;
private _list = [];
private _rects = createHashMap;
([] call comspec_atak_native_fnc_accent) params ["_acc"];

private _mk = {
    params ["_class", "_p", ["_text", ""]];
    private _c = [_class, _p, _text, false] call comspec_atak_native_fnc_pageCtrl;
    _list pushBack _c;
    _c
};
private _hex = {
    params ["_rgba"];
    private _h = "0123456789abcdef";
    "#" + ((_rgba select [0, 3]) apply { private _v = round (((_x max 0) min 1) * 255); (_h select [floor (_v / 16), 1]) + (_h select [_v mod 16, 1]) } joinString "")
};
private _btn = {
    params ["_rect", "_label", "_code", ["_state", ""], ["_tip", ""], ["_enabled", true]];
    private _b = ["COMSPEC_RscButton", _rect, _label] call _mk;
    // Libellé plus large que le bouton : police réduite pour qu'il tienne (fn_textFit).
    [_b, (_fs * 0.86) max COMSPEC_ATAK_FontMin] call comspec_atak_native_fnc_textFit;
    switch (_state) do {
        case "on": { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
        case "blue": { _b ctrlSetBackgroundColor [0.12, 0.32, 0.55, 0.95]; };
        case "amber": { _b ctrlSetBackgroundColor [0.55, 0.40, 0.10, 0.95]; };
        case "red": { _b ctrlSetBackgroundColor [0.45, 0.12, 0.10, 0.95]; };
        default {};
    };
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b ctrlEnable _enabled;
    _b ctrlAddEventHandler ["ButtonClick", _code];
    // Le focus resté sur le bouton le dessinait devant les effets d'écran : il revient au bouton hors écran.
    _b ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
    _b
};
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_planAction;", str _a, str _v] };
// Fond de panneau (barres, palette, menus).
private _panel = {
    params ["_r", ["_rgba", [0.035, 0.045, 0.040, 0.97]], ["_edge", []]];
    private _p = ["COMSPEC_RscText", _r] call _mk;
    _p ctrlSetBackgroundColor _rgba;
    if ((count _edge) > 0) then { private _e = ["COMSPEC_RscText", [_r select 0, _r select 1, _r select 2, pixelH * 3]] call _mk; _e ctrlSetBackgroundColor _edge; };
    _p
};
private _stext = {
    params ["_r", "_t", ["_size", 1]];
    private _c = ["COMSPEC_RscStructuredText", _r] call _mk;
    _c ctrlSetStructuredText parseText ((_t) call comspec_atak_native_fnc_tr);
    _c ctrlSetFontHeight (_fs * _size);
    _c
};

// ---- Barre du haut ----
private _ty = _by + _pad / 2;
[[_bx, _by, _bw, _lay get "top"], [0.03, 0.04, 0.035, 1]] call _panel;
private _tx = _bx + _pad;
private _toolName = ((_kinds getOrDefault [_ui get "tool", []]) param [1, ""]);
private _wTools = _bw * 0.15;
[[_tx, _ty, _wTools, _rowH], ["☰ OUTILS", format ["☰ OUTILS · %1", _toolName]] select (_toolName isNotEqualTo ""), ["pal"] call _act, ["", "on"] select (_ui get "pal"), "Palette d'outils : zones, lignes, points (APP-6A)"] call _btn;
_rects set ["tools", [_tx, _ty, _wTools, _rowH]];
_tx = _tx + _wTools + _gap * 2;
private _tgt = _ui get "target";
private _can = ["can"] call comspec_atak_native_fnc_planAction;
private _nIn = count (allPlayers select { (_x getVariable ["COMSPEC_PlanRoom", ""]) isEqualTo _room && {_room isNotEqualTo ""} });
private _segs = [
    ["S", ["CAMP", "CAMP (LECT.)"] select !_can, "Plan partagé du camp (qui le modifie : réglage serveur). Les nouveaux éléments y vont."],
    ["D", "BROUILLON", "Votre plan personnel, préparé à l'avance, gardé dans votre profil"],
    ["R", ["SALLE …", format ["SALLE %1 · %2", _room, _nIn max 1]] select (_room isNotEqualTo ""), "Plan collaboratif : tous ceux qui ont le code dessinent dessus (créer, rejoindre, quitter)"]
];
private _segW = _bw * 0.105;
private _segX0 = _tx;
{
    _x params ["_k", "_t", "_tip"];
    private _code = if (_k isEqualTo "R" && {_room isNotEqualTo "" && {_tgt isEqualTo "R"}}) then { ["menu", "ROOM"] call _act } else { ["target", _k] call _act };
    private _st = ["", ["blue", "amber", "blue"] select (["S", "D", "R"] find _k)] select (_tgt isEqualTo _k);
    [[_segX0 + _forEachIndex * (_segW + pixelW), _ty, _segW, _rowH], _t, _code, _st, _tip] call _btn;
} forEach _segs;
_rects set ["layers", [_segX0, _ty, _segW * 3 + pixelW * 2, _rowH]];
_rects set ["room", [_segX0 + (_segW + pixelW) * 2, _ty, _segW, _rowH]];
// À droite : historique, export, bibliothèque, aide.
private _redoN = count (uiNamespace getVariable ["COMSPEC_ATAK_PlanRedo", []]);
private _right = [
    ["undo", "↶ ANNULER", ["undo"] call _act, "", "Annule la dernière modification (Ctrl+Z) ; pendant un tracé : le dernier point", true],
    ["redo", "↷ RÉTABLIR", ["redo"] call _act, "", "Refait ce qui vient d'être annulé (Ctrl+Y)", _redoN > 0],
    ["export", ["EXPORTER ▾", "EXPORTER ▴"] select ((_ui get "menu") isEqualTo "EXPORT"), ["menu", "EXPORT"] call _act, ["", "on"] select ((_ui get "menu") isEqualTo "EXPORT"), "Copier le plan (code ou texte de briefing), enregistrer, publier, importer", true],
    ["plans", "PLANS", ["view", "LIB"] call _act, "", "Plans enregistrés, publication, import, affichage", true],
    ["help", "? AIDE", ["help"] call _act, ["", "on"] select (_ui get "help"), "Aide de l'outil en cours et raccourcis (touche H)", true]
];
private _rw = _bw * 0.088;
private _rx = _bx + _bw - _pad - (count _right) * (_rw + _gap) + _gap;
{
    _x params ["_k", "_t", "_code", "_st", "_tip", "_en"];
    private _r = [_rx + _forEachIndex * (_rw + _gap), _ty, _rw, _rowH];
    [_r, _t, _code, _st, _tip, _en] call _btn;
    _rects set [_k, _r];
} forEach _right;
_rects set ["history", [_rx, _ty, _rw * 2 + _gap, _rowH]];

// ---- Palette d'outils (par-dessus la gauche de la carte) ----
if (_ui get "pal") then {
    private _pw = (_bw * 0.25) max (_font * 9 / _ratio);
    private _pr = [_mx, _my, _pw, _mh];
    _rects set ["pal", _pr];
    [_pr, [0.03, 0.04, 0.035, 0.94]] call _panel;
    private _edge = ["COMSPEC_RscText", [_mx + _pw - pixelW, _my, pixelW, _mh]] call _mk;
    _edge ctrlSetBackgroundColor [0.30, 0.36, 0.32, 0.9];
    private _ip = _pad / 2;
    private _cats = _cat get "cats";
    private _tw = (_pw - _ip * 2 - _gap * ((count _cats) - 1)) / (count _cats);
    {
        _x params ["_c", "_t"];
        [[_mx + _ip + _forEachIndex * (_tw + _gap), _my + _ip, _tw, _rowH * 0.9], _t, ["tab", _c] call _act, ["", "on"] select ((_ui get "cat") isEqualTo _c)] call _btn;
    } forEach _cats;
    private _keys = (_cats select { (_x select 0) isEqualTo (_ui get "cat") }) param [0, ["", "", []]] select 2;
    private _rows = ceil ((count _keys) / 2);
    private _y0 = _my + _ip + _rowH * 0.9 + _gap;
    private _th = (((_my + _mh - _ip) - _y0 - _gap * (_rows - 1)) / (_rows max 1)) min (_rowH * 1.35);
    private _cw = (_pw - _ip * 2 - _gap) / 2;
    {
        private _def = _kinds get _x;
        private _on = (_ui get "tool") isEqualTo _x;
        private _r = [_mx + _ip + (_forEachIndex mod 2) * (_cw + _gap), _y0 + (floor (_forEachIndex / 2)) * (_th + _gap), _cw, _th];
        private _bg = ["COMSPEC_RscText", _r] call _mk;
        _bg ctrlSetBackgroundColor ([[0.07, 0.10, 0.09, 0.98], [0.10, 0.24, 0.13, 1]] select _on);
        if (_on) then { private _o = ["COMSPEC_RscText", [_r select 0, _r select 1, pixelW * 3, _r select 3]] call _mk; _o ctrlSetBackgroundColor [0.36, 0.78, 0.42, 1]; };
        private _col = [_colors getOrDefault [_def select 4, [1, 1, 1, 1]]] call _hex;
        [[(_r select 0) + pixelW * 6, (_r select 1) + (_th - _font * 1.5) / 2 max 0, _cw - pixelW * 8, _th],
            format ["<t font='COMSPEC_BarlowBold' size='1' color='%1'>%2</t><br/><t size='0.7' color='#8a9a93'>%3</t>", _col, _def select 1, ((_def select 2) regexReplace [" \(.*\)", ""]) regexReplace [" : .*", ""]], 0.95] call _stext;
        private _b = ["COMSPEC_RscButtonOverlay", _r] call _mk;
        _b ctrlSetTooltip ((_def select 2) call comspec_atak_native_fnc_tr);
        _b ctrlAddEventHandler ["ButtonClick", ["tool", _x] call _act];
        _b ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
    } forEach _keys;
};

// ---- Barre du bas ----
private _yb = _my + _mh;
[[_bx, _yb, _bw, _lay get "bot"], [0.03, 0.04, 0.035, 1]] call _panel;
_yb = _yb + _gap;
private _tool = _ui get "tool";
private _def = _kinds getOrDefault [_tool, []];
private _geom = _def param [3, ""];
private _sel = ["selItem"] call comspec_atak_native_fnc_planAction;
private _hasSel = (count _sel) > 0 && {_tool isEqualTo ""};
private _pv = uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []];
private _hintW = _bw - _pad * 2;
if ((count _pv) > 1) then {
    private _pw = _bw * 0.12;
    [[_bx + _bw - _pad - _pw * 2 - _gap, _yb, _pw, _rowH * 0.9], "AJOUTER", ["previewAdd"] call _act, "on", format ["Ajouter l'aperçu « %1 » à la couche visée", _pv select 0]] call _btn;
    [[_bx + _bw - _pad - _pw, _yb, _pw, _rowH * 0.9], "FERMER", ["previewOff"] call _act, "", "Fermer l'aperçu"] call _btn;
    _hintW = _hintW - _pw * 2 - _gap * 2;
};
private _hintTxt = switch (true) do {
    case ((count _pv) > 1): { format ["<t size='0.8' color='#e0c060'>APERÇU : %1 (%2 éléments)</t>", (_pv select 0) regexReplace ["[<>&]", ""], count (_pv select 1)] };
    case (_hasSel): {
        _sel params ["", "_k", "_pts", ["_t", ""], ["_ck", "WHITE"], "", "", "", "", ["_au", ""]];
        private _sd = _kinds getOrDefault [_k, ["", _k, _k]];
        format ["<t size='0.8' color='#8a9a93'>Sélection : <t font='COMSPEC_BarlowBold' color='%1'>%2</t> · %3%4 · %5%6 · Suppr : effacer · clic droit : menu</t>",
            [_colors getOrDefault [_ck, [1, 1, 1, 1]]] call _hex, _sd select 1, _sd select 2,
            ["", format [" · %1 points", count _pts]] select ((count _pts) > 1),
            createHashMapFromArray [["S", "plan du camp"], ["R", "salle"], ["D", "brouillon"]] getOrDefault [_ui get "selLayer", ""],
            ["", format [" · posé par %1", _au regexReplace ["[<>&]", ""]]] select (_au isNotEqualTo "")]
    };
    default { ["hintText"] call comspec_atak_native_fnc_planAction };
};
private _hint = [[_bx + _pad, _yb, _hintW, _rowH], _hintTxt] call _stext;
_ctrls set ["hint", _hint];
_yb = _yb + _rowH + _gap;
private _sw = _rowH * 0.95 / _ratio;
private _cx = _bx + _pad;
// Pastilles de couleur : outil actif (prochains éléments) ou élément sélectionné.
private _swatches = {
    params ["_curKey", "_codeFor"];
    {
        private _on = _curKey isEqualTo _x;
        private _c = ["COMSPEC_RscButton", [_cx, _yb, _sw, _rowH * 0.95], ["", "●"] select _on] call _mk;
        _c ctrlSetBackgroundColor (_colors get _x);
        _c ctrlSetTextColor ([[1, 1, 1, 1], [0, 0, 0, 1]] select (_x in ["WHITE", "YELLOW", "GREEN", "CYAN"]));
        _c ctrlAddEventHandler ["ButtonClick", [_x] call _codeFor];
        _c ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
        _cx = _cx + _sw + _gap / 2;
    } forEach (_cat get "colorOrder");
    _cx = _cx + _gap;
};
private _edit = {
    params ["_r", "_value", "_tip", "_onSet"];
    private _e = ["COMSPEC_RscEdit", _r, _value] call _mk;
    _e ctrlSetFontHeight (_fs * 0.9);
    _e ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    _e ctrlAddEventHandler ["KillFocus", _onSet];
    _e
};
_rects set ["bottom", [_bx, _my + _mh, _bw, _lay get "bot"]];
switch (true) do {
    // Tracé : couleur, épaisseur, options du type, texte, TERMINER / ANNULER.
    case (_tool isNotEqualTo ""): {
        [_ui get "color", { params ["_k"]; ["color", _k] call _act }] call _swatches;
        private _rest = [];
        { _rest pushBack [_x select 0, ["width", _x select 1] call _act, ["", "on"] select ((_ui get "width") isEqualTo (_x select 1)), "Épaisseur du trait"]; } forEach [["FIN", 1], ["MOY.", 2], ["ÉPAIS", 3]];
        if (_geom in ["LINE", "AREA", "DIR"]) then { _rest pushBack ["TIRETS", ["dash"] call _act, ["", "on"] select (_ui get "dash"), "Trait en tirets"]; };
        if (_geom isEqualTo "LINE") then {
            _rest pushBack ["FLÈCHE", ["arrow"] call _act, ["", "on"] select (_ui get "arrow"), "Pointe de flèche au bout, dans le sens du dernier segment"];
            _rest pushBack [["SEGMENTS", "CRAYON"] select (_ui get "pen"), ["pen"] call _act, ["", "on"] select (_ui get "pen"), "CRAYON : maintenir le clic et dessiner (lissé) · SEGMENTS : clic point par point"];
        };
        private _ew = _bw * 0.16;
        private _aw = _bw * 0.095;
        private _endX = _bx + _bw - _pad - _aw * 2 - _gap * 2 - _ew;
        private _ow = (((_endX - _cx) - _gap * (count _rest)) / ((count _rest) max 1)) min (_bw * 0.075);
        {
            _x params ["_t", "_code", "_st", "_tip"];
            [[_cx + _forEachIndex * (_ow + _gap), _yb, _ow, _rowH * 0.95], _t, _code, _st, _tip] call _btn;
        } forEach _rest;
        private _e = [[_endX, _yb, _ew, _rowH], _ui get "text", format ["Texte des prochains éléments (vide : %1). CLEARED / UNCLEARED, CP, RP, TRP sont numérotés tout seuls.", _def select 1],
            { params ["_c"]; ["text", ctrlText _c] call comspec_atak_native_fnc_planAction; }] call _edit;
        _e ctrlAddEventHandler ["KeyUp", { params ["_c"]; ["text", ctrlText _c] call comspec_atak_native_fnc_planAction; }];
        private _ax = _endX + _ew + _gap;
        [[_ax, _yb, _aw, _rowH], "✓ TERMINER", ["finish"] call _act, ["", "on"] select ((count (_ui get "draft")) > 1 && {_geom in ["LINE", "AREA"]}), "Termine le trait ou ferme la zone (aussi : Entrée, clic droit, double clic)", _geom in ["LINE", "AREA"]] call _btn;
        [[_ax + _aw + _gap, _yb, _aw, _rowH], ["✕ OUTIL", "✕ TRACÉ"] select ((count (_ui get "draft")) > 0), ["cancel"] call _act, "", "Abandonne le tracé en cours, puis l'outil (Échap)"] call _btn;
    };
    // Sélection : fiche de l'élément.
    case (_hasSel): {
        _sel params ["", "_k", "", ["_t", ""], ["_ck", "WHITE"], "", "", "", "", "", ["_desc", ""]];
        private _ew = _bw * 0.15;
        private _e1 = [[_cx, _yb, _ew, _rowH], _t, "Texte affiché sur la carte (40 caractères)", { params ["_c"]; ["selSet", ["text", ctrlText _c]] call comspec_atak_native_fnc_planAction; }] call _edit;
        _cx = _cx + _ew + _gap;
        private _dw = _bw * 0.3;
        private _e2 = [[_cx, _yb, _dw, _rowH], _desc, "Description : détails pour le briefing (300 caractères), reprise dans le texte exporté", { params ["_c"]; ["selSet", ["desc", ctrlText _c]] call comspec_atak_native_fnc_planAction; }] call _edit;
        if (_desc isEqualTo "") then { _e2 ctrlSetTooltip (("Description (vide) : détails pour le briefing, reprise dans le texte exporté") call comspec_atak_native_fnc_tr); };
        _cx = _cx + _dw + _gap;
        if (uiNamespace getVariable ["COMSPEC_ATAK_PlanFocusEdit", false]) then { uiNamespace setVariable ["COMSPEC_ATAK_PlanFocusEdit", false]; [{ ctrlSetFocus _this; }, _e1] call CBA_fnc_execNextFrame; };
        private _canSel = ["can", _ui get "selLayer"] call comspec_atak_native_fnc_planAction;
        _sw = _rowH * 0.8 / _ratio;
        [_ck, { params ["_k"]; ["selSet", ["color", _k]] call _act }] call _swatches;
        private _aw = _bw * 0.085;
        private _acts = [["DUPLIQUER", ["dup"] call _act, "", "Copie décalée (Ctrl+D)"]];
        if (_k in ["CLEARED", "UNCLEARED"]) then { _acts pushBack ["NET. ⇄", ["toggleClear"] call _act, "", "Bâtiment : CLEARED <-> UNCLEARED (même numéro)"]; };
        _acts pushBack ["SUPPRIMER", ["delete"] call _act, "red", "Efface l'élément (Suppr ; Ctrl+Z annule)"];
        private _ax = _bx + _bw - _pad - (count _acts) * (_aw + _gap) + _gap;
        { _x params ["_tt", "_code", "_st", "_tip"]; [[_ax + _forEachIndex * (_aw + _gap), _yb, _aw, _rowH], _tt, _code, _st, _tip, _canSel] call _btn; } forEach _acts;
        if (!_canSel) then { { _x ctrlEnable false; } forEach [_e1, _e2]; };
    };
    // Repos : résumé des couches, tutoriel, tout effacer.
    default {
        private _sh = count (["shared"] call comspec_atak_native_fnc_planAction);
        private _dr = count (["draft"] call comspec_atak_native_fnc_planAction);
        private _rm = count (["room"] call comspec_atak_native_fnc_planAction);
        private _last = (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [2, ""];
        private _sum = format ["<t size='0.8' color='#a9b8b0'>Camp : <t color='#e6efe9'>%1</t>%2 · Brouillon : <t color='#e6efe9'>%3</t>%4 · Choisissez un outil (☰ OUTILS) ou cliquez un élément</t>",
            _sh, ["", format [" « %1 »", _last regexReplace ["[<>&]", ""]]] select (_last isNotEqualTo ""), _dr, ["", format [" · Salle %1 : <t color='#e6efe9'>%2</t>", _room, _rm]] select (_room isNotEqualTo "")];
        private _aw = _bw * 0.11;
        [[_cx, _yb, _bw - _pad * 2 - (_aw + _gap) * 2, _rowH], _sum] call _stext;
        private _arm = (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PlanClearArm", -10])) <= 4;
        [[_bx + _bw - _pad - _aw * 2 - _gap, _yb, _aw, _rowH], "TUTORIEL", ["tuto", 0] call _act, "", "Visite guidée de l'app Plan"] call _btn;
        [[_bx + _bw - _pad - _aw, _yb, _aw, _rowH], ["TOUT EFFACER", "CONFIRMER ?"] select _arm, ["clearTarget"] call _act, "red", "Efface le brouillon et la couche partagée visée si vous avez le droit (deux appuis ; Ctrl+Z annule)"] call _btn;
    };
};

// ---- Menus déroulants et clic droit ----
// Lignes : [clé, libellé, précision, code, rouge] ; ligne "-" : séparateur. Fermés par un clic sur la carte ou Échap.
// La carte reçoit aussi les clics faits sur les menus posés par-dessus : son MouseButtonDown fermait le menu, qui était
// redessiné sans ses boutons avant leur ButtonClick (« Créer une salle ne fait rien »). Chaque menu est donc une zone
// bloquée pour la carte (_blocks), et chaque ligne une zone cliquable (_hits) jouée par fn_planMap au clic ; le bouton
// la joue aussi, une seule fois grâce à _once.
private _blocks = [];
private _hits = [];
private _once = {
    params ["_code"];
    compile format ["if ((diag_tickTime - (uiNamespace getVariable ['COMSPEC_ATAK_PlanHitT', -1])) > 0.4) then { uiNamespace setVariable ['COMSPEC_ATAK_PlanHitT', diag_tickTime]; call %1; };", _code]
};
private _menu = {
    params ["_x0", "_y0", "_w", "_title", "_sub", "_rows", ["_edgeCol", _acc]];
    private _rh = _font * 1.45;
    private _hh = _font * 1.9;
    private _h = _hh + _rh * (count _rows) + pixelH * 6;
    _x0 = (_x0 max (_bx + pixelW * 4)) min (_bx + _bw - _w - pixelW * 4);
    _y0 = (_y0 max (_my + pixelH * 4)) min (_my + _mh - _h - pixelH * 4);
    { private _o = ["COMSPEC_RscText", [_x0 + pixelW * _x, _y0 + pixelH * _x, _w, _h]] call _mk; _o ctrlSetBackgroundColor [0, 0, 0, 0.22]; } forEach [2, 4];
    private _br = ["COMSPEC_RscText", [_x0 - pixelW, _y0 - pixelH, _w + pixelW * 2, _h + pixelH * 2]] call _mk;
    _br ctrlSetBackgroundColor [0.30, 0.36, 0.32, 0.9];
    [[_x0, _y0, _w, _h], [0.035, 0.042, 0.038, 0.98], _edgeCol] call _panel;
    [[_x0 + pixelW * 8, _y0 + pixelH * 5, _w - pixelW * 16, _hh], format ["<t font='COMSPEC_BarlowBold' size='0.95' color='#e6efe9'>%1</t><br/><t size='0.75' color='#8a9a93'>%2</t>", _title, _sub]] call _stext;
    private _ry = _y0 + pixelH * 6 + _hh;
    {
        _x params ["_k", "_lab", ["_s", ""], ["_code", {}], ["_red", false]];
        private _dv = ["COMSPEC_RscText", [_x0 + pixelW * 8, _ry, _w - pixelW * 16, pixelH]] call _mk;
        _dv ctrlSetBackgroundColor [1, 1, 1, 0.07];
        [[_x0 + pixelW * 10, _ry + (_rh - _font * 0.95) / 2, _w - pixelW * 20, _rh],
            format ["<t font='COMSPEC_BarlowBold' size='0.95' color='%1'>%2</t><t size='0.72' color='#7d8c85' align='right'>%3</t>", ["#e6efe9", "#f2685c"] select _red, _lab, (_s regexReplace ["[<>&]", ""]) select [0, 30]]] call _stext;
        private _b = ["COMSPEC_RscButtonOverlay", [_x0, _ry, _w, _rh]] call _mk;
        private _run = [_code] call _once;
        _b ctrlAddEventHandler ["ButtonClick", _run];
        _b ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
        _hits pushBack [[_x0, _ry, _w, _rh], _run];
        _ry = _ry + _rh;
    } forEach _rows;
    _blocks pushBack [_x0, _y0, _w, _h];
    [_x0, _y0, _w, _h]
};
private _mw = ((_font * 16) / _ratio) min (_bw * 0.45);
private _menuName = _ui get "menu";
if (_menuName isEqualTo "EXPORT") then {
    private _src = [_ui get "expSrc", _ui get "target"] select ((_ui get "expSrc") isEqualTo "");
    if (_src isEqualTo "R" && {_room isEqualTo ""}) then { _src = "S"; };
    private _srcName = createHashMapFromArray [["S", "plan du camp"], ["D", "brouillon"], ["R", format ["salle %1", _room]]] get _src;
    private _n = count (["items", _src] call comspec_atak_native_fnc_planAction);
    // Nom proposé à l'enregistrement : couche et heure (renommable depuis la vue PLANS).
    private _t = systemTime;
    private _saveName = format ["%1 %2h%3", ["Camp", "Brouillon", "Salle " + _room] select (["S", "D", "R"] find _src), _t select 3, [str (_t select 4), "0" + str (_t select 4)] select ((_t select 4) < 10)];
    private _rows = [
        ["SRC", format ["Source : %1", _srcName], "changer", ["expSrc"] call _act],
        ["CODE", "Copier le code du plan", "COMSPECPLAN1 · autre ATAK", ["export", _src] call _act],
        ["TEXT", "Copier le texte de briefing", "grilles · Discord", ["exportText", _src] call _act],
        ["SAVE", "Enregistrer dans mes plans", "vue PLANS", compile format ["['save', [%1, %2]] call comspec_atak_native_fnc_planAction;", str _src, str _saveName]]
    ];
    if (_src isNotEqualTo "S") then { _rows pushBack ["PUB", "Publier sur le plan du camp", ["édition réservée", "ajoute au camp"] select _can, ["publish", [_src, false]] call _act]; };
    _rows pushBack ["IMP", "Importer un plan", "presse-papiers → brouillon", ["import"] call _act];
    private _er = _rects get "export";
    [(_er select 0) + (_er select 2) - _mw, _my + pixelH * 4, _mw, "EXPORTER", format ["%1 élément(s) · %2", _n, _srcName], _rows, [0.18, 0.42, 0.66, 1]] call _menu;
};
if (_menuName isEqualTo "ROOM") then {
    private _rr = _rects get "room";
    private _x0 = _rr select 0;
    if (_room isEqualTo "") then {
        // Créer, ou rejoindre par code (champ et bouton sous le menu).
        private _r = [_x0, _my + pixelH * 4, _mw, "SALLE COLLABORATIVE", "tous ceux qui ont le code dessinent sur le même plan",
            [["NEW", "Créer une salle", "un code est donné", ["roomNew"] call _act]], [0.18, 0.42, 0.66, 1]] call _menu;
        private _ey = (_r select 1) + (_r select 3) + _gap;
        [[_r select 0, _ey, _mw, _rowH * 1.25 + _gap * 2], [0.035, 0.042, 0.038, 0.98]] call _panel;
        _blocks pushBack [_r select 0, _ey, _mw, _rowH * 1.25 + _gap * 2];
        private _e = [[(_r select 0) + _gap, _ey + _gap, _mw * 0.55, _rowH], uiNamespace getVariable ["COMSPEC_ATAK_PlanRoomInput", ""], "Code de la salle (5 caractères, ex. K7Q2M)",
            { params ["_c"]; ["roomInput", ctrlText _c] call comspec_atak_native_fnc_planAction; }] call _edit;
        _e ctrlAddEventHandler ["KeyUp", { params ["_c"]; ["roomInput", ctrlText _c] call comspec_atak_native_fnc_planAction; }];
        // Rejoindre : le code tapé est relu dans le champ (sa perte de focus n'arrive pas toujours avant le clic).
        private _join = [compile format ["private _e = (uiNamespace getVariable ['COMSPEC_ATAK_PlanCtrls', createHashMap]) getOrDefault ['roomEdit', controlNull]; if (!isNull _e) then { ['roomInput', ctrlText _e] call comspec_atak_native_fnc_planAction; }; ['roomJoin', ''] call comspec_atak_native_fnc_planAction;"]] call _once;
        _ctrls set ["roomEdit", _e];
        private _jr = [(_r select 0) + _mw * 0.55 + _gap * 2, _ey + _gap, _mw * 0.45 - _gap * 3, _rowH];
        [_jr, "REJOINDRE", _join, "on", "Rejoindre la salle de ce code"] call _btn;
        _hits pushBack [_jr, _join];
    } else {
        private _rv = missionNamespace getVariable ["COMSPEC_ATAK_PlanRoom_" + _room, []];
        private _who = (allPlayers select { (_x getVariable ["COMSPEC_PlanRoom", ""]) isEqualTo _room }) apply { name _x };
        [_x0, _my + pixelH * 4, _mw, format ["SALLE %1", _room], format ["créée par %1 · %2 dedans : %3", _rv param [4, "?"], count _who, (_who select [0, 4]) joinString ", "], [
            ["COPY", "Copier le code", _room, ["roomCopy"] call _act],
            ["TGT", "Dessiner dans la salle", ["", "actif"] select ((_ui get "target") isEqualTo "R"), ["target", "R"] call _act],
            ["PUB", "Publier sur le plan du camp", ["édition réservée", "ajoute au camp"] select _can, ["publish", ["R", false]] call _act],
            ["SAVE", "Enregistrer dans mes plans", "vue PLANS", compile format ["['save', ['R', 'Salle %1']] call comspec_atak_native_fnc_planAction;", _room]],
            ["LEAVE", "Quitter la salle", "elle reste aux autres", ["roomLeave"] call _act, true]
        ], [0.18, 0.42, 0.66, 1]] call _menu;
    };
};
private _ctx = _ui get "ctx";
if ((count _ctx) > 0) then {
    private _pos = _ctx get "pos";
    private _sp = _map ctrlMapWorldToScreen _pos;
    private _id = _ctx getOrDefault ["id", ""];
    private _ly = _ctx getOrDefault ["layer", ""];
    private _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef;
    private _rows = [];
    private _title = _grid;
    private _sub = format ["%1 m d'altitude", round (getTerrainHeightASL _pos)];
    if (_id isNotEqualTo "" && {(count _sel) > 0}) then {
        _sel params ["", "_k", "", ["_t", ""], "", "", "", "", "", ["_au", ""]];
        private _sd = _kinds getOrDefault [_k, ["", _k, _k]];
        private _canL = ["can", _ly] call comspec_atak_native_fnc_planAction;
        _title = format ["%1 %2", _sd select 1, ["", format ["« %1 »", _t regexReplace ["[<>&]", ""]]] select (_t isNotEqualTo "" && {_t isNotEqualTo (_sd select 1)})];
        _sub = format ["%1 · %2%3", _grid, createHashMapFromArray [["S", "plan du camp"], ["R", "salle"], ["D", "brouillon"]] getOrDefault [_ly, ""], ["", format [" · %1", _au]] select (_au isNotEqualTo "")];
        if (_canL) then {
            _rows append [["EDIT", "Modifier texte et description", "fiche en bas", ["ctxAct", "EDIT"] call _act], ["DUP", "Dupliquer", "Ctrl+D", ["ctxAct", "DUP"] call _act]];
            if (_k in ["CLEARED", "UNCLEARED"]) then { _rows pushBack ["TOGGLE", ["Marquer non nettoyé", "Marquer nettoyé"] select (_k isEqualTo "UNCLEARED"), "même numéro", ["ctxAct", "TOGGLE"] call _act]; };
        };
        { _x params ["_to", "_lab"]; if (_ly isNotEqualTo _to && {_to isNotEqualTo "R" || {_room isNotEqualTo ""}}) then { _rows pushBack ["TO" + _to, _lab, "copie", ["ctxAct", "TO" + _to] call _act]; }; } forEach [["D", "Copier dans le brouillon"], ["S", "Copier dans le plan du camp"], ["R", "Copier dans la salle"]];
        _rows append [["COPY", "Copier la grille", "10 chiffres", ["ctxAct", "COPY"] call _act], ["CENTER", "Centrer ici", "", ["ctxAct", "CENTER"] call _act]];
        if (_canL) then { _rows pushBack ["DELETE", "Supprimer", "Suppr", ["ctxAct", "DELETE"] call _act, true]; };
    } else {
        private _into = createHashMapFromArray [["S", "plan du camp"], ["R", "salle"], ["D", "brouillon"]] get (_ui get "target");
        _rows = [
            ["NOTE", "Poser une note ici", _into, ["ctxAct", "NOTE"] call _act],
            ["UNITH", "Unité ennemie ici", _into, ["ctxAct", "UNITH"] call _act],
            ["CP", "Point de contrôle ici", "numéroté", ["ctxAct", "CP"] call _act],
            ["RP", "Point de ralliement ici", "numéroté", ["ctxAct", "RP"] call _act],
            ["COPY", "Copier la grille", "10 chiffres", ["ctxAct", "COPY"] call _act],
            ["CENTER", "Centrer ici", "", ["ctxAct", "CENTER"] call _act],
            ["HELP", "Aide", "H", ["ctxAct", "HELP"] call _act]
        ];
    };
    private _ring = ["COMSPEC_RscIcon", [(_sp select 0) - _font * 0.45 / _ratio, (_sp select 1) - _font * 0.45, _font * 0.9 / _ratio, _font * 0.9], "\A3\ui_f\data\map\markers\military\circle_CA.paa"] call _mk;
    _ring ctrlSetTextColor _acc;
    private _w = ((_font * 15) / _ratio) min (_bw * 0.4);
    private _x0 = (_sp select 0) + pixelW * 6;
    if (_x0 + _w > _mx + _mw) then { _x0 = (_sp select 0) - _w - pixelW * 6; };
    [_x0, (_sp select 1) + pixelH * 6, _w, _title, _sub, _rows] call _menu;
};
_rects set ["map", [_mx, _my, _mw, _mh]];
// Zones de la carte couvertes par un panneau (palette ; l'aide ajoute le sien) : un clic n'y pose rien.
_rects set ["block", _blocks + ([[], [_rects getOrDefault ["pal", []]]] select (_ui get "pal"))];
_rects set ["hits", _hits];
uiNamespace setVariable ["COMSPEC_ATAK_PlanRects", _rects];
uiNamespace setVariable ["COMSPEC_ATAK_PlanChrome", _list];
// Aide et tutoriel par-dessus tout (leurs contrôles rejoignent la même liste).
["draw"] call comspec_atak_native_fnc_planHelp;
true
