/*
    Fiche des points de passage, au-dessus de la carte en mode POINTS DE PASSAGE (fn_pageMap) :
    - nouvelle étape : aperçu, nom (vide : nom automatique CP 3, RP 1…), type, partage (moi seul, groupe, camp),
      « plusieurs étapes », VALIDER au point choisi d'un clic droit ; un clic gauche pose tout de suite ;
    - étape existante (clic sur l'étape, ou MODIFIER L'ÉTAPE du clic droit) : nom, type, DÉPLACER ICI (clic droit pour
      le nouveau point), avant / après dans l'ordre, NAVIGUER, SUPPRIMER, NOUVELLE ÉTAPE.
    Params : [[x, y, w, h] zone disponible] -> bas de la fiche
*/
params ["_rect"];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_hMax"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _pad = _l get "pad";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
private _wp = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _pts = _wp getOrDefault ["pts", []];
private _sel = _s getOrDefault ["wpSel", -1];
if (_sel >= count _pts) then { _sel = -1; _s set ["wpSel", -1]; };
private _edit = _sel >= 0;
private _types = ["types"] call comspec_atak_native_fnc_wpAction;
private _type = if (_edit) then { (_pts select _sel) param [2, "WP"] } else { _s getOrDefault ["wpType", "WP"] };
private _tRow = ["type", _type] call comspec_atak_native_fnc_wpAction;
private _col = _tRow select 3;
private _anchor = _s getOrDefault ["wpAnchor", []];
private _hasAnchor = (count _anchor) >= 2;
private _share = profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1];
private _green = [0.36, 0.78, 0.42, 1];
private _rh = _fs * 1.55;
private _y = _y0;
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _bg = ["COMSPEC_RscMapPanel", [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, _hMax]] call _mk;
// Liseré à la couleur du type.
private _edge = ["COMSPEC_RscText", [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, pixelH * 3]] call _mk;
_edge ctrlSetBackgroundColor _col;

// 1. En-tête : aperçu du type, résumé, VALIDER (ou TERMINÉ), fermer.
private _ph = _rh * 1.7;
private _pw = _ph / _ratio;
private _pt = ["COMSPEC_RscText", [_x0, _y, _pw, _ph]] call _mk;
_pt ctrlSetBackgroundColor [(_col select 0) * 0.25, (_col select 1) * 0.25, (_col select 2) * 0.25, 0.95];
private _pic = ["COMSPEC_RscIcon", [_x0 + _pw * 0.18, _y + _ph * 0.12, _pw * 0.64, _ph * 0.64], ["icon", _type] call comspec_atak_native_fnc_wpAction] call _mk;
_pic ctrlSetTextColor _col;
private _num = ["COMSPEC_RscTextCenter", [_x0, _y + _ph * 0.72, _pw, _ph * 0.28], str ([(count _pts) + 1, _sel + 1] select _edit)] call _mk;
_num ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
_num ctrlSetTextColor [0.92, 0.95, 0.93, 1];
private _ib = _rh;
private _ibW = _ib / _ratio;
private _closeX = _x0 + _w - _ibW;
private _cf = ["COMSPEC_RscText", [_closeX, _y + (_ph - _ib) / 2, _ibW, _ib]] call _mk;
_cf ctrlSetBackgroundColor [0.10, 0.12, 0.11, 0.95];
private _ci = ["COMSPEC_RscIcon", [_closeX + _ibW * 0.2, _y + (_ph - _ib) / 2 + _ib * 0.2, _ibW * 0.6, _ib * 0.6], "\z\comspec_atak_native\addons\main\data\ui_close.paa"] call _mk;
_ci ctrlSetTextColor [0.95, 0.45, 0.40, 1];
private _cb = ["COMSPEC_RscButtonOverlay", [_closeX, _y + (_ph - _ib) / 2, _ibW, _ib]] call _mk;
_cb ctrlSetTooltip (("Fermer (retour à la sélection)") call comspec_atak_native_fnc_tr);
_cb ctrlAddEventHandler ["ButtonClick", { ["close"] call comspec_atak_native_fnc_wpAction; }];
private _vw = (_w * 0.22) max (_fs * 6 / _ratio * 0.6);
private _vx = _closeX - _pad / 2 - _vw;
private _vb = ["COMSPEC_RscButtonPrimary", [_vx, _y + (_ph - _rh * 1.15) / 2, _vw, _rh * 1.15], ["VALIDER", "TERMINÉ"] select _edit] call _mk;
_vb ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
_vb ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
if (_edit) then {
    _vb ctrlSetTooltip (("Garder le nom saisi et revenir à la pose de nouvelles étapes") call comspec_atak_native_fnc_tr);
    _vb ctrlAddEventHandler ["ButtonClick", {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        ["rename", [_s getOrDefault ["wpSel", -1], _s getOrDefault ["wpName", ""]]] call comspec_atak_native_fnc_wpAction;
        ["new"] call comspec_atak_native_fnc_wpAction;
    }];
} else {
    _vb ctrlSetTooltip (([
        "Clic droit sur la carte pour choisir le point, puis VALIDER",
        "Poser l'étape au point choisi (clic droit)"
    ] select _hasAnchor) call comspec_atak_native_fnc_tr);
    if !(_hasAnchor) then { _vb ctrlSetBackgroundColor [0.16, 0.22, 0.18, 0.95]; _vb ctrlSetTextColor [0.55, 0.62, 0.58, 1]; };
    _vb ctrlAddEventHandler ["ButtonClick", { ["place"] call comspec_atak_native_fnc_wpAction; }];
};
// Résumé : titre, type · partage · itinéraire, puis le point choisi ou la consigne.
private _tot = 0;
for "_i" from 1 to ((count _pts) - 1) do { _tot = _tot + (((_pts select (_i - 1)) select 0) distance2D ((_pts select _i) select 0)); };
private _title = if (_edit) then { format ["Étape %1 : %2", _sel + 1, (_pts select _sel) select 1] } else { format ["Nouvelle étape %1", (count _pts) + 1] };
private _where = if (_hasAnchor) then {
    private _from = if (_edit) then { (_pts select _sel) select 0 } else { if ((count _pts) > 0) then { (_pts select ((count _pts) - 1)) select 0 } else { getPosATL player } };
    format ["<t color='#5cc76b'>%1</t> <t font='EtelkaMonospacePro'>%2</t> · %3 · %4°", ["POINT", "NOUVEAU POINT"] select _edit, [_anchor, 8] call comspec_atak_native_fnc_gridRef,
        [_from distance2D _anchor] call _fmt, round (_from getDir _anchor)]
} else {
    if (_edit) then {
        format ["<t font='EtelkaMonospacePro'>%1</t> · <t color='#d9a441'>%2</t>", [(_pts select _sel) select 0, 8] call comspec_atak_native_fnc_gridRef, ("clic droit : nouveau point, puis DÉPLACER ICI") call comspec_atak_native_fnc_tr]
    } else {
        format ["<t color='#d9a441'>%1</t>", ("Clic droit : choisir le point · clic gauche : poser tout de suite") call comspec_atak_native_fnc_tr]
    }
};
private _sum = ["COMSPEC_RscStructuredText", [_x0 + _pw + _pad, _y, (_vx - _pad) - (_x0 + _pw + _pad), _ph]] call _mk;
_sum ctrlSetStructuredText parseText ((format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t color='#8a9a93' size='0.85'>%2 · partage %3 · itinéraire %4 étape(s), %5</t><br/><t size='0.8'>%6</t>",
    _title regexReplace ["[<>&]", ""], toLower (_tRow select 1), toLower (["shareName", _share] call comspec_atak_native_fnc_wpAction), count _pts, [_tot] call _fmt, _where]) call comspec_atak_native_fnc_tr);
_sum ctrlSetFontHeight (_fs * 0.9);
_y = _y + _ph + _pad;

private _lab = { params ["_t", "_yy"]; private _c = ["COMSPEC_RscLabel", [_x0, _yy, _w * 0.14, _rh], _t] call _mk; _c ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin); };
private _fx = _x0 + _w * 0.14;
private _fw = _w * 0.86;

// 2. Nom : saisie gardée à chaque touche (un rendu de la carte ne la perd pas), Entrée = VALIDER / TERMINÉ.
["NOM", _y] call _lab;
private _e = ["COMSPEC_RscEdit", [_fx, _y, _fw * 0.6, _rh], _s getOrDefault ["wpName", ""]] call _mk;
_e ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
_e ctrlSetTooltip (("Nom de l'étape (24 caractères). Vide : nom automatique") call comspec_atak_native_fnc_tr);
_e ctrlAddEventHandler ["KeyUp", { params ["_c"]; (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["wpName", ctrlText _c]; false }];
_e ctrlAddEventHandler ["KeyDown", {
    params ["_c", "_key"];
    if !(_key in [28, 156]) exitWith { false };
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _s set ["wpName", ctrlText _c];
    [{
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_s getOrDefault ["wpSel", -1]) >= 0) then {
            ["rename", [_s getOrDefault ["wpSel", -1], _s getOrDefault ["wpName", ""]]] call comspec_atak_native_fnc_wpAction;
        } else {
            ["place"] call comspec_atak_native_fnc_wpAction;
        };
    }] call CBA_fnc_execNextFrame;
    true
}];
private _hint = ["COMSPEC_RscStructuredText", [_fx + _fw * 0.6 + _pad / 2, _y, _fw * 0.4 - _pad / 2, _rh]] call _mk;
_hint ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#8a9a93'>%1</t>",
    [format ["Vide : « %1 »", ["autoName", _type] call comspec_atak_native_fnc_wpAction], "Entrée : renommer"] select _edit]) call comspec_atak_native_fnc_tr);
_hint ctrlSetFontHeight (_fs * 0.9);
_y = _y + _rh + _pad / 2;

// 3. Type : tuiles (icône dans la couleur du type et nom court).
["TYPE", _y] call _lab;
private _n = count _types;
private _tw = _fw / _n;
private _th = _rh * 1.6;
{
    _x params ["_k", "_label", "", "_c", "_pre"];
    private _on = _k isEqualTo _type;
    private _tx = _fx + _forEachIndex * _tw;
    if (_on) then {
        private _fr = ["COMSPEC_RscText", [_tx - pixelW, _y - pixelH, _tw - _pad / 3 + pixelW * 2, _th + pixelH * 2]] call _mk;
        _fr ctrlSetBackgroundColor _c;
    };
    private _tb = ["COMSPEC_RscText", [_tx, _y, _tw - _pad / 3, _th]] call _mk;
    _tb ctrlSetBackgroundColor ([[0.06, 0.075, 0.065, 0.95], [(_c select 0) * 0.3, (_c select 1) * 0.3, (_c select 2) * 0.3, 1]] select _on);
    private _ih = _th * 0.5;
    private _iw = _ih / _ratio;
    private _ic = ["COMSPEC_RscIcon", [_tx + (_tw - _pad / 3 - _iw) / 2, _y + _th * 0.08, _iw, _ih], ["icon", _k] call comspec_atak_native_fnc_wpAction] call _mk;
    _ic ctrlSetTextColor _c;
    private _lt = ["COMSPEC_RscTextCenter", [_tx, _y + _th * 0.6, _tw - _pad / 3, _th * 0.38], _pre] call _mk;
    _lt ctrlSetFontHeight ((_fs * 0.7) max COMSPEC_ATAK_FontMin);
    _lt ctrlSetTextColor ([[0.70, 0.76, 0.72, 1], [1, 1, 1, 1]] select _on);
    private _b = ["COMSPEC_RscButtonOverlay", [_tx, _y, _tw - _pad / 3, _th]] call _mk;
    _b ctrlSetTooltip ((_label) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", compile ([
        format ["['setType', '%1'] call comspec_atak_native_fnc_wpAction;", _k],
        format ["['retype', [%1, '%2']] call comspec_atak_native_fnc_wpAction;", _sel, _k]
    ] select _edit)];
} forEach _types;
_y = _y + _th + _pad / 2;

// 4. Partage (Groupe par défaut : l'itinéraire suit en direct chez les équipiers) et pose de plusieurs étapes.
["PARTAGE", _y] call _lab;
private _sw = (_fw * 0.66) / 3;
{
    private _on = _forEachIndex isEqualTo _share;
    private _b = ["COMSPEC_RscButton", [_fx + _forEachIndex * _sw, _y, _sw - _pad / 3, _rh], ["shareName", _forEachIndex] call comspec_atak_native_fnc_wpAction] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
    if (_on) then { _b ctrlSetBackgroundColor _green; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlSetTooltip ((_x) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", compile format ["['setShare', %1] call comspec_atak_native_fnc_wpAction;", _forEachIndex]];
} forEach [
    "Moi seul : l'itinéraire reste sur mon téléphone",
    "Groupe : chaque étape posée, modifiée ou supprimée s'affiche aussitôt chez mon groupe",
    "Camp : tout mon camp voit l'itinéraire et ses changements"
];
private _ox = _fx + _fw * 0.66 + _pad / 2;
private _ow = _x0 + _w - _ox;
if (_edit) then {
    private _nb = ["COMSPEC_RscButton", [_ox, _y, _ow, _rh], "+ NOUVELLE ÉTAPE"] call _mk;
    _nb ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
    _nb ctrlSetTooltip (("Revenir à la pose de nouvelles étapes") call comspec_atak_native_fnc_tr);
    _nb ctrlAddEventHandler ["ButtonClick", { ["new"] call comspec_atak_native_fnc_wpAction; }];
} else {
    private _series = profileNamespace getVariable ["COMSPEC_ATAK_WpSeries", true];
    private _sb = ["COMSPEC_RscButton", [_ox, _y, _ow, _rh], ["☐  Plusieurs étapes", "☑  Plusieurs étapes"] select _series] call _mk;
    _sb ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
    if (_series) then { _sb ctrlSetTextColor _green; };
    _sb ctrlSetTooltip (("Après VALIDER, la fiche reste ouverte pour l'étape suivante") call comspec_atak_native_fnc_tr);
    _sb ctrlAddEventHandler ["ButtonClick", { ["series"] call comspec_atak_native_fnc_wpAction; }];
};
_y = _y + _rh + _pad / 2;

// 5. Étape existante : déplacer, ordre, naviguer, supprimer.
if (_edit) then {
    private _acts = [
        ["DÉPLACER ICI", format ["['move', %1] call comspec_atak_native_fnc_wpAction;", _sel], _hasAnchor, "Clic droit sur la carte pour choisir le nouveau point"],
        ["◀ AVANT", format ["['up', %1] call comspec_atak_native_fnc_wpAction;", _sel], _sel > 0, "Passer avant l'étape précédente"],
        ["APRÈS ▶", format ["['down', %1] call comspec_atak_native_fnc_wpAction;", _sel], _sel < ((count _pts) - 1), "Passer après l'étape suivante"],
        ["NAVIGUER", format ["['goto', %1] call comspec_atak_native_fnc_wpAction;", _sel], true, "Cap sur cette étape"],
        ["SUPPRIMER", format ["['del', %1] call comspec_atak_native_fnc_wpAction;", _sel], true, "Retirer l'étape de l'itinéraire"]
    ];
    private _aw = _w / (count _acts);
    {
        _x params ["_t", "_code", "_ok", "_tip"];
        private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _aw, _y, _aw - _pad / 3, _rh], _t] call _mk;
        _b ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);
        _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
        if (_t isEqualTo "SUPPRIMER") then { _b ctrlSetBackgroundColor [0.55, 0.15, 0.12, 1]; _b ctrlSetTextColor [1, 1, 1, 1]; };
        if (_t isEqualTo "DÉPLACER ICI" && {_ok}) then { _b ctrlSetBackgroundColor _green; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
        _b ctrlEnable _ok;
        _b ctrlAddEventHandler ["ButtonClick", compile _code];
    } forEach _acts;
    _y = _y + _rh + _pad / 2;
};
_bg ctrlSetPosition [_x0 - _pad, _y0 - _pad, _w + 2 * _pad, (_y - _y0) + _pad];
_bg ctrlCommit 0;
_y
