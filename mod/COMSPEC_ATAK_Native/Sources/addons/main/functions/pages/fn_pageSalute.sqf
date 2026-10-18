/*
    App SALUTE : compte rendu d'observation (taille, activité, lieu, unité, heure, équipement), sorti de l'app Alertes.
    Même famille que l'app Reco (en-tête sombre, pastilles, cadres de saisie, cartes), en bleu :
      - une ligne par lettre : grande lettre, libellé, exemple et champ de saisie ;
      - L : MA POSITION ou SOUS MON REGARD (point visé), distance et azimut ; T : MAINTENANT ;
      - ENVOYER LE SALUTE (au camp et au poste) et EFFACER ;
      - DERNIERS SALUTE de la mission (journal des alertes), en cartes.
    Paysage : S, A, U à gauche, L, T, E à droite. Portrait : l'un sous l'autre (défilement).
    Envoi et brouillon : fn_alertsAction (salute, saluteSet, saluteClear), champs sS à sE.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _land = _l get "landscape";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Form", _form];
private _d = uiNamespace getVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
private _hint = _s getOrDefault ["saluteHint", ""];
private _bridge = [] call comspec_atak_native_fnc_bridge && {!isNil "comspec_overwatch_connect_fnc_sendTacticalAlert"};

private _cBg = [0.026, 0.031, 0.028, 1];
private _cHdr = [0.012, 0.015, 0.013, 1];
private _cTile = [0.065, 0.078, 0.071, 1];
private _cField = [0.014, 0.017, 0.015, 1];
private _cLine = [0.16, 0.20, 0.17, 1];
private _cBlue = [0.30, 0.62, 0.98, 1];
private _cText = [0.92, 0.95, 0.93, 1];
private _cDim = [0.55, 0.61, 0.58, 1];
private _cInk = [0.02, 0.04, 0.07, 1];

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
    _b ctrlAddEventHandler ["ButtonClick", _code];
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
private _fit = {
    params ["_c", "_w", "_fh", ["_min", 0.6]];
    private _f = _fh;
    while { (ctrlTextWidth _c) > _w && {_f > _fh * _min} } do { _f = _f * 0.92; _c ctrlSetFontHeight ((_f) max COMSPEC_ATAK_FontMin); };
};

// Lettres dans l'ordre doctrinal S-A-L-U-T-E (retour de jeu 1.97.0 : Unité passait avant Lieu) :
// clé du brouillon, lettre, libellé, exemple, valeur par défaut.
private _fields = [
    ["sS", "S", "Volume (Size)", "nombre et type · ex. 6 PAX, 2 pick-up", ""],
    ["sA", "A", "Activité (Activity)", "ce qu'ils font · ex. patrouille vers le nord", ""],
    ["sL", "L", "Localisation (Location)", "carroyage de 6 à 10 chiffres", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef],
    ["sU", "U", "Unité, tenue (Unit)", "uniforme, insignes, signes distinctifs", ""],
    ["sT", "T", "Heure d'observation (Time)", "heure de l'observation", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
    ["sE", "E", "Équipement (Equipment)", "armes, véhicules, matériel", ""]
];
private _val = { params ["_k", "_def"]; _d getOrDefault [_k, _def] };
private _filled = { (trim ([_x select 0, _x select 4] call _val)) isNotEqualTo "" } count _fields;

private _bg = [[0, 0, _bw, _bh], _cBg] call _box;

// ---------- En-tête ----------
private _hdrH = _font * 2.7;
[[0, 0, _bw, _hdrH], _cHdr] call _box;
[[0, 0, pixelW * 4, _hdrH], _cBlue] call _box;
[[0, _hdrH - pixelH * 2, _bw, pixelH * 2], [_cBlue select 0, _cBlue select 1, _cBlue select 2, 0.7]] call _box;
["COMSPEC_RscText", [_pad, _hdrH * 0.08, _bw * 0.5, _font * 1.3], "SALUTE", _font * 1.2, _cBlue, true] call _lbl;
["COMSPEC_RscText", [_pad, _hdrH * 0.08 + _font * 1.25, _bw * 0.5, _fs * 1.2], format ["COMPTE RENDU D'OBSERVATION · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString], _fs * 0.8, _cDim] call _lbl;
private _place = "";
private _loc = nearestLocations [getPos player, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000];
if ((count _loc) > 0) then { _place = toUpper text (_loc select 0); };
["COMSPEC_RscTextRight", [_bw * 0.45, _hdrH * 0.08, _bw * 0.55 - _pad, _font * 1.3], [_place, "—"] select (_place isEqualTo ""), _fs, _cText, true] call _lbl;
private _chipX = _bw - _pad;
private _chipY = _hdrH * 0.08 + _font * 1.3;
{
    _x params ["_t", "_rgba", "_fg"];
    private _c = ["COMSPEC_RscTextCenter", [0, _chipY, 0.1, _fs * 1.15], _t, _fs * 0.72, _fg, true] call _lbl;
    private _cw = (ctrlTextWidth _c) + _pad;
    _chipX = _chipX - _cw;
    _c ctrlSetPosition [_chipX, _chipY, _cw, _fs * 1.15];
    _c ctrlSetBackgroundColor _rgba;
    _c ctrlCommit 0;
    _chipX = _chipX - _pad / 3;
} forEach [[["CAMP SEULEMENT", "CAMP + POSTE"] select _bridge, [0.13, 0.16, 0.14, 1], _cText], [format ["%1/6 REMPLIS", _filled], [_cTile, _cBlue] select (_filled >= 2), [_cDim, _cInk] select (_filled >= 2)]];

// ---------- Champs ----------
private _y0 = _hdrH + _pad;
private _wL = [_bw - 2 * _pad, (_bw - 3 * _pad) / 2] select _land;
private _xR = [_pad, _pad * 2 + _wL] select _land;
private _wR = [_bw - 2 * _pad, _bw - _xR - _pad] select _land;
private _rowH = _font * 1.6;
private _labH = _fs * 1.3;
private _blkH = _labH + _rowH;
private _letW = _blkH / _ratio * 0.62;
private _gap = _pad * 0.5;
private _yCol = [_y0, _y0];
// Rendu suivant : la saisie en cours est gardée d'abord (fn_alertsAction saluteSet).
{
    _x params ["_k", "_letter", "_name", "_ex", "_def"];
    private _right = _land && {_forEachIndex >= 3};
    private _bx = [_pad, _xR] select _right;
    private _bww = [_wL, _wR] select _right;
    private _by = _yCol select ([0, 1] select _right);
    if (!_land) then { _by = _yCol select 0; };
    private _v = [_k, _def] call _val;
    private _on = (trim _v) isNotEqualTo "";
    // Lettre.
    [[_bx, _by, _letW, _blkH], [_cTile, [0.10, 0.20, 0.32, 1]] select _on] call _box;
    [[_bx, _by, _letW, pixelH * 3], [[1, 1, 1, 0.07], _cBlue] select _on] call _box;
    ["COMSPEC_RscTextCenter", [_bx, _by + _blkH * 0.12, _letW, _blkH * 0.8], _letter, _blkH * 0.62, [_cDim, _cBlue] select _on, true] call _lbl;
    // Libellé et exemple.
    private _fx = _bx + _letW + _gap;
    private _fw = _bww - _letW - _gap;
    private _lt = ["COMSPEC_RscStructuredText", [_fx, _by, _fw, _labH]] call _mk;
    _lt ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#6f7a74'>%2</t>", toUpper _name, _ex]) call comspec_atak_native_fnc_tr);
    _lt ctrlSetFontHeight (_fs * 0.9);
    // Boutons rapides de L et T, à droite du champ.
    private _quick = switch (_k) do {
        case "sL": { [["ICI", "ui_gps", "['saluteSet', ['sL', [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]] call comspec_atak_native_fnc_alertsAction;", "Ma position"],
            ["VISÉ", "reco_eye", "['saluteSet', ['sL', [(if (isNil 'comspec_overwatch_connect_fnc_reconLookPos') then { screenToWorld [0.5, 0.5] } else { [] call comspec_overwatch_connect_fnc_reconLookPos }), 8] call comspec_atak_native_fnc_gridRef]] call comspec_atak_native_fnc_alertsAction;", "Point sous mon regard"]] };
        case "sT": { [["MAINTENANT", "", "['saluteSet', ['sT', [dayTime, 'HH:MM'] call BIS_fnc_timeToString]] call comspec_atak_native_fnc_alertsAction;", "Heure actuelle"]] };
        default { [] };
    };
    private _qbw = (_font * 3.4) min (_fw * 0.22);
    private _ew = _fw - (count _quick) * (_qbw + _gap * 0.6);
    private _ey = _by + _labH;
    [[_fx, _ey, _ew, _rowH], _cField] call _box;
    [[_fx, _ey + _rowH - pixelH, _ew, pixelH], [_cBlue select 0, _cBlue select 1, _cBlue select 2, [0.25, 0.7] select _on]] call _box;
    private _e = ["COMSPEC_RscEdit", [_fx, _ey, _ew, _rowH], _v] call _mk;
    _e ctrlSetBackgroundColor [0, 0, 0, 0];
    _e ctrlSetFontHeight (_font * 0.95);
    if (_k isEqualTo "sL") then { _e ctrlSetFont "EtelkaMonospacePro"; };
    _form set [_k, _e];
    {
        _x params ["_t", "_icon", "_code", "_tip"];
        private _qx = _fx + _ew + _gap * 0.6 + _forEachIndex * (_qbw + _gap * 0.6);
        [[_qx, _ey, _qbw, _rowH], _cTile] call _box;
        private _tx = _qx;
        if (_icon isNotEqualTo "") then {
            private _ih = _rowH * 0.5;
            private _ic = ["COMSPEC_RscPicture", [_qx + _pad * 0.4, _ey + _rowH * 0.25, _ih / _ratio, _ih], _dir + _icon + ".paa"] call _mk;
            _ic ctrlSetTextColor _cBlue;
            _tx = _qx + _pad * 0.4 + _ih / _ratio;
        };
        private _c = ["COMSPEC_RscTextCenter", [_tx, _ey, _qbw - (_tx - _qx), _rowH], _t, _fs * 0.8, _cText, true] call _lbl;
        [_c, (_qbw - (_tx - _qx)) * 0.94, _fs * 0.8, 0.55] call _fit;
        [[_qx, _ey, _qbw, _rowH], compile _code, _tip] call _hit;
    } forEach _quick;
    private _ny = _by + _blkH;
    // Lieu lisible : distance et azimut sous le champ.
    if (_k isEqualTo "sL") then {
        private _gc = ((_v splitString " ") joinString "");
        private _gp = if (_gc regexMatch "^[0-9]{6,10}$" && {((count _gc) mod 2) isEqualTo 0}) then { ([_gc] call BIS_fnc_gridToPos) param [0, []] } else { [] };
        private _info = if ((count _gp) >= 2) then {
            private _dd = player distance2D _gp;
            if (_dd < 15) then { "ici, sur ma position" } else { format ["à %1, azimut %2°", [format ["%1 m", round _dd], format ["%1 km", (_dd / 1000) toFixed 1]] select (_dd >= 1000), round (player getDir _gp)] }
        } else { ["", "carroyage illisible : ma position sera utilisée"] select _on };
        if (_info isNotEqualTo "") then {
            ["COMSPEC_RscText", [_fx, _ny, _fw, _fs * 1.2], _info, _fs * 0.75, [_cDim, [0.95, 0.62, 0.36, 1]] select ((count _gp) < 2)] call _lbl;
        };
        _ny = _ny + _fs * 1.2;
    };
    _ny = _ny + _pad * 0.6;
    if (_right) then { _yCol set [1, _ny]; } else { _yCol set [0, _ny]; };
    if (!_land) then { _yCol set [0, _ny]; };
} forEach _fields;
private _y = (_yCol select 0) max (_yCol select 1);

// ---------- ENVOYER ----------
private _w = _bw - 2 * _pad;
private _sh = _font * 1.9;
private _clrW = (_font * 5) min (_w * 0.3);
private _sendW = _w - _clrW - _gap;
[[_pad, _y, _sendW, _sh], [[0.10, 0.20, 0.32, 1], _cBlue] select (_filled >= 2)] call _box;
private _si = ["COMSPEC_RscPicture", [_pad * 2, _y + _sh * 0.22, _sh * 0.56 / _ratio, _sh * 0.56], _dir + "ui_send.paa"] call _mk;
_si ctrlSetTextColor ([_cDim, _cInk] select (_filled >= 2));
["COMSPEC_RscTextCenter", [_pad, _y, _sendW, _sh], "ENVOYER LE SALUTE", _font, [_cDim, _cInk] select (_filled >= 2), true] call _lbl;
[[_pad, _y, _sendW, _sh], { ["salute"] call comspec_atak_native_fnc_alertsAction; }, "Au camp et au poste, avec la position du lieu"] call _hit;
[[_pad + _sendW + _gap, _y, _clrW, _sh], _cTile] call _box;
["COMSPEC_RscTextCenter", [_pad + _sendW + _gap, _y, _clrW, _sh], "EFFACER", _fs * 0.9, _cDim, true] call _lbl;
[[_pad + _sendW + _gap, _y, _clrW, _sh], { ["saluteClear"] call comspec_atak_native_fnc_alertsAction; }, "Vider le compte rendu"] call _hit;
_y = _y + _sh + _pad * 0.5;
private _helpT = ["Au moins deux lettres, dont la taille ou l'activité.", "Prêt à partir."] select (_filled >= 2);
if (_hint isNotEqualTo "") then { _helpT = _hint; };
private _h = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 2.4]] call _mk;
_h ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#7aa89a'>%1</t>", [_helpT] call _esc]) call comspec_atak_native_fnc_tr);
_y = _y + ((ctrlTextHeight _h) max (_fs * 1.2)) + _pad;

// ---------- DERNIERS SALUTE ----------
private _log = (missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []]) select { (_x select 0) isEqualTo "SALUTE" };
_y = [_pad, _y, _w, "Derniers SALUTE", str (count _log)] call _sec;
if ((count _log) isEqualTo 0) then {
    ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Aucun compte rendu pendant cette mission.", _fs * 0.85, _cDim] call _lbl;
    _y = _y + _fs * 1.4 + _pad;
};
private _ch = _fs * 2.9;
for "_i" from ((count _log) - 1) to (((count _log) - 8) max 0) step -1 do {
    (_log select _i) params ["", "_who", "", "_lg", "_text", "_hour"];
    [[_pad, _y, _w, _ch], _cTile] call _box;
    [[_pad, _y, pixelW * 4, _ch], _cBlue] call _box;
    private _ci = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _ch * 0.22, _ch * 0.56 / _ratio, _ch * 0.56], _dir + "app_salute.paa"] call _mk;
    _ci ctrlSetTextColor _cBlue;
    private _cx = _pad * 2.2 + _ch * 0.56 / _ratio;
    private _c = ["COMSPEC_RscStructuredText", [_cx, _y + _ch * 0.08, _w - (_cx - _pad) - _pad, _ch * 0.9]] call _mk;
    _c ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#4d9ffa'>%1</t>  <t font='EtelkaMonospacePro' size='0.8' color='#8a9a93'>%2 · %3</t><br/><t size='0.85'>%4</t>",
        [_who] call _esc, _hour, [_lg] call _esc, [[_text, "—"] select (_text isEqualTo "")] call _esc]) call comspec_atak_native_fnc_tr);
    _c ctrlSetFontHeight _fs;
    _y = _y + _ch + _pad * 0.4;
};
_y = _y + _pad;
_bg ctrlSetPosition [0, 0, _bw, _bh max _y];
_bg ctrlCommit 0;
true
