/*
    Formulaire défilant (pages Athena, Réseau, Réglages, éditeur de marqueur).
    Params : [lignes, rectangle, dans la zone de contenu (true) ou absolu sur le display (false), couleur du fond]
    Lignes :
      ["title", texte]                       ["text", texte structuré]
      ["edit", clé, libellé, valeur]          ["memo", clé, libellé, valeur, nb lignes]
      ["combo", clé, libellé, [[texte, donnée, image, couleur]...], donnée choisie]
      ["toggle", libellé, actif, code]        ["buttons", [[libellé, code, principal, actif, aide]...]]
      ["password", clé, libellé]              ["hero", image, texte structuré]
      ["gap"]
      ["person", image, texte structuré, [[libellé, code, principal, actif, aide]...], couleur image]  (ligne compacte : vignette, texte, boutons à droite ;
          principal = true, false ou "red" pour un bouton de confirmation)
      ["section", titre, sous-titre]        ["switch", libellé, actif, code, aide, imposé]
      ["fold", titre, résumé, ouverte, code]   en-tête de section repliable : barre pleine largeur cliquable, chevron
          à droite ; le contenu qui suit n'est ajouté par la page que si la section est ouverte
      ["slider", libellé, valeur 0-100, code, aide, actif, icône, code de l'icône, aide de l'icône]
          curseur 0-100 au cran de 1 : code appelé avec [valeur] pendant le glissé (sans redessiner la page) ; icône
          (data\nom) cliquable à gauche, valeur à droite (0 = MUET) ; actif = liseré et icône en couleur d'accent
      ["segment", libellé, [[texte, code, actif, aide]...], aide]   ["info", libellé, valeur]   ["image", chemin]
      ["line", numéro, libellé, valeur, genre, clé, aide]   ligne de formulaire type 9-LINE, hauteur fixe :
          numéro en pastille, libellé à gauche, valeur à droite. genre "auto" (valeur calculée, en couleur d'accent ;
          vide ou "-" : l'aide en gris), "warn" (valeur en rouge), "edit" (champ de saisie clé dans la colonne valeur,
          l'aide en infobulle et en gris sous le libellé), "autoedit" (même champ, prérempli d'une proposition
          automatique affichée en couleur d'accent)
      ["bar", [[titre de groupe, [[libellé, code, état, aide, actif]...]]...]]
          barre de boutons par groupes titrés (façon app Drone) ; état "" | "on" (vert, actif) | "red" (confirmation)
          | "main" (bouton principal) ; les groupes passent à la ligne quand les boutons deviendraient trop étroits.
      ["line", ..., "mono"]  8e paramètre « mono » : ligne de fiche dense (carte CAS), libellé en capitales grises,
          valeur en police à chasse fixe, hauteur réduite.
      ["strip", texte gauche, texte droit, couleur du fond]   bandeau de terminal pleine largeur (texte structuré).
      ["steps", [[libellé, état]...]]   frise d'étapes en cases : état "done" | "now" | "hot" | "abort" | "todo".
      ["banner", texte structuré, couleur du fond]   bandeau d'état en gros caractères, centré.
      ["big", [[libellé, code, couleur, actif, aide]...]]   gros boutons tactiles ; couleur "red" | "amber" | "green" | "".
      ["text", texte, couleur du fond]   la couleur du fond (facultative) pose le texte sur une carte.
      ["notif", icône, couleur, titre, à droite, texte, code ouvrir, code fermer, aide]   carte de notification : liseré
          et pastille d'icône à la couleur de gravité, titre en gras, heure / compteur à droite, texte en dessous ;
          clic sur la carte = code ouvrir ({} : rien), croix à droite = code fermer ({} : pas de croix)
      ["empty", icône, titre, sous-titre]   état vide centré : grande icône pâle, titre et explication
    L'aide (facultative) s'affiche en infobulle. Hauteur occupée par le contenu : uiNamespace COMSPEC_ATAK_FormH ;
    fond du formulaire : (contrôle renvoyé) getVariable "bg".
    Les champs sont lisibles ensuite par [clé] call comspec_atak_native_fnc_formValue.
*/
params ["_rows", "_rect", ["_inContent", true], ["_bgColor", [0.035, 0.045, 0.04, 1]]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
_rect params ["_rx", "_ry", "_rw", "_rh"];
private _bg = ["COMSPEC_RscPanel", _rect, "", _inContent] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor _bgColor;
private _grp = ["COMSPEC_RscControlsGroup", _rect, "", _inContent] call comspec_atak_native_fnc_pageCtrl;
private _w = _rw - 0.014 - 2 * _pad;
private _rowH = _font * 1.55;
private _y = _pad;
private _fields = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
// Clic différé d'une image : beaucoup d'actions redessinent la page (ou en changent), ce qui supprimait le bouton
// pendant son propre gestionnaire, une cause connue d'arrêt du moteur (« can't resize AutoArray to negative size »).
private _click = {
    params ["_b", "_code"];
    if (_code isEqualType "") then { _code = compile _code; };
    _b setVariable ["comspec_click", _code];
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        [{ params ["_c", "_code"]; [_c] call _code; }, [_c, _c getVariable ["comspec_click", {}]]] call CBA_fnc_execNextFrame;
    }];
};
private _mk = {
    params ["_class", "_pos", ["_text", ""]];
    private _c = _d ctrlCreate [_class, -1, _grp];
    [_c, _class] call comspec_atak_native_fnc_fontApply;
    _c ctrlSetPosition _pos;
    if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
    _c ctrlCommit 0;
    _c
};
private _lineIdx = 0; // lignes "line" : fond alterné
{
    private _row = _x;
    switch (_row select 0) do {
        case "title": {
            private _t = ["COMSPEC_RscText", [_pad, _y, _w, _rowH], toUpper (_row select 1)] call _mk;
            _t ctrlSetFontHeight _fs;
            _t ctrlSetTextColor _acc;
            _y = _y + _rowH;
        };
        case "text": {
            _row params ["", "_txt", ["_tbg", []]];
            private _card = (count _tbg) isEqualTo 4;
            private _ix = [0, _pad / 2] select _card;
            private _cb = controlNull;
            if (_card) then { _cb = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk; _cb ctrlSetBackgroundColor _tbg; };
            private _t = ["COMSPEC_RscStructuredText", [_pad + _ix, _y + _ix / 2, _w - 2 * _ix, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((_txt) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max (_fs * 1.2);
            _t ctrlSetPosition [_pad + _ix, _y + _ix / 2, _w - 2 * _ix, _h];
            _t ctrlCommit 0;
            if (_card) then { _cb ctrlSetPosition [_pad, _y, _w, _h + _ix]; _cb ctrlCommit 0; _h = _h + _ix; };
            _y = _y + _h + ([_pad / 2, pixelH * 2] select _card);
        };
        case "strip": {
            // Bandeau de terminal : fond sombre pleine largeur, texte à gauche et à droite, chacun dans sa colonne
            // (un texte long passe à la ligne et le bandeau grandit, au lieu de chevaucher l'autre côté).
            _row params ["", "_left", ["_right", ""], ["_sbg", [0.02, 0.028, 0.024, 1]]];
            private _sh = _fs * 1.45;
            private _hasR = _right isNotEqualTo "";
            private _lw = [_w - _pad * 0.8, _w * 0.52 - _pad * 0.4] select _hasR;
            private _sb = ["COMSPEC_RscText", [_pad, _y, _w, _sh]] call _mk;
            _sb ctrlSetBackgroundColor _sbg;
            private _lt = ["COMSPEC_RscStructuredText", [_pad * 1.4, _y, _lw, _sh]] call _mk;
            _lt ctrlSetStructuredText parseText ((_left) call comspec_atak_native_fnc_tr);
            private _parts = [_lt];
            if (_hasR) then {
                private _rt = ["COMSPEC_RscStructuredText", [_pad + _w * 0.52, _y, _w * 0.48 - _pad * 0.4, _sh]] call _mk;
                _rt ctrlSetStructuredText parseText ((format ["<t align='right'>%1</t>", _right]) call comspec_atak_native_fnc_tr);
                _parts pushBack _rt;
            };
            private _hh = _sh;
            { _hh = _hh max ((ctrlTextHeight _x) + pixelH * 4); } forEach _parts;
            { private _th = (ctrlTextHeight _x) min _hh; _x ctrlSetPositionY (_y + (_hh - _th) / 2); _x ctrlSetPositionH _th; _x ctrlCommit 0; } forEach _parts;
            _sb ctrlSetPositionH _hh; _sb ctrlCommit 0;
            _y = _y + _hh + pixelH * 2;
        };
        case "steps": {
            // Frise de la mission en cases accolées (deux rangées si les cases deviennent trop étroites).
            private _st = _row select 1;
            private _n = count _st;
            private _minW = _fs * 4.6 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _per = ((floor (_w / _minW)) max 1) min _n;
            if (_per < _n) then { _per = ceil (_n / (ceil (_n / _per))); };
            private _cw = _w / (_per max 1);
            private _ch = _fs * 1.35;
            {
                _x params ["_lbl", ["_state", "todo"]];
                private _cx = _pad + (_forEachIndex mod _per) * _cw;
                private _cy = _y + (floor (_forEachIndex / _per)) * (_ch + pixelH * 2);
                private _c = ["COMSPEC_RscTextCenter", [_cx, _cy, _cw - pixelW * 2, _ch], _lbl] call _mk;
                _c ctrlSetFont (["EtelkaMonospacePro", "EtelkaMonospaceProBold"] select (_state in ["now", "hot", "abort"]));
                _c ctrlSetFontHeight (_fs * 0.72);
                switch (_state) do {
                    case "done": { _c ctrlSetBackgroundColor [0.12, 0.30, 0.16, 1]; _c ctrlSetTextColor [0.55, 0.88, 0.6, 1]; };
                    case "now": { _c ctrlSetBackgroundColor [0.95, 0.67, 0.2, 1]; _c ctrlSetTextColor [0.05, 0.04, 0.02, 1]; };
                    case "hot";
                    case "abort": { _c ctrlSetBackgroundColor [0.85, 0.2, 0.16, 1]; _c ctrlSetTextColor [1, 1, 1, 1]; };
                    default { _c ctrlSetBackgroundColor [0.07, 0.085, 0.078, 1]; _c ctrlSetTextColor [0.36, 0.42, 0.39, 1]; };
                };
            } forEach _st;
            _y = _y + (ceil (_n / (_per max 1))) * (_ch + pixelH * 2) + _pad / 3;
        };
        case "banner": {
            // Bandeau d'état : la situation de la mission en gros, centrée, sur la couleur de l'état.
            _row params ["", "_txt", ["_bbg", [0.07, 0.085, 0.078, 1]]];
            private _bb = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _bb ctrlSetBackgroundColor _bbg;
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + _pad / 3, _w - _pad, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t align='center'>%1</t>", _txt]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max (_fs * 1.3);
            _t ctrlSetPosition [_pad * 1.5, _y + _pad / 3, _w - _pad, _h];
            _t ctrlCommit 0;
            _bb ctrlSetPosition [_pad, _y, _w, _h + _pad * 0.66];
            _bb ctrlCommit 0;
            _y = _y + _h + _pad * 0.66 + _pad / 2;
        };
        case "big": {
            // Gros boutons tactiles (gants) : décision de tir, appels principaux.
            private _btns = _row select 1;
            private _n = count _btns;
            private _bh2 = _rowH * 1.55;
            private _bw2 = (_w - (_n - 1) * _pad / 2) / (_n max 1);
            {
                _x params ["_label", "_code", ["_col", ""], ["_enabled", true], ["_tip", ""]];
                private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_bw2 + _pad / 2), _y, _bw2, _bh2], _label] call _mk;
                _b ctrlSetFont "EtelkaMonospaceProBold";
                _b ctrlSetFontHeight (_fs * 1.05);
                private _bgc = switch (_col) do {
                    case "red": { [0.78, 0.17, 0.13, 1] };
                    case "amber": { [0.85, 0.58, 0.16, 1] };
                    case "green": { [0.30, 0.68, 0.36, 1] };
                    default { [0.12, 0.145, 0.13, 1] };
                };
                if (!_enabled) then { _bgc = [(_bgc select 0) * 0.35 + 0.04, (_bgc select 1) * 0.35 + 0.05, (_bgc select 2) * 0.35 + 0.045, 1]; };
                _b ctrlSetBackgroundColor _bgc;
                _b ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [[1, 1, 1, 1], [0.05, 0.04, 0.02, 1]] select (_col isEqualTo "amber")] select _enabled);
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlEnable _enabled;
                [_b, _code] call _click;
            } forEach _btns;
            _y = _y + _bh2 + _pad / 2;
        };
        case "edit";
        case "memo": {
            _row params ["_kind", "_key", "_label", ["_value", ""], ["_lines", 3]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight (_fs * 0.9);
            _y = _y + _fs * 1.2;
            private _h = [_rowH, _font * 1.15 * _lines + _font * 0.3] select (_kind isEqualTo "memo");
            private _e = [["COMSPEC_RscEdit", "COMSPEC_RscEditMulti"] select (_kind isEqualTo "memo"), [_pad, _y, _w, _h], _value] call _mk;
            _e ctrlSetFontHeight _font;
            _fields set [_key, _e];
            _y = _y + _h + _pad / 2;
        };
        case "combo": {
            _row params ["", "_key", "_label", "_items", ["_selData", ""]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight (_fs * 0.9);
            _y = _y + _fs * 1.2;
            private _c = ["COMSPEC_RscCombo", [_pad, _y, _w, _rowH]] call _mk;
            _c ctrlSetFontHeight _font;
            private _sel = 0;
            {
                _x params ["_text", "_data", ["_pic", ""], ["_color", []]];
                private _i = _c lbAdd ((_text) call comspec_atak_native_fnc_tr);
                _c lbSetData [_i, _data];
                if (_pic isNotEqualTo "") then { _c lbSetPicture [_i, _pic]; };
                if ((count _color) isEqualTo 4) then { _c lbSetPictureColor [_i, _color]; _c lbSetPictureColorSelected [_i, _color]; };
                if (_data isEqualTo _selData) then { _sel = _i; };
            } forEach _items;
            _c lbSetCurSel _sel;
            _fields set [_key, _c];
            _y = _y + _rowH + _pad / 2;
        };
        case "toggle": {
            _row params ["", "_label", "_on", "_code"];
            private _b = ["COMSPEC_RscButton", [_pad, _y, _w, _rowH], format ["%1   %2", ["○", "●"] select _on, _label]] call _mk;
            _b ctrlSetFontHeight _fs;
            if (_on) then { _b ctrlSetTextColor _acc; };
            [_b, _code] call _click;
            _y = _y + _rowH + _pad / 3;
        };
        case "section": {
            // En-tête de section : barre verte, titre et sous-titre.
            _row params ["", "_title", ["_sub", ""]];
            _y = _y + _pad / 2;
            private _bar = ["COMSPEC_RscText", [_pad, _y + _fs * 0.15, _pad * 0.35, _fs * 1.1]] call _mk;
            _bar ctrlSetBackgroundColor _acc;
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y, _w - _pad, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%3'>%1</t>%2", toUpper _title, ["", format ["<br/><t size='0.8' color='#8a9a93'>%1</t>", _sub]] select (_sub isNotEqualTo ""), _accHex]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max (_fs * 1.3);
            _t ctrlSetPosition [_pad * 1.6, _y, _w - _pad, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad / 2;
        };
        case "switch": {
            // Ligne réglage : libellé + aide à gauche, interrupteur à droite ; « IMPOSÉ » si la communauté fixe la valeur.
            _row params ["", "_label", "_on", "_code", ["_help", ""], ["_locked", false]];
            private _pillW = _font * 3.6 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _card ctrlSetBackgroundColor [0.085, 0.105, 0.095, 1];
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + _pad / 4, _w - _pillW - _pad * 2.5, _rowH]] call _mk;
            // Libellé en blanc franc, aide en gris clair (retour de jeu : les lignes semblaient grisées).
            _t ctrlSetStructuredText parseText ((format ["<t size='0.95' color='#eef4f0'>%1</t>%2", _label, ["", format ["<br/><t size='0.75' color='#a9b8b1'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = ((ctrlTextHeight _t) + _pad / 2) max _rowH;
            _t ctrlSetPosition [_pad * 1.5, _y + _pad / 4, _w - _pillW - _pad * 2.5, _h - _pad / 2];
            _t ctrlCommit 0;
            _card ctrlSetPosition [_pad, _y, _w, _h];
            _card ctrlCommit 0;
            private _ph = _fs * 1.35;
            private _pill = ["COMSPEC_RscTextCenter", [_pad + _w - _pillW - _pad / 2, _y + (_h - _ph) / 2, _pillW, _ph], [["NON", "OUI"] select _on, format ["IMPOSÉ · %1", ["NON", "OUI"] select _on]] select _locked] call _mk;
            _pill ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            _pill ctrlSetBackgroundColor ([[[0.30, 0.34, 0.32, 1], _acc] select _on, [0.55, 0.38, 0.08, 1]] select _locked);
            _pill ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.03, 0.05, 0.04, 1]] select (_on && {!_locked}));
            if !(_locked) then {
                private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w, _h]] call _mk;
                [_b, _code] call _click;
            } else {
                _card ctrlSetTooltip (("Réglé par votre communauté sur Athena (Contrôle serveur)") call comspec_atak_native_fnc_tr);
            };
            _y = _y + _h + _pad / 3;
        };
        case "fold": {
            // Section repliable : grande cible de clic, titre à gauche, résumé et chevron à droite.
            _row params ["", "_title", ["_sub", ""], ["_open", false], ["_code", {}]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _h = _rowH * 1.2;
            _y = _y + _pad / 4;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _h]] call _mk;
            _card ctrlSetBackgroundColor ([[0.075, 0.09, 0.083, 1], [0.10, 0.125, 0.112, 1]] select _open);
            private _bar = ["COMSPEC_RscText", [_pad, _y, pixelW * 4, _h]] call _mk;
            _bar ctrlSetBackgroundColor ([[0.30, 0.34, 0.32, 1], _acc] select _open);
            private _cs = _h * 0.5;
            private _cw = _cs / _ratio;
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.8, _y, _w * 0.5, _h]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%2'>%1</t>", toUpper _title, ["#eef4f0", _accHex] select _open]) call comspec_atak_native_fnc_tr);
            _t ctrlSetPosition [_pad * 1.8, _y + (((_h - (ctrlTextHeight _t)) / 2) max 0), _w * 0.5, ctrlTextHeight _t];
            _t ctrlCommit 0;
            private _sw = _w * 0.45 - _cw - _pad;
            private _st = ["COMSPEC_RscStructuredText", [_pad + _w - _cw - _pad * 1.5 - _sw, _y, _sw, _h]] call _mk;
            _st ctrlSetStructuredText parseText ((format ["<t align='right' size='0.8' color='#a9b8b1'>%1</t>", _sub]) call comspec_atak_native_fnc_tr);
            _st ctrlSetPosition [_pad + _w - _cw - _pad * 1.5 - _sw, _y + (((_h - (ctrlTextHeight _st)) / 2) max 0), _sw, ctrlTextHeight _st];
            _st ctrlCommit 0;
            private _ch = ["COMSPEC_RscPicture", [_pad + _w - _cw - _pad * 0.75, _y + (_h - _cs) / 2, _cw, _cs], format ["\z\comspec_atak_native\addons\main\data\%1.paa", ["ui_chevron_down", "ui_chevron_up"] select _open]] call _mk;
            _ch ctrlSetTextColor ([[0.78, 0.84, 0.8, 1], _acc] select _open);
            private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w, _h]] call _mk;
            _b ctrlSetTooltip ((["Déplier", "Replier"] select _open) call comspec_atak_native_fnc_tr);
            [_b, _code] call _click;
            _y = _y + _h + _pad / ([4, 2] select _open);
        };
        case "slider": {
            _row params ["", "_label", "_val", "_code", ["_help", ""], ["_active", false], ["_icon", ""], ["_iconCode", {}], ["_iconTip", ""]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _h = _rowH * 1.3;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _h]] call _mk;
            _card ctrlSetBackgroundColor [0.085, 0.105, 0.095, 1];
            if (_active) then {
                private _rim = ["COMSPEC_RscText", [_pad, _y, pixelW * 3, _h]] call _mk;
                _rim ctrlSetBackgroundColor _acc;
            };
            private _ix = _pad * 1.6;
            if (_icon isNotEqualTo "") then {
                private _is = _rowH * 0.7;
                private _ic = ["COMSPEC_RscPicture", [_ix, _y + (_h - _is) / 2, _is / _ratio, _is], format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon]] call _mk;
                _ic ctrlSetTextColor ([[0.62, 0.68, 0.65, 1], _acc] select _active);
                private _ib = ["COMSPEC_RscButtonOverlay", [_ix, _y + (_h - _is) / 2, _is / _ratio, _is]] call _mk;
                if (_iconTip isNotEqualTo "") then { _ib ctrlSetTooltip ((_iconTip) call comspec_atak_native_fnc_tr); };
                [_ib, _iconCode] call _click;
                _ix = _ix + _is / _ratio + _pad;
            };
            private _lw = _w * 0.34;
            private _t = ["COMSPEC_RscStructuredText", [_ix, _y, _lw, _h]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t size='0.95' color='#eef4f0'>%1</t>%2", _label, ["", format ["<br/><t size='0.72' color='#a9b8b1'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _th = ctrlTextHeight _t;
            _t ctrlSetPosition [_ix, _y + (((_h - _th) / 2) max 0), _lw, _th min _h];
            _t ctrlCommit 0;
            private _vw = _font * 2.8 / _ratio;
            private _sx = _ix + _lw + _pad / 2;
            private _sw = (_pad + _w - _pad / 2 - _vw) - _sx - _pad / 2;
            private _vt = ["COMSPEC_RscTextRight", [_pad + _w - _pad / 2 - _vw, _y, _vw, _h], [(str (round _val)) + " %", "MUET"] select ((round _val) <= 0)] call _mk;
            _vt ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _vt ctrlSetFontHeight _fs;
            _vt ctrlSetTextColor ([[0.86, 0.91, 0.88, 1], _acc] select _active);
            private _sl = ["COMSPEC_RscSlider", [_sx, _y + _h * 0.3, _sw, _h * 0.4]] call _mk;
            _sl sliderSetRange [0, 100];
            _sl sliderSetSpeed [1, 10];
            _sl sliderSetPosition (_val max 0 min 100);
            _sl setVariable ["comspec_slide", [_code, _vt, round _val]];
            _sl ctrlAddEventHandler ["SliderPosChanged", {
                params ["_c", "_v"];
                (_c getVariable ["comspec_slide", [{}, controlNull, -1]]) params ["_code", "_vt", "_last"];
                _v = round _v;
                if (_v isEqualTo _last) exitWith {};
                _c setVariable ["comspec_slide", [_code, _vt, _v]];
                if (!isNull _vt) then { _vt ctrlSetText (([(str _v) + " %", "MUET"] select (_v <= 0)) call comspec_atak_native_fnc_tr); };
                [_v] call _code;
            }];
            _y = _y + _h + _pad / 3;
        };
        case "segment": {
            // Choix exclusif en une ligne : libellé au-dessus, boutons accolés, celui choisi en vert.
            _row params ["", "_label", "_opts", ["_help", ""]];
            if (_label isNotEqualTo "") then {
                private _lb = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 1.3]] call _mk;
                _lb ctrlSetStructuredText parseText ((format ["<t size='0.9'>%1</t>%2", _label, ["", format ["  <t size='0.75' color='#8a9a93'>%1</t>", _help]] select (_help isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                private _lh = (ctrlTextHeight _lb) max (_fs * 1.3);
                if (_lh > _fs * 1.3) then { _lb ctrlSetPosition [_pad, _y, _w, _lh]; _lb ctrlCommit 0; };
                _y = _y + _lh + _fs * 0.05;
            };
            private _n = count _opts;
            private _sw = _w / (_n max 1);
            {
                _x params ["_text", "_code", ["_active", false], ["_tip", ""]];
                private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _sw, _y, _sw - _pad / 6, _rowH * 0.9], _text] call _mk;
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlSetFontHeight (_fs * 0.9);
                // Option non choisie mais disponible : fond clair-sombre et texte blanc (ne doit pas sembler désactivée).
                if (_active) then { _b ctrlSetBackgroundColor _acc; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; } else { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor [0.92, 0.96, 0.93, 1]; };
                [_b, _code] call _click;
            } forEach _opts;
            _y = _y + _rowH * 0.9 + _pad / 2;
        };
        case "info": {
            _row params ["", "_label", "_value"];
            private _a = ["COMSPEC_RscStructuredText", [_pad, _y, _w * 0.5, _fs * 1.3]] call _mk;
            _a ctrlSetStructuredText parseText ((format ["<t size='0.85' color='#8a9a93'>%1</t>", _label]) call comspec_atak_native_fnc_tr);
            private _b = ["COMSPEC_RscStructuredText", [_pad + _w * 0.4, _y, _w * 0.6, _fs * 1.3]] call _mk;
            _b ctrlSetStructuredText parseText ((format ["<t size='0.85' align='right'>%1</t>", _value]) call comspec_atak_native_fnc_tr);
            private _ih2 = ((ctrlTextHeight _a) max (ctrlTextHeight _b)) max (_fs * 1.3);
            if (_ih2 > _fs * 1.3) then {
                _a ctrlSetPosition [_pad, _y, _w * 0.5, _ih2]; _a ctrlCommit 0;
                _b ctrlSetPosition [_pad + _w * 0.4, _y, _w * 0.6, _ih2]; _b ctrlCommit 0;
            };
            _y = _y + _ih2;
        };
        case "line": {
            _row params ["", "_num", "_label", ["_value", ""], ["_kind", "auto"], ["_key", ""], ["_help", ""], ["_style", ""]];
            private _mono = _style isEqualTo "mono";
            private _h = _rowH * ([1.25, 1.02] select _mono);
            private _odd = (_lineIdx mod 2) isEqualTo 1;
            _lineIdx = _lineIdx + 1;
            private _bgL = ["COMSPEC_RscText", [_pad, _y, _w, _h - pixelH * 2]] call _mk;
            _bgL ctrlSetBackgroundColor ([[0.055, 0.07, 0.064, 1], [0.07, 0.088, 0.08, 1]] select _odd);
            // Pastille du numéro, carrée à l'écran.
            private _nw = (_h * 0.7) * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _nb = ["COMSPEC_RscText", [_pad * 1.5, _y + _h * 0.15, _nw, _h * 0.7 - pixelH * 2]] call _mk;
            _nb ctrlSetBackgroundColor [_acc select 0, _acc select 1, _acc select 2, [0.9, 0.25] select (_num isEqualTo "")];
            private _nt = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + (_h - _fs * 1.25) / 2, _nw, _fs * 1.25]] call _mk;
            _nt ctrlSetStructuredText parseText ((format ["<t align='center' font='%2' color='#08100c'>%1</t>", _num, [(COMSPEC_ATAK_FontSet select 1), "EtelkaMonospaceProBold"] select _mono]) call comspec_atak_native_fnc_tr);
            private _lx = _pad * 2.5 + _nw;
            private _lw = (_w * 0.46) - _nw - _pad;
            private _vx = _pad + _w * 0.46;
            private _vw = _w * 0.54 - _pad;
            private _isEdit = _kind in ["edit", "autoedit"];
            // Libellé (et, pour un champ, l'aide en dessous) centré verticalement.
            private _lt = ["COMSPEC_RscStructuredText", [_lx, _y, _lw, _h]] call _mk;
            _lt ctrlSetStructuredText parseText ((format ["<t color='%3' size='%4'>%1</t>%2", [_label, toUpper _label] select _mono,
                ["", format ["<br/><t size='0.72' color='#93a39c'>%1</t>", _help]] select (_isEdit && {_help isNotEqualTo ""} && {!_mono}), ["#e3ebe7", "#9fb0a8"] select _mono, [1, 0.82] select _mono]) call comspec_atak_native_fnc_tr);
            private _th = (ctrlTextHeight _lt) min _h;
            _lt ctrlSetPosition [_lx, _y + (_h - _th) / 2, _lw, _th];
            _lt ctrlCommit 0;
            if (_isEdit) then {
                private _e = ["COMSPEC_RscEdit", [_vx, _y + _h * 0.12, _vw, _h * 0.76], _value] call _mk;
                _e ctrlSetFontHeight _font;
                if (_mono) then { _e ctrlSetFont "EtelkaMonospacePro"; _e ctrlSetFontHeight (_font * 0.85); };
                // Proposition automatique (modifiable) : en couleur d'accent, comme une valeur calculée.
                if (_kind isEqualTo "autoedit") then { _e ctrlSetTextColor _acc; };
                if (_help isNotEqualTo "") then { _e ctrlSetTooltip ((_help) call comspec_atak_native_fnc_tr); };
                _fields set [_key, _e];
            } else {
                private _empty = _value in ["", "-"];
                private _col = switch (true) do { case _empty: { "#93a39c" }; case (_kind isEqualTo "warn"): { "#ff6a5c" }; default { _accHex } };
                private _vt = ["COMSPEC_RscStructuredText", [_vx, _y, _vw, _h]] call _mk;
                _vt ctrlSetStructuredText parseText ((format ["<t align='right' color='%1' font='%2' size='%4'>%3</t>", _col,
                    [[(COMSPEC_ATAK_FontSet select 1), (COMSPEC_ATAK_FontSet select 0)] select _empty, ["EtelkaMonospaceProBold", (COMSPEC_ATAK_FontSet select 0)] select _empty] select _mono,
                    [_value, _help] select _empty, [1, 0.9] select _mono]) call comspec_atak_native_fnc_tr);
                private _vh = (ctrlTextHeight _vt) min _h;
                _vt ctrlSetPosition [_vx, _y + (_h - _vh) / 2, _vw, _vh];
                _vt ctrlCommit 0;
            };
            _y = _y + _h;
        };
        case "notif": {
            _row params ["", "_icon", "_rgb", "_title", ["_meta", ""], ["_body", ""], ["_open", {}], ["_close", {}], ["_tip", ""]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            private _e = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
            private _hasClose = _close isNotEqualTo {};
            private _ih = _font * 1.75;
            private _iw = _ih * _ratio;
            private _cbw = [0, _font * 1.6 * _ratio] select _hasClose;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _card ctrlSetBackgroundColor [0.07, 0.088, 0.08, 1];
            private _strip = ["COMSPEC_RscText", [_pad, _y, pixelW * 4, _rowH]] call _mk;
            _strip ctrlSetBackgroundColor _rgb;
            private _tx = _pad * 2.2 + _iw;
            private _tw = (_w - _iw - _cbw - _pad * 2.6) max (_w * 0.3);
            private _t = ["COMSPEC_RscStructuredText", [_tx, _y + _pad / 3, _tw, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#eef4f0'>%1</t><t size='0.75' align='right' color='#8a9a93'>%2</t>%3",
                [_title] call _e, _meta, ["", format ["<br/><t size='0.85' color='#c6d2cc'>%1</t>", [_body] call _e]] select (_body isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = ((ctrlTextHeight _t) + _pad * 0.8) max (_ih + _pad * 0.8);
            _t ctrlSetPosition [_tx, _y + _pad * 0.4, _tw, _h - _pad * 0.8];
            _t ctrlCommit 0;
            { _x ctrlSetPosition [ctrlPosition _x select 0, _y, ctrlPosition _x select 2, _h]; _x ctrlCommit 0; } forEach [_card, _strip];
            // Pastille d'icône : carré teinté à la couleur de gravité, icône blanche.
            private _iy = _y + _pad * 0.4;
            private _tile = ["COMSPEC_RscText", [_pad * 1.6, _iy, _iw, _ih]] call _mk;
            _tile ctrlSetBackgroundColor [_rgb select 0, _rgb select 1, _rgb select 2, 0.22];
            private _p = ["COMSPEC_RscPicture", [_pad * 1.6 + _iw * 0.17, _iy + _ih * 0.17, _iw * 0.66, _ih * 0.66], _icon] call _mk;
            _p ctrlSetTextColor [0.95, 0.98, 0.96, 1];
            if (_open isNotEqualTo {}) then {
                private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w - _cbw, _h]] call _mk;
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                [_b, _open] call _click;
            };
            if (_hasClose) then {
                private _cx = _pad + _w - _cbw;
                private _cs = _font * 0.9;
                private _ci = ["COMSPEC_RscPicture", [_cx + (_cbw - _cs * _ratio) / 2, _y + (_h - _cs) / 2, _cs * _ratio, _cs], "\z\comspec_atak_native\addons\main\data\ui_close.paa"] call _mk;
                _ci ctrlSetTextColor [0.62, 0.69, 0.65, 1];
                private _cb = ["COMSPEC_RscButtonOverlay", [_cx, _y, _cbw, _h]] call _mk;
                _cb ctrlSetTooltip (("Effacer cette notification") call comspec_atak_native_fnc_tr);
                [_cb, _close] call _click;
            };
            _y = _y + _h + _pad / 3;
        };
        case "empty": {
            _row params ["", "_icon", "_title", ["_sub", ""]];
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            _y = _y + _pad * 2;
            private _is = _font * 3.2;
            private _p = ["COMSPEC_RscPicture", [_pad + (_w - _is * _ratio) / 2, _y, _is * _ratio, _is], _icon] call _mk;
            _p ctrlSetTextColor [0.36, 0.45, 0.41, 1];
            _y = _y + _is + _pad;
            private _t = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold' color='#dfe8e3'>%1</t>%2", _title,
                ["", format ["<br/><t align='center' size='0.85' color='#8a9a93'>%1</t>", _sub]] select (_sub isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max _rowH;
            _t ctrlSetPosition [_pad, _y, _w, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad * 2;
        };
        case "gap": { _y = _y + _pad; };
        case "person": {
            _row params ["", "_pic", "_text", ["_btns", []], ["_picColor", [1, 1, 1, 1]]];
            private _ih = _font * 2.2;
            private _iw = _ih * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _bg = ["COMSPEC_RscText", [_pad, _y, _w, _ih + _pad / 2]] call _mk;
            _bg ctrlSetBackgroundColor [0.06, 0.075, 0.07, 1];
            private _p = ["COMSPEC_RscPicture", [_pad * 1.3, _y + _pad / 4, _iw, _ih], _pic] call _mk;
            _p ctrlSetTextColor _picColor;
            private _nb = count _btns;
            // Trois boutons ou plus (DÉTAILS, ENVOYER, SUPPRIMER) : plus étroits pour laisser de la place au texte.
            private _bwid = _w * ([0.2, 0.14] select (_nb >= 3));
            // Jamais de largeur nulle ou négative (même famille d'arrêt moteur que plus haut).
            private _tw = (_w - _iw - _pad * 1.5 - _nb * (_bwid + _pad / 3)) max (_w * 0.2);
            private _t = ["COMSPEC_RscStructuredText", [_pad * 2 + _iw, _y + _pad / 4, _tw, _ih]] call _mk;
            _t ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
            // Texte long (fil OSINT, messages) : la ligne s'agrandit au lieu de couper le texte.
            private _rowIh = _ih max ((ctrlTextHeight _t) + _pad / 4);
            if (_rowIh > _ih) then {
                _t ctrlSetPosition [_pad * 2 + _iw, _y + _pad / 4, _tw, _rowIh]; _t ctrlCommit 0;
                _bg ctrlSetPosition [_pad, _y, _w, _rowIh + _pad / 2]; _bg ctrlCommit 0;
            };
            {
                _x params ["_label", "_code", ["_primary", false], ["_enabled", true], ["_tip", ""]];
                // « red » à la place de principal : bouton de confirmation (second appui pour supprimer).
                private _red = _primary isEqualTo "red";
                if !(_primary isEqualType true) then { _primary = false; };
                private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, [_pad + _w - (_nb - _forEachIndex) * (_bwid + _pad / 3), _y + _pad / 4 + _ih * 0.15, _bwid, _ih * 0.7], _label] call _mk;
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
                // Bouton secondaire au même gris-vert que les barres et onglets (le bleu par défaut détonnait).
                if !(_primary) then { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [0.92, 0.96, 0.93, 1]] select _enabled); };
                if (_red) then { _b ctrlSetBackgroundColor [0.78, 0.2, 0.16, 0.95]; _b ctrlSetTextColor [1, 1, 1, 1]; };
                _b ctrlEnable _enabled;
                [_b, _code] call _click;
            } forEach _btns;
            _y = _y + _rowIh + _pad * 0.75;
        };
        case "hero": {
            _row params ["", "_pic", "_text"];
            private _ih = _font * 3.2;
            private _iw = _ih * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]); // carré à l'écran
            // Image hors addon (photo Athena téléchargée sur le disque) : le logo ATAK reste dessous. Si Arma ne peut pas
            // charger le fichier, le logo reste visible au lieu d'un cadre vide (le logo « disparaissait » après connexion).
            if (_pic isNotEqualTo "" && {(_pic select [0, 1]) isNotEqualTo "\"}) then {
                private _under = ["COMSPEC_RscPicture", [_pad, _y, _iw, _ih], "\z\comspec_atak_native\addons\main\data\logo_atak.paa"] call _mk;
                _under ctrlSetTextColor [1, 1, 1, 1];
            };
            private _p = ["COMSPEC_RscPicture", [_pad, _y, _iw, _ih], _pic] call _mk;
            _p ctrlSetTextColor [1, 1, 1, 1];
            private _t = ["COMSPEC_RscStructuredText", [_pad * 2 + _iw, _y, _w - _iw - _pad, _ih]] call _mk;
            _t ctrlSetStructuredText parseText ((_text) call comspec_atak_native_fnc_tr);
            private _h = (ctrlTextHeight _t) max _ih;
            _t ctrlSetPosition [_pad * 2 + _iw, _y, _w - _iw - _pad, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad;
        };
        case "image": {
            // Photo en pleine largeur (format 16/9 à l'écran, proportions conservées).
            _row params ["", "_pic"];
            private _ih = _w * 0.5625 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _bgI = ["COMSPEC_RscText", [_pad, _y, _w - _pad, _ih]] call _mk;
            _bgI ctrlSetBackgroundColor [0, 0, 0, 0.6];
            ["COMSPEC_RscSlide", [_pad, _y, _w - _pad, _ih], _pic] call _mk;
            _y = _y + _ih + _pad;
        };
        case "password": {
            // Mot de passe masqué : on garde la vraie valeur à part et on n'affiche que des points.
            _row params ["", "_key", "_label"];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight (_fs * 0.9);
            _y = _y + _fs * 1.2;
            private _secretVar = format ["COMSPEC_ATAK_Secret_%1", _key];
            private _cur = uiNamespace getVariable [_secretVar, ""];
            private _e = ["COMSPEC_RscEdit", [_pad, _y, _w, _rowH], (_cur splitString "") apply { "•" } joinString ""] call _mk;
            _e ctrlSetFontHeight _font;
            _e setVariable ["secretVar", _secretVar];
            private _redraw = {
                params ["_c"];
                [{ params ["_c"]; if (!isNull _c) then { _c ctrlSetText (((((uiNamespace getVariable [_c getVariable "secretVar", ""]) splitString "") apply { "•" } joinString "")) call comspec_atak_native_fnc_tr); }; }, [_c]] call CBA_fnc_execNextFrame;
            };
            _e setVariable ["redraw", _redraw];
            _e ctrlAddEventHandler ["Char", {
                params ["_c", "_char"];
                private _v = _c getVariable "secretVar";
                uiNamespace setVariable [_v, (uiNamespace getVariable [_v, ""]) + toString [_char]];
                [_c] call (_c getVariable "redraw");
            }];
            _e ctrlAddEventHandler ["KeyDown", {
                params ["_c", "_key"];
                private _v = _c getVariable "secretVar";
                private _cur = uiNamespace getVariable [_v, ""];
                if (_key isEqualTo 14) then { uiNamespace setVariable [_v, _cur select [0, ((count _cur) - 1) max 0]]; [_c] call (_c getVariable "redraw"); };
                if (_key isEqualTo 211) then { uiNamespace setVariable [_v, ""]; [_c] call (_c getVariable "redraw"); };
                false
            }];
            _fields set [_key, _e];
            _y = _y + _rowH + _pad / 2;
        };
        case "buttons": {
            private _btns = _row select 1;
            private _n = count _btns;
            private _bw = (_w - (_n - 1) * _pad / 2) / (_n max 1);
            {
                _x params ["_label", "_code", ["_primary", false], ["_enabled", true], ["_tip", ""]];
                private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, [_pad + _forEachIndex * (_bw + _pad / 2), _y, _bw, _rowH], _label] call _mk;
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlSetFontHeight _fs;
                _b ctrlEnable _enabled;
                [_b, _code] call _click;
            } forEach _btns;
            _y = _y + _rowH + _pad / 2;
        };
        case "bar": {
            // Groupes titrés côte à côte (titre gris souligné de vert), passage à la ligne si les boutons deviennent
            // trop étroits pour leur libellé.
            private _groups = (_row select 1) select { (count (_x select 1)) > 0 };
            private _minW = _fs * 6.5 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _perRow = (floor (_w / _minW)) max 1;
            private _lines = [];
            private _cur = [];
            private _n = 0;
            {
                private _k = count (_x select 1);
                if (_n > 0 && {_n + _k > _perRow}) then { _lines pushBack _cur; _cur = []; _n = 0; };
                _cur pushBack _x;
                _n = _n + _k;
            } forEach _groups;
            if ((count _cur) > 0) then { _lines pushBack _cur; };
            private _lh = _fs * 1.1; // titre de groupe + interligne, sans coupe
            private _bh = _rowH * 0.92;
            private _gw = _pad * 0.8;
            {
                private _line = _x;
                private _cnt = 0;
                { _cnt = _cnt + (count (_x select 1)); } forEach _line;
                private _bwid = (_w - ((count _line) - 1) * _gw) / (_cnt max 1);
                private _x0 = _pad;
                private _titled = (_line findIf { (_x select 0) isNotEqualTo "" }) >= 0;
                {
                    _x params ["_glabel", "_btns"];
                    private _span = _bwid * (count _btns);
                    if (_titled && {_glabel isNotEqualTo ""}) then {
                        private _t = ["COMSPEC_RscStructuredText", [_x0, _y, _span, _lh]] call _mk;
                        _t ctrlSetStructuredText parseText ((format ["<t size='0.72' font='COMSPEC_BarlowBold' color='#8a9a93'>%1</t>", toUpper _glabel]) call comspec_atak_native_fnc_tr);
                        _t ctrlEnable false;
                        private _ln = ["COMSPEC_RscText", [_x0, _y + _lh - pixelH * 3, _span - _pad / 3, pixelH * 2]] call _mk;
                        _ln ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.45];
                    };
                    {
                        _x params ["_lbl", "_code", ["_state", ""], ["_tip", ""], ["_en", true]];
                        private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_state isEqualTo "main"), [_x0 + _forEachIndex * _bwid, _y + ([0, _lh] select _titled), _bwid - _pad / 3, _bh], _lbl] call _mk;
                        _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
                        switch (_state) do {
                            case "on": { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
                            case "red": { _b ctrlSetBackgroundColor [0.78, 0.2, 0.16, 0.95]; _b ctrlSetTextColor [1, 1, 1, 1]; };
                            case "main": {};
                            default { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [0.92, 0.96, 0.93, 1]] select _en); };
                        };
                        if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                        _b ctrlEnable _en;
                        [_b, _code] call _click;
                    } forEach _btns;
                    _x0 = _x0 + _span + _gw;
                } forEach _line;
                _y = _y + ([0, _lh] select _titled) + _bh + _pad / 2;
            } forEach _lines;
        };
    };
} forEach _rows;
uiNamespace setVariable ["COMSPEC_ATAK_Form", _fields];
uiNamespace setVariable ["COMSPEC_ATAK_FormH", _y + _pad];
_grp setVariable ["bg", _bg];
_grp
