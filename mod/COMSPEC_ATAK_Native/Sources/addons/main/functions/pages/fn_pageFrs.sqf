/*
    App FRS / FRM : fiche de renseignement simplifiée, mise en page façon ATAK.
      - en-tête : date et lieu, pastilles des thèmes et du type de fiche ;
      - au centre, le texte du renseignement avec son compteur (1000 caractères) ;
      - volet ENTÊTE à gauche (type, urgence, recueil, date, lieu, carroyage, thèmes, dossier) ;
      - volet PIÈCES JOINTES à droite (4 au plus : captures, photothèque) et fiches envoyées ;
      - barre du bas : accueil, plein écran, bouton rond (trombone, menu galerie / dossier / appareil photo), envoi.
    Mêmes champs que le rédacteur d'Overwatch ; envoi au bureau SSE d'Athena (/api/sse/notes) par sa DLL,
    pièces jointes ensuite (/api/sse/notes/{id}/pieces).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["title", "Fiches de renseignement"], ["text", "<t color='#8a9a93'>Les fiches partent vers Athena par COMSPEC Overwatch, qui n'est pas chargé sur ce serveur.</t>"]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _mini = _l get "mini";
private _ratio = pixelH / pixelW;
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _btn = {
    params ["_rect", "_code", ["_tip", ""]];
    private _b = ["COMSPEC_RscButtonInvisible", _rect] call _mk;
    _b ctrlAddEventHandler ["ButtonClick", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip _tip; };
    _b
};
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _cat = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_FrsUi", createHashMap];
private _pieces = uiNamespace getVariable ["COMSPEC_ATAK_FrsPieces", []];
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_FrsHint", ["", false]];
private _max = _cat getOrDefault ["body_max", 1000];

// Valeurs par défaut : date de la mission, ville la plus proche, carroyage du joueur.
private _now = date;
private _p2 = { params ["_n"]; if (_n < 10) then { format ["0%1", _n] } else { str _n } };
if !("date" in _draft) then { _draft set ["date", format ["%1/%2/%3 %4:%5", [_now select 2] call _p2, [_now select 1] call _p2, _now select 0, [_now select 3] call _p2, [_now select 4] call _p2]]; };
if ((_draft getOrDefault ["place", ""]) isEqualTo "") then {
    private _loc = nearestLocations [getPos player, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000];
    if ((count _loc) > 0) then { _draft set ["place", toUpper text (_loc select 0)]; };
};
if !("grid" in _draft) then { _draft set ["grid", [player, 8] call comspec_atak_native_fnc_gridRef]; };

// Fond général
private _bg = ["COMSPEC_RscText", [0, 0, _bw, _bh]] call _mk;
_bg ctrlSetBackgroundColor [0.165, 0.165, 0.165, 1];

// En-tête : date à gauche, lieu à droite, pastilles dessous.
private _hdrH = _fs * 2.9;
private _hdr = ["COMSPEC_RscText", [0, 0, _bw, _hdrH]] call _mk;
_hdr ctrlSetBackgroundColor [0.07, 0.07, 0.07, 1];
private _dateC = ["COMSPEC_RscText", [_pad, 0, _bw * 0.45, _fs * 1.3], (_draft get "date") splitString " " param [0, ""]] call _mk;
_dateC ctrlSetFontHeight (_fs * 0.85); _dateC ctrlSetTextColor [0.6, 0.6, 0.6, 1];
private _placeC = ["COMSPEC_RscTextRight", [_bw * 0.45, 0, _bw * 0.55 - _pad, _fs * 1.3], [_draft getOrDefault ["place", ""], [_draft get "grid"] call _esc] select ((_draft getOrDefault ["place", ""]) isEqualTo "")] call _mk;
_placeC ctrlSetFontHeight (_fs * 0.85); _placeC ctrlSetTextColor [0.6, 0.6, 0.6, 1];
private _chipX = _pad;
private _chip = {
    params ["_text", "_rgb"];
    private _c = ["COMSPEC_RscTextCenter", [_chipX, _fs * 1.35, 0.1, _fs * 1.25], _text] call _mk;
    _c ctrlSetFontHeight (_fs * 0.8);
    private _w = (ctrlTextWidth _c) + _pad;
    _c ctrlSetPosition [_chipX, _fs * 1.35, _w, _fs * 1.25]; _c ctrlCommit 0;
    _c ctrlSetBackgroundColor _rgb; _c ctrlSetTextColor [1, 1, 1, 1];
    _chipX = _chipX + _w + _pad / 3;
};
private _themes = _draft getOrDefault ["themes", []];
{
    private _code = _x;
    private _lab = ((_cat get "themes") select { (_x select 0) isEqualTo _code }) param [0, [_code, _code]];
    [toUpper (_lab select 1), [0.93, 0.1, 0.1, 1]] call _chip;
} forEach _themes;
[_draft getOrDefault ["kind", "FRM"], [0.27, 0.22, 0.62, 1]] call _chip;
private _urg = _draft getOrDefault ["urgency", "routine"];
if (_urg isNotEqualTo "routine") then { [toUpper ((((_cat get "urgencies") select { (_x select 0) isEqualTo _urg }) param [0, [_urg, _urg]]) select 1), [0.85, 0.45, 0.1, 1]] call _chip; };
if ((count _themes) isEqualTo 0) then {
    private _c = ["COMSPEC_RscText", [_chipX, _fs * 1.35, _bw * 0.5, _fs * 1.25], "Aucun thème : ouvrez l'Entête"] call _mk;
    _c ctrlSetFontHeight (_fs * 0.75); _c ctrlSetTextColor [0.55, 0.55, 0.55, 1];
};

// Barre du bas (bandeau violet à creux) : dimensions communes.
private _barH = (_font * 2.1) max (_bh * 0.075);
private _barTop = _bh - _barH;

// Texte du renseignement
private _hw = _fs * 1.1 * _ratio;
private _tx = _hw + _pad;
private _tw = _bw - 2 * _tx;
private _cntH = _fs * 1.25;
private _ty = _hdrH + _pad;
private _th = _barTop - _ty - _cntH - _pad * 2.2;
private _tbg = ["COMSPEC_RscText", [_tx, _ty, _tw, _th]] call _mk;
_tbg ctrlSetBackgroundColor [0.235, 0.235, 0.235, 1];
private _body = _draft getOrDefault ["body", ""];
private _ph = ["COMSPEC_RscStructuredText", [_tx + _pad / 2, _ty + _pad / 3, _tw - _pad, _fs * 2.6]] call _mk;
_ph ctrlSetStructuredText parseText "<t size='0.8' color='#8c8c8c' font='RobotoCondensedLight'>Veuillez inscrire vos informations dans ce cadre. N'hésitez pas à modifier la date, les thèmes ou le lieu (Entête) en fonction de l'événement.</t>";
_ph ctrlShow (_body isEqualTo "");
private _edit = ["COMSPEC_RscEditMulti", [_tx, _ty, _tw, _th], _body] call _mk;
_edit ctrlSetBackgroundColor [0, 0, 0, 0];
_edit ctrlSetFontHeight _font;
(uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) set ["body", _edit];
private _cnt = ["COMSPEC_RscTextCenter", [_tx + _tw - _fs * 4.2 * _ratio, _ty + _th, _fs * 4.2 * _ratio, _cntH]] call _mk;
_cnt ctrlSetFontHeight (_fs * 0.85);
_cnt ctrlSetTextColor [1, 1, 1, 1];
uiNamespace setVariable ["COMSPEC_ATAK_FrsCount", [_cnt, _ph, _max]];
private _upd = {
    params ["_e"];
    (uiNamespace getVariable ["COMSPEC_ATAK_FrsCount", []]) params [["_c", controlNull], ["_p", controlNull], ["_m", 1000]];
    private _n = count (ctrlText _e);
    _c ctrlSetText format ["%1/%2", _n, _m];
    _c ctrlSetBackgroundColor ([[0.93, 0.1, 0.1, 1], [0.27, 0.22, 0.62, 1]] select (_n >= 10 && {_n <= _m}));
    _p ctrlShow (_n isEqualTo 0);
};
[_edit] call _upd;
_edit setVariable ["upd", _upd];
_edit ctrlAddEventHandler ["KeyUp", { params ["_e"]; [_e] call (_e getVariable ["upd", {}]); }];
_edit ctrlAddEventHandler ["SetFocus", { params ["_e"]; ((uiNamespace getVariable ["COMSPEC_ATAK_FrsCount", []]) param [1, controlNull]) ctrlShow false; }];
if ((_hint select 0) isNotEqualTo "") then {
    private _h = ["COMSPEC_RscStructuredText", [_tx, _ty + _th, _tw - _fs * 4.4 * _ratio, _cntH * 1.6]] call _mk;
    _h ctrlSetStructuredText parseText format ["<t size='0.8' color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), [_hint select 0] call _esc];
};

// Poignées des volets (texte vertical lettre par lettre).
private _handle = {
    params ["_x0", "_label", "_code", "_on"];
    private _hh = _bh * 0.24;
    private _hy = _hdrH + (_barTop - _hdrH - _hh) / 2;
    private _c = ["COMSPEC_RscText", [_x0, _hy, _hw, _hh]] call _mk;
    _c ctrlSetBackgroundColor ([[0.30, 0.30, 0.30, 1], [0.27, 0.22, 0.62, 1]] select _on);
    private _t = ["COMSPEC_RscStructuredText", [_x0, _hy + _pad / 3, _hw, _hh]] call _mk;
    _t ctrlSetStructuredText parseText format ["<t align='center' size='0.62' color='#e6e6e6'>%1</t>", (_label splitString "") joinString "<br/>"];
    [[_x0, _hy, _hw, _hh], _code, _label] call _btn;
};

// Volet ENTÊTE (gauche)
private _headOn = _ui getOrDefault ["head", false];
private _dw = _bw * ([0.6, 0.92] select _mini);
if (_headOn) then {
    private _kinds = (_cat get "kinds") apply { [format ["%1 · %2", _x select 0, _x select 1], _x select 0] };
    private _urgs = (_cat get "urgencies") apply { [_x select 1, _x select 0] };
    private _src = [["Non précisé", ""]] + ((_cat get "sources") apply { [format ["%1 · %2", _x select 0, _x select 1], _x select 0] });
    private _rows = [
        ["section", "Entête", "Type, urgence, date, lieu et thèmes de la fiche"],
        ["combo", "kind", "Type de fiche", _kinds, _draft getOrDefault ["kind", "FRM"]],
        ["combo", "urgency", "Urgence", _urgs, _draft getOrDefault ["urgency", "routine"]],
        ["combo", "source", "Recueil", _src, _draft getOrDefault ["source", ""]],
        ["edit", "date", "Date et heure de l'événement (JJ/MM/AAAA HH:MM)", _draft get "date"],
        ["edit", "place", "Lieu", _draft getOrDefault ["place", ""]],
        ["edit", "grid", "Repère (carroyage)", _draft get "grid"],
        ["buttons", [["MA POSITION", { [] call comspec_atak_native_fnc_frsDraftSave; (uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap]) set ["grid", [player, 8] call comspec_atak_native_fnc_gridRef]; ["FRS"] call comspec_atak_native_fnc_pageRender; }]]],
        ["section", format ["Thèmes %1 / %2", count _themes, _cat getOrDefault ["themes_max", 4]], "Un à quatre"]
    ];
    private _row = [];
    {
        _x params ["_code", "_label"];
        private _on = _code in _themes;
        _row pushBack [[_label, format ["● %1", _label]] select _on, compile format ["['%1'] call comspec_atak_native_fnc_frsToggleTheme;", _code], _on];
        if ((count _row) isEqualTo 2) then { _rows pushBack ["buttons", _row]; _row = []; };
    } forEach (_cat get "themes");
    if ((count _row) > 0) then { _rows pushBack ["buttons", _row]; };
    _rows append [
        ["edit", "case", "Rattacher à un dossier (facultatif)", _draft getOrDefault ["case", ""]],
        ["buttons", [
            ["EFFACER LA FICHE", { uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", createHashMap]; uiNamespace setVariable ["COMSPEC_ATAK_FrsPieces", []]; uiNamespace setVariable ["COMSPEC_ATAK_FrsHint", ["", false]]; uiNamespace setVariable ["COMSPEC_ATAK_FrsClearing", true]; [{ ["FRS"] call comspec_atak_native_fnc_pageRender; uiNamespace setVariable ["COMSPEC_ATAK_FrsClearing", false]; }] call CBA_fnc_execNextFrame; }],
            ["FERMER", { ["head"] call comspec_atak_native_fnc_frsAction; }, true]
        ]]
    ];
    [_rows, [0, 0, _dw, _barTop], true, [0.07, 0.07, 0.07, 0.99]] call comspec_atak_native_fnc_formRender;
};
[[0, _dw] select _headOn, "ENTÊTE", { ["head"] call comspec_atak_native_fnc_frsAction; }, _headOn] call _handle;

// Volet PIÈCES JOINTES (droite)
private _pjOn = _ui getOrDefault ["pj", false];
private _pw = _bw * ([0.85, 0.92] select _mini);
if (_pjOn) then {
    private _view = _ui getOrDefault ["view", ""];
    private _rows = [];
    switch (_view) do {
        case "gallery": {
            private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
            _rows append [["title", "Photothèque"], ["text", "<t size='0.8' color='#9a9a9a'>Captures du poste (dossier COMSPEC et Screenshots). JOINDRE l'ajoute à la fiche.</t>"]];
            if ((count _lib) isEqualTo 0) then { _rows pushBack ["text", "<t color='#9a9a9a'>Aucune photo sur ce poste.</t>"]; };
            for "_i" from ((count _lib) - 1) to (((count _lib) - 30) max 0) step -1 do {
                (_lib select _i) params ["_path", "_name"];
                private _in = (_pieces findIf { (_x select 1) isEqualTo _path }) >= 0;
                _rows pushBack ["person", _dir + "ui_gallery.paa", format ["%1<br/><t size='0.75' color='#9a9a9a'>%2</t>", [_name] call _esc, ["", "déjà jointe"] select _in],
                    [["JOINDRE", compile format ["['add', %1] call comspec_atak_native_fnc_frsAction;", _i], true, !_in]], [0.8, 0.8, 0.8, 1]];
            };
            _rows pushBack ["buttons", [["RETOUR AUX PIÈCES", { ["back"] call comspec_atak_native_fnc_frsAction; }]]];
        };
        case "sent": {
            private _sent = uiNamespace getVariable ["COMSPEC_ATAK_FrsSent", []];
            _rows pushBack ["title", format ["Fiches envoyées (%1)", count _sent]];
            if ((count _sent) isEqualTo 0) then { _rows pushBack ["text", "<t color='#9a9a9a'>Aucune fiche envoyée pendant cette session.</t>"]; };
            {
                _x params ["_time", "_kind", "_ref", "_status", "_excerpt", ["_np", 0]];
                _rows pushBack ["text", format ["<t color='#7d6ff0' font='RobotoCondensedBold'>%1</t>  %2  <t color='#9a9a9a'>%3 · %4%5</t><br/><t size='0.85'>%6</t>", _kind, _ref, _time, _status, ["", format [" · %1 pièce(s)", _np]] select (_np > 0), [_excerpt] call _esc]];
            } forEach (+_sent call { reverse _this; _this });
            _rows pushBack ["buttons", [["RETOUR AUX PIÈCES", { ["back"] call comspec_atak_native_fnc_frsAction; }]]];
        };
        default {
            _rows pushBack ["title", format ["Pièce(s) jointe(s) (%1/4)", count _pieces]];
            if ((count _pieces) isEqualTo 0) then { _rows pushBack ["text", "<t size='0.85' color='#9a9a9a'>Le bouton rond du bas ajoute une capture de la vue (appareil photo), une photo de la photothèque (galerie) ou ouvre vos fiches envoyées (dossier).</t>"]; };
            {
                _x params ["_kind", "_path", "_name", "_grid"];
                _rows pushBack ["person", _dir + (["ui_gallery.paa", "ui_photocam.paa"] select (_kind isEqualTo "capture")), format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.75' color='#9a9a9a'>%2 · %3</t>", [_name] call _esc, ["Photo", "Capture"] select (_kind isEqualTo "capture"), _grid],
                    [["RETIRER", compile format ["['del', %1] call comspec_atak_native_fnc_frsAction;", _forEachIndex]]], [0.85, 0.85, 0.85, 1]];
            } forEach _pieces;
        };
    };
    [_rows, [_bw - _pw, 0, _pw, _barTop], true, [0.165, 0.165, 0.165, 0.99]] call comspec_atak_native_fnc_formRender;
};
[[_bw - _hw, _bw - _pw - _hw] select _pjOn, format ["PJ %1/4", count _pieces], { ["pj"] call comspec_atak_native_fnc_frsAction; }, _pjOn] call _handle;

// Barre du bas : bandeau violet (creux au centre), accueil, plein écran, envoi, bouton rond.
private _purple = [0.086, 0.047, 0.25, 1];
private _imgH = _barH / 0.7;
private _bar = ["COMSPEC_RscPicture", [0, _bh - _imgH, _bw, _imgH], _dir + "frs_bar.paa"] call _mk;
_bar ctrlSetTextColor _purple;
private _ih = _barH * 0.5;
private _iw = _ih * _ratio;
private _iy = _barTop + (_barH - _ih) / 2;
private _icon = {
    params ["_x0", "_file", "_code", "_tip", ["_rgb", [1, 1, 1, 1]], ["_disc", []]];
    if ((count _disc) > 0) then {
        private _d = ["COMSPEC_RscPicture", [_x0 - _iw * 0.15, _iy - _ih * 0.15, _iw * 1.3, _ih * 1.3], _dir + "ui_disc.paa"] call _mk;
        _d ctrlSetTextColor _disc;
    };
    private _p = ["COMSPEC_RscPicture", [_x0, _iy, _iw, _ih], _dir + _file] call _mk;
    _p ctrlSetTextColor _rgb;
    [[_x0 - _iw * 0.3, _barTop, _iw * 1.6, _barH], _code, _tip] call _btn;
};
[_pad, "ui_home.paa", { ["home"] call comspec_atak_native_fnc_frsAction; }, "Accueil"] call _icon;
[_bw * 0.25, "ui_expand.paa", { ["full"] call comspec_atak_native_fnc_frsAction; }, "Mini / plein écran"] call _icon;
[_bw - _pad - _iw, "ui_check.paa", { ["send"] call comspec_atak_native_fnc_frsAction; }, "Envoyer la fiche au bureau SSE", [1, 1, 1, 1], [0.93, 0.1, 0.1, 1]] call _icon;

// Bouton rond central et menu galerie / dossier / appareil photo.
private _fabOn = _ui getOrDefault ["fab", false];
private _fh = _barH * 1.15;
private _fw = _fh * _ratio;
private _fcx = _bw / 2;
private _fcy = _barTop - _fh * 0.05;
private _round = {
    params ["_cx", "_cy", "_scale", "_bgRgb", "_file", "_fgRgb", "_code", "_tip"];
    private _w = _fw * _scale; private _h = _fh * _scale;
    private _d = ["COMSPEC_RscPicture", [_cx - _w / 2, _cy - _h / 2, _w, _h], _dir + "ui_disc.paa"] call _mk;
    _d ctrlSetTextColor _bgRgb;
    private _p = ["COMSPEC_RscPicture", [_cx - _w * 0.27, _cy - _h * 0.27, _w * 0.54, _h * 0.54], _dir + _file] call _mk;
    _p ctrlSetTextColor _fgRgb;
    [[_cx - _w / 2, _cy - _h / 2, _w, _h], _code, _tip] call _btn;
};
if (_fabOn) then {
    private _navy = [0.10, 0.06, 0.30, 1];
    private _r = _fh * 1.2;
    [_fcx, _fcy - _r, 0.95, _navy, "ui_gallery.paa", [1, 1, 1, 1], { ["gallery"] call comspec_atak_native_fnc_frsAction; }, "Joindre une photo de la photothèque"] call _round;
    [_fcx - _r * 0.95 * _ratio, _fcy - _r * 0.45, 0.95, _navy, "ui_folder.paa", [1, 1, 1, 1], { ["folder"] call comspec_atak_native_fnc_frsAction; }, "Fiches envoyées"] call _round;
    [_fcx + _r * 0.95 * _ratio, _fcy - _r * 0.45, 0.95, _navy, "ui_photocam.paa", [1, 1, 1, 1], { ["camera"] call comspec_atak_native_fnc_frsAction; }, "Capture de la vue jointe à la fiche"] call _round;
    [_fcx, _fcy, 1, [0.78, 0.78, 0.78, 1], "ui_minus.paa", [0.25, 0.25, 0.25, 1], { ["fab"] call comspec_atak_native_fnc_frsAction; }, "Fermer"] call _round;
} else {
    [_fcx, _fcy, 1, [1, 1, 1, 1], "ui_clip.paa", [0.2, 0.2, 0.2, 1], { ["fab"] call comspec_atak_native_fnc_frsAction; }, "Pièces jointes"] call _round;
    if ((count _pieces) > 0) then {
        ["COMSPEC_RscBadge", [_fcx + _fw * 0.22, _fcy - _fh * 0.55, _fs * 1.1 * _ratio, _fs * 1.1], str (count _pieces)] call _mk;
    };
};
true
