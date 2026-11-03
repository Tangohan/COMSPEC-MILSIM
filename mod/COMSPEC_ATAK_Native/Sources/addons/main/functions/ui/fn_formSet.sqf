/*
    Met à jour sur place une ligne de fn_formRender déclarée avec un id (retour de jeu 2.9.27 : un clic sur un réglage
    redessinait toute la page). Aucun contrôle créé ni supprimé.
    Params : [id, valeur, ...]
      switch : valeur = actif (booléen)
      slider : valeur = [valeur, actif, icône (data\nom, "" : inchangée)]
      fold   : valeur = résumé (texte)
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
        _sl sliderSetPosition _val;
        private _d = _sl getVariable ["comspec_slide", [{}, controlNull, -1, 0]];
        _sl setVariable ["comspec_slide", [_d select 0, _d select 1, _val, _d param [3, 0]]];
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
    case "fold": {
        private _t = _e get "text";
        if (isNull _t) exitWith { false };
        _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='" + (_th get "hexText") + "'>%1</t>%2", toUpper (_e get "title"),
            ["", format ["<t align='right' size='0.85' color='" + (_th get "hexTextDim") + "'>%1</t>", _v]] select (_v isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
        true
    };
    default { false };
};
