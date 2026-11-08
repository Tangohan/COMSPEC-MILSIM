/*
    Met à jour sur place une ligne de fn_formRender déclarée avec un id (retour de jeu 2.9.27 : un clic sur un réglage
    redessinait toute la page). Aucun contrôle créé ni supprimé.
    Params : [id, valeur, ...]
      switch : valeur = actif (booléen)
      slider : valeur = [valeur, actif, icône (data\nom, "" : inchangée)]
      fold   : valeur = résumé (texte)
      person : valeur = [texte structuré, teinte de l'image [r, v, b, a], boutons [[libellé ("" : inchangé), actif, principal, visible]...]]
               (ligne « person » déclarée avec un 7e paramètre) ; valeur [] : ligne masquée
      btn    : valeur = [libellé ("" : inchangé), état ("" | "on" | "red"), actif (sans valeur : inchangé), infobulle ("" : inchangée)]
               (bouton de la barre de l'app Drone, 6e élément)
      btnrow : valeur = visible (booléen), ou [[libellé ("" : inchangé), actif, style ("" | "main" | "red"), infobulle]...] un par bouton
               (ligne « buttons » déclarée avec un id, 3e élément)
      segment : valeur = l'option choisie (ligne « segment » déclarée avec un id, 5e élément des options)
    Retour : true si la ligne est affichée et a été mise à jour.
*/
params [["_id", ""], ["_v", false]];
disableSerialization;
private _e = (uiNamespace getVariable ["COMSPEC_ATAK_FormIds", createHashMap]) getOrDefault [_id, createHashMap];
if ((count _e) isEqualTo 0) exitWith { false };
// Couleurs du thème (fn_theme) : mêmes jetons que fn_formRender.
private _th = [] call comspec_atak_native_fnc_theme;
private _acc = _th get "accent";
switch (_e get "kind") do {
    case "switch": {
        private _pill = _e get "pill";
        if (isNull _pill) exitWith { false };
        private _locked = _e get "locked";
        _pill ctrlSetText (([["NON", "OUI"] select _v, format ["IMPOSÉ · %1", ["NON", "OUI"] select _v]] select _locked) call comspec_atak_native_fnc_tr);
        _pill ctrlSetFontHeight (_e get "fh");
        private _tw = ctrlTextWidth _pill;
        if (_tw > (_e get "w") * 0.9) then { _pill ctrlSetFontHeight (((_e get "fh") * (_e get "w") * 0.9 / _tw) max COMSPEC_ATAK_FontMin); };
        _pill ctrlSetBackgroundColor ([[(_th get "off"), _acc] select _v, (_th get "warnDim")] select _locked);
        _pill ctrlSetTextColor ([(_th get "text"), (_th get "onAccent")] select (_v && {!_locked}));
        true
    };
    case "slider": {
        private _sl = _e get "slider";
        if (isNull _sl) exitWith { false };
        _v params [["_val", 0], ["_active", false], ["_icon", ""]];
        private _min = _e get "min";
        _val = round (_val max _min min (_e get "max"));
        // Valeur notée avant de déplacer le curseur : cette mise à jour ne doit pas relancer l'action du curseur.
        private _d = _sl getVariable ["comspec_slide", [{}, controlNull, -1, 0]];
        _sl setVariable ["comspec_slide", [_d select 0, _d select 1, _val, _d param [3, 0]]];
        _sl sliderSetPosition _val;
        private _vt = _e get "value";
        if (!isNull _vt) then {
            _vt ctrlSetText (([(str _val) + " %", "MUET"] select (_min <= 0 && {_val <= 0})) call comspec_atak_native_fnc_tr);
            _vt ctrlSetTextColor ([(_th get "text"), (_th get "accentInk")] select _active);
        };
        private _rim = _e get "rim";
        if (!isNull _rim) then { _rim ctrlShow _active; };
        private _ic = _e get "icon";
        if (!isNull _ic) then {
            _ic ctrlSetTextColor ([(_th get "textDim"), (_th get "accentInk")] select _active);
            if (_icon isNotEqualTo "") then { _ic ctrlSetText ((format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon]) call comspec_atak_native_fnc_tr); };
        };
        true
    };
    case "segment": {
        private _shown = false;
        {
            _x params ["_b", "_val"];
            if (!isNull _b) then {
                _shown = true;
                private _on = _val isEqualTo _v;
                _b ctrlSetBackgroundColor ([(_th get "surface2"), _acc] select _on);
                _b ctrlSetTextColor ([(_th get "text"), (_th get "onAccent")] select _on);
            };
        } forEach (_e get "btns");
        _shown
    };
    case "fold": {
        private _t = _e get "text";
        if (isNull _t) exitWith { false };
        _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + (_th get "hexText") + "'>%1</t>%2", toUpper (_e get "title"),
            ["", format ["<t align='right' size='0.85' color='" + (_th get "hexTextDim") + "'>%1</t>", _v]] select (_v isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
        true
    };
    case "person": {
        private _t = _e get "text";
        if (isNull _t) exitWith { false };
        if (_v isEqualTo []) exitWith {
            { if (!isNull _x) then { _x ctrlShow false; }; } forEach ([_t, _e get "pic", _e getOrDefault ["bg", controlNull]] + (_e get "btns"));
            true
        };
        _v params ["_txt", ["_col", [1, 1, 1, 1]], ["_set", []]];
        {
            _x params [["_lbl", ""], ["_en", true], ["_pri", false], ["_vis", true]];
            private _b = (_e get "btns") param [_forEachIndex, controlNull];
            if (isNull _b) then { continue };
            _b ctrlShow _vis;
            if (_lbl isNotEqualTo "") then { _b ctrlSetText ((_lbl) call comspec_atak_native_fnc_tr); };
            _b ctrlEnable _en;
            _b ctrlSetBackgroundColor ([(_th get "surface2"), _acc] select _pri);
            _b ctrlSetTextColor ([[(_th get "off"), (_th get "text")] select _en, (_th get "onAccent")] select _pri);
        } forEach _set;
        _t ctrlSetStructuredText parseText ((_txt) call comspec_atak_native_fnc_tr);
        private _p = _e get "pic";
        if (!isNull _p) then { _p ctrlSetTextColor _col; };
        true
    };
    case "btn": {
        private _b = _e get "btn";
        if (isNull _b) exitWith { false };
        _v params [["_lbl", ""], ["_state", ""], ["_enable", -1], ["_tip", ""]];
        if (_lbl isNotEqualTo "") then { _b ctrlSetText ((_lbl) call comspec_atak_native_fnc_tr); };
        if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
        if (_enable isEqualType true) then { _e set ["en", _enable]; _b ctrlEnable _enable; };
        private _en = _e get "en";
        switch (_state) do {
            case "on": { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
            case "red": { _b ctrlSetBackgroundColor [0.78, 0.2, 0.16, 0.95]; _b ctrlSetTextColor [1, 1, 1, 1]; };
            default { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [0.92, 0.96, 0.93, 1]] select _en); };
        };
        true
    };
    case "btnrow": {
        private _shown = false;
        private _each = _v isEqualType [];
        {
            if (isNull _x) then { continue };
            _shown = true;
            if (!_each) then { _x ctrlShow _v; continue };
            (_v param [_forEachIndex, []]) params [["_lbl", ""], ["_en", true], ["_style", ""], ["_tip", ""]];
            if (_lbl isNotEqualTo "") then { _x ctrlSetText ((_lbl) call comspec_atak_native_fnc_tr); };
            if (_tip isNotEqualTo "") then { _x ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
            _x ctrlEnable _en;
            switch (_style) do {
                case "red": { _x ctrlSetBackgroundColor (_th get "alert"); _x ctrlSetTextColor [1, 1, 1, 1]; };
                case "main": { _x ctrlSetBackgroundColor _acc; _x ctrlSetTextColor (_th get "onAccent"); };
                default { _x ctrlSetBackgroundColor (_th get "surface2"); _x ctrlSetTextColor ([(_th get "off"), (_th get "text")] select _en); };
            };
        } forEach (_e get "btns");
        _shown
    };
    default { false };
};
