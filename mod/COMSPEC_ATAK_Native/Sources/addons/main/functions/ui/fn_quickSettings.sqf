/*
    Volet rapide Android, tiré de la barre d'état (clic sur l'heure / la batterie, ou sur la petite poignée sous l'heure).
    En tête, la fiche de l'opérateur : portrait opérateur du site Athena (fn_avatarPath, initiales en attendant),
    indicatif, grade et nom, unité et communauté ; à droite l'heure, la date et l'état de la liaison Athena.
    Réutilise les réglages existants, sans état parallèle :
      Luminosité  : profil COMSPEC_ATAK_Brightness (0,2 à 1 ; voile sombre de fn_deviceOverlay, lueur fn_screenGlow, batterie)
      Mode nuit   : profil COMSPEC_ATAK_NightMode (NORMAL / ROUGE / SOMBRE)
      Mode avion  : fn_airplaneMode             Silencieux : profil COMSPEC_ATAK_Silent
      Vibration   : profil COMSPEC_ATAK_Vibrate (verrouillable par la communauté, fn_pref)
      Bluetooth   : fn_btAction                 Balise IR  : fn_irBeacon (pas de lampe sur la coque : c'est la « lampe » IR)
      Réseau      : fn_linkQuality (ouvre l'app Réseau)   Batterie : COMSPEC_ATAK_Battery (ouvre l'app Statut)
    Params : [action, argument]
      "handle" : pose la zone cliquable de la barre d'état et la poignée (fn_layoutApply)
      "toggle" / "open" / "close" : volet animé (glisse depuis la barre d'état, remonte à la fermeture)
      "build"  : redessine sans animation (fn_fx « raise » après un rendu de page, changement d'un réglage)
      "tick"   : chaque seconde (fn_statusUpdate) : redessine si batterie, réseau ou un réglage ont changé
      "act" clé : action d'une tuile
*/
params [["_act", "toggle"], ["_arg", ""]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _s getOrDefault ["interactive", false];
private _kill = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ShadeCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", []];
};
// Signature de ce qui est affiché : batterie, barres, avion, réglages, portrait, heure.
private _sig = {
    private _lq = [] call comspec_atak_native_fnc_linkQuality;
    [floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]), _lq getOrDefault ["bars", 0], _lq getOrDefault ["label", ""], [] call comspec_atak_native_fnc_airplaneMode,
        profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"], profileNamespace getVariable ["COMSPEC_ATAK_Silent", false],
        profileNamespace getVariable ["COMSPEC_ATAK_Vibrate", true], (missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false],
        ["state"] call comspec_atak_native_fnc_irBeacon,
        // Fiche opérateur : portrait reçu du site, liaison Athena, heure à la minute.
        [player] call comspec_atak_native_fnc_avatarPath, missionNamespace getVariable ["COMSPEC_AthenaReady", false], floor (dayTime * 60)]
};
switch (_act) do {
    case "handle": {
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "status") params ["_sx", "_sy", "_sw", "_sh"];
        (uiNamespace getVariable ["COMSPEC_ATAK_ShadeHandle", []]) params [["_btn", controlNull], ["_pill", controlNull]];
        if (isNull _btn || {(ctrlParent _btn) isNotEqualTo _d}) then {
            _pill = _d ctrlCreate ["COMSPEC_RscText", -1];
            _pill ctrlEnable false;
            _pill ctrlSetBackgroundColor [0.62, 0.68, 0.65, 0.75];
            _btn = _d ctrlCreate ["COMSPEC_RscButtonOverlay", -1];
            _btn ctrlSetTooltip (("Volet rapide : luminosité, mode nuit, avion, silencieux…") call comspec_atak_native_fnc_tr);
            _btn ctrlAddEventHandler ["ButtonClick", { [{ ["toggle"] call comspec_atak_native_fnc_quickSettings; }] call CBA_fnc_execNextFrame; }];
            uiNamespace setVariable ["COMSPEC_ATAK_ShadeHandle", [_btn, _pill]];
        };
        // Batterie, météo et heure (jusqu'à la cloche) : rien de cliquable dessous.
        _btn ctrlSetPosition [_sx, _sy, _sw * 0.58, _sh];
        _btn ctrlCommit 0;
        _pill ctrlSetPosition [_sx + _sw * 0.46, _sy + _sh - pixelH * 3, _sw * 0.08, pixelH * 2];
        _pill ctrlCommit 0;
        { _x ctrlShow _interactive; } forEach [_btn, _pill];
        true
    };
    case "toggle": {
        [["open", "close"] select (uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false])] call comspec_atak_native_fnc_quickSettings
    };
    case "close": {
        if !(uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", false];
        saveProfileNamespace;
        private _ctrls = uiNamespace getVariable ["COMSPEC_ATAK_ShadeCtrls", []];
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", []];
        if (["anim"] call comspec_atak_native_fnc_fx) then {
            _ctrls params [["_dim", controlNull], ["_dimB", controlNull], ["_grp", controlNull]];
            if (!isNull _dimB) then { _dimB ctrlEnable false; };
            if (!isNull _grp) then { private _p = ctrlPosition _grp; _p set [3, 0]; _grp ctrlSetPosition _p; _grp ctrlCommit 0.15; };
            if (!isNull _dim) then { _dim ctrlSetFade 1; _dim ctrlCommit 0.15; };
            [{ { ctrlDelete _x; } forEach _this; }, _ctrls, 0.17] call CBA_fnc_waitAndExecute;
        } else {
            { ctrlDelete _x; } forEach _ctrls;
        };
        true
    };
    case "open";
    case "build": {
        if (!_interactive) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", false]; call _kill; false };
        private _anim = _act isEqualTo "open" && {["anim"] call comspec_atak_native_fnc_fx};
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", true];
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeSig", call _sig];
        call _kill;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
        (_l get "status") params ["", "", "", "_sth"];
        private _pad = (_l get "pad") * 2;
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _acc = ([] call comspec_atak_native_fnc_accent) select 0;
        private _made = [];
        // Voile sur le reste de l'écran : un clic dessus referme le volet.
        private _dim = _d ctrlCreate ["COMSPEC_RscText", -1];
        _dim ctrlEnable false;
        _dim ctrlSetPosition [_dx, _dy + _sth, _dw, _dh - _sth];
        _dim ctrlSetBackgroundColor [0, 0, 0, 0.45];
        _dim ctrlCommit 0;
        private _dimB = _d ctrlCreate ["COMSPEC_RscButtonInvisible", -1];
        _dimB ctrlSetPosition [_dx, _dy + _sth, _dw, _dh - _sth];
        _dimB ctrlSetTooltip (("Fermer le volet") call comspec_atak_native_fnc_tr);
        _dimB ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_quickSettings; }] call CBA_fnc_execNextFrame; }];
        _dimB ctrlCommit 0;
        _made append [_dim, _dimB];
        private _grp = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1];
        _made pushBack _grp;
        private _mk = {
            params ["_class", "_pos", ["_text", ""]];
            private _c = _d ctrlCreate [_class, -1, _grp];
            _c ctrlSetPosition _pos;
            if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        private _w = _dw;
        private _cols = [4, 6] select (_l get "landscape");
        private _gap = _pad * 0.6;
        private _tw = (_w - _pad * 2 - _gap * (_cols - 1)) / _cols;
        private _th = (_tw * _ratio * 0.66) max (_font * 3.2);
        // Contenu : fiche opérateur, luminosité, tuiles, pied.
        private _cardH = _font * 3.3;
        private _rowH = _font * 1.8;
        private _lq = [] call comspec_atak_native_fnc_linkQuality;
        private _bat = floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
        private _air = [] call comspec_atak_native_fnc_airplaneMode;
        private _night = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
        private _vib = ["COMSPEC_ATAK_Vibrate", true, "native_vibrate"] call comspec_atak_native_fnc_pref;
        private _ico = { "\z\comspec_atak_native\addons\main\data\" + _this + ".paa" };
        private _tiles = [
            ["night", "Mode nuit", ["NORMAL", "ROUGE", "SOMBRE"] select ((["OFF", "RED", "DIM"] find _night) max 0), _night isNotEqualTo "OFF", "qs_night"],
            ["airplane", "Mode avion", ["DÉSACTIVÉ", "ACTIVÉ"] select _air, _air, "qs_airplane"],
            ["silent", "Silencieux", ["SONS", "AUCUN SON"] select (profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]), profileNamespace getVariable ["COMSPEC_ATAK_Silent", false], "qs_silent"],
            ["vibrate", "Vibration", (["DÉSACTIVÉE", "ACTIVÉE"] select (_vib select 0)) + (["", " · IMPOSÉ"] select (_vib select 1)), _vib select 0, "ui_vibrate"],
            ["bt", "Bluetooth", ["DÉSACTIVÉ", "ACTIVÉ"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false]), (missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false], "qs_bt"],
            ["ir", "Balise IR", ["ÉTEINTE", "ALLUMÉE"] select (["state"] call comspec_atak_native_fnc_irBeacon), ["state"] call comspec_atak_native_fnc_irBeacon, "qs_ir"],
            ["net", "Réseau", if (_air) then { "MODE AVION" } else { format ["%1 · %2/4", toUpper (_lq getOrDefault ["label", "—"]), _lq getOrDefault ["bars", 0]] }, !_air && {(_lq getOrDefault ["bars", 0]) >= 1}, "app_network"],
            ["battery", "Batterie", format ["%1 %%", _bat], _bat > 20, "qs_battery"]
        ];
        private _rows = ceil ((count _tiles) / _cols);
        // Tout doit tenir sous la barre d'état : les tuiles rapetissent si l'écran est court.
        // Place libre en fin de dernière rangée (paysage : 4 cases) : les deux boutons s'y rangent, l'un sur l'autre.
        private _free = _rows * _cols - (count _tiles);
        private _inRow = _free >= 2;
        private _fixed = _pad + _cardH + _pad + _rowH + _gap + _rows * _gap + ([_rowH, 0] select _inRow) + _pad * 1.4;
        _th = (_th min (((_dh - _sth) - _fixed) / _rows)) max (_font * 2.4);
        private _H = (_fixed + _rows * _th) min (_dh - _sth);
        private _gy = _dy + _sth;
        // Teintes : fond, tuile éteinte, accent assombri (tuile allumée), accent éclairci (texte sur accent).
        private _accDim = [(_acc select 0) * 0.30, (_acc select 1) * 0.30, (_acc select 2) * 0.30, 1];
        private _accHi = [(_acc select 0) + (1 - (_acc select 0)) * 0.45, (_acc select 1) + (1 - (_acc select 1)) * 0.45, (_acc select 2) + (1 - (_acc select 2)) * 0.45, 1];
        private _dimTxt = [0.55, 0.61, 0.58, 1];
        private _bg = ["COMSPEC_RscText", [0, 0, _w, _H]] call _mk;
        _bg ctrlSetBackgroundColor [0.028, 0.036, 0.032, 0.985];
        private _edge = ["COMSPEC_RscText", [0, 0, _w, pixelH * 2]] call _mk;
        _edge ctrlSetBackgroundColor [(_acc select 0), (_acc select 1), (_acc select 2), 0.55];

        // Fiche opérateur : portrait Athena (site), indicatif, nom et grade, unité et communauté.
        private _v = { params ["_k", ["_d", ""]]; private _r = missionNamespace getVariable [_k, _d]; if (_r isEqualType "") then { [_r, _d] select (_r in ["", "—"]) } else { str _r } };
        private _cs = ["comspec_profile_callsign", [player, true] call comspec_atak_native_fnc_unitCallsign] call _v;
        private _name = ["comspec_profile_name", name player] call _v;
        private _grade = ["comspec_profile_grade"] call _v;
        private _unit = [player, true] call comspec_atak_native_fnc_unitGroup;
        if ((["comspec_profile_unit"] call _v) isEqualTo "") then { _unit = ""; };
        private _tenant = ["comspec_tenant_name"] call _v;
        private _online = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
        private _ph = _cardH;
        private _pw = _ph * _ratio;
        private _py = _pad;
        private _frame = ["COMSPEC_RscText", [_pad - pixelW * 2, _py - pixelH * 2, _pw + pixelW * 4, _ph + pixelH * 4]] call _mk;
        _frame ctrlSetBackgroundColor ([[0.30, 0.34, 0.32, 1], _acc] select _online);
        private _pbg = ["COMSPEC_RscText", [_pad, _py, _pw, _ph]] call _mk;
        _pbg ctrlSetBackgroundColor [0.07, 0.09, 0.08, 1];
        // Sans portrait (hors ligne, pas encore reçu, aucun sur le site) : initiales de l'indicatif.
        private _words = (_cs splitString " -_.") select { _x isNotEqualTo "" };
        private _ini = toUpper (if ((count _words) > 1) then { ((_words select 0) select [0, 1]) + ((_words select 1) select [0, 1]) } else { (_cs select [0, 2]) });
        private _in = ["COMSPEC_RscTextCenter", [_pad, _py, _pw, _ph]] call _mk;
        _in ctrlSetText ((_ini) call comspec_atak_native_fnc_tr);
        _in ctrlSetFont "RobotoCondensedBold";
        _in ctrlSetFontHeight (_font * 1.5);
        _in ctrlSetTextColor _accHi;
        private _photo = [player] call comspec_atak_native_fnc_avatarPath;
        if (_photo isNotEqualTo "") then {
            private _pic = ["COMSPEC_RscSlide", [_pad, _py, _pw, _ph]] call _mk;
            _pic ctrlSetText ((_photo) call comspec_atak_native_fnc_tr);
        };
        private _tx = _pad * 2 + _pw;
        private _txW = _w * 0.62 - _tx;
        private _l1 = ["COMSPEC_RscText", [_tx, _py - _font * 0.1, _txW, _font * 1.35]] call _mk;
        _l1 ctrlSetText ((_cs) call comspec_atak_native_fnc_tr);
        _l1 ctrlSetFont "RobotoCondensedBold";
        _l1 ctrlSetFontHeight (_font * 1.2);
        _l1 ctrlSetTextColor _acc;
        private _l2 = ["COMSPEC_RscText", [_tx, _py + _font * 1.2, _txW, _font * 1.1]] call _mk;
        _l2 ctrlSetText ((([_name, format ["%1 · %2", _grade, _name]] select (_grade isNotEqualTo ""))) call comspec_atak_native_fnc_tr);
        _l2 ctrlSetFontHeight _fs;
        _l2 ctrlSetTextColor [0.88, 0.92, 0.90, 1];
        private _l3 = ["COMSPEC_RscText", [_tx, _py + _font * 2.25, _txW, _font * 1.1]] call _mk;
        _l3 ctrlSetText (((([_unit, _tenant] select { _x isNotEqualTo "" }) joinString " · ")) call comspec_atak_native_fnc_tr);
        _l3 ctrlSetFontHeight (_fs * 0.88);
        _l3 ctrlSetTextColor _dimTxt;
        // À droite : heure, date, liaison Athena.
        date params ["_yy", "_mm", "_dd"];
        private _zm = _mm; private _zy = _yy;
        if (_zm < 3) then { _zm = _zm + 12; _zy = _zy - 1; };
        // Jour de la semaine (congruence de Zeller : 0 = samedi).
        private _K = _zy mod 100;
        private _J = floor (_zy / 100);
        private _wd = (_dd + floor (13 * (_zm + 1) / 5) + _K + floor (_K / 4) + floor (_J / 4) + 5 * _J) mod 7;
        private _hr = ["COMSPEC_RscTextRight", [_w * 0.5, _py - _font * 0.25, _w * 0.5 - _pad, _font * 1.7], [dayTime, "HH:MM"] call BIS_fnc_timeToString] call _mk;
        _hr ctrlSetFont "RobotoCondensedBold";
        _hr ctrlSetFontHeight (_font * 1.55);
        _hr ctrlSetTextColor [0.95, 0.98, 0.96, 1];
        private _dt = ["COMSPEC_RscTextRight", [_w * 0.5, _py + _font * 1.4, _w * 0.5 - _pad, _font * 1.0],
            format ["%1 %2 %3", (["SAMEDI", "DIMANCHE", "LUNDI", "MARDI", "MERCREDI", "JEUDI", "VENDREDI"] select _wd) call comspec_atak_native_fnc_tr, _dd,
                (["JANVIER", "FÉVRIER", "MARS", "AVRIL", "MAI", "JUIN", "JUILLET", "AOÛT", "SEPTEMBRE", "OCTOBRE", "NOVEMBRE", "DÉCEMBRE"] select ((_mm - 1) max 0 min 11)) call comspec_atak_native_fnc_tr]] call _mk;
        _dt ctrlSetFontHeight (_fs * 0.88);
        _dt ctrlSetTextColor _dimTxt;
        private _st = ["COMSPEC_RscTextRight", [_w * 0.5, _py + _font * 2.25, _w * 0.5 - _pad, _font * 1.1], ["●  ATHENA HORS LIGNE", "●  ATHENA EN LIGNE"] select _online] call _mk;
        _st ctrlSetFont "RobotoCondensedBold";
        _st ctrlSetFontHeight (_fs * 0.82);
        _st ctrlSetTextColor ([_dimTxt, _acc] select _online);
        private _y = _py + _cardH + _pad * 0.75;
        private _div = ["COMSPEC_RscText", [_pad, _y, _w - _pad * 2, pixelH]] call _mk;
        _div ctrlSetBackgroundColor [1, 1, 1, 0.08];
        _y = _y + _pad * 0.25;

        // Luminosité : petit soleil, curseur, grand soleil, valeur.
        private _is = _rowH * 0.62;
        private _isw = _is * _ratio;
        private _vw = _w * 0.07;
        private _s1 = ["COMSPEC_RscPicture", [_pad, _y + (_rowH - _is * 0.75) / 2, _isw * 0.75, _is * 0.75], "qs_sun_low" call _ico] call _mk;
        _s1 ctrlSetTextColor _dimTxt;
        private _sx = _pad + _isw * 0.75 + _gap;
        private _sw = _w - _pad * 2 - _isw * 0.75 - _isw - _vw - _gap * 3;
        private _sl = ["COMSPEC_RscSlider", [_sx, _y + _rowH * 0.28, _sw, _rowH * 0.44]] call _mk;
        _sl sliderSetRange [0.2, 1];
        _sl sliderSetSpeed [0.05, 0.1];
        _sl sliderSetPosition ((profileNamespace getVariable ["COMSPEC_ATAK_Brightness", 1]) max 0.2 min 1);
        _sl ctrlSetTooltip (("Luminosité de l'écran") call comspec_atak_native_fnc_tr);
        private _s2 = ["COMSPEC_RscPicture", [_sx + _sw + _gap, _y + (_rowH - _is) / 2, _isw, _is], "qs_sun" call _ico] call _mk;
        _s2 ctrlSetTextColor _accHi;
        private _bv = ["COMSPEC_RscTextRight", [_w - _pad - _vw, _y, _vw, _rowH], format ["%1 %%", round (((profileNamespace getVariable ["COMSPEC_ATAK_Brightness", 1]) max 0.2 min 1) * 100)]] call _mk;
        _bv ctrlSetFont "RobotoCondensedBold";
        _bv ctrlSetFontHeight _fs;
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeBright", _bv];
        _sl ctrlAddEventHandler ["SliderPosChanged", {
            params ["", "_v"];
            private _b = ((round (_v * 20)) / 20) max 0.2 min 1;
            profileNamespace setVariable ["COMSPEC_ATAK_Brightness", _b];
            private _bv = uiNamespace getVariable ["COMSPEC_ATAK_ShadeBright", controlNull];
            if (!isNull _bv) then { _bv ctrlSetText ((format ["%1 %%", round (_b * 100)]) call comspec_atak_native_fnc_tr); };
            // Voile appliqué au plus 10 fois par seconde pendant le glissé.
            if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_BrightAt", -1]) > 0.1) then {
                uiNamespace setVariable ["COMSPEC_ATAK_BrightAt", diag_tickTime];
                [] call comspec_atak_native_fnc_deviceOverlay;
            };
        }];
        _y = _y + _rowH + _gap;

        // Tuiles : icône, nom, état ; allumée = fond teinté de l'accent et liseré en haut.
        {
            _x params ["_key", "_label", "_state", "_on", "_icon"];
            private _col = _forEachIndex mod _cols;
            private _row = floor (_forEachIndex / _cols);
            private _x0 = _pad + _col * (_tw + _gap);
            private _y0 = _y + _row * (_th + _gap);
            private _tb = ["COMSPEC_RscText", [_x0, _y0, _tw, _th]] call _mk;
            _tb ctrlSetBackgroundColor ([[0.075, 0.090, 0.083, 1], _accDim] select _on);
            private _tl = ["COMSPEC_RscText", [_x0, _y0, _tw, pixelH * ([1, 3] select _on)]] call _mk;
            _tl ctrlSetBackgroundColor ([[1, 1, 1, 0.07], _acc] select _on);
            private _ih = _th * 0.30;
            private _iw = _ih * _ratio;
            private _ic = ["COMSPEC_RscPicture", [_x0 + (_tw - _iw) / 2, _y0 + _th * 0.13, _iw, _ih], _icon call _ico] call _mk;
            _ic ctrlSetTextColor ([[0.66, 0.72, 0.69, 1], _accHi] select _on);
            private _lb = ["COMSPEC_RscTextCenter", [_x0, _y0 + _th * 0.47, _tw, _th * 0.24], _label] call _mk;
            _lb ctrlSetFont "RobotoCondensedBold";
            _lb ctrlSetFontHeight _fs;
            _lb ctrlSetTextColor ([[0.84, 0.88, 0.86, 1], [1, 1, 1, 1]] select _on);
            private _fh = _fs;
            while { (ctrlTextWidth _lb) > _tw * 0.94 && {_fh > _fs * 0.7} } do { _fh = _fh * 0.92; _lb ctrlSetFontHeight _fh; };
            private _stt = ["COMSPEC_RscTextCenter", [_x0, _y0 + _th * 0.71, _tw, _th * 0.2], _state] call _mk;
            _stt ctrlSetFontHeight (_fs * 0.78);
            _stt ctrlSetTextColor ([_dimTxt, _accHi] select _on);
            _fh = _fs * 0.78;
            while { (ctrlTextWidth _stt) > _tw * 0.94 && {_fh > _fs * 0.55} } do { _fh = _fh * 0.92; _stt ctrlSetFontHeight _fh; };
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _y0, _tw, _th]] call _mk;
            _b setVariable ["key", _key];
            _b ctrlSetTooltip ((format ["%1 : %2", _label, _state]) call comspec_atak_native_fnc_tr);
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ ["act", _this] call comspec_atak_native_fnc_quickSettings; }, _c getVariable ["key", ""]] call CBA_fnc_execNextFrame; }];
        } forEach _tiles;
        _y = _y + _rows * (_th + _gap);
        // Boutons : Réglages complets (accent), fermer (discret) ; dans la place libre des tuiles, sinon en pied.
        private _bw2 = (_w - _pad * 2 - _gap) / 2;
        private _p1 = [_pad, _y, _bw2, _rowH];
        private _p2 = [_pad + _bw2 + _gap, _y, _bw2, _rowH];
        if (_inRow) then {
            private _bx = _pad + (_cols - _free) * (_tw + _gap);
            private _bwr = _free * _tw + (_free - 1) * _gap;
            private _by = _y - _th - _gap;
            private _bh = (_th - _gap * 0.5) / 2;
            _p1 = [_bx, _by, _bwr, _bh];
            _p2 = [_bx, _by + _bh + _gap * 0.5, _bwr, _bh];
        };
        private _b1 = ["COMSPEC_RscButton", _p1, "TOUS LES RÉGLAGES"] call _mk;
        _b1 ctrlSetFont "RobotoCondensedBold";
        _b1 ctrlSetFontHeight _fs;
        _b1 ctrlSetBackgroundColor _accDim;
        _b1 ctrlSetTextColor _accHi;
        _b1 ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_quickSettings; ["SETTINGS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }];
        private _b2 = ["COMSPEC_RscButton", _p2, "FERMER"] call _mk;
        _b2 ctrlSetFont "RobotoCondensedBold";
        _b2 ctrlSetFontHeight _fs;
        _b2 ctrlSetBackgroundColor [0.075, 0.090, 0.083, 1];
        _b2 ctrlSetTextColor [0.84, 0.88, 0.86, 1];
        _b2 ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_quickSettings; }] call CBA_fnc_execNextFrame; }];
        // Poignée en bas du volet.
        private _hp = ["COMSPEC_RscText", [_w * 0.46, _H - _pad * 0.45, _w * 0.08, pixelH * 3]] call _mk;
        _hp ctrlSetBackgroundColor [0.62, 0.68, 0.65, 0.6];
        // Ouverture : le volet descend de la barre d'état (le groupe s'agrandit, son contenu reste en place).
        if (_anim) then {
            _grp ctrlSetPosition [_dx, _gy, _w, 0];
            _grp ctrlCommit 0;
            _grp ctrlSetPosition [_dx, _gy, _w, _H];
            _grp ctrlCommit 0.2;
            _dim ctrlSetFade 1;
            _dim ctrlCommit 0;
            _dim ctrlSetFade 0;
            _dim ctrlCommit 0.2;
        } else {
            _grp ctrlSetPosition [_dx, _gy, _w, _H];
            _grp ctrlCommit 0;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", _made];
        if (_act isEqualTo "open") then {
            ["front"] call comspec_atak_native_fnc_fx;
            [] call comspec_atak_native_fnc_overlayFront;
        };
        true
    };
    case "tick": {
        if !(uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) exitWith { false };
        if ((call _sig) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ShadeSig", []])) exitWith { false };
        ["build"] call comspec_atak_native_fnc_quickSettings;
        ["front"] call comspec_atak_native_fnc_fx;
        true
    };
    case "act": {
        switch (_arg) do {
            case "night": {
                private _m = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
                private _n = ["OFF", "RED", "DIM"] select ((((["OFF", "RED", "DIM"] find _m) max 0) + 1) mod 3);
                profileNamespace setVariable ["COMSPEC_ATAK_NightMode", _n];
                [format ["Mode nuit : %1", ["normal", "filtre rouge", "écran sombre"] select (["OFF", "RED", "DIM"] find _n)], 2] call comspec_atak_native_fnc_toast;
            };
            case "airplane": { ["toggle"] call comspec_atak_native_fnc_airplaneMode; };
            case "silent": {
                private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]);
                profileNamespace setVariable ["COMSPEC_ATAK_Silent", _on];
                [["Sons activés", "Silencieux : aucun son"] select _on, 2] call comspec_atak_native_fnc_toast;
            };
            case "vibrate": {
                if ((["COMSPEC_ATAK_Vibrate", true, "native_vibrate"] call comspec_atak_native_fnc_pref) select 1) exitWith {
                    ["Vibration imposée par votre communauté", 2.5] call comspec_atak_native_fnc_toast;
                };
                private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_Vibrate", true]);
                profileNamespace setVariable ["COMSPEC_ATAK_Vibrate", _on];
                [["Vibration désactivée", "Vibration activée"] select _on, 2] call comspec_atak_native_fnc_toast;
                if (_on) then { [] call comspec_atak_native_fnc_vibrate; };
            };
            case "bt": { ["toggle"] call comspec_atak_native_fnc_btAction; };
            case "ir": { ["toggle"] call comspec_atak_native_fnc_irBeacon; };
            case "net": { ["close"] call comspec_atak_native_fnc_quickSettings; ["NETWORK"] call comspec_atak_native_fnc_navigate; };
            case "battery": { ["close"] call comspec_atak_native_fnc_quickSettings; ["STATUS"] call comspec_atak_native_fnc_navigate; };
        };
        if (uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) then {
            ["build"] call comspec_atak_native_fnc_quickSettings;
            ["front"] call comspec_atak_native_fnc_fx;
            // Le toast éventuel repasse devant le volet redessiné.
            [] call comspec_atak_native_fnc_toast;
        };
        [] call comspec_atak_native_fnc_deviceOverlay;
        true
    };
    default { false };
}
