/*
    Lignes « CATALOG » du formulaire générique (idd 93600) : listes du site de la communauté.
      ["fill", combo]     remplit la liste (filtre en cours appliqué, choix gardé)
      ["filter", champ]   le champ de filtre a changé
      ["open"]            à l'ouverture : ligne d'état, relecture du site si besoin
      ["refresh"]         bouton ACTUALISER
      ["updated"]         le site a répondu (événement comspec_sse_catalogueUpdated) : toutes les listes sont refaites
    Ligne d'état 93603 : jamais d'alerte ni de message de discussion, l'origine des listes se lit dans le formulaire.
*/
params [["_op", "", [""]], ["_arg", controlNull]];
if (!hasInterface) exitWith {};
disableSerialization;
private _display = uiNamespace getVariable ["COMSPEC_SSE_FormDisplay", displayNull];

private _status = {
    params [["_busy", false]];
    if (isNull _display) exitWith {};
    private _text = ["status"] call comspec_sse_fnc_catalogue;
    if (_busy isEqualTo true) then { _text = ("Lecture des listes sur le site…") call comspec_sse_fnc_tr; };
    (_display displayCtrl 93603) ctrlSetStructuredText parseText ((format ["<t color='#9FAAA2'>%1</t>", _text]) call comspec_sse_fnc_tr);
};

switch (_op) do {
    case "fill": {
        private _ctrl = _arg;
        if (isNull _ctrl) exitWith {};
        (_ctrl getVariable ["comspec_sse_catSpec", []]) params [["_section", "", [""]], ["_codes", [], [[]]], ["_labels", [], [[]]], ["_current", ""], ["_none", "", [""]]];
        (["choices", [_section, _codes, _labels]] call comspec_sse_fnc_catalogue) params ["_vals", "_labs", "_source"];
        private _sel = lbCurSel _ctrl;
        if (_sel >= 0) then { _current = _ctrl lbData _sel; };
        if !(_current isEqualType "") then { _current = str _current; };
        private _filter = _ctrl getVariable ["comspec_sse_catFilter", controlNull];
        private _query = if (isNull _filter) then { "" } else { toLower (trim (ctrlText _filter)) };
        // Filtre utile sur une liste longue seulement.
        if (!isNull _filter) then { _filter ctrlShow ((count _vals) > 8 || {_query isNotEqualTo ""}); };
        lbClear _ctrl;
        private _pick = -1;
        if (_none isNotEqualTo "") then {
            private _i = _ctrl lbAdd ((_none) call comspec_sse_fnc_tr);
            _ctrl lbSetData [_i, ""];
            if (_current isEqualTo "") then { _pick = _i; };
        };
        {
            private _label = _labs param [_forEachIndex, _x];
            if !(_label isEqualType "") then { _label = str _label; };
            if (_query isEqualTo "" || {((toLower _label) find _query) >= 0} || {((toLower _x) find _query) >= 0} || {_x isEqualTo _current}) then {
                // Libellés du mod : traduits ; libellés du site : déjà dans la langue du téléphone.
                private _i = _ctrl lbAdd (if (_source isEqualTo "local") then { (_label) call comspec_sse_fnc_tr } else { _label }); // i18n:brut
                _ctrl lbSetData [_i, _x];
                if (_x isEqualTo _current) then { _pick = _i; };
            };
        } forEach _vals;
        // Valeur déjà posée mais absente de la liste (dossier clos, saisie d'une autre partie) : gardée, jamais perdue.
        if (_pick < 0 && {_current isNotEqualTo ""}) then {
            _pick = _ctrl lbAdd ((format [("%1 (hors liste)") call comspec_sse_fnc_tr, _current]) call comspec_sse_fnc_tr);
            _ctrl lbSetData [_pick, _current];
        };
        if ((lbSize _ctrl) > 0) then { _ctrl lbSetCurSel (_pick max 0); };
    };
    case "filter": {
        ["fill", _arg getVariable ["comspec_sse_catCombo", controlNull]] call comspec_sse_fnc_uiFormCatalog;
    };
    case "open": {
        if !(uiNamespace getVariable ["comspec_sse_catFormEH", false]) then {
            uiNamespace setVariable ["comspec_sse_catFormEH", true];
            ["comspec_sse_catalogueUpdated", { ["updated"] call comspec_sse_fnc_uiFormCatalog; }] call CBA_fnc_addEventHandler;
        };
        [["ensure"] call comspec_sse_fnc_catalogue] call _status;
    };
    case "refresh": {
        [["refresh"] call comspec_sse_fnc_catalogue] call _status;
    };
    case "updated": {
        if (isNull _display) exitWith {};
        {
            _x params ["_type", "_ctrl"];
            if (_type isEqualTo "CATALOG") then { ["fill", _ctrl] call comspec_sse_fnc_uiFormCatalog; };
        } forEach ((uiNamespace getVariable ["COMSPEC_SSE_FormState", [[]]]) param [0, []]);
        [] call _status;
    };
};
