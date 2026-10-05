/*
    App Comptes rendus. Params : [action, argument, valeur]
      "tab" onglet (NEW, SENT, RECV)       "type" code : ouvre le formulaire (ou l'app qui gère ce C.R. pour un code @…)
      "back" : retour à la liste des types "set" clé valeur : choix d'un segment
      "here" clé : grille = ma position     "pick" clé / "picked" position : grille pointée sur la carte
      "send" : diffuse le C.R. en jeu (camp ou groupe) et l'enregistre sur Athena (POST /api/atak/reports)
      "recv" [C.R., camp] : réception (événement comspec_atak_native_report, rejoué par la synchro serveur)
      "open" id : déplie / replie un C.R.   "map" id : carte sur le C.R.   "del" id : le retire de ma liste
      "web" id : renvoie à Athena un C.R. qui n'y est pas arrivé
    État : uiNamespace COMSPEC_ATAK_RepUi (onglet, type, valeurs saisies, C.R. déplié, champ pointé sur la carte)
           missionNamespace COMSPEC_ATAK_Reports (id → C.R., les miens et ceux reçus)
*/
params [["_act", ""], ["_arg", ""], ["_val", ""]];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_RepUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_RepUi", _ui];
private _vals = _ui getOrDefault ["vals", createHashMap];
_ui set ["vals", _vals];
private _list = missionNamespace getVariable ["COMSPEC_ATAK_Reports", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_Reports", _list];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "REPORTS") then { ["REPORTS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _types = [] call comspec_atak_native_fnc_reportTypes;
private _def = { params ["_code"]; private _i = _types findIf { (_x select 0) isEqualTo _code }; if (_i < 0) then { [] } else { _types select _i } };
// Garde la saisie (textes, listes) avant de redessiner : les segments sont déjà dans _vals.
private _save = {
    private _t = [_ui getOrDefault ["type", ""]] call _def;
    if ((count _t) isEqualTo 0 || {(_t select 3) isEqualTo ""}) exitWith {};
    {
        _x params ["_k", "_kind"];
        if (_kind in ["edit", "memo", "num", "dtg", "grid", "combo"]) then {
            private _v = ["rp_" + _k, _vals getOrDefault [_k, ""]] call comspec_atak_native_fnc_formValue;
            _vals set [_k, _v];
        };
    } forEach (_t select 5);
};
// Exécution du C.R. sur Athena (Overwatch embarqué). Renvoie "OK", "ÉCHEC" ou "HORS LIGNE".
private _toWeb = {
    params ["_r"];
    if (isNil "comspec_overwatch_connect_fnc_submitTacticalReport") exitWith { "HORS LIGNE" };
    private _sd = createHashMapFromArray [["kind", toLower (_r get "code")], ["grid", _r get "grid"], ["source", "COMSPEC ATAK · Comptes rendus"]];
    { _sd set [_x select 0, _x select 2]; } forEach (_r get "lines");
    private _prio = switch (_r get "prio") do { case "PRIORITAIRE": { "PRIORITY" }; case "IMMÉDIAT": { "IMMEDIATE" }; case "FLASH": { "FLASH" }; default { "ROUTINE" } };
    // Une seule ligne : le JSON d'Overwatch (hashMapToJson) n'échappe pas les retours à la ligne.
    private _details = ((_r get "lines") apply { format ["%1 : %2", _x select 1, _x select 2] }) joinString " | ";
    private _ok = [_r get "web_type", _prio, _r get "summary", _details, _sd, _r get "pos"] call comspec_overwatch_connect_fnc_submitTacticalReport;
    ["ÉCHEC", "OK"] select (_ok isEqualType true && {_ok})
};
switch (_act) do {
    case "tab": { call _save; _ui set ["tab", _arg]; _ui set ["open", ""]; call _rerender; };
    case "type": {
        private _t = [_arg] call _def;
        if ((count _t) isEqualTo 0) exitWith {};
        // Raccourci : ce C.R. a déjà son app (Feux, JTAC, MEDEVAC du Médical, Logistique).
        if ((_t select 3) isEqualTo "") exitWith {
            (_t select 5) params ["_page", ["_tab", ""]];
            if (_page isEqualTo "MEDICAL" && {_tab isNotEqualTo ""}) then { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["medTab", _tab]; };
            [_page] call comspec_atak_native_fnc_navigate;
        };
        if ((_ui getOrDefault ["type", ""]) isNotEqualTo _arg) then { _vals = createHashMap; _ui set ["vals", _vals]; };
        _ui set ["type", _arg];
        _ui set ["tab", "NEW"];
        call _rerender;
    };
    case "back": { call _save; _ui set ["type", ""]; call _rerender; };
    case "set": { call _save; _vals set [_arg, _val]; call _rerender; };
    case "here": { call _save; _vals set [_arg, [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]; call _rerender; };
    case "pick": {
        call _save;
        _ui set ["pick", _arg];
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["reportPick", _arg];
        ["MAP"] call comspec_atak_native_fnc_navigate;
        ["INFO", "Touchez la carte pour placer la position du compte rendu", 4, 20] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        private _k = _ui getOrDefault ["pick", ""];
        if (_k isNotEqualTo "") then { _vals set [_k, [_arg, 8] call comspec_atak_native_fnc_gridRef]; };
        _ui set ["pick", ""];
        ["REPORTS"] call comspec_atak_native_fnc_navigate;
    };
    case "send": {
        call _save;
        private _t = [_ui getOrDefault ["type", ""]] call _def;
        if ((count _t) isEqualTo 0 || {(_t select 3) isEqualTo ""}) exitWith {};
        _t params ["_code", "_label", "", "_webType", "", "_fields"];
        private _lines = [];
        private _grid = "";
        {
            _x params ["_k", "_kind", "_flabel", ["_farg", ""]];
            private _v = _vals getOrDefault [_k, ""];
            if (_v isEqualTo "" && {_kind in ["seg", "combo"]}) then { _v = _farg select 0; };
            if !(_v isEqualType "") then { _v = str _v; };
            _v = trim ((_v splitString toString [13, 10]) joinString " / ");
            if (_kind isEqualTo "grid" && {_grid isEqualTo ""}) then { _grid = _v; };
            if (_v isNotEqualTo "" && {!(_k in ["prio", "dest"])}) then { _lines pushBack [_k, _flabel, _v]; };
        } forEach _fields;
        // Un C.R. vide n'apprend rien à personne.
        if (({ !((_x select 0) in ["grid", "location", "time", "end"]) } count _lines) isEqualTo 0) exitWith {
            ["WARNING", "Remplissez au moins un champ du compte rendu", 4, 30] call comspec_atak_native_fnc_notify;
        };
        private _pos = getPosASL player;
        private _g = (_grid splitString " ,.-") joinString "";
        if (_g isNotEqualTo "") then {
            private _p = ([_g] call BIS_fnc_gridToPos) param [0, []];
            if ((count _p) >= 2) then { _pos = [_p select 0, _p select 1, 0]; };
        };
        if (_grid isEqualTo "") then { _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef; };
        private _prio = _vals getOrDefault ["prio", "ROUTINE"];
        private _dest = _vals getOrDefault ["dest", "CAMP"];
        // Résumé : type, grille et les trois premiers éléments renseignés.
        private _head = (_lines select { !((_x select 0) in ["grid", "location", "time", "end", "start"]) }) apply { _x select 2 };
        _head resize ((count _head) min 3);
        private _summary = format ["%1 · %2%3", _label, _grid, (_head apply { format [" · %1", (_x select [0, 60])] }) joinString ""];
        private _r = createHashMapFromArray [
            ["id", format ["CR%1%2", floor (random 9000) + 1000, floor (diag_tickTime * 10) mod 1000]],
            ["code", _code], ["label", _label], ["web_type", _webType], ["prio", _prio], ["dest", _dest],
            ["by", [player, true] call comspec_atak_native_fnc_unitCallsign], ["uid", getPlayerUID player],
            ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["grid", _grid], ["pos", [_pos select 0, _pos select 1, 0]],
            ["summary", _summary], ["lines", _lines], ["grp", netId group player], ["web", ""]
        ];
        _list set [_r get "id", _r];
        if (_dest isNotEqualTo "AUCUNE") then {
            [{ params ["_r", "_side"]; ["comspec_atak_native_report", [_r, _side]] call CBA_fnc_globalEvent; }, [_r, str side group player], _label, 2] call comspec_atak_native_fnc_netSend;
        };
        _r set ["web", [_r] call _toWeb];
        ["SUCCESS", format ["%1 transmis%2", _label, switch (_r get "web") do {
            case "OK": { " · enregistré sur Athena" };
            case "HORS LIGNE": { " en jeu · Athena non connecté" };
            default { " en jeu · Athena n'a pas répondu (RENVOYER dans Envoyés)" };
        }], 5, 45] call comspec_atak_native_fnc_notify;
        _ui set ["vals", createHashMap];
        _ui set ["type", ""];
        _ui set ["tab", "SENT"];
        _ui set ["open", _r get "id"];
        call _rerender;
    };
    case "recv": {
        _arg params [["_r", createHashMap], ["_side", ""]];
        if !(_r isEqualType createHashMap) exitWith {};
        if (_side isNotEqualTo str side group player) exitWith {};
        if ((_r getOrDefault ["dest", "CAMP"]) isEqualTo "MON GROUPE" && {(_r getOrDefault ["grp", ""]) isNotEqualTo netId group player}) exitWith {};
        private _id = _r getOrDefault ["id", ""];
        if (_id isEqualTo "" || {_id in _list}) exitWith {};
        _list set [_id, _r];
        if ((_r getOrDefault ["uid", ""]) isNotEqualTo getPlayerUID player) then {
            private _urgent = (_r getOrDefault ["prio", ""]) in ["IMMÉDIAT", "FLASH"];
            [["TACTICAL", "WARNING"] select _urgent, format ["%1%2 de %3 · %4", ["", (_r get "prio") + " · "] select _urgent, _r getOrDefault ["label", "C.R."], _r getOrDefault ["by", "?"], _r getOrDefault ["grid", ""]], [5, 8] select _urgent, [40, 70] select _urgent] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    case "open": { call _save; _ui set ["open", ["", _arg] select ((_ui getOrDefault ["open", ""]) isNotEqualTo _arg)]; call _rerender; };
    case "map": {
        private _p = (_list getOrDefault [_arg, createHashMap]) getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "del": { _list deleteAt _arg; call _rerender; };
    case "web": {
        private _r = _list getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        _r set ["web", [_r] call _toWeb];
        call _rerender;
    };
};
true
