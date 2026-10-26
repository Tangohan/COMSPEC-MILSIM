/*
    App CR fin d'op (fn_debrief) : la fiche imposée par le commandement et, pour qui peut l'imposer, le panneau IMPOSER.
      MES CR   : la fiche en cours (à remplir : questions ; à lire : texte puis LU ET COMPRIS), puis les CR déjà remis ;
      IMPOSER  : mode (à remplir / à lire), joueurs visés (tous, mon camp, mon groupe), titre et texte, puis le suivi de
                 chaque demande de la partie : un joueur par ligne, de la couleur de son état (envoyé, reçu, ouvert, remis).
    Params : [page, [x, y, largeur, hauteur]]
*/
disableSerialization;
params ["", ["_rect", []]];
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_DbfUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_DbfUi", _ui];
private _all = missionNamespace getVariable ["COMSPEC_ATAK_Dbf", createHashMap];
private _may = ["may"] call comspec_atak_native_fnc_debrief;
private _tab = [_ui getOrDefault ["tab", "CR"], "CR"] select !_may;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_k", ""], ["_v", ""]]; compile format ["[%1, %2, %3] call comspec_atak_native_fnc_debrief;", str _a, str _k, str _v] };
private _icon = "\z\comspec_atak_native\addons\main\data\app_reports.paa";
private _pend = (values _all) select { (_x get "state") isNotEqualTo "done" };
private _done = (values _all) select { (_x get "state") isEqualTo "done" };
private _rows = [];
if (_may) then {
    _rows pushBack ["segment", "", [
        [[format ["MES CR (%1)", count _pend], "MES CR"] select ((count _pend) isEqualTo 0), ["tab", "CR"] call _act, _tab isEqualTo "CR"],
        ["IMPOSER", ["tab", "STAFF"] call _act, _tab isEqualTo "STAFF"]
    ]];
};

if (_tab isEqualTo "CR") then {
    private _id = _ui getOrDefault ["open", ""];
    private _e = _all getOrDefault [_id, createHashMap];
    if ((count _e) isEqualTo 0 && {(count _pend) > 0}) then { _e = _pend select ((count _pend) - 1); _ui set ["open", _e get "id"]; };
    if ((count _e) isEqualTo 0) exitWith {
        _rows pushBack ["empty", _icon, "Aucun CR demandé", "Quand le commandement impose le compte rendu de fin d'opération, il s'ouvre ici."];
    };
    ["opened", _e get "id"] call comspec_atak_native_fnc_debrief;
    private _read = (_e get "mode") isEqualTo "read";
    private _isDone = (_e get "state") isEqualTo "done";
    _rows pushBack ["strip", format ["<t font='COMSPEC_BarlowBold' color='#e8c547'>CR FIN D'OP</t>  <t font='COMSPEC_BarlowBold' color='#e3ebe7'>%1</t>", [toUpper (_e get "title")] call _esc],
        format ["<t size='0.85' color='#8a9a93'>%1 · %2</t>", [_e getOrDefault ["by", ""]] call _esc, _e getOrDefault ["at", ""]]];
    if (_isDone) then {
        private _web = _e getOrDefault ["web", ""];
        _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.15' color='#ffffff'>%1</t>", [format ["CR REMIS À %1 ✓", _e getOrDefault ["doneAt", ""]], format ["LU À %1 ✓", _e getOrDefault ["doneAt", ""]]] select (_read)], [0.10, 0.45, 0.24, 1]];
        if (_web isNotEqualTo "") then {
            _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", ["#e5483a", "#5cc76b"] select (_web isEqualTo "OK"),
                switch (_web) do { case "OK": { "Reçu par Athena ✓" }; case "EN COURS": { "Envoi à Athena…" }; case "HORS LIGNE": { "Athena hors ligne : renvoyez-le une fois reconnecté." }; default { "Athena ne l'a pas reçu." } }]];
            if (!_read && {_web in ["ÉCHEC", "HORS LIGNE"]}) then { _rows pushBack ["buttons", [["RENVOYER À ATHENA", ["web", _e get "id"] call _act, true]]]; };
        };
    } else {
        _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.15' color='#140c02'>%1</t>", ["À REMPLIR AVANT DE QUITTER", "À LIRE AVANT DE QUITTER"] select _read], [0.92, 0.6, 0.18, 1]];
    };
    if ((_e getOrDefault ["intro", ""]) isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.9' color='#c9d4cf'>%1</t>", [_e get "intro"] call _esc]]; };
    if (_read) then {
        // Paragraphes du site (déjà en <br/>) et retours à la ligne du texte écrit en jeu ; le reste est échappé.
        private _t = [[_e getOrDefault ["read", ""]] call _esc, "&lt;br/&gt;", "<br/>"] call CBA_fnc_replace;
        _t = [[_t, toString [13], ""] call CBA_fnc_replace, toString [10], "<br/>"] call CBA_fnc_replace;
        _rows pushBack ["text", format ["<t size='0.95' color='#e3ebe7'>%1</t>", _t], [0.05, 0.06, 0.055, 1]];
        if (!_isDone) then { _rows pushBack ["big", [["LU ET COMPRIS", ["read"] call _act, "green", true, "Prévient le commandement que vous l'avez lu"]]]; };
    } else {
        private _vals = _e getOrDefault ["vals", createHashMap];
        private _errors = _ui getOrDefault ["errors", []];
        private _ans = _e getOrDefault ["answers", createHashMap];
        {
            _x params ["_k", "_type", "_label", ["_help", ""], ["_req", 0], ["_opts", []]];
            private _warn = _k in _errors;
            private _lbl = format ["<t color='%1'>%2</t>%3", ["#e3ebe7", "#ff6a5c"] select _warn, [_label] call _esc, ["", "<t color='#e8c547'> *</t>"] select (_req isEqualTo 1)];
            private _v = _vals getOrDefault [_k, ""];
            if (_isDone) then {
                _rows pushBack ["line", "", _label, [_ans getOrDefault ["a_" + _k, _v], "-"] select ((_ans getOrDefault ["a_" + _k, _v]) isEqualTo ""), "auto", "", ""];
                continue;
            };
            switch (_type) do {
                case "textarea": {
                    _rows pushBack ["text", _lbl + (["", format ["  <t size='0.75' color='#8a9a93'>%1</t>", [_help] call _esc]] select (_help isNotEqualTo ""))];
                    _rows pushBack ["memo", "dbf_" + _k, "", _v, 4];
                };
                case "select": {
                    if ((count _opts) <= 5) then {
                        _rows pushBack ["lineseg", "", _lbl, _opts apply { [_x, ["set", _k, _x] call _act, _x isEqualTo _v] }, _help, _warn];
                    } else {
                        _rows pushBack ["linecombo", "", _lbl, "dbf_" + _k, [["—", ""]] + (_opts apply { [_x, _x] }), _v, _help, _warn];
                    };
                };
                case "checkbox": {
                    private _on = _v isEqualTo "Oui";
                    _rows pushBack ["lineseg", "", _lbl, [["OUI", ["set", _k, "Oui"] call _act, _on], ["NON", ["set", _k, "Non"] call _act, _v isEqualTo "Non"]], _help, _warn];
                };
                default { _rows pushBack ["line", "", _lbl, _v, "edit", "dbf_" + _k, _help, "", _warn]; };
            };
        } forEach (_e get "qs");
        if (!_isDone) then {
            if ((count _errors) > 0) then { _rows pushBack ["text", "<t size='0.85' color='#ff6a5c'>Répondez aux questions marquées d'une étoile.</t>"]; };
            _rows pushBack ["big", [["TRANSMETTRE LE CR", ["send"] call _act, "green", true, "Le commandement et Athena le reçoivent"]]];
        };
    };
    // Autres CR de la partie.
    private _others = (values _all) select { (_x get "id") isNotEqualTo (_e get "id") };
    if ((count _others) > 0) then {
        _rows pushBack ["section", "Autres CR de la partie", ""];
        {
            private _d = (_x get "state") isEqualTo "done";
            _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='%2'>%3</t>", [_x get "title"] call _esc, ["#e8c547", "#5cc76b"] select _d,
                [["À remplir", "À lire"] select ((_x get "mode") isEqualTo "read"), format ["Remis à %1", _x getOrDefault ["doneAt", ""]]] select _d],
                [["OUVRIR", ["open", _x get "id"] call _act, !_d]]];
        } forEach _others;
    };
} else {
    // ── Panneau du commandement ───────────────────────────────────────────────────────────────────────────────
    private _cfg = missionNamespace getVariable ["COMSPEC_ATAK_DbfCfg", createHashMap];
    private _mode = _ui getOrDefault ["sMode", _cfg getOrDefault ["mode", "fill"]];
    private _target = _ui getOrDefault ["sTarget", "all"];
    _rows pushBack ["section", "Imposer le CR de fin d'opération", "Il s'ouvre dans le téléphone des joueurs visés"];
    _rows pushBack ["lineseg", "", "Mode", [["À REMPLIR", ["sMode", "fill"] call _act, _mode isEqualTo "fill"], ["À LIRE", ["sMode", "read"] call _act, _mode isEqualTo "read"]], ""];
    _rows pushBack ["lineseg", "", "Joueurs", [["TOUS", ["sTarget", "all"] call _act, _target isEqualTo "all"], ["MON CAMP", ["sTarget", "side"] call _act, _target isEqualTo "side"],
        ["MON GROUPE", ["sTarget", "group"] call _act, _target isEqualTo "group"]], ""];
    _rows pushBack ["line", "", "Titre", _ui getOrDefault ["sTitle", "Fin d'opération"], "edit", "dbf_s_title", ""];
    _rows pushBack ["memo", "dbf_s_text", ["Message en tête de la fiche (facultatif)", "Texte à lire"] select (_mode isEqualTo "read"), _ui getOrDefault ["sText", ""], 4];
    if (_mode isEqualTo "fill") then {
        private _q = _cfg getOrDefault ["qs", []];
        _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", [format ["Questions du site : %1", count _q], "Questionnaire intégré : déroulé, points forts, à améliorer, pertes, liaisons, note"] select ((count _q) isEqualTo 0)]];
    };
    _rows pushBack ["big", [["IMPOSER LE CR", ["sForce"] call _act, "amber", true, "Ouvre la fiche dans les téléphones visés et la ramène toutes les 2 min tant qu'elle n'est pas remise"]]];
    _rows pushBack ["section", "Suivi des CR de la partie", "Demandes faites en jeu ; celles du site se suivent sur Athena"];
    private _track = missionNamespace getVariable ["COMSPEC_ATAK_DbfTrack", []];
    if ((count _track) isEqualTo 0) then { _rows pushBack ["text", "<t size='0.85' color='#8a9a93'>Aucune demande pour l'instant.</t>"]; };
    private _stLbl = createHashMapFromArray [["sent", ["Envoyé", "#6aa8ff"]], ["received", ["Reçu", "#6aa8ff"]], ["opened", ["Ouvert", "#f08a3c"]], ["read", ["Lu ✓", "#e3ebe7"]], ["submitted", ["Remis ✓", "#e3ebe7"]]];
    {
        _x params ["_ref", "_m", "_title", "_by", "_at", "_lifted", ["_ps", []]];
        private _ok = { (_x select 2) in ["read", "submitted"] } count _ps;
        _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='#8a9a93'>%2</t><br/><t size='0.8'>%3</t>", [_title] call _esc,
            [["À remplir", "À lire"] select (_m isEqualTo "read"), "Levé"] select _lifted, [format ["%1 · %2 · %3/%4 remis", _by, _at, _ok, count _ps]] call _esc],
            [["RELANCER", ["sRemind", _ref] call _act, false, !_lifted && {_ok < (count _ps)}, "Ramène la fiche chez ceux qui ne l'ont pas remise"], ["LEVER", ["sLift", _ref] call _act, "red", !_lifted, "Retire la fiche des téléphones"]]];
        {
            _x params ["", "_name", "_st", "_t"];
            (_stLbl getOrDefault [_st, [_st, "#8a9a93"]]) params ["_sl", "_sc"];
            _rows pushBack ["info", format ["<t color='%1'>%2</t>", _sc, [_name] call _esc], format ["<t color='%1'>%2</t> <t color='#8a9a93'>%3</t>", _sc, _sl, _t]];
        } forEach _ps;
    } forEach (_track select [((count _track) - 4) max 0]);
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
