/*
    App Alertes : détresse et contact, dans la famille de l'app Reco (en-tête sombre, pastilles, tuiles à icône, cartes).
      - PANIQUE en tête, sur toute la largeur : double appui (5 s pour confirmer), alerte les téléphones alliés du camp
        directement (marche sans Athena) et le poste par COMSPEC Link ;
      - ALERTES RAPIDES : contact (TIC), fin de contact, appareil abattu, en trois tuiles ;
      - JOURNAL : alertes reçues du camp et envoyées, en cartes de la couleur de leur type (CARTE pour centrer).
    En tête, les avis de l'administration ATHENA (fn_adminNotices) jusqu'à COMPRIS.
    Le compte rendu SALUTE a son app à lui (fn_pageSalute) ; un raccourci y mène depuis les alertes rapides.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _armed = diag_tickTime < (_s getOrDefault ["panicArmedUntil", -1]);
private _hint = _s getOrDefault ["alertsHint", ""];
private _bridge = [] call comspec_atak_native_fnc_bridge && {!isNil "comspec_overwatch_connect_fnc_sendTacticalAlert"};
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";

// Teintes : fond nuit, rouge de détresse, texte.
private _cBg = [0.026, 0.031, 0.028, 1];
private _cHdr = [0.012, 0.015, 0.013, 1];
private _cTile = [0.065, 0.078, 0.071, 1];
private _cLine = [0.16, 0.20, 0.17, 1];
private _cRed = [0.90, 0.24, 0.20, 1];
private _cText = [0.92, 0.95, 0.93, 1];
private _cDim = [0.55, 0.61, 0.58, 1];
private _cInk = [0.04, 0.035, 0.02, 1];

private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _box = { params ["_rect", "_rgba"]; private _c = ["COMSPEC_RscText", _rect] call _mk; _c ctrlSetBackgroundColor _rgba; _c };
private _lbl = {
    params ["_cls", "_rect", "_t", ["_fh", _fs], ["_col", _cText], ["_bold", false]];
    private _c = [_cls, _rect, _t] call _mk;
    _c ctrlSetFontHeight ((_fh) max COMSPEC_ATAK_FontMin);
    _c ctrlSetTextColor _col;
    if (_bold) then { _c ctrlSetFont (COMSPEC_ATAK_FontSet select 1); };
    _c
};
private _hit = {
    params ["_rect", "_code", ["_tip", ""]];
    private _b = ["COMSPEC_RscButtonInvisible", _rect] call _mk;
    // Clic différé d'une image (comme fn_formRender) : beaucoup d'actions redessinent la page, ce qui supprimait le bouton
    // pendant son propre gestionnaire, une cause connue d'arrêt du moteur (« can't resize AutoArray to negative size »).
    if (_code isEqualType "") then { _code = compile _code; };
    _b setVariable ["comspec_click", _code];
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        [{ params ["_c", "_code"]; [_c] call _code; }, [_c, _c getVariable ["comspec_click", {}]]] call CBA_fnc_execNextFrame;
    }];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b
};
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _sec = {
    params ["_x0", "_y0", "_w", "_t", ["_right", ""], ["_rightCol", _cDim]];
    ["COMSPEC_RscText", [_x0, _y0, _w * 0.7, _fs * 1.3], _t, _fs * 0.8, _cDim, true] call _lbl;
    if (_right isNotEqualTo "") then { ["COMSPEC_RscTextRight", [_x0 + _w * 0.3, _y0, _w * 0.7, _fs * 1.3], _right, _fs * 0.8, _rightCol, true] call _lbl; };
    [[_x0, _y0 + _fs * 1.3, _w, pixelH], _cLine] call _box;
    _y0 + _fs * 1.3 + _pad * 0.6
};
// Texte qui rétrécit tant qu'il dépasse la largeur donnée.
private _fit = {
    params ["_c", "_w", "_fh", ["_min", 0.6]];
    private _f = _fh;
    while { (ctrlTextWidth _c) > _w && {_f > _fh * _min} } do { _f = _f * 0.92; _c ctrlSetFontHeight ((_f) max COMSPEC_ATAK_FontMin); };
};

private _bg = [[0, 0, _bw, _bh], _cBg] call _box;

// ---------- En-tête ----------
private _hdrH = _font * 2.7;
private _grid = [getPosASL player, 8] call comspec_atak_native_fnc_gridRef;
[[0, 0, _bw, _hdrH], _cHdr] call _box;
[[0, 0, pixelW * 4, _hdrH], _cRed] call _box;
[[0, _hdrH - pixelH * 2, _bw, pixelH * 2], [_cRed select 0, _cRed select 1, _cRed select 2, 0.7]] call _box;
["COMSPEC_RscText", [_pad, _hdrH * 0.08, _bw * 0.5, _font * 1.3], "ALERTES", _font * 1.2, _cRed, true] call _lbl;
["COMSPEC_RscText", [_pad, _hdrH * 0.08 + _font * 1.25, _bw * 0.5, _fs * 1.2], format ["DÉTRESSE ET CONTACT · %1", (["now"] call comspec_atak_native_fnc_clockFmt)], _fs * 0.8, _cDim] call _lbl;
["COMSPEC_RscTextRight", [_bw * 0.45, _hdrH * 0.08, _bw * 0.55 - _pad, _font * 1.3], _grid, _fs, _cText, true] call _lbl;
// Pastille : à qui partent les alertes.
private _chip = ["COMSPEC_RscTextCenter", [0, _hdrH * 0.08 + _font * 1.3, 0.1, _fs * 1.15], ["CAMP SEULEMENT", "CAMP + POSTE"] select _bridge, _fs * 0.72, [_cText, _cInk] select _bridge, true] call _lbl;
private _cw = (ctrlTextWidth _chip) + _pad;
_chip ctrlSetPosition [_bw - _pad - _cw, _hdrH * 0.08 + _font * 1.3, _cw, _fs * 1.15];
_chip ctrlSetBackgroundColor ([[0.13, 0.16, 0.14, 1], [0.36, 0.78, 0.42, 1]] select _bridge);
_chip ctrlCommit 0;
_chip ctrlSetTooltip ((["Athena hors ligne : l'alerte part aux téléphones du camp", "L'alerte part aux téléphones du camp et au poste Athena"] select _bridge) call comspec_atak_native_fnc_tr);

// ---------- ADMINISTRATION ATHENA ----------
// Avis non validés (compromission, levée, données réinitialisées, certificat, numéro, message imposé) : en tête, COMPRIS pour fermer.
private _w = _bw - 2 * _pad;
private _y = _hdrH + _pad;
private _notices = ["list"] call comspec_atak_native_fnc_adminNotices;
if ((count _notices) > 0) then {
    _y = [_pad, _y, _w, "Administration ATHENA", format ["%1 à lire", count _notices], [1, 0.8, 0.3, 1]] call _sec;
    private _bwOk = (_font * 4.2) min (_w * 0.3);
    {
        (["style", _x] call comspec_atak_native_fnc_adminNotices) params ["_t", "_msg", "_rgba"];
        _x params ["_id", "_kind", "", "", "_body", "", "_hour"];
        private _col = [(_rgba select 0) * 1.4 min 1, (_rgba select 1) * 1.4 min 1, (_rgba select 2) * 1.4 min 1, 1];
        private _ch3 = _fs * 4.4;
        [[_pad, _y, _w, _ch3], [(_rgba select 0) * 0.35, (_rgba select 1) * 0.35, (_rgba select 2) * 0.35, 1]] call _box;
        [[_pad, _y, pixelW * 5, _ch3], _col] call _box;
        private _cx = _pad * 1.8;
        private _c = ["COMSPEC_RscStructuredText", [_cx, _y + _fs * 0.15, _w - (_cx - _pad) - _bwOk - _pad, _ch3 - _fs * 0.2]] call _mk;
        _c ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t font='EtelkaMonospacePro' size='0.8' color='#c9d2cd'>%3</t><br/><t size='0.9' color='#ffffff'>%4</t>%5",
            (_col select [0, 3]) call BIS_fnc_colorRGBtoHTML, [toUpper _t] call _esc, _hour, [_msg] call _esc,
            ["", format ["<br/><t size='0.8' color='#c9d2cd'>%1</t>", [_body] call _esc]] select (_kind isNotEqualTo "message" && {_body isNotEqualTo ""})]) call comspec_atak_native_fnc_tr);
        _c ctrlSetFontHeight _fs;
        private _bx = _pad + _w - _bwOk - _pad * 0.4;
        [[_bx, _y + _ch3 * 0.25, _bwOk, _ch3 * 0.5], _col] call _box;
        ["COMSPEC_RscTextCenter", [_bx, _y + _ch3 * 0.25, _bwOk, _ch3 * 0.5], "COMPRIS", _fs * 0.85, _cInk, true] call _lbl;
        [[_bx, _y + _ch3 * 0.25, _bwOk, _ch3 * 0.5], compile format ["['ack', %1] call comspec_atak_native_fnc_adminNotices;", str _id], "Fermer l'avis (Athena est prévenu)"] call _hit;
        _y = _y + _ch3 + _pad * 0.4;
    } forEach (_notices select [0, 4]);
    _y = _y + _pad * 0.6;
};

// ---------- PANIQUE ----------
private _ph = (_font * 4.2) max (_bh * 0.26);
_ph = _ph min (_font * 6);
// Repos : rouge sombre, cadre rouge. Armé : rouge vif, texte sombre.
[[_pad, _y, _w, _ph], [[0.20, 0.035, 0.03, 1], [0.86, 0.13, 0.09, 1]] select _armed] call _box;
{ [_x, [_cRed, [1, 0.85, 0.80, 1]] select _armed] call _box; } forEach [
    [_pad, _y, _w, pixelH * 2], [_pad, _y + _ph - pixelH * 2, _w, pixelH * 2], [_pad, _y, pixelW * 2, _ph], [_pad + _w - pixelW * 2, _y, pixelW * 2, _ph]
];
private _ih = _ph * 0.62;
private _ic = ["COMSPEC_RscPicture", [_pad * 2.2, _y + (_ph - _ih) / 2, _ih / _ratio, _ih], _dir + "alert_panic.paa"] call _mk;
_ic ctrlSetTextColor ([_cRed, [1, 1, 1, 1]] select _armed);
private _tx = _pad * 3.4 + _ih / _ratio;
private _tw = _w - (_tx - _pad) - _pad;
private _pt = ["COMSPEC_RscText", [_tx, _y + _ph * 0.12, _tw, _ph * 0.42], ["PANIQUE", "CONFIRMER LA PANIQUE"] select _armed, _ph * 0.36, [1, 1, 1, 1], true] call _lbl;
[_pt, _tw, _ph * 0.36, 0.5] call _fit;
private _ps = ["COMSPEC_RscStructuredText", [_tx, _y + _ph * 0.56, _tw, _ph * 0.42]] call _mk;
_ps ctrlSetStructuredText parseText ((["<t color='#d7b0aa'>Alerte l'ensemble des opérateurs de votre détresse. Il est nécessaire d'appuyer deux fois d'affilée pour valider l'envoi.</t>",
    "<t color='#ffffff' font='COMSPEC_BarlowBold'>Appuyez encore dans les 5 secondes pour ENVOYER.</t>"] select _armed) call comspec_atak_native_fnc_tr);
_ps ctrlSetFontHeight (_fs * 0.95);
[[_pad, _y, _w, _ph], { ["panic"] call comspec_atak_native_fnc_alertsAction; }, "Deux appuis : alerte de détresse à tout le camp"] call _hit;
_y = _y + _ph + _pad * 0.4;
["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.2], "Raccourci clavier : touches CBA, catégorie COMSPEC ATAK, « Bouton PANIQUE ».", _fs * 0.75, _cDim] call _lbl;
_y = _y + _fs * 1.2 + _pad * 0.3;
if (_hint isNotEqualTo "") then {
    private _h = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 2.4]] call _mk;
    _h ctrlSetStructuredText parseText ((format ["<t size='0.85' color='#7aa89a'>%1</t>", [_hint] call _esc]) call comspec_atak_native_fnc_tr);
    _y = _y + ((ctrlTextHeight _h) max (_fs * 1.2)) + _pad * 0.3;
};
_y = _y + _pad * 0.4;

// ---------- ALERTES RAPIDES ----------
_y = [_pad, _y, _w, "Alertes rapides", "avec ma grille et l'heure"] call _sec;
private _gap = _pad * 0.5;
private _quick = [
    ["TIC", "CONTACT", "Troupes au contact", [0.95, 0.67, 0.20, 1], "alert_tic"],
    ["TIC_CLEAR", "FIN DE CONTACT", "Contact rompu", [0.36, 0.78, 0.42, 1], "alert_clear"],
    ["EAGLE_DOWN", "APPAREIL ABATTU", "Aéronef au sol", [0.90, 0.28, 0.23, 1], "alert_eagle"]
];
private _qw = (_w - 2 * _gap) / 3;
private _qh = (_font * 3.4) min ((_qw * _ratio * 0.75) max (_font * 2.6));
{
    _x params ["_k", "_t", "_sub", "_rgba", "_icon"];
    private _qx = _pad + _forEachIndex * (_qw + _gap);
    [[_qx, _y, _qw, _qh], _cTile] call _box;
    [[_qx, _y, _qw, pixelH * 3], _rgba] call _box;
    private _qih = _qh * 0.38;
    private _qi = ["COMSPEC_RscPicture", [_qx + (_qw - _qih / _ratio) / 2, _y + _qh * 0.12, _qih / _ratio, _qih], _dir + _icon + ".paa"] call _mk;
    _qi ctrlSetTextColor _rgba;
    private _c = ["COMSPEC_RscTextCenter", [_qx, _y + _qh * 0.54, _qw, _qh * 0.24], _t, _fs * 0.9, _cText, true] call _lbl;
    [_c, _qw * 0.94, _fs * 0.9] call _fit;
    private _c2 = ["COMSPEC_RscTextCenter", [_qx, _y + _qh * 0.74, _qw, _qh * 0.2], _sub, _fs * 0.7, _cDim] call _lbl;
    [_c2, _qw * 0.94, _fs * 0.7] call _fit;
    [[_qx, _y, _qw, _qh], compile format ["['quick', '%1'] call comspec_atak_native_fnc_alertsAction;", _k], _sub] call _hit;
} forEach _quick;
_y = _y + _qh + _pad * 0.6;
// Raccourci vers l'app SALUTE.
private _rh = _font * 1.6;
[[_pad, _y, _w, _rh], _cTile] call _box;
[[_pad, _y, pixelW * 3, _rh], [0.30, 0.62, 0.98, 1]] call _box;
private _si = ["COMSPEC_RscPicture", [_pad * 1.8, _y + _rh * 0.2, _rh * 0.6 / _ratio, _rh * 0.6], _dir + "app_salute.paa"] call _mk;
_si ctrlSetTextColor [0.30, 0.62, 0.98, 1];
private _st = ["COMSPEC_RscStructuredText", [_pad * 2.6 + _rh * 0.6 / _ratio, _y + _rh * 0.18, _w * 0.8, _rh]] call _mk;
_st ctrlSetStructuredText parseText (("<t font='COMSPEC_BarlowBold'>COMPTE RENDU SALUTE</t>  <t color='#8a9a93' size='0.85'>dans l'app SALUTE ›</t>") call comspec_atak_native_fnc_tr);
_st ctrlSetFontHeight _fs;
[[_pad, _y, _w, _rh], { ["SALUTE"] call comspec_atak_native_fnc_navigate; }, "Ouvrir l'app SALUTE"] call _hit;
_y = _y + _rh + _pad;

// ---------- COMPROMISSIONS ----------
// ATAK du camp ramassés par l'ennemi (fn_captureServer) : à valider par un chef de groupe, le propriétaire ou un Zeus.
private _caps = ["mine"] call comspec_atak_native_fnc_compromise;
if ((count _caps) > 0) then {
    private _open = { (_x select 7) isEqualTo "suspected" } count _caps;
    _y = [_pad, _y, _w, "Compromissions", [format ["%1 à valider", _open], "traitées"] select (_open isEqualTo 0), [_cDim, [0.95, 0.55, 0.15, 1]] select (_open > 0)] call _sec;
    private _bwBtn = (_font * 4.2) min (_w * 0.3);
    {
        _x params ["_id", "", "_cs", "_num", "_imei", "_grid", "_hour", "_state", "_by", "_ath", "_ouid", "_lastGrid", "", "_wiped", "_live"];
        private _col = switch (_state) do { case "validated": { [0.36, 0.78, 0.42, 1] }; case "dismissed": { [0.55, 0.61, 0.58, 1] }; default { [0.95, 0.55, 0.15, 1] }; };
        private _stTxt = switch (_state) do {
            case "validated": { format ["VALIDÉE par %1", _by] };
            case "dismissed": { format ["REJETÉE par %1", _by] };
            default { "SUSPECTÉE" };
        };
        private _l2 = format ["IMEI %1 · n° %2", _imei, _num];
        private _l3 = switch (true) do {
            case (_wiped): { format ["Ramassé vers %1 · effacé avant la capture, rien d'exploitable", _grid] };
            case (_state isEqualTo "validated"): {
                format ["Exclu du réseau · Athena : %1", switch (_ath) do {
                    case "ok": { "certificat révoqué" };
                    case "offline": { "hors ligne, révoquer depuis le poste" };
                    default { "révocation demandée…" };
                }]
            };
            case (_lastGrid isNotEqualTo ""): { format ["Ramassé vers %1 · <t color='#f2ab33'>vu sur le réseau vers %2</t>%3", _grid, _lastGrid, ["", " (porteur neutralisé)"] select !_live] };
            default { format ["Ramassé vers %1 à %2", _grid, _hour] };
        };
        private _can = ["can", _x] call comspec_atak_native_fnc_compromise;
        private _nb = [1, 3] select _can;
        private _ch2 = _fs * 4.1;
        [[_pad, _y, _w, _ch2], _cTile] call _box;
        [[_pad, _y, pixelW * 4, _ch2], _col] call _box;
        private _cx = _pad * 1.8;
        private _c = ["COMSPEC_RscStructuredText", [_cx, _y + _fs * 0.15, _w - (_cx - _pad) - _bwBtn - _pad, _ch2 - _fs * 0.2]] call _mk;
        _c ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='#c9d2cd'>%3</t>  <t font='EtelkaMonospacePro' size='0.8' color='#8a9a93'>%4</t><br/><t size='0.85' font='EtelkaMonospacePro'>%5</t><br/><t size='0.85'>%6</t>",
            (_col select [0, 3]) call BIS_fnc_colorRGBtoHTML, _stTxt, [[_cs, "Votre ATAK"] select (_ouid isEqualTo getPlayerUID player)] call _esc, _hour, _l2, _l3]) call comspec_atak_native_fnc_tr);
        _c ctrlSetFontHeight _fs;
        // Boutons à droite : VALIDER, REJETER (si permis), CARTE.
        private _bx = _pad + _w - _bwBtn - _pad * 0.4;
        private _bh1 = (_ch2 - _pad * 0.3 * (_nb + 1)) / _nb;
        private _btns = [["CARTE", compile format ["['map', %1] call comspec_atak_native_fnc_compromise;", str _id], "Centrer la carte sur la dernière position connue", [0.10, 0.12, 0.11, 1], _cText]];
        if (_can) then {
            _btns = [
                ["VALIDER", compile format ["['validate', %1] call comspec_atak_native_fnc_compromise;", str _id], "Confirmer la compromission : téléphone exclu du réseau, certificat révoqué sur Athena", [0.86, 0.47, 0.10, 1], _cInk],
                ["REJETER", compile format ["['dismiss', %1] call comspec_atak_native_fnc_compromise;", str _id], "Fausse alerte : le téléphone reste sur le réseau", [0.10, 0.12, 0.11, 1], _cDim]
            ] + _btns;
        };
        {
            _x params ["_bt", "_code", "_tip", "_bg2", "_fg"];
            private _by2 = _y + _pad * 0.3 + _forEachIndex * (_bh1 + _pad * 0.3);
            [[_bx, _by2, _bwBtn, _bh1], _bg2] call _box;
            ["COMSPEC_RscTextCenter", [_bx, _by2, _bwBtn, _bh1], _bt, _fs * 0.8, _fg, true] call _lbl;
            [[_bx, _by2, _bwBtn, _bh1], _code, _tip] call _hit;
        } forEach _btns;
        _y = _y + _ch2 + _pad * 0.4;
    } forEach (_caps select [0, 5]);
    ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.2], "Retrouver un ATAK volé : app GE > GÉOLOC, avec son IMEI ou son numéro.", _fs * 0.75, _cDim] call _lbl;
    _y = _y + _fs * 1.2 + _pad;
};

// ---------- JOURNAL ----------
private _log = missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []];
private _lab = createHashMapFromArray [
    ["PANIC", ["PANIQUE", [0.90, 0.24, 0.20, 1], "alert_panic"]], ["TIC", ["CONTACT", [0.95, 0.67, 0.20, 1], "alert_tic"]],
    ["TIC_CLEAR", ["FIN DE CONTACT", [0.36, 0.78, 0.42, 1], "alert_clear"]], ["EAGLE_DOWN", ["APPAREIL ABATTU", [0.90, 0.28, 0.23, 1], "alert_eagle"]],
    ["SALUTE", ["SALUTE", [0.30, 0.62, 0.98, 1], "app_salute"]], ["COMPROMISE", ["COMPROMISSION", [0.95, 0.55, 0.15, 1], "app_ew"]]
];
_y = [_pad, _y, _w, "Journal", str (count _log)] call _sec;
if ((count _log) isEqualTo 0) then {
    ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Aucune alerte depuis le début de la mission.", _fs * 0.85, _cDim] call _lbl;
    _y = _y + _fs * 1.4 + _pad;
};
private _ch = _fs * 2.9;
private _bwMap = (_font * 3.6) min (_w * 0.25);
for "_i" from ((count _log) - 1) to (((count _log) - 12) max 0) step -1 do {
    (_log select _i) params ["_type", "_who", "_pos", "_lg", "_text", "_hour"];
    (_lab getOrDefault [_type, [_type, [0.62, 0.67, 0.64, 1], "app_alerts"]]) params ["_t", "_col", "_icon"];
    [[_pad, _y, _w, _ch], _cTile] call _box;
    [[_pad, _y, pixelW * 4, _ch], _col] call _box;
    private _ci = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _ch * 0.22, _ch * 0.56 / _ratio, _ch * 0.56], _dir + _icon + ".paa"] call _mk;
    _ci ctrlSetTextColor _col;
    private _cx = _pad * 2.2 + _ch * 0.56 / _ratio;
    private _c = ["COMSPEC_RscStructuredText", [_cx, _y + _ch * 0.08, _w - (_cx - _pad) - _bwMap - _pad, _ch * 0.9]] call _mk;
    _c ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='#c9d2cd'>%3</t>  <t font='EtelkaMonospacePro' size='0.8' color='#8a9a93'>%4 · %5</t><br/><t size='0.85'>%6</t>",
        (_col select [0, 3]) call BIS_fnc_colorRGBtoHTML, _t, [_who] call _esc, _hour, [_lg] call _esc, [[_text, "—"] select (_text isEqualTo "")] call _esc]) call comspec_atak_native_fnc_tr);
    _c ctrlSetFontHeight _fs;
    // CARTE : centre la carte sur l'alerte.
    private _mx = _pad + _w - _bwMap - _pad * 0.4;
    [[_mx, _y + _ch * 0.22, _bwMap, _ch * 0.56], [0.10, 0.12, 0.11, 1]] call _box;
    ["COMSPEC_RscTextCenter", [_mx, _y + _ch * 0.22, _bwMap, _ch * 0.56], "CARTE", _fs * 0.8, _cText, true] call _lbl;
    [[_mx, _y + _ch * 0.22, _bwMap, _ch * 0.56], compile format ["['map', %1] call comspec_atak_native_fnc_alertsAction;", _i], "Centrer la carte sur l'alerte"] call _hit;
    _y = _y + _ch + _pad * 0.4;
};
if ((count _log) > 0) then {
    private _vw = (_font * 6) min (_w * 0.4);
    [[_pad + _w - _vw, _y, _vw, _font * 1.4], _cTile] call _box;
    ["COMSPEC_RscTextCenter", [_pad + _w - _vw, _y, _vw, _font * 1.4], "VIDER LE JOURNAL", _fs * 0.8, _cDim, true] call _lbl;
    [[_pad + _w - _vw, _y, _vw, _font * 1.4], { ["clearLog"] call comspec_atak_native_fnc_alertsAction; }] call _hit;
    _y = _y + _font * 1.4 + _pad;
};
_y = _y + _pad;
_bg ctrlSetPosition [0, 0, _bw, _bh max _y];
_bg ctrlCommit 0;
true
