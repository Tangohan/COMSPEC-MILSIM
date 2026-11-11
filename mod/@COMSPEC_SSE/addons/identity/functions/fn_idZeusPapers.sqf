/*
    Papiers de l'IA, fenêtre du Zeus (dialogs/idZeusPapers.hpp) : le document tel qu'un joueur le voit (même rendu que la
    visionneuse, fn_idViewerOnLoad) et, à côté, l'analyse réservée au Zeus. Remplace l'ancien bandeau de texte de
    « Voir les vrais papiers » (module ZEN, menu « SSE · Roleplay de l'IA », page « IA de la zone »).
    [_op, _arg] call comspec_sse_fnc_idZeusPapers
      open [cible]     ouvre la fenêtre et demande les papiers au serveur (fn_idServer « truth » : Zeus vérifié là-bas,
                       réponse au seul poste demandeur, rien de diffusé ; JIP sans objet). Cible : personne (IA ou joueur),
                       ou objet / porte-objets au sol qui contient des papiers.
      data [données]   réponse du serveur (fn_idClient « zeusPapers », fn_idZeusPapersData)
      load display · unload · render · pick i (document choisi) · refresh · act n | give (actions du panneau)
      status [texte, couleur]   ligne d'état de la fenêtre (retour des actions) : jamais une alerte Arma
    Textes : stringtable de l'addon (STR_COMSPEC_SSE_ZP_*), français et anglais.
*/
params [["_op", "render", [""]], ["_arg", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _p = "STR_COMSPEC_SSE_ZP_";
private _L = { localize (_p + _this) };
private _esc = { private _t = _this; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// Phrase de l'analyse [clé, arguments] : un argument « STR_… » est traduit, une liste de clés devient « a, b ».
private _say = {
    params [["_k", ""], ["_args", []]];
    private _a = _args apply {
        if (_x isEqualType [] ) then { (_x apply { if (_x isEqualType "" && {(_x select [0, 4]) isEqualTo "STR_"}) then { localize _x } else { str _x } }) joinString ", " } else {
            if (_x isEqualType "" && {(_x select [0, 4]) isEqualTo "STR_"}) then { localize _x } else { _x }
        }
    };
    format ([localize _k] + _a)
};
private _col = createHashMapFromArray [["ok", "#7fd08a"], ["warn", "#f2c94c"], ["bad", "#e5675a"], ["muted", "#9fb0a8"], ["zeus", "#ffb84d"], ["text", "#e6ede6"]];

if (_op isEqualTo "open") exitWith {
    _arg params [["_t", objNull, [objNull]], ["_want", "", [""]]];
    if (isNull _t) exitWith { false };
    private _req = diag_tickTime;
    uiNamespace setVariable ["comspec_sse_idZeus", createHashMapFromArray [["target", _t], ["data", createHashMap], ["cur", 0], ["want", _want], ["status", ["Loading", "muted"]], ["req", _req]]];
    ["send", ["truth", [player, _t]]] call comspec_sse_fnc_idClient;
    // Pas de réponse (cible supprimée, serveur occupé) : dit dans la fenêtre, jamais en alerte.
    [{
        private _st = uiNamespace getVariable ["comspec_sse_idZeus", createHashMap];
        if ((_st getOrDefault ["req", -1]) isEqualTo _this && {(count (_st getOrDefault ["data", createHashMap])) isEqualTo 0}) then {
            ["status", ["NoReply", "warn"]] call comspec_sse_fnc_idZeusPapers;
        };
    }, _req, 8] call CBA_fnc_waitAndExecute;
    if (!isNil "comspec_atak_native_fnc_display" && {!isNull ([] call comspec_atak_native_fnc_display)}) then { [] call comspec_atak_native_fnc_close; };
    if (isNull (uiNamespace getVariable ["comspec_sse_idZeusDisplay", displayNull])) then {
        [{ createDialog "COMSPEC_SSE_IdZeusPapers"; }] call CBA_fnc_execNextFrame;
    } else { ["render"] call comspec_sse_fnc_idZeusPapers; };
    true
};

private _st = uiNamespace getVariable ["comspec_sse_idZeus", createHashMap];
if (_op isEqualTo "data") exitWith {
    private _data = _arg param [0, createHashMap];
    if (!(_data isEqualType createHashMap) || {(count _st) isEqualTo 0}) exitWith { false };
    // Réponse d'une demande plus ancienne (autre cible) : ignorée.
    if ((_data getOrDefault ["target", ""]) isNotEqualTo (netId (_st getOrDefault ["target", objNull]))) exitWith { false };
    _st set ["data", _data];
    if (((_st getOrDefault ["status", []]) param [0, ""]) in ["Loading", "NoReply", "Refreshing"]) then { _st set ["status", ["Updated", "ok", [_data getOrDefault ["at", ""]]]]; };
    private _n = count (_data getOrDefault ["docs", []]);
    // Ouverte sur un papier précis (page « IA de la zone ») : ce papier d'abord.
    private _wi = (_data getOrDefault ["docs", []]) findIf { (_x select 0) isEqualTo (_st getOrDefault ["want", ""]) };
    if (_wi >= 0) then { _st set ["cur", _wi]; _st set ["want", ""]; };
    if ((_st get "cur") >= _n) then { _st set ["cur", 0]; };
    ["render"] call comspec_sse_fnc_idZeusPapers;
    true
};
if (_op isEqualTo "unload") exitWith {
    uiNamespace setVariable ["comspec_sse_idZeusDisplay", displayNull];
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["comspec_sse_idViewerCtrls", []]);
    uiNamespace setVariable ["comspec_sse_idViewerCtrls", []];
    true
};
if (_op isEqualTo "load") then {
    uiNamespace setVariable ["comspec_sse_idZeusDisplay", _arg];
    (_arg) call comspec_sse_fnc_trDisplay;
    _op = "render";
};
private _d = uiNamespace getVariable ["comspec_sse_idZeusDisplay", displayNull];
if (isNull _d || {(count _st) isEqualTo 0}) exitWith { false };
private _c = { _d displayCtrl _this };
private _data = _st getOrDefault ["data", createHashMap];
private _docs = _data getOrDefault ["docs", []];
private _t = _st getOrDefault ["target", objNull];
private _man = _data getOrDefault ["man", !isNull _t && {_t isKindOf "CAManBase"}];
private _isAi = _man && {!(_data getOrDefault ["player", false])};
private _cur = _docs param [_st getOrDefault ["cur", 0], []];

// Police réduite jusqu'à ce que le texte tienne dans la largeur (même règle que l'éditeur SSE, fn_editor « _fit »).
private _fit = {
    params ["_ct"];
    if (isNull _ct || {!ctrlShown _ct}) exitWith {};
    private _base = _ct getVariable ["comspec_sse_fh0", -1];
    if (_base < 0) then { _base = ctrlFontHeight _ct; _ct setVariable ["comspec_sse_fh0", _base]; };
    _ct ctrlSetFontHeight _base;
    private _w = ((ctrlPosition _ct) select 2) * 0.94;
    private _n = 0;
    while { (ctrlTextWidth _ct) > _w && {_n < 6} } do { _ct ctrlSetFontHeight ((ctrlFontHeight _ct) * 0.9); _n = _n + 1; };
};
private _setStatus = {
    params [["_key", ""], ["_tone", "ok"], ["_args", []]];
    _st set ["status", [_key, _tone, _args]];
    private _s = 95123 call _c;
    _s ctrlSetStructuredText parseText (if (_key isEqualTo "") then { "" } else { format ["<t color='%1'>%2</t>", _col getOrDefault [_tone, "#e6ede6"], ([_p + _key, _args] call _say) call _esc] });
};

switch (_op) do {
    case "status": { _arg params [["_k", ""], ["_tone", "ok"], ["_a", []]]; [_k, _tone, _a] call _setStatus; };
    case "pick": {
        // Liste remplie par le rendu (lbClear, lbSetCurSel) : ces sélections-là ne comptent pas.
        private _i = [_arg, 0] select !(_arg isEqualType 0);
        if (_st getOrDefault ["busy", false] || {_i < 0} || {_i isEqualTo (_st getOrDefault ["cur", 0])}) exitWith {};
        _st set ["cur", _i];
        _st set ["side", "recto"];
        ["render"] call comspec_sse_fnc_idZeusPapers;
    };
    case "refresh": {
        if (isNull _t) exitWith { ["Gone", "warn"] call _setStatus; };
        private _req = diag_tickTime;
        _st set ["req", _req];
        ["Refreshing", "muted"] call _setStatus;
        ["send", ["truth", [player, _t]]] call comspec_sse_fnc_idClient;
    };
    case "act": {
        private _acts = _st getOrDefault ["acts", []];
        private _a = if (_arg isEqualType 0) then { _acts param [_arg, ""] } else { _arg };
        private _id = _cur param [0, ""];
        private _an = _cur param [2, createHashMap];
        private _later = { [{ if (!isNull (uiNamespace getVariable ["comspec_sse_idZeusDisplay", displayNull])) then { ["refresh"] call comspec_sse_fnc_idZeusPapers; }; }, [], _this] call CBA_fnc_waitAndExecute; };
        switch (_a) do {
            case "give": {
                // Sélecteur de document (fn_idDocPicker) par-dessus cette fenêtre ; relue quand il se ferme.
                private _pos = if (_man) then { [] } else { (getPosATL _t) select [0, 2] };
                ["open", [[objNull, _t] select _man, _pos]] call comspec_sse_fnc_idDocPicker;
                ["StPicker", "muted"] call _setStatus;
                [{ isNull (uiNamespace getVariable ["comspec_sse_docPickDisplay", displayNull]) && {isNull (findDisplay 93800)} }, {
                    if (!isNull (uiNamespace getVariable ["comspec_sse_idZeusDisplay", displayNull])) then { [{ ["refresh"] call comspec_sse_fnc_idZeusPapers; }, [], 1] call CBA_fnc_waitAndExecute; };
                }, [], 600] call CBA_fnc_waitUntilAndExecute;
            };
            case "true";
            case "forged": {
                if (_id isEqualTo "") exitWith {};
                ["send", ["aiCfg", [player, "docMark", [_t, _id, ["civil", "forged"] select (_a isEqualTo "forged")]]]] call comspec_sse_fnc_idClient;
                [["StMarkTrue", "StMarkForged"] select (_a isEqualTo "forged"), "ok"] call _setStatus;
                0.8 call _later;
            };
            case "report";
            case "unreport": {
                ["send", ["report", [player, _t, ["vole", ""] select (_a isEqualTo "unreport")]]] call comspec_sse_fnc_idClient;
                [["StReport", "StUnreport"] select (_a isEqualTo "unreport"), "ok"] call _setStatus;
                0.8 call _later;
            };
            case "strip": {
                if (isNil "comspec_sse_fnc_iarpClient") exitWith {};
                ["send", ["papersOff", [player, [_t]]]] call comspec_sse_fnc_iarpClient;
                ["StStrip", "ok"] call _setStatus;
                0.8 call _later;
            };
            case "sheet": {
                if (isNil "comspec_sse_fnc_aiConfigOpen") exitWith {};
                closeDialog 2;
                [{ [_this] call comspec_sse_fnc_aiConfigOpen; }, _t] call CBA_fnc_execNextFrame;
            };
        };
    };
    case "render": {
        _st set ["busy", true];
        private _grid = [] call comspec_sse_fnc_uiGrid;
        _grid params ["_gx", "_gy", "_gw", "_gh"];
        // --- En-tête : qui (nom donné, unité Arma en second) ---------------------------------------------------------
        private _who = if (isNull _t) then { "Gone" call _L } else {
            if (_man) then {
                private _given = _data getOrDefault ["given", ""];
                private _un = _data getOrDefault ["unitName", name _t];
                if (_given isEqualTo "" || {(toUpper _given) isEqualTo (toUpper _un)}) then { _un } else { format ["UnitAs" call _L, _given, _un] }
            } else { format ["%1 · %2", _data getOrDefault ["unitName", typeOf _t], mapGridPosition _t] }
        };
        (95102 call _c) ctrlSetText _who;
        { [_x call _c] call _fit; } forEach [95101, 95102, 95103, 95104, 95110, 95120];

        // --- Liste des documents ---------------------------------------------------------------------------------
        private _cb = 95111 call _c;
        lbClear _cb;
        {
            _x params ["_id", "_pub", "_an", "_more"];
            private _lbl = ["label", [_pub getOrDefault ["type", ""], _pub getOrDefault ["country", ""]]] call comspec_sse_fnc_idDocTypes;
            if ((count _lbl) > 20) then { _lbl = (_lbl select [0, 19]) + "…"; };
            private _row = format ["DocItem" call _L, _lbl, _pub getOrDefault ["number", ""]];
            if (_more getOrDefault ["foreign", false]) then { _row = _row + " · " + ("Foreign" call _L); };
            _cb lbAdd _row;
        } forEach _docs;
        _cb ctrlShow (_docs isNotEqualTo []);
        (95110 call _c) ctrlShow (_docs isNotEqualTo []);
        if (_docs isNotEqualTo []) then { _cb lbSetCurSel (_st getOrDefault ["cur", 0]); };

        // --- Document : rendu de la visionneuse dans la zone de gauche -------------------------------------------
        private _loaded = (count _data) > 0;
        private _hasDoc = _cur isNotEqualTo [];
        private _view = uiNamespace getVariable ["comspec_sse_idView", createHashMap];
        private _prevSide = if ((_view getOrDefault ["zeusDoc", ""]) isEqualTo (_cur param [0, "-"])) then { _view getOrDefault ["side", "recto"] } else { "recto" };
        { (_x call _c) ctrlShow _hasDoc; } forEach [93720, 93704];
        if (_hasDoc) then {
            uiNamespace setVariable ["comspec_sse_idView", createHashMapFromArray [
                ["doc", +(_cur select 1)], ["own", false], ["side", _prevSide], ["zoom", false], ["auto", false], ["shot", true], ["subj", objNull],
                ["status", ""], ["n0", createHashMap], ["zeus", true], ["zeusDoc", _cur select 0], ["vh", 25 * _gh],
                ["box", [_gx + 0.8 * _gw, _gy + 5.8 * _gh, 23.4 * _gw, 15.6 * _gh]]
            ]];
            uiNamespace setVariable ["comspec_sse_idViewerDisplay", _d];
            ["render"] call comspec_sse_fnc_idViewerOnLoad;
        } else {
            ['stop'] call comspec_sse_fnc_idFace;
            { ctrlDelete _x; } forEach (uiNamespace getVariable ["comspec_sse_idViewerCtrls", []]);
            uiNamespace setVariable ["comspec_sse_idViewerCtrls", []];
            (93710 call _c) ctrlShow false;
            (93720 call _c) ctrlSetText "";
        };
        // Pas de papiers : explication à la place du document, et le bouton pour en créer.
        private _empty = 95112 call _c;
        private _emptyKey = switch (true) do {
            case (isNull _t): { "Gone" };
            case (!_loaded): { "Loading" };
            case (_hasDoc): { "" };
            case (!_man): { "NoPapersObj" };
            case (_isAi): { "NoPapersAi" };
            default { "NoPapersPerson" };
        };
        _empty ctrlShow (_emptyKey isNotEqualTo "");
        if (_emptyKey isNotEqualTo "") then { _empty ctrlSetStructuredText parseText format ["<t align='center' size='1.15' color='%1'>%2</t>", _col get "text", (_emptyKey call _L) call _esc]; };
        (95113 call _c) ctrlShow (_loaded && {!_hasDoc} && {!isNull _t});
        [95113 call _c] call _fit;

        // --- Analyse du Zeus --------------------------------------------------------------------------------------
        private _h = { format ["<br/><t font='RobotoCondensedBold' color='%1'>%2</t><br/>", _col get "zeus", (_this call _L) call _esc] };
        private _row = { params ["_k", "_v", ["_tone", "text"]]; format ["<t color='%1'>%2 : </t><t color='%3'>%4</t><br/>", _col get "muted", (_k call _L) call _esc, _col getOrDefault [_tone, _col get "text"], _v call _esc] };
        private _dmy = { private _q = _this splitString "-"; if ((count _q) < 3) exitWith { _this }; format ["%1.%2.%3", _q select 2, _q select 1, _q select 0] };
        private _txt = "";
        if (_hasDoc) then {
            _cur params ["_id", "_pub", "_an", "_more"];
            _txt = _txt + format ["<t size='1.15' color='%1'>%2</t><br/>", _col getOrDefault [_an getOrDefault ["state", "ok"], _col get "text"], ((_an getOrDefault ["verdict", ["", []]]) call _say) call _esc];
            if (_more getOrDefault ["foreign", false]) then { _txt = _txt + format ["<t color='%1'>%2</t><br/>", _col get "warn", (format ["ForeignOf" call _L, _more getOrDefault ["owner", "?"]]) call _esc]; };
            (_an getOrDefault ["names", ["", "", "", "", false]]) params ["_pl", "_pf", "_tl", "_tf", "_diff"];
            _txt = _txt + ("HIdentity" call _h);
            _txt = _txt + (["Printed", format ["%1 %2", toUpper _pl, _pf]] call _row);
            _txt = _txt + (["True", format ["%1 %2 (%3)", toUpper _tl, _tf, (["Same", "Differs"] select _diff) call _L], ["ok", "bad"] select _diff] call _row);
            private _kindTxt = localize (_an getOrDefault ["kindKey", _p + "Kind_civil"]);
            if ((_an getOrDefault ["forgeryKey", ""]) isNotEqualTo "") then { _kindTxt = _kindTxt + " · " + localize (_an get "forgeryKey"); };
            _txt = _txt + (["Kind", _kindTxt, ["ok", "warn"] select ((_an getOrDefault ["kind", "civil"]) in ["forged", "cover", "alias"])] call _row);
            _txt = _txt + (["Type", format ["%1 · %2 · %3", ["label", [_pub getOrDefault ["type", ""], _pub getOrDefault ["country", ""]]] call comspec_sse_fnc_idDocTypes, _pub getOrDefault ["number", ""], _pub getOrDefault ["countryName", _pub getOrDefault ["country", ""]]]] call _row);
            _txt = _txt + (["Born", format ["%1 · %2", (_pub getOrDefault ["dob", ""]) call _dmy, _pub getOrDefault ["pob", ""]]] call _row);
            _txt = _txt + (["Issued", (_an getOrDefault ["issued", ""]) call _dmy] call _row);
            _txt = _txt + (["Expires", format ["%1%2", (_an getOrDefault ["expires", ""]) call _dmy, ["", " (" + ("Expired" call _L) + ")"] select (_an getOrDefault ["expired", false])], ["text", "warn"] select (_an getOrDefault ["expired", false])] call _row);
            _txt = _txt + ("HQuality" call _h);
            { _txt = _txt + ((_x call _say) call _esc) + "<br/>"; } forEach (_an getOrDefault ["qualityLines", []]);
            _txt = _txt + ("HFlaws" call _h);
            private _fl = _an getOrDefault ["flaws", []];
            if (_fl isEqualTo []) then { _txt = _txt + format ["<t color='%1'>%2</t><br/>", _col get "ok", ("NoFlaw" call _L) call _esc]; };
            { _x params ["", "_tk", "_dk"]; _txt = _txt + format ["<t color='%1'>• %2</t> : %3<br/>", _col get "warn", (_tk call _say) call _esc, (_dk call _say) call _esc]; } forEach _fl;
            _txt = _txt + ("HChecks" call _h);
            { _x params ["_k", "_a", "_tone"]; _txt = _txt + format ["<t color='%1'>• %2</t><br/>", _col getOrDefault [_tone, _col get "text"], ([_k, _a] call _say) call _esc]; } forEach (_an getOrDefault ["checks", []]);
            private _bolo = _more getOrDefault ["bolo", []];
            if (_bolo isNotEqualTo []) then { _txt = _txt + format ["<t color='%1'>• %2</t><br/>", _col get "bad", (format ["BoloDoc" call _L, _bolo select 0, _bolo param [1, ""]]) call _esc]; };
            _txt = _txt + format ["<t color='%1'>• %2</t><br/>", _col get "muted", ((["Virtual", "Object"] select ((_more getOrDefault ["cls", ""]) isNotEqualTo "")) call _L) call _esc];
        };
        if (_man && {_loaded}) then {
            _txt = _txt + ("HPerson" call _h);
            if ((_data getOrDefault ["given", ""]) isNotEqualTo "") then { _txt = _txt + (["Given", _data get "given"] call _row); };
            if ((_data getOrDefault ["true", ""]) isNotEqualTo "") then { _txt = _txt + (["TrueName", _data get "true", ["ok", "bad"] select ((toUpper (_data getOrDefault ["given", ""])) isNotEqualTo (toUpper (_data get "true")) && {(_data getOrDefault ["given", ""]) isNotEqualTo ""})] call _row); };
            _txt = _txt + (["Unit", _data getOrDefault ["unitName", ""]] call _row);
            private _cv = _data getOrDefault ["cover", []];
            if (_cv isNotEqualTo []) then {
                _cv params ["_state", "_why", "_sc", "_reasons", "_susp"];
                private _stt = switch (_state) do { case "burnt": { format ["CoverBurnt" call _L, _why call comspec_sse_fnc_tr] }; case "on": { "CoverOn" call _L }; default { "CoverOff" call _L }; };
                private _rs = if (_reasons isEqualTo []) then { "" } else { " (" + ((_reasons apply { _x call comspec_sse_fnc_tr }) joinString ", ") + ")" };
                _txt = _txt + format ["<t color='%1'>• %2</t><br/>", _col get (["warn", "bad"] select (_state isEqualTo "burnt")), (format ["Cover" call _L, _stt, _sc, _rs, _susp]) call _esc];
            };
            private _hv = _data getOrDefault ["hvt", []];
            if (_hv isNotEqualTo []) then { _txt = _txt + format ["<t color='%1'>• %2</t><br/>", _col get "bad", (format ["Hvt" call _L, _hv select 0, format ["%1 %2", toUpper (_hv select 1), _hv select 2], _hv select 3]) call _esc]; };
            private _bf = _data getOrDefault ["boloFace", []];
            if (_bf isNotEqualTo []) then { _txt = _txt + format ["<t color='%1'>• %2</t><br/>", _col get "bad", (format ["BoloFace" call _L, _bf select 0, _bf param [1, ""]]) call _esc]; };
        };
        private _grp = 95121 call _c;
        private _body = _d displayCtrl 95122;
        _body ctrlSetStructuredText parseText _txt;
        private _gp = ctrlPosition _grp;
        _body ctrlSetPosition [0, 0, 13.4 * _gw, (ctrlTextHeight _body) max (_gp select 3)];
        _body ctrlCommit 0;

        // --- Actions (celles qui existent déjà : sélecteur, vrai / faux, signalement, retrait, fiche de l'IA) --------
        private _acts = [];
        if (!isNull _t && {_loaded}) then {
            if (_hasDoc || {!_man}) then { _acts pushBack "give"; };
            if (_hasDoc && {_man} && {!((_cur select 3) getOrDefault ["foreign", false])}) then {
                _acts pushBack (["forged", "true"] select (((_cur select 2) getOrDefault ["kind", "civil"]) isEqualTo "forged"));
            };
            if (_hasDoc && {_man}) then { _acts pushBack (["report", "unreport"] select (((_cur select 2) getOrDefault ["report", ""]) isNotEqualTo "")); };
            if (_isAi && {_hasDoc} && {!isNil "comspec_sse_fnc_iarpClient"}) then { _acts pushBack "strip"; };
            if (_isAi && {!isNil "comspec_sse_fnc_aiConfigOpen"}) then { _acts pushBack "sheet"; };
        };
        _st set ["acts", _acts];
        private _names = createHashMapFromArray [["give", "BtnOther"], ["true", "BtnTrue"], ["forged", "BtnForged"], ["report", "BtnReport"], ["unreport", "BtnUnreport"], ["strip", "BtnStrip"], ["sheet", "BtnSheet"]];
        for "_i" from 0 to 5 do {
            private _b = (95130 + _i) call _c;
            private _a = _acts param [_i, ""];
            _b ctrlShow (_a isNotEqualTo "");
            if (_a isNotEqualTo "") then {
                _b ctrlSetText ((_names get _a) call _L);
                _b ctrlSetTooltip (((_names get _a) + "_D") call _L);
                [_b] call _fit;
            };
        };
        (_st getOrDefault ["status", []]) params [["_sk", ""], ["_tone", "ok"], ["_sa", []]];
        [_sk, _tone, _sa] call _setStatus;
        _st set ["busy", false];
    };
};
true
