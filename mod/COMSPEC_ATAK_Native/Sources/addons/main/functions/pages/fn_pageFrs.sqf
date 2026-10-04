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
// Volet ouvert : on masque la zone de saisie (un champ texte actif se dessine par-dessus les volets).
if ((_ui getOrDefault ["head", false]) || {_ui getOrDefault ["pj", false]}) then { _edit ctrlShow false; _ph ctrlShow false; };
_edit setVariable ["upd", _upd];
_edit ctrlAddEventHandler ["KeyUp", { params ["_e"]; [_e] call (_e getVariable ["upd", {}]); }];
_edit ctrlAddEventHandler ["SetFocus", { params ["_e"]; ((uiNamespace getVariable ["COMSPEC_ATAK_FrsCount", []]) param [1, controlNull]) ctrlShow false; }];
if ((_hint select 0) isNotEqualTo "") then {
    private _h = ["COMSPEC_RscStructuredText", [_tx, _ty + _th, _tw - _fs * 4.4 * _ratio, _cntH * 1.6]] call _mk;
    _h ctrlSetStructuredText parseText format ["<t size='0.8' color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), [_hint select 0] call _esc];
};

// Poignées des volets (texte vertical lettre par lettre).
// Volet ouvert : languette étroite, collée au bord du volet et centrée sur sa hauteur (zone de saisie masquée).
private _hwOpen = (_fs * 1.15 / _ratio) min _hw;
private _handle = {
    params ["_x0", "_label", "_code", "_on", ["_side", "left"]];
    private _w = [_hw, _hwOpen] select _on;
    if (_on && {_side isEqualTo "right"}) then { _x0 = _x0 + _hw - _w; };
    private _hh = [_bh * 0.24, (_bh * 0.2) min (_barTop * 0.4)] select _on;
    private _hy = [_hdrH + (_barTop - _hdrH - _hh) / 2, (_barTop - _hh) / 2] select _on;
    private _c = ["COMSPEC_RscText", [_x0, _hy, _w, _hh]] call _mk;
    _c ctrlSetBackgroundColor ([[0.30, 0.30, 0.30, 1], [0.27, 0.22, 0.62, 1]] select _on);
    private _t = ["COMSPEC_RscStructuredText", [_x0, _hy + _pad / 3, _w, _hh]] call _mk;
    // Lettres passées une à une : splitString "" coupe les caractères accentués (Ê) en octets.
    _t ctrlSetStructuredText parseText format ["<t align='center' size='0.62' color='#e6e6e6'>%1</t>", _label joinString "<br/>"];
    [[_x0, _hy, _w, _hh], _code, _label joinString ""] call _btn;
};

// Volet ENTÊTE (gauche)
private _headOn = _ui getOrDefault ["head", false];
private _dw = _bw * ([0.42, 0.8] select _mini);
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
[[0, _dw] select _headOn, ["E", "N", "T", "Ê", "T", "E"], { ["head"] call comspec_atak_native_fnc_frsAction; }, _headOn, "left"] call _handle;

// Volet PIÈCES JOINTES (droite)
private _pjOn = _ui getOrDefault ["pj", false];
private _view = _ui getOrDefault ["view", ""];
// Grille des pièces : volet étroit (~40 % en paysage, plus large en portrait), assez pour deux colonnes lisibles.
// Photothèque et fiches Athena (listes) gardent la largeur d'avant.
private _land = _l getOrDefault ["landscape", false];
private _pw = if (_view isEqualTo "") then {
    (_bw * ([0.62, 0.40] select _land)) max ((_font * 11 / _ratio) min (_bw * 0.8))
} else { _bw * ([0.48, 0.8] select _mini) };
if (_pjOn && {_view isEqualTo ""}) then {
    // Grille 2 x 2 : vignette, nom et croix de retrait pour une pièce ; tuile pointillée « + » et trois sources pour un emplacement libre.
    private _x0 = _bw - _pw;
    private _panel = ["COMSPEC_RscText", [_x0, 0, _pw, _barTop]] call _mk;
    _panel ctrlSetBackgroundColor [0.11, 0.11, 0.13, 0.99];
    private _np = count _pieces;
    private _gx = _x0 + _pad;
    private _gw = _pw - 2 * _pad;
    private _titleH = _fs * 1.6;
    private _tt = ["COMSPEC_RscText", [_gx, _pad / 2, _gw, _titleH], "PIÈCES JOINTES"] call _mk;
    _tt ctrlSetFont "RobotoCondensedBold"; _tt ctrlSetFontHeight (_fs * 0.95); _tt ctrlSetTextColor [0.9, 0.9, 0.9, 1];
    private _cw = _fs * 2.6 / _ratio;
    private _cc = ["COMSPEC_RscTextCenter", [_gx + _gw - _cw, _pad / 2 + _titleH * 0.12, _cw, _titleH * 0.76], format ["%1/4", _np]] call _mk;
    _cc ctrlSetFontHeight (_fs * 0.8); _cc ctrlSetTextColor [1, 1, 1, 1];
    _cc ctrlSetBackgroundColor ([[0.27, 0.22, 0.62, 1], [0.85, 0.45, 0.1, 1]] select (_np >= 4));
    private _closeH = _fs * 1.5;
    private _helpH = _fs * 1.2;
    private _gy = _pad / 2 + _titleH + _pad / 2;
    private _avail = _barTop - _gy - _helpH - _closeH - _pad * 2;
    private _tw = (_gw - _pad) / 2;
    private _tileH = (((_avail - _pad) / 2) min (_tw * _ratio * 0.9)) max (_fs * 2.5);
    // Pointillés d'un cadre (tirets courts, couleur atténuée).
    private _dash = {
        params ["_rx", "_ry", "_rw", "_rh"];
        private _tx = pixelW * 2; private _ty = pixelH * 2;
        private _rgb = [0.42, 0.42, 0.48, 0.9];
        private _nx = 6; private _ny = 5;
        for "_k" from 0 to (_nx - 1) do {
            private _sx = _rx + _rw * _k / _nx;
            { (["COMSPEC_RscText", [_sx, _x, _rw / _nx * 0.55, _ty]] call _mk) ctrlSetBackgroundColor _rgb; } forEach [_ry, _ry + _rh - _ty];
        };
        for "_k" from 0 to (_ny - 1) do {
            private _sy = _ry + _rh * _k / _ny;
            { (["COMSPEC_RscText", [_x, _sy, _tx, _rh / _ny * 0.55]] call _mk) ctrlSetBackgroundColor _rgb; } forEach [_rx, _rx + _rw - _tx];
        };
    };
    for "_i" from 0 to 3 do {
        private _cx = _gx + (_i mod 2) * (_tw + _pad);
        private _cy = _gy + (floor (_i / 2)) * (_tileH + _pad);
        if (_i < _np) then {
            (_pieces select _i) params ["_kind", "_path", "_name", ["_grid", ""]];
            private _tile = ["COMSPEC_RscText", [_cx, _cy, _tw, _tileH]] call _mk;
            _tile ctrlSetBackgroundColor [0.2, 0.2, 0.23, 1];
            private _labH = _fs * 1.15;
            // Arma n'affiche que .jpg / .paa : une capture PNG garde l'icône de sa nature.
            private _p = (_path splitString (toString [92])) joinString "/";
            private _lp = toLower _p;
            if ((_lp select [(count _lp) - 4]) in [".jpg", "jpeg", ".paa"]) then {
                ["COMSPEC_RscSlide", [_cx, _cy, _tw, _tileH - _labH], _p] call _mk;
            } else {
                private _ih = (_tileH - _labH) * 0.5;
                private _ic = ["COMSPEC_RscPicture", [_cx + (_tw - _ih / _ratio) / 2, _cy + (_tileH - _labH - _ih) / 2, _ih / _ratio, _ih], _dir + (["ui_gallery.paa", "ui_photocam.paa"] select (_kind isEqualTo "capture"))] call _mk;
                _ic ctrlSetTextColor [0.62, 0.62, 0.66, 1];
            };
            private _lab = ["COMSPEC_RscText", [_cx, _cy + _tileH - _labH, _tw, _labH], format ["%1 %2", ["Photo ·", "Capture ·"] select (_kind isEqualTo "capture"), _name]] call _mk;
            _lab ctrlSetFontHeight (_fs * 0.72); _lab ctrlSetTextColor [0.92, 0.92, 0.92, 1];
            _lab ctrlSetBackgroundColor [0, 0, 0, 0.65];
            _lab ctrlSetTooltip format ["%1%2", _name, ["", format [" · %1", _grid]] select (_grid isNotEqualTo "")];
            // Croix de retrait (coin haut droit).
            private _xs = (_fs * 1.35) min (_tileH * 0.35);
            private _xw = _xs / _ratio;
            private _xr = [_cx + _tw - _xw - _pad / 4, _cy + _pad / 4, _xw, _xs];
            (["COMSPEC_RscPicture", _xr, _dir + "ui_disc.paa"] call _mk) ctrlSetTextColor [0.8, 0.12, 0.12, 0.95];
            (["COMSPEC_RscPicture", [(_xr select 0) + _xw * 0.25, (_xr select 1) + _xs * 0.25, _xw * 0.5, _xs * 0.5], _dir + "ui_close.paa"] call _mk) ctrlSetTextColor [1, 1, 1, 1];
            [_xr, compile format ["['del', %1] call comspec_atak_native_fnc_frsAction;", _i], "Retirer la pièce"] call _btn;
        } else {
            private _tile = ["COMSPEC_RscText", [_cx, _cy, _tw, _tileH]] call _mk;
            _tile ctrlSetBackgroundColor [0.14, 0.14, 0.16, 1];
            [_cx, _cy, _tw, _tileH] call _dash;
            private _plusH = _tileH * 0.42;
            private _pl = ["COMSPEC_RscTextCenter", [_cx, _cy + _tileH * 0.04, _tw, _plusH], "+"] call _mk;
            _pl ctrlSetFontHeight (_plusH * 0.85); _pl ctrlSetTextColor [0.5, 0.5, 0.56, 1];
            // Trois sources compactes (mêmes actions que le bouton rond).
            private _srcs = [
                ["ui_photocam.paa", "Caméra", { ["camera"] call comspec_atak_native_fnc_frsAction; }, "Caméra : capture de la vue jointe à la fiche"],
                ["ui_gallery.paa", "Galerie", { ["gallery"] call comspec_atak_native_fnc_frsAction; }, "Galerie : joindre une photo de la photothèque"],
                ["ui_folder.paa", "Athena", { ["folder"] call comspec_atak_native_fnc_frsAction; }, "Fiches Athena et leurs photos"]
            ];
            private _sw = (_tw - _pad / 2) / 3;
            private _sy = _cy + _tileH * 0.48;
            private _sh = _tileH * 0.48;
            private _labels = _sh > (_fs * 2.2);
            private _ih = ([_sh * 0.7, _sh * 0.5] select _labels) min (_sw * _ratio * 0.7);
            {
                _x params ["_file", "_name", "_code", "_tip"];
                private _sx = _cx + _pad / 4 + _forEachIndex * _sw;
                private _bgS = ["COMSPEC_RscText", [_sx + _sw * 0.06, _sy, _sw * 0.88, _sh - _pad / 4]] call _mk;
                _bgS ctrlSetBackgroundColor [0.10, 0.06, 0.30, 1];
                private _iy = _sy + ([(_sh - _ih) / 2, _sh * 0.08] select _labels);
                (["COMSPEC_RscPicture", [_sx + (_sw - _ih / _ratio) / 2, _iy, _ih / _ratio, _ih], _dir + _file] call _mk) ctrlSetTextColor [1, 1, 1, 1];
                if (_labels) then {
                    private _t = ["COMSPEC_RscTextCenter", [_sx, _iy + _ih, _sw, _sh - _ih - _sh * 0.12], _name] call _mk;
                    _t ctrlSetFontHeight ((_fs * 0.62) min ((_sh - _ih) * 0.7)); _t ctrlSetTextColor [0.85, 0.85, 0.9, 1];
                };
                [[_sx, _sy, _sw, _sh], _code, _tip] call _btn;
            } forEach _srcs;
        };
    };
    // Aide sur une ligne, sous la grille, puis FERMER compact en bas du volet.
    private _hy = _gy + 2 * _tileH + _pad * 1.5;
    private _help = ["COMSPEC_RscText", [_gx, _hy, _gw, _helpH], [
        "4 pièces au plus, envoyées avec la fiche. La croix en retire une.",
        "Maximum atteint : retirez une pièce (croix) pour en joindre une autre."
    ] select (_np >= 4)] call _mk;
    _help ctrlSetFontHeight (_fs * 0.7); _help ctrlSetTextColor [0.6, 0.6, 0.64, 1];
    _help ctrlSetTooltip "Caméra : capture de la vue · Galerie : photothèque du poste · Athena : fiches envoyées et leurs photos";
    private _fw0 = (_gw * 0.5) max ((_fs * 6 / _ratio) min _gw);
    private _close = ["COMSPEC_RscButtonPrimary", [_x0 + (_pw - _fw0) / 2, _barTop - _closeH - _pad, _fw0, _closeH], "FERMER"] call _mk;
    _close ctrlSetFontHeight (_fs * 0.85);
    _close ctrlAddEventHandler ["ButtonClick", { ["pj"] call comspec_atak_native_fnc_frsAction; }];
};
if (_pjOn && {_view isNotEqualTo ""}) then {
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
            // Fiches d'Athena (les miennes ou celles de la communauté) avec leurs photos, et celles de cette session.
            private _scope = _ui getOrDefault ["libScope", "mine"];
            private _lib = uiNamespace getVariable ["COMSPEC_ATAK_FrsLib", createHashMap];
            private _sent = uiNamespace getVariable ["COMSPEC_ATAK_FrsSent", []];
            _rows pushBack ["segment", "", [
                ["MES FICHES", { ["libScope", "mine"] call comspec_atak_native_fnc_frsAction; }, _scope isEqualTo "mine"],
                ["COMMUNAUTÉ", { ["libScope", "all"] call comspec_atak_native_fnc_frsAction; }, _scope isEqualTo "all"],
                ["SESSION", { ["libScope", "session"] call comspec_atak_native_fnc_frsAction; }, _scope isEqualTo "session"]
            ]];
            if (_scope isEqualTo "session") then {
                _rows pushBack ["title", format ["Envoyées pendant la session (%1)", count _sent]];
                if ((count _sent) isEqualTo 0) then { _rows pushBack ["text", "<t color='#9a9a9a'>Aucune fiche envoyée pendant cette session.</t>"]; };
                {
                    _x params ["_time", "_kind", "_ref", "_status", "_excerpt", ["_np", 0]];
                    _rows pushBack ["text", format ["<t color='#7d6ff0' font='RobotoCondensedBold'>%1</t>  %2  <t color='#9a9a9a'>%3 · %4%5</t><br/><t size='0.85'>%6</t>", _kind, _ref, _time, _status, ["", format [" · %1 pièce(s)", _np]] select (_np > 0), [_excerpt] call _esc]];
                } forEach (+_sent call { reverse _this; _this });
            } else {
                if !(_scope in _lib) then { [{ ["load", _this] call comspec_atak_native_fnc_frsLibrary; }, _scope] call CBA_fnc_execNextFrame; };
                (_lib getOrDefault [_scope, [[], "", ""]]) params ["_notes", "_at", "_err"];
                private _open = _ui getOrDefault ["libOpen", ""];
                private _note = _notes param [(_notes findIf { (_x select 0) isEqualTo _open }), []];
                if (_open isNotEqualTo "" && {(count _note) > 0}) then {
                    // Fiche ouverte : en-tête, texte, photos.
                    _note params ["_id", "_ref", "_kind", "_title", "_grid", "_urg", "_status", "_when", "_author", "_body", "_urls"];
                    _rows append [
                        ["title", format ["%1 · %2", _kind, _ref]],
                        ["text", format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#9a9a9a'>%2 · %3 · %4%5%6</t>", [_title] call _esc, _when, _urg, _status, ["", format [" · %1", _grid]] select (_grid isNotEqualTo ""), ["", format [" · %1", [_author] call _esc]] select (_author isNotEqualTo "")]],
                        ["text", format ["<t size='0.9'>%1</t>", [([_body] call _esc), " ¶ ", "<br/>"] call CBA_fnc_replace]]
                    ];
                    if ((count _urls) > 0) then {
                        private _imgs = (uiNamespace getVariable ["COMSPEC_ATAK_FrsImg", createHashMap]) getOrDefault [_id, []];
                        if ((count _imgs) isEqualTo 0) then {
                            _rows pushBack ["text", format ["<t size='0.85' color='#9a9a9a'>Chargement de %1 photo(s)…</t>", count _urls]];
                            [{ ["images", _this] call comspec_atak_native_fnc_frsLibrary; }, _id] call CBA_fnc_execNextFrame;
                        } else {
                            { if (_x isEqualTo "") then { _rows pushBack ["text", "<t size='0.8' color='#9a9a9a'>Photo au format PNG : visible sur Athena seulement.</t>"]; } else { _rows pushBack ["image", _x]; }; } forEach _imgs;
                        };
                    };
                    _rows pushBack ["buttons", [["RETOUR À LA LISTE", { ["libOpen", ""] call comspec_atak_native_fnc_frsAction; }]]];
                } else {
                    _rows pushBack ["title", format ["Fiches Athena (%1)", count _notes]];
                    if (_err isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e0a040'>%1</t>", _err]]; };
                    if (_at isEqualTo "" && {_err isEqualTo ""}) then { _rows pushBack ["text", "<t color='#9a9a9a'>Récupération des fiches…</t>"]; };
                    if (_at isNotEqualTo "" && {(count _notes) isEqualTo 0} && {_err isEqualTo ""}) then { _rows pushBack ["text", "<t color='#9a9a9a'>Aucune fiche sur Athena.</t>"]; };
                    {
                        _x params ["_id", "_ref", "_kind", "_title", "_grid", "_urg", "_status", "_when", "_author", "_body", "_urls"];
                        _rows pushBack ["person", _dir + (["ui_folder.paa", "ui_gallery.paa"] select ((count _urls) > 0)),
                            format ["<t color='#7d6ff0' font='RobotoCondensedBold'>%1</t>  %2<br/><t size='0.85'>%3</t><br/><t size='0.75' color='#9a9a9a'>%4 · %5%6</t>", _kind, _ref, [_title] call _esc, _when, _status, ["", format [" · %1 photo(s)", count _urls]] select ((count _urls) > 0)],
                            [["OUVRIR", compile format ["['libOpen', %1] call comspec_atak_native_fnc_frsAction;", str _id], true, true]], [0.8, 0.8, 0.8, 1]];
                    } forEach _notes;
                    _rows pushBack ["buttons", [[format ["ACTUALISER%1", ["", format [" (%1)", _at]] select (_at isNotEqualTo "")], compile format ["['load', %1] call comspec_atak_native_fnc_frsLibrary;", str _scope]]]];
                };
            };
            _rows pushBack ["buttons", [["RETOUR AUX PIÈCES", { ["back"] call comspec_atak_native_fnc_frsAction; }]]];
        };
    };
    [_rows, [_bw - _pw, 0, _pw, _barTop], true, [0.11, 0.11, 0.13, 0.99]] call comspec_atak_native_fnc_formRender;
};
[[_bw - _hw, _bw - _pw - _hw] select _pjOn, ["P", "J", " ", str (count _pieces), "/", "4"], { ["pj"] call comspec_atak_native_fnc_frsAction; }, _pjOn, "right"] call _handle;

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
// Volet PJ ouvert : il porte ses propres sources, le menu rond reste fermé ; le bouton n'est dessiné que s'il ne mord pas sur le volet.
if (_pjOn) then { _fabOn = false; };
private _fabFree = !_pjOn || {(_fcx + _fw / 2) < (_bw - _pw - _hwOpen)};
if (_fabOn) then {
    private _navy = [0.10, 0.06, 0.30, 1];
    private _r = _fh * 1.2;
    // Bulles au-dessus du bandeau (creux compris) : aucune ne recouvre les icônes de la barre.
    private _bh2 = _fh * 0.95 / 2;
    private _sideY = (_fcy - _r * 0.45) min ((_bh - _imgH) - _bh2 - _pad / 3);
    private _topY = (_fcy - _r) min (_sideY - _fh * 0.95);
    private _dx = (_r * 0.95 * _ratio) min (_fcx - _fw * 0.95 / 2 - _pad);
    [_fcx, _topY, 0.95, _navy, "ui_gallery.paa", [1, 1, 1, 1], { ["gallery"] call comspec_atak_native_fnc_frsAction; }, "Joindre une photo de la photothèque"] call _round;
    [_fcx - _dx, _sideY, 0.95, _navy, "ui_folder.paa", [1, 1, 1, 1], { ["folder"] call comspec_atak_native_fnc_frsAction; }, "Fiches Athena"] call _round;
    [_fcx + _dx, _sideY, 0.95, _navy, "ui_photocam.paa", [1, 1, 1, 1], { ["camera"] call comspec_atak_native_fnc_frsAction; }, "Capture de la vue jointe à la fiche"] call _round;
    [_fcx, _fcy, 1, [0.78, 0.78, 0.78, 1], "ui_minus.paa", [0.25, 0.25, 0.25, 1], { ["fab"] call comspec_atak_native_fnc_frsAction; }, "Fermer"] call _round;
} else {
    if (_fabFree) then {
        [_fcx, _fcy, 1, [1, 1, 1, 1], "ui_clip.paa", [0.2, 0.2, 0.2, 1], { ["fab"] call comspec_atak_native_fnc_frsAction; }, "Pièces jointes"] call _round;
        if ((count _pieces) > 0) then {
            ["COMSPEC_RscBadge", [_fcx + _fw * 0.22, _fcy - _fh * 0.55, _fs * 1.1 * _ratio, _fs * 1.1], str (count _pieces)] call _mk;
        };
    };
};
true
