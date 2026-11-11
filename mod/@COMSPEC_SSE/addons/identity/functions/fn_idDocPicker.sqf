/*
    Sélecteur de document (dialogs/docPicker.hpp) : choisir un document précis (pays, type — papiers intégrés et
    extensions actives — avec aperçu) pour le donner à une personne (IA ou joueur) ou le poser au sol.
    Zeus : module ZEN « COMSPEC · Identité › Donner des papiers » (fn_idZenRegister) ; le serveur délivre (fn_idServer give).
    Eden : menu COMSPEC SSE › Donner un document : écrit les attributs des unités sélectionnées (COMSPEC_ID_Carry = clé
           du type, COMSPEC_ID_Country, COMSPEC_ID_Kind, COMSPEC_ID_Quality) ; le document est délivré à l'apparition.
    ["open", [personne ou objNull, position [x, y] ou []]]   ouvre la fenêtre
    ["load", display] · ["select"] · ["give"] · ["unload"]  (fenêtre)
*/
params [["_op", "open", [""]], ["_a", [], [[], displayNull]]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _tr = { _this call comspec_sse_fnc_tr };
private _esc = { private _t = _this; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };

if (_op isEqualTo "open") exitWith {
    _a params [["_u", objNull, [objNull]], ["_pos", [], [[]]]];
    if (isNull _u && {_pos isEqualTo []} && {!is3DEN}) exitWith { ["Placez le module sur une personne, ou cliquez un endroit du monde pour poser un document au sol.", "warn"] call comspec_sse_fnc_zeusNotify; false };
    uiNamespace setVariable ["comspec_sse_docPick", createHashMapFromArray [["unit", _u], ["pos", _pos], ["type", ""], ["country", "auto"], ["ctrls", []]]];
    if (is3DEN) then { (findDisplay 313) createDisplay "COMSPEC_SSE_DocPicker"; } else { createDialog "COMSPEC_SSE_DocPicker"; };
    true
};
private _st = uiNamespace getVariable ["comspec_sse_docPick", createHashMap];
if (_op isEqualTo "unload") exitWith { uiNamespace setVariable ["comspec_sse_docPickDisplay", displayNull]; true };
if (_op isEqualTo "load") then { uiNamespace setVariable ["comspec_sse_docPickDisplay", _a]; };
private _d = uiNamespace getVariable ["comspec_sse_docPickDisplay", displayNull];
if (isNull _d || {(count _st) isEqualTo 0}) exitWith { false };
private _c = { _d displayCtrl _this };
private _status = { params ["_t", ["_col", "#cfe8cf"]]; (93862 call _c) ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _col, (_t call _tr) call _esc]; };
private _lbData = { private _l = _this call _c; private _i = lbCurSel _l; if (_i < 0) then { "" } else { _l lbData _i } };
private _u = _st get "unit";
private _pos = _st get "pos";

// --- Aperçu : gabarit du type, silhouette dans le cadre photo, champs d'exemple (mêmes coordonnées que la visionneuse) ---
private _preview = {
    { ctrlDelete _x; } forEach (_st getOrDefault ["ctrls", []]);
    private _ctrls = [];
    private _type = _st get "type";
    private _t = ["get", _type] call comspec_sse_fnc_idDocTypes;
    private _pic = 93820 call _c;
    (ctrlPosition (93821 call _c)) params ["_bx", "_by", "_bw", "_bh"];
    if ((count _t) isEqualTo 0) exitWith { _pic ctrlSetText ""; _pic ctrlCommit 0; (93822 call _c) ctrlSetStructuredText parseText ""; _st set ["ctrls", _ctrls]; };
    private _country = _st getOrDefault ["country", "auto"];
    private _lc = [_country, "alt"] select (_country isEqualTo "auto");
    private _zone = "";
    private _cinfo = ([] call comspec_sse_fnc_idCountries) getOrDefault [toUpper _lc, createHashMap];
    if (_cinfo getOrDefault ["realSim", false]) then { _zone = _cinfo getOrDefault ["zone", ""]; };
    if ((_cinfo getOrDefault ["tier", ""]) in ["full", "generic"]) then { _zone = "bare"; };
    private _tex = ["texture", [_type, "recto", _lc, _zone]] call comspec_sse_fnc_idDocTypes;
    // Document au format réel, centré dans le cadre de l'aperçu (1 x = 4/3 d'un y).
    private _ratio = _t get "ratio";
    private _ch = _bh * 0.92;
    private _cw = _ch * _ratio * 0.75;
    if (_cw > _bw * 0.94) then { _cw = _bw * 0.94; _ch = _cw / (_ratio * 0.75); };
    private _cx = _bx + (_bw - _cw) / 2;
    private _cy = _by + (_bh - _ch) / 2;
    _pic ctrlSetText _tex;
    _pic ctrlSetPosition [_cx, _cy, _cw, _ch];
    _pic ctrlCommit 0;
    private _layout = ["side", [_type, "recto"]] call comspec_sse_fnc_idDocTypes;
    private _mk = {
        params ["_cls", "_fx", "_fy", "_fw", "_fh"];
        private _k = _d ctrlCreate [_cls, -1];
        _k ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch];
        _k ctrlCommit 0;
        _ctrls pushBack _k;
        _k
    };
    private _photo = _layout getOrDefault ["photo", []];
    if (_photo isNotEqualTo []) then {
        _photo params ["_fx", "_fy", "_fw", "_fh"];
        (["RscPicture", _fx, _fy, _fw, _fh] call _mk) ctrlSetText "\z\comspec_sse\addons\identity\data\silhouette_co.paa";
    };
    private _cfgSize = configFile >> "RscStructuredText" >> "size";
    private _base = if (isNumber _cfgSize) then { getNumber _cfgSize } else { call compile (getText _cfgSize) };
    if (isNil "_base" || {!(_base isEqualType 0)} || {_base <= 0}) then { _base = 0.04; };
    private _last = ctrlText (93834 call _c);
    private _first = ctrlText (93835 call _c);
    private _sample = createHashMapFromArray [["last", [_last, "NOM"] select (_last isEqualTo "")], ["first", [_first, "Prénom"] select (_first isEqualTo "")],
        ["sex", "M"], ["nat", toUpper _lc], ["dob", "01.01.1990"], ["pob", _cinfo getOrDefault ["cities", ["-"]] select 0], ["number", "SPÉCIMEN"], ["expires", "31.12.2035"],
        ["issued", "01.01.2025"], ["authority", _cinfo getOrDefault ["authority", ""]], ["address", "1 RUE DU PORT"], ["profession", "—"], ["rank", "Sergent"], ["force", "Forces armées"],
        ["country", toUpper _lc], ["countryName", _cinfo getOrDefault ["name", ""]], ["natText", _cinfo getOrDefault ["natText", ""]],
        ["height", ["", "178 cm"] select (_zone isEqualTo "bare")], ["permit", "Résident · Resident"], ["plate", "AB-123-CD"], ["vehicle", "—"],
        ["vin", "SSE00000000000000"], ["holder", "NOM Prénom"], ["firstReg", "01.01.2020"]];
    if ((_sample get "natText") isNotEqualTo "") then { _sample set ["nat", _sample get "natText"]; };
    // Pays réel : bandeau, titre et étiquettes dans la langue du pays, comme sur le papier délivré.
    _ctrls append ([_d, [_cx, _cy, _cw, _ch], _sample, _type, "recto", _layout, _tex] call comspec_sse_fnc_idCountryChrome);
    {
        private _f = _x;
        private _v = _f get "text";
        if (_v isEqualTo "") then { _v = _sample getOrDefault [toLower (_f get "key"), _f get "default"]; };
        if ((_f get "input") && {(_f get "default") isNotEqualTo ""}) then { _v = _f get "default"; };
        if (_v isEqualTo "" && {(_f get "input")}) then { _v = _f get "label"; };
        if ((_f get "transform") isEqualTo "upper") then { _v = toUpper _v; };
        if (_v isNotEqualTo "") then {
            private _k = ["RscStructuredText", _f get "x", _f get "y", _f get "w", (_f get "h") * 1.5] call _mk;
            _k ctrlSetBackgroundColor [0, 0, 0, 0];
            _k ctrlSetStructuredText parseText format ["<t size='%1' font='%2' color='%3' shadow='0'>%4</t>", ((_f get "h") * _ch) / _base, ["RobotoCondensed", "RobotoCondensedBold"] select ((_f get "font") isEqualTo "name"), _f get "color", _v call _esc];
            _k ctrlCommit 0;
        };
    } forEach (_layout getOrDefault ["fields", []]);
    _st set ["ctrls", _ctrls];
    // Renseignements du type.
    private _ext = "";
    if ((_t get "extension") isNotEqualTo "") then {
        private _e = (["extensions"] call comspec_sse_fnc_idDocTypes) select { (_x select 0) isEqualTo (_t get "extension") };
        _ext = if (_e isEqualTo []) then { _t get "extension" } else { format ["%1 (%2, v%3)", (_e select 0) select 1, (_e select 0) select 2, (_e select 0) select 3] };
    };
    private _parts = [
        format ["%1 · %2", ["label", _type] call comspec_sse_fnc_idDocTypes, [("Recto seul" call _tr), ("Recto et verso" call _tr)] select ((_t get "sides") > 1)],
        [("Sans MRZ" call _tr), format [("Bande MRZ %1" call _tr), toUpper (_t get "mrz")]] select ((_t get "mrz") isNotEqualTo ""),
        [("Sans photo" call _tr), ("Photo" call _tr)] select (_photo isNotEqualTo []),
        [("Tous pays" call _tr), (_t get "countries") joinString ", "] select ((_t get "countries") isNotEqualTo [])
    ];
    if (_ext isNotEqualTo "") then { _parts pushBack format [("Extension : %1" call _tr), _ext]; };
    (93822 call _c) ctrlSetStructuredText parseText format ["<t color='#c9d3dc'>%1</t>", (_parts joinString " · ") call _esc];
    // Champs propres au document.
    private _inputs = _t getOrDefault ["inputs", []];
    {
        private _in = _inputs param [_forEachIndex, []];
        private _e = (93840 + _forEachIndex) call _c;
        private _l = (93845 + _forEachIndex) call _c;
        _e ctrlShow (_in isNotEqualTo []);
        _l ctrlShow (_in isNotEqualTo []);
        if (_in isNotEqualTo []) then {
            _l ctrlSetText ((_in select 1) call _tr);
            if ((_e getVariable ["key", ""]) isNotEqualTo (_in select 0)) then { _e ctrlSetText (_in select 2); _e setVariable ["key", _in select 0]; };
        };
    } forEach [0, 1, 2];
};

// --- Listes -----------------------------------------------------------------------------------------------------
private _fillTypes = {
    private _l = 93811 call _c;
    private _keep = _st get "type";
    lbClear _l;
    private _country = _st getOrDefault ["country", "auto"];
    private _keys = ["list", [[_country, ""] select (_country isEqualTo "auto")]] call comspec_sse_fnc_idDocTypes;
    private _cats = createHashMapFromArray [["identity", "Identité"], ["travel", "Voyage"], ["permit", "Permis"], ["professional", "Professionnel"], ["pass", "Laissez-passer"], ["other", "Autres"]];
    private _lastCat = "";
    private _sel = -1;
    {
        private _t = ["get", _x] call comspec_sse_fnc_idDocTypes;
        private _cat = _t get "category";
        if (_cat isNotEqualTo _lastCat) then {
            private _i = _l lbAdd (toUpper ((_cats getOrDefault [_cat, _cat]) call _tr));
            _l lbSetData [_i, ""];
            _l lbSetColor [_i, [0.55, 0.6, 0.65, 1]];
            _lastCat = _cat;
        };
        private _name = ["label", [_x, [_country, ""] select (_country isEqualTo "auto")]] call comspec_sse_fnc_idDocTypes;
        if ((_t get "extension") isNotEqualTo "") then {
            private _e = (["extensions"] call comspec_sse_fnc_idDocTypes) select { (_x select 0) isEqualTo (_t get "extension") };
            if (_e isNotEqualTo []) then { _name = format ["%1 (%2)", _name, (_e select 0) select 1]; };
        };
        private _i = _l lbAdd ("    " + _name);
        _l lbSetData [_i, _x];
        if ((_t get "icon") isNotEqualTo "") then { _l lbSetPicture [_i, _t get "icon"]; };
        if (_x isEqualTo _keep || {_sel < 0}) then { _sel = _i; };
    } forEach _keys;
    if (_sel >= 0) then { _l lbSetCurSel _sel; };
    _st set ["type", 93811 call _lbData];
};

if (_op isEqualTo "load") exitWith {
    // Titre et cible.
    private _target = switch (true) do {
        case (is3DEN): { private _n = count (get3DENSelected "object"); format [("Eden : %1 unité(s) sélectionnée(s)" call _tr), _n] };
        case (!isNull _u): { format [("Cible : %1" call _tr), name _u] };
        case (_pos isNotEqualTo []): { format [("Au sol en %1" call _tr), mapGridPosition _pos] };
        default { "" };
    };
    (93802 call _c) ctrlSetText _target;
    // Pays : ceux du jeu (réglage, paquet), pays de la mission en premier.
    private _lc = 93810 call _c;
    // Pays réels d'abord (registre, dans l'ordre), puis un séparateur et les pays fictifs d'Arma, puis ceux du paquet Athena.
    private _all = [] call comspec_sse_fnc_idCountries;
    private _ordered = ["ordered"] call comspec_sse_fnc_idCountries;
    private _realC = _ordered select { !((_all get _x) getOrDefault ["fictional", true]) && {(_all get _x) getOrDefault ["active", true]} };
    private _fictC = (_ordered - _realC) select { (_all get _x) getOrDefault ["active", true] };
    _fictC = _fictC + (((keys _all) - _ordered) select { (_all get _x) getOrDefault ["active", true] });
    private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isEqualTo "fr";
    private _i = _lc lbAdd ("Pays de la mission" call _tr);
    _lc lbSetData [_i, "auto"];
    {
        private _i = _lc lbAdd (["label", [_x, ["en", "fr"] select _fr]] call comspec_sse_fnc_idCountries);
        _lc lbSetData [_i, _x];
    } forEach _realC;
    if (_fictC isNotEqualTo []) then {
        private _i = _lc lbAdd ("— Pays fictifs (cartes Arma) —" call _tr);
        _lc lbSetData [_i, ""];
        _lc lbSetColor [_i, [0.55, 0.6, 0.65, 1]];
    };
    {
        private _i = _lc lbAdd (["label", [_x, ["en", "fr"] select _fr]] call comspec_sse_fnc_idCountries);
        _lc lbSetData [_i, _x];
    } forEach _fictC;
    _lc lbSetCurSel 0;
    _lc ctrlAddEventHandler ["LBSelChanged", { ["select", "country"] call comspec_sse_fnc_idDocPicker; }];
    (93811 call _c) ctrlAddEventHandler ["LBSelChanged", { ["select", "type"] call comspec_sse_fnc_idDocPicker; }];
    // Nature, procédé, qualité.
    private _fill = { params ["_idc", "_rows"]; private _k = _idc call _c; lbClear _k; { private _i = _k lbAdd ((_x select 1) call _tr); _k lbSetData [_i, _x select 0]; } forEach _rows; _k lbSetCurSel 0; };
    [93830, [["civil", "Vrais papiers"], ["cover", "Couverture préparée (passe tout sauf la biométrie)"], ["forged", "Faux papiers"]]] call _fill;
    [93831, [["", "Au hasard (selon la qualité)"], ["counterfeit", "Contrefaçon complète"], ["altered", "Vrai document modifié"], ["photo_swap", "Photo substituée"], ["stolen_blank", "Vierge volé"]]] call _fill;
    private _sl = 93832 call _c;
    _sl sliderSetRange [0, 100];
    _sl sliderSetSpeed [5, 10];
    _sl sliderSetPosition 50;
    _sl ctrlAddEventHandler ["SliderPosChanged", { params ["_s", "_v"]; ((ctrlParent _s) displayCtrl 93833) ctrlSetText format [("Qualité du faux : %1" call comspec_sse_fnc_tr), round _v]; }];
    { (_x call _c) ctrlAddEventHandler ["KeyUp", { ["select", "name"] call comspec_sse_fnc_idDocPicker; }]; } forEach [93834, 93835];
    // Poser au sol : seulement avec une position (Zeus), jamais dans Eden.
    private _ground = 93851 call _c;
    _ground cbSetChecked (isNull _u && {_pos isNotEqualTo []});
    _ground ctrlEnable (_pos isNotEqualTo [] && {!is3DEN});
    (93852 call _c) ctrlEnable (_pos isNotEqualTo [] && {!is3DEN});
    (93860 call _c) ctrlSetText ((["DONNER", "APPLIQUER AUX UNITÉS"] select is3DEN) call _tr);
    [["Choisissez un pays et un type ; l'aperçu montre le recto.", "Eden : les choix sont écrits dans les attributs « Papiers » des unités sélectionnées."] select is3DEN] call _status;
    call _fillTypes;
    call _preview;
    // Bouton « ? » du manuel.
    if (!isNil "comspec_sse_fnc_guideButton") then {
        (ctrlPosition (93802 call _c)) params ["_tx", "_ty", "_tw", "_th"];
        [_d, "Documents", _tx + _tw - safezoneH * 0.04, _ty + safezoneH * 0.008, safezoneH * 0.034] call comspec_sse_fnc_guideButton;
    };
    true
};

if (_op isEqualTo "select") exitWith {
    switch (_a) do {
        case "country": {
            // Séparateur des pays fictifs : on prend le pays qui suit.
            if ((93810 call _lbData) isEqualTo "") then { private _l = 93810 call _c; private _n = (lbCurSel _l) + 1; if (_n < lbSize _l) then { _l lbSetCurSel _n; }; };
            _st set ["country", [93810 call _lbData, "auto"] select ((93810 call _lbData) isEqualTo "")];
            call _fillTypes;
        };
        case "type": { _st set ["type", 93811 call _lbData]; };
    };
    if ((_st get "type") isEqualTo "") then {
        // Ligne de catégorie cliquée : on prend le type qui suit.
        private _l = 93811 call _c;
        private _i = (lbCurSel _l) + 1;
        if (_i < lbSize _l) then { _l lbSetCurSel _i; _st set ["type", _l lbData _i]; };
    };
    call _preview;
    true
};

if (_op isEqualTo "give") exitWith {
    private _type = _st get "type";
    if (_type isEqualTo "") exitWith { ["Choisissez un type de document.", "#f2c94c"] call _status; false };
    private _t = ["get", _type] call comspec_sse_fnc_idDocTypes;
    private _country = _st getOrDefault ["country", "auto"];
    private _kind = 93830 call _lbData;
    private _fk = 93831 call _lbData;
    private _q = round sliderPosition (93832 call _c);
    private _last = trim ctrlText (93834 call _c);
    private _first = trim ctrlText (93835 call _c);
    private _ath = trim ctrlText (93836 call _c);
    private _spec = createHashMapFromArray [["type", _type], ["country", _country], ["kind", _kind], ["forgeryKind", _fk], ["quality", _q], ["last", _last], ["first", _first], ["athena", _ath]];
    { _x params ["_k"]; private _e = (93840 + _forEachIndex) call _c; if (_forEachIndex < 3) then { _spec set [_k, trim ctrlText _e]; }; } forEach (_t getOrDefault ["inputs", []]);
    if (is3DEN) exitWith {
        private _sel = get3DENSelected "object";
        if (_sel isEqualTo []) exitWith { ["Sélectionnez au moins une unité dans Eden.", "#f2c94c"] call _status; false };
        {
            _x set3DENAttribute ["COMSPEC_ID_Carry", _type];
            _x set3DENAttribute ["COMSPEC_ID_Country", _country];
            _x set3DENAttribute ["COMSPEC_ID_Kind", _kind];
            _x set3DENAttribute ["COMSPEC_ID_Quality", _q];
        } forEach _sel;
        [format ["Attributs « Papiers » écrits sur %1 unité(s) : %2 (%3). Le document est délivré à l'apparition.", count _sel, ["label", _type] call comspec_sse_fnc_idDocTypes, _kind]] call _status;
        true
    };
    private _ground = cbChecked (93851 call _c) && {_pos isNotEqualTo []};
    if (isNull _u && {!_ground}) exitWith { ["Aucune personne visée : cochez « Poser au sol ».", "#f2c94c"] call _status; false };
    ["send", ["give", [player, [_u, _pos select [0, 2]] select _ground, _spec]]] call comspec_sse_fnc_idClient;
    [format [["Demande envoyée au serveur : %1 pour %2.", "Demande envoyée au serveur : %1 posé au sol en %2."] select _ground, ["label", _type] call comspec_sse_fnc_idDocTypes, [[name _u, "?"] select (isNull _u), mapGridPosition (_pos select [0, 2])] select _ground]] call _status;
    true
};
false
