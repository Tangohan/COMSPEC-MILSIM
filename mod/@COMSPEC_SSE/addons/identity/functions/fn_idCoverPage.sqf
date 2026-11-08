/*
    App « Ma couverture » du téléphone ATAK (page IDCOVER, déclarée dans COMSPEC_ATAK_Apps du SSE, main.pbo intact).
      MA FICHE   fiche RP du seul joueur concerné : état de l'infiltration, cohérence de la couverture (papiers,
                 légende, équipement, comportement), identité, adresse, employeur, téléphone simulé, contacts, documents
                 complémentaires, historique, biographie, famille, langues, phrases types, sujets sensibles, secrets,
                 brief RP d'Athena (paquet, Steam ID) ; question posée par un contrôleur, avec les réponses au choix
      ZEUS       (curateur seulement) les infiltrés et leur cohérence ; un clic ouvre la fiche de l'un d'eux
    Rendu : [page, rectangle] (fn_pageRender de l'ATAK). Actions : ["act", action, argument] call comspec_sse_fnc_idCoverPage
      refresh · tab · answer [qid, index] · open netId (Zeus) · back
    État local (jamais diffusé) : comspec_sse_idCoverSheets (netId -> fiche reçue du serveur), comspec_sse_idQuestion.
*/
params [["_page", "IDCOVER"], ["_rect", []], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_idCoverApp", createHashMap];
uiNamespace setVariable ["comspec_sse_idCoverApp", _st];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
private _sheets = missionNamespace getVariable ["comspec_sse_idCoverSheets", createHashMap];
missionNamespace setVariable ["comspec_sse_idCoverSheets", _sheets];
private _zeus = !isNull (getAssignedCuratorLogic player);

if (_page isEqualTo "act") exitWith {
    switch (_rect) do {
        case "refresh": {
            ["send", ["coverSheet", [player, [player, objectFromNetId (_st getOrDefault ["open", ""])] select ((_st getOrDefault ["open", ""]) isNotEqualTo "")]]] call comspec_sse_fnc_idClient;
            if (_zeus) then { ["send", ["coverList", [player]]] call comspec_sse_fnc_idClient; };
        };
        case "tab": { _st set ["tab", _arg]; _st set ["open", ""]; if (_arg isEqualTo "zeus") then { ["send", ["coverList", [player]]] call comspec_sse_fnc_idClient; }; call _render; };
        case "open": { _st set ["open", _arg]; ["send", ["coverSheet", [player, objectFromNetId _arg]]] call comspec_sse_fnc_idClient; call _render; };
        case "back": { _st set ["open", ""]; call _render; };
        case "answer": {
            _arg params [["_qid", ""], ["_i", -1]];
            private _q = missionNamespace getVariable ["comspec_sse_idQuestion", []];
            if (_q isEqualTo []) exitWith {};
            (_q select 3) set [_qid, _i];
            ["send", ["answer", [player, _qid, _i]]] call comspec_sse_fnc_idClient;
            call _render;
        };
    };
    true
};

disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _tr = { _this call comspec_sse_fnc_tr };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_x2", ""]]; compile format ["['act', '%1', %2] call comspec_sse_fnc_idCoverPage;", _a, str _x2] };
private _icon = "\z\comspec_sse\addons\identity\data\app_id.paa";
private _tab = _st getOrDefault ["tab", "me"];
if (!_zeus) then { _tab = "me"; };
private _rows = [];

// Question en attente (checkpoint, contrôle IA) : réponses au choix.
(missionNamespace getVariable ["comspec_sse_idQuestion", []]) params [["_qWho", ""], ["_qList", []], ["_qEnd", 0], ["_qDone", createHashMap]];
private _open = _qList select { !((_x select 0) in _qDone) };
if (_open isNotEqualTo [] && {_qEnd > time}) then {
    (_open select 0) params ["_qid", "_text", "_opts"];
    _rows pushBack ["banner", format ["<t color='#f2ab33'>%1</t>", format [("%1 vous interroge (%2 s)" call _tr), [_qWho] call _esc, round (_qEnd - time)]], [0.18, 0.12, 0.03, 1]];
    _rows pushBack ["section", (_text call _tr), ""];
    private _b = [];
    { _b pushBack [[_x call _tr] call _esc, ["answer", [_qid, _forEachIndex]] call _act, false, true]; } forEach _opts;
    _rows pushBack ["buttons", _b];
    _rows pushBack ["text", format ["<t size='0.8' color='#8b98a7'>%1</t>", ("Aussi : menu ACE personnel › Mes papiers › Répondre." call _tr) call _esc]];
};
if (_zeus) then {
    _rows pushBack ["segment", "", [
        ["MA FICHE" call _tr, ["tab", "me"] call _act, _tab isEqualTo "me"],
        [format ["%1 (%2)", "ZEUS" call _tr, count (missionNamespace getVariable ["comspec_sse_idCoverList", []])], ["tab", "zeus"] call _act, _tab isEqualTo "zeus"]
    ]];
};

private _sheetRows = {
    params ["_s"];
    private _state = _s getOrDefault ["state", "off"];
    (switch (_state) do {
        case "on": { ["INFILTRÉ · COUVERTURE INTACTE", "#34d399", [0.04, 0.16, 0.1, 1]] };
        case "burnt": { ["COUVERTURE BRÛLÉE", "#ff5a5a", [0.3, 0.02, 0.02, 1]] };
        default { ["IDENTITÉ DE VOS PAPIERS", "#8b98a7", [0.07, 0.09, 0.11, 1]] };
    }) params ["_bt", "_bc", "_bg"];
    _rows pushBack ["banner", format ["<t color='%1'>%2</t>", _bc, _bt call _tr], _bg];
    if (_state isEqualTo "burnt" && {(_s getOrDefault ["burnWhy", ""]) isNotEqualTo ""}) then { _rows pushBack ["info", "Motif" call _tr, [(_s get "burnWhy") call _tr] call _esc]; };
    private _score = _s getOrDefault ["score", -1];
    if (_score >= 0) then {
        _rows pushBack ["info", "Cohérence" call _tr, format ["%1 %2", _score, "/ 100"]];
        private _why = _s getOrDefault ["why", []];
        if (_why isNotEqualTo []) then { _rows pushBack ["text", format ["<t size='0.85' color='#f2ab33'>%1</t>", [(_why apply { _x call _tr }) joinString " · "] call _esc]]; };
    };
    _rows pushBack ["person", "\z\comspec_sse\addons\identity\data\silhouette_co.paa",
        format ["<t size='1.1' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8b98a7'>%2 · %3</t>", [_s getOrDefault ["name", "?"]] call _esc,
            [_s getOrDefault ["dob", ""]] call _esc, [_s getOrDefault ["pob", ""]] call _esc], [], [1, 1, 1, 1]];
    if ((_s getOrDefault ["trueName", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Vrai nom (Zeus)" call _tr, [_s get "trueName"] call _esc]; };
    if ((_s getOrDefault ["title", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Brief" call _tr, [_s get "title"] call _esc]; };
    _rows pushBack ["info", "Adresse" call _tr, [_s getOrDefault ["address", ""]] call _esc];
    _rows pushBack ["info", "Profession" call _tr, [(_s getOrDefault ["profession", ""]) call _tr] call _esc];
    _rows pushBack ["info", "Employeur" call _tr, [_s getOrDefault ["employer", ""]] call _esc];
    private _ph = _s getOrDefault ["phone", createHashMap];
    _rows pushBack ["info", "Téléphone" call _tr, [format ["%1%2", _ph getOrDefault ["number", ""], ["", format [" · %1", _ph getOrDefault ["model", ""]]] select ((_ph getOrDefault ["model", ""]) isNotEqualTo "")]] call _esc];
    if ((_s getOrDefault ["travel", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Motif du déplacement" call _tr, [(_s get "travel") call _tr] call _esc]; };
    private _list = {
        params ["_title", "_items"];
        if (_items isEqualTo []) exitWith {};
        _rows pushBack ["section", _title call _tr, ""];
        { _rows pushBack ["text", format ["<t size='0.9'>%1</t>", [_x call _tr] call _esc]]; } forEach _items;
    };
    ["Contacts", (_s getOrDefault ["contacts", []]) apply { _x params [["_n", ""], ["_r", ""], ["_p", ""]]; trim format ["%1%2%3", _n, ["", format [" (%1)", _r]] select (_r isNotEqualTo ""), ["", format [" · %1", _p]] select (_p isNotEqualTo "")] }] call _list;
    ["Documents complémentaires", _s getOrDefault ["extraDocs", []]] call _list;
    ["Historique", _s getOrDefault ["history", []]] call _list;
    ["Biographie", [_s getOrDefault ["biography", ""]] select { _x isNotEqualTo "" }] call _list;
    ["Famille", [_s getOrDefault ["family", ""]] select { _x isNotEqualTo "" }] call _list;
    ["Langues", _s getOrDefault ["languages", []]] call _list;
    ["Phrases types", (_s getOrDefault ["phrases", []]) apply { format ["« %1 »", _x] }] call _list;
    ["Sujets sensibles", _s getOrDefault ["sensitive", []]] call _list;
    ["Secrets", _s getOrDefault ["secrets", []]] call _list;
    ["Consignes", [_s getOrDefault ["instructions", ""]] select { _x isNotEqualTo "" }] call _list;
};

if (_tab isEqualTo "zeus") then {
    private _openId = _st getOrDefault ["open", ""];
    private _s = _sheets getOrDefault [_openId, createHashMap];
    if (_openId isNotEqualTo "" && {(count _s) > 0}) exitWith {
        [_s] call _sheetRows;
        _rows pushBack ["buttons", [["RETOUR" call _tr, ["back"] call _act]]];
    };
    private _lst = missionNamespace getVariable ["comspec_sse_idCoverList", []];
    if (_lst isEqualTo []) exitWith { _rows pushBack ["empty", _icon, "Aucun infiltré" call _tr, "Module Zeus « Couverture » sur un joueur ou une IA pour l'infiltrer." call _tr]; };
    {
        _x params ["_net", "_n", "_cn", "_state", "_score"];
        _rows pushBack ["notif", _icon, [[0.55, 0.6, 0.65, 1], [0.2, 0.83, 0.6, 1], [1, 0.35, 0.35, 1]] select ((["off", "on", "burnt"] find _state) max 0),
            format ["%1 · %2", _n, _cn], format ["%1/100", _score],
            ((["Levée", "Couverture intacte", "Brûlée"] select ((["off", "on", "burnt"] find _state) max 0)) call _tr),
            ["open", _net] call _act, {}];
    } forEach _lst;
} else {
    private _s = _sheets getOrDefault [netId player, createHashMap];
    if ((count _s) isEqualTo 0) exitWith {
        _rows pushBack ["empty", _icon, "Fiche en chargement" call _tr, "Votre fiche est demandée au serveur." call _tr];
        if ((time - (_st getOrDefault ["asked", -100])) > 10) then {
            _st set ["asked", time];
            ["send", ["coverSheet", [player, player]]] call comspec_sse_fnc_idClient;
        };
    };
    [_s] call _sheetRows;
};
_rows pushBack ["buttons", [["Actualiser" call _tr, ["refresh"] call _act]]];
_rows pushBack ["text", format ["<t size='0.8' color='#8b98a7'>%1</t>", ("Fiche personnelle : visible de vous seul (et du Zeus)." call _tr) call _esc]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
