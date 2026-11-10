/*
    Page « IA de la zone » de l'éditeur SSE (client du Zeus) : détecter les IA, les cocher, voir leur fiche (nom donné, vrai
    nom, vérité, profil Athena, papiers, comportement, camp), appliquer à la sélection ou à son groupe, garder pour les
    prochaines parties, ouvrir et compléter les papiers. Tout est vérifié et appliqué sur le serveur (fn_editorAiServer,
    fn_idAiConfig) ; rien par image. Textes : stringtable.xml de l'addon (STR_COMSPEC_SSE_ED_*).
    Liste (fn_editorAiServer « rows ») : [netId, nom donné, vrai nom, vérité, camp, rôle, réf. profil, id profil, nb papiers,
    comportement, groupe, nom d'unité Arma, papiers portés, nature, id gardé]. Un clic coche ou décoche la ligne ; la fiche
    montre la dernière ligne cliquée ; les actions portent sur les IA cochées.
    Profils Athena : liste reçue du serveur (paquet d'identité, « settings.ai_profiles ») ; « Actualiser » fait recharger le
    paquet par le serveur (fn_idServer « aiCfg reload »), puis relit la liste.
    Aide (« ? ») : chapitre CfgHints COMSPEC_SSE › AiZone, posé sur cette fenêtre (fn_openGuide sinon).
*/
params [["_op", ""], ["_arg", []]];
if (!hasInterface) exitWith { false };
// Appel distant : réponses du serveur seulement (aiReply).
if (isRemoteExecuted && {!isServer} && {remoteExecutedOwner isNotEqualTo 2}) exitWith { false };
disableSerialization;
// Envoi à Athena depuis ce poste (SubmitIdCheck, contexte planifié) : renvoie la réponse brute (« OK|id|kept »). Avant le
// switch : la valeur serait masquée par le « true » final.
if (_op isEqualTo "post") exitWith {
    if !(_arg isEqualType createHashMap) exitWith { "" };
    if !(["has"] call comspec_sse_fnc_idAthenaPack) exitWith { "ERR|no_link" };
    _arg set ["map", missionNamespace getVariable ["COMSPEC_MapId", 1]];
    _arg set ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]];
    private _json = [_arg] call comspec_sse_fnc_toJsonApprox;
    private _parts = [];
    for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
    private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
    if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
    if !(_raw isEqualType "") then { _raw = str _raw; };
    _raw
};
private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_editorAi", createHashMap];
uiNamespace setVariable ["comspec_sse_editorAi", _st];
private _L = { localize ("STR_COMSPEC_SSE_ED_" + _this) };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _send = {
    params ["_sop", ["_x1", []]];
    private _call = [_sop, [player] + _x1];
    if (isServer) then { _call call comspec_sse_fnc_editorAiServer; } else { _call remoteExecCall ["comspec_sse_fnc_editorAiServer", 2]; };
};
// Voie de l'addon identity (papier imprimé, rechargement du paquet).
private _sendId = { params ["_sub", ["_x1", []]]; ["send", ["aiCfg", [player, _sub, _x1]]] call comspec_sse_fnc_idClient; };
// Papiers à porter : anciens alias (fn_idRegistry « kit »), puis chaque clé du registre (CfgComspecDocuments, extensions actives
// comprises : « Carte de presse (Documents d'exemple) »). Même valeur que l'attribut Eden COMSPEC_ID_Carry.
private _papers = ["auto", "none", "card", "passport", "license", "card_license", "card_passport", "military"];
private _extNames = createHashMap;
{ _extNames set [toLower (_x select 0), _x select 1]; } forEach (["extensions"] call comspec_sse_fnc_idDocTypes);
{
    private _ext = toLower ((["get", _x] call comspec_sse_fnc_idDocTypes) getOrDefault ["extension", ""]);
    private _label = ["label", _x] call comspec_sse_fnc_idDocTypes;
    _papers pushBack [_x, if (_ext isEqualTo "") then { format ["%1 [%2]", _label, _x] } else { format ["%1 (%2)", _label, _extNames getOrDefault [_ext, _ext]] }];
} forEach (["list", [""]] call comspec_sse_fnc_idDocTypes);
private _kinds = ["civil", "cover", "forged"];
private _behav = ["auto", "coopere", "nerveux", "ment", "refuse", "fuit", "agressif", "rendu"];
private _sides = ["auto", "civ", "west", "east", "guer"];
// Libellé d'un choix de papiers à porter : alias de l'éditeur, sinon type du registre.
private _papersLabel = { params ["_k"]; if (isLocalized ("STR_COMSPEC_SSE_ED_Papers_" + _k)) exitWith { ("Papers_" + _k) call _L }; private _r = (_papers select { _x isEqualType [] && {(_x select 0) isEqualTo _k} }) param [0, [_k, _k]]; _r select 1 };
// Types de documents servis (registre, extensions actives) : liste « Ajouter un papier » et libellés des papiers d'une IA.
private _docTypes = (["list", [""]] call comspec_sse_fnc_idDocTypes) apply { [_x, ["label", _x] call comspec_sse_fnc_idDocTypes] };
// Libellé d'un type de document : celui du registre (extensions comprises), sinon la clé de l'éditeur.
private _docLabel = { params ["_k"]; private _n = ["label", _k] call comspec_sse_fnc_idDocTypes; if (_n isEqualType "" && {_n isNotEqualTo ""}) then { _n } else { ("Doc_" + _k) call _L } };
private _roles = ["civilian", "military", "suspect", "hvt"];
private _states = ["athena", "generated", "pending", "kept"];
private _picOff = "\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_unchecked_ca.paa";
private _picOn = "\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_checked_ca.paa";
private _colOn = [0.361, 0.780, 0.420, 1];
private _colOff = [0.914, 0.941, 0.925, 1];
private _ctrl = { _d displayCtrl _this };
private _fill = {
    // [idc, codes (ou [code, libellé]), préfixe de clé, [[donnée, texte]…] en tête]
    params ["_idc", "_codes", "_prefix", ["_head", []]];
    private _c = _idc call _ctrl;
    lbClear _c;
    { private _i = _c lbAdd (_x select 1); _c lbSetData [_i, _x select 0]; } forEach _head;
    // Code seul : libellé de l'éditeur (préfixe de clé) ; [code, libellé] : libellé donné (types du registre).
    { if (_x isEqualType []) then { private _i = _c lbAdd (_x select 1); _c lbSetData [_i, _x select 0]; } else { private _i = _c lbAdd ((_prefix + _x) call _L); _c lbSetData [_i, _x]; }; } forEach _codes;
    _c lbSetCurSel 0;
};
private _data = { private _c = _this call _ctrl; private _i = lbCurSel _c; if (_i < 0) exitWith { "" }; _c lbData _i };
private _pick = { params ["_idc", "_code"]; private _c = _idc call _ctrl; for "_i" from 0 to ((lbSize _c) - 1) do { if ((_c lbData _i) isEqualTo _code) exitWith { _c lbSetCurSel _i; }; }; };
private _note = { params ["_t", ["_kind", ""]]; (94548 call _ctrl) ctrlSetText _t; if (_kind isNotEqualTo "") then { [_t, _kind] call comspec_sse_fnc_toast; }; };
// Libellé plus long que sa case : police réduite par paliers (60 % au plus bas) au lieu d'être coupé.
private _fit = {
    params ["_c"];
    if (isNull _c || {!ctrlShown _c}) exitWith {};
    private _base = _c getVariable ["comspec_sse_fh0", -1];
    if (_base < 0) then { _base = ctrlFontHeight _c; _c setVariable ["comspec_sse_fh0", _base]; };
    _c ctrlSetFontHeight _base;
    private _w = ((ctrlPosition _c) select 2) * 0.94;
    private _n = 0;
    while { (ctrlTextWidth _c) > _w && {_n < 5} } do {
        _c ctrlSetFontHeight ((ctrlFontHeight _c) * 0.9);
        _n = _n + 1;
    };
};
private _fitAll = {
    { [_x call _ctrl] call _fit; } forEach [94503, 94504, 94506, 94576, 94523, 94524, 94525, 94505, 94518, 94521, 94520, 94530, 94550, 94553, 94556, 94533, 94535, 94559, 94562, 94511, 94513, 94514, 94515, 94517, 94541, 94544, 94546, 94545,
        94577, 94578, 94579, 94580, 94581, 94582, 94583, 94584, 94566, 94567, 94568, 94569];
};
private _rowOf = { params ["_net"]; private _rows = _st getOrDefault ["rows", []]; private _i = _rows findIf { (_x select 0) isEqualTo _net }; if (_i < 0) then { [] } else { _rows select _i } };
private _checkedUnits = { ((_st getOrDefault ["checked", []]) apply { objectFromNetId _x }) select { !isNull _x && {alive _x} } };
private _spec = { createHashMapFromArray [["profile", 94532 call _data], ["papers", 94534 call _data], ["kind", 94536 call _data], ["behaviour", 94538 call _data], ["side", 94540 call _data]] };
private _ctxPos = {
    private _ctx = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["ctx", [[], objNull]];
    private _p = _ctx param [0, []];
    if (_p isNotEqualTo []) exitWith { _p };
    if (!isNull curatorCamera) exitWith { screenToWorld [0.5, 0.5] };
    getPosATL player
};
private _setRowMark = { params ["_l", "_r", "_on"]; _l lnbSetPicture [[_r, 0], [_picOff, _picOn] select _on]; _l lnbSetColor [[_r, 1], [_colOff, _colOn] select _on]; };
private _counts = {
    private _n = count (_st getOrDefault ["checked", []]);
    (94525 call _ctrl) ctrlSetText format ["Checked" call _L, _n];
    (94545 call _ctrl) ctrlSetText ([("Apply" call _L), format ["%1 (%2)", "Apply" call _L, _n]] select (_n > 0));
    [94545 call _ctrl] call _fit;
    [94525 call _ctrl] call _fit;
    if (_n > 1) then { ["MultiNote" call _L] call _note; };
};
private _render = {
    private _l = 94510 call _ctrl;
    _st set ["busy", true];
    lnbClear _l;
    private _q = toLower (trim (ctrlText (94507 call _ctrl)));
    private _fs = 94508 call _data;
    private _fr = 94509 call _data;
    private _fe = 94522 call _data;
    private _checked = _st getOrDefault ["checked", []];
    private _rows = _st getOrDefault ["rows", []];
    private _shown = 0;
    {
        _x params ["_net", "_given", "_true", "_truth", "_side", "_role", "_pref", "_pid", "_nd", "", "_grp", "_uname", "", "", "_keptId"];
        private _state = if (_pid isNotEqualTo "") then { "athena" } else { ["generated", "pending"] select (_truth isEqualTo "pending") };
        private _ok = (_fs isEqualTo "" || {_side isEqualTo _fs}) && {_fr isEqualTo "" || {_role isEqualTo _fr}}
            && {_fe isEqualTo "" || {if (_fe isEqualTo "kept") then { _keptId isNotEqualTo "" } else { _state isEqualTo _fe }}}
            && {_q isEqualTo "" || {((toLower format ["%1 %2 %3 %4 %5", _given, _true, _uname, _grp, _pref]) find _q) >= 0}};
        if (_ok) then {
            private _r = _l lnbAddRow ["", [_given, "(" + ("TruthS_pending" call _L) + ")"] select (_given isEqualTo ""), ["", _true] select ((toLower _true) isNotEqualTo (toLower _given)),
                ("TruthS_" + _truth) call _L, ("SideS_" + _side) call _L, ("RoleS_" + _role) call _L, [_pref, "-"] select (_pref isEqualTo ""), [str _nd, "-"] select (_nd isEqualTo 0), _grp];
            _l lnbSetData [[_r, 0], _net];
            [_l, _r, _net in _checked] call _setRowMark;
            _shown = _shown + 1;
        };
    } forEach _rows;
    _l lnbSetCurSelRow -1;
    _st set ["busy", false];
    (94505 call _ctrl) ctrlSetText format ["Count" call _L, count _rows];
    private _e = 94528 call _ctrl;
    _e ctrlSetText ([[("EmptyFilter" call _L), ("Empty" call _L)] select (_rows isEqualTo []), ""] select (_shown > 0));
    // Rien à dire avant la première réponse du serveur.
    _e ctrlShow (_shown isEqualTo 0 && {_st getOrDefault ["scanned", false]});
    call _counts;
};
private _setStatus = {
    params [["_n", 0], ["_at", ""], ["_has", false]];
    private _t = if (_n > 0) then { if (_at isEqualTo "") then { format ["ProfStatusNoDate" call _L, _n] } else { format ["ProfStatus" call _L, _n, _at] } } else { "ProfNone" call _L };
    (94552 call _ctrl) ctrlSetText _t;
};
private _keptFill = {
    params ["_list"];
    private _c = 94519 call _ctrl;
    lbClear _c;
    {
        _x params ["_id", "_label", "_src", "_when"];
        private _i = _c lbAdd format ["%1 · %2%3", _label, ("KeptSrc_" + _src) call _L, ["", " · " + _when] select (_when isNotEqualTo "")];
        _c lbSetData [_i, _id];
    } forEach _list;
    if (_list isEqualTo []) then { private _i = _c lbAdd ("KeptNone" call _L); _c lbSetData [_i, ""]; };
    _c lbSetCurSel 0;
    ["keptSel"] call comspec_sse_fnc_editorAi;
};
private _renderDocs = {
    private _c = 94512 call _ctrl;
    _st set ["busyDocs", true];
    lnbClear _c;
    {
        _x params ["_id", "_type", "_num", "", "", "_kind", ["_country", ""]];
        private _r = _c lnbAddRow [("DocS_" + _type) call _L, _country, _num, ("DocKS_" + _kind) call _L];
        _c lnbSetData [[_r, 0], _id];
        if (_kind isEqualTo "forged") then { _c lnbSetColor [[_r, 3], [0.95, 0.45, 0.35, 1]]; };
    } forEach (_st getOrDefault ["docs", []]);
    _c lnbSetCurSelRow -1;
    _st set ["busyDocs", false];
    // « Pas encore de papier » seulement une fois la réponse du serveur reçue pour cette IA.
    (94529 call _ctrl) ctrlShow ((_st getOrDefault ["docs", []]) isEqualTo [] && {_st getOrDefault ["docsKnown", false]});
    ["docSel"] call comspec_sse_fnc_editorAi;
};
// Réglages de la fiche posés d'après l'IA (une seule cochée) : profil, papiers portés, nature, comportement ; camp inchangé.
private _loadRow = {
    params ["_net"];
    private _row = [_net] call _rowOf;
    if (_row isEqualTo []) exitWith {};
    if ((_row select 7) isNotEqualTo "") then { (94554 call _ctrl) ctrlSetText ""; ["profFilter"] call comspec_sse_fnc_editorAi; };
    [94532, _row select 7] call _pick;
    [94534, _row select 12] call _pick;
    [94536, _row select 13] call _pick;
    [94538, _row select 9] call _pick;
    [94540, "auto"] call _pick;
};
private _selDoc = { private _c = 94512 call _ctrl; private _r = lnbCurSelRow _c; if (_r < 0) exitWith { "" }; _c lnbData [_r, 0] };
private _apply = {
    params ["_group", "_keep"];
    private _units = call _checkedUnits;
    if (_units isEqualTo []) exitWith { ["NeedCheck" call _L, "WARNING"] call _note; };
    if (_group) then {
        private _all = [];
        { { _all pushBackUnique _x; } forEach (units group _x); } forEach _units;
        _units = _all select { !isPlayer _x && {alive _x} };
    };
    [format ["Applying" call _L, count _units]] call _note;
    ["apply", [_units, call _spec, _keep]] call _send;
};

switch (_op) do {
    case "load": {
        { _st set [_x, []]; } forEach ["checked", "rows", "docs", "profiles", "scanArgs"];
        { _st set [_x, false]; } forEach ["scanned", "docsKnown", "busy", "busyDocs"];
        _st set ["cur", ""];
        [94502, [], "", [["25", "25 m"], ["50", "50 m"], ["100", "100 m"], ["250", "250 m"], ["500", "500 m"]]] call _fill;
        (94502 call _ctrl) lbSetCurSel 1;
        [94508, ["civ", "west", "east", "guer"], "Side_", [["", "FSide_All" call _L]]] call _fill;
        [94509, _roles, "Role_", [["", "FRole_All" call _L]]] call _fill;
        [94522, _states, "State_", [["", "FState_All" call _L]]] call _fill;
        [94534, _papers, "Papers_"] call _fill;
        [94536, _kinds, "Kind_"] call _fill;
        [94538, _behav, "Beh_"] call _fill;
        [94540, _sides, "Side_"] call _fill;
        [94516, _docTypes, "Doc_"] call _fill;
        [94532, [], "", [["", "ProfGenerate" call _L]]] call _fill;
        (94510 call _ctrl) lnbSetColumnsPos [0, 0.03, 0.25, 0.42, 0.52, 0.575, 0.66, 0.78, 0.845];
        (94512 call _ctrl) lnbSetColumnsPos [0.01, 0.31, 0.49, 0.79];
        { (_x call _ctrl) ctrlSetText ""; } forEach [94507, 94554, 94003, 94528, 94548];
        [0, "", false] call _setStatus;
        [[]] call _keptFill;
        { (_x call _ctrl) ctrlEnable false; } forEach [94513, 94514, 94515, 94517];
        call _fitAll;
        call _render;
        ["sheet"] call comspec_sse_fnc_editorAi;
        private _ctx = (uiNamespace getVariable ["comspec_sse_editorState", createHashMap]) getOrDefault ["ctx", [[], objNull]];
        private _o = _ctx param [1, objNull];
        // Module posé sur une IA : tout son groupe est listé. Sinon, sélection du Zeus, puis les IA autour.
        private _sel = (curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} };
        if (!isNull _o && {_o isKindOf "CAManBase"}) then { _sel = units group _o; };
        // Liste donnée par l'appelant (fn_aiConfigOpen) : elle prime, une seule fois.
        private _given = uiNamespace getVariable ["comspec_sse_editorAiUnits", []];
        uiNamespace setVariable ["comspec_sse_editorAiUnits", []];
        if (_given isNotEqualTo []) then { _sel = _given; };
        if (_sel isNotEqualTo []) then { ["scanUnits", _sel] call comspec_sse_fnc_editorAi; } else { ["scan"] call comspec_sse_fnc_editorAi; };
    };
    case "scan": {
        private _args = [call _ctxPos, parseNumber (94502 call _data), []];
        _st set ["scanArgs", _args];
        ["scan", _args] call _send;
    };
    case "scanZeus": {
        private _sel = (curatorSelected param [0, []]) select { _x isKindOf "CAManBase" && {!isPlayer _x} };
        if (_sel isEqualTo []) exitWith { ["NoZeusSel" call _L, "WARNING"] call _note; };
        ["scanUnits", _sel] call comspec_sse_fnc_editorAi;
    };
    case "scanUnits": {
        private _args = [[], 0, _arg select { !isNull _x }];
        _st set ["scanArgs", _args];
        ["scan", _args] call _send;
    };
    case "rescan": {
        private _args = _st getOrDefault ["scanArgs", []];
        if (_args isEqualTo []) exitWith { ["scan"] call comspec_sse_fnc_editorAi; };
        if ((_args select 2) isNotEqualTo []) then { _args set [2, (_args select 2) select { !isNull _x && {alive _x} }]; };
        ["scan", _args] call _send;
    };
    case "filter": { call _render; };
    case "click": {
        if (_st getOrDefault ["busy", false]) exitWith {};
        private _l = 94510 call _ctrl;
        private _r = lnbCurSelRow _l;
        if (_r < 0) exitWith {};
        private _net = _l lnbData [_r, 0];
        private _checked = _st getOrDefault ["checked", []];
        private _on = !(_net in _checked);
        if (_on) then { _checked pushBack _net; } else { _checked = _checked - [_net]; };
        _st set ["checked", _checked];
        _st set ["cur", _net];
        // Ligne relâchée tout de suite : un second clic sur la même ligne doit aussi déclencher l'évènement.
        _st set ["busy", true];
        [_l, _r, _on] call _setRowMark;
        _l lnbSetCurSelRow -1;
        _st set ["busy", false];
        call _counts;
        if ((count _checked) <= 1) then { [_net] call _loadRow; };
        ["sheet"] call comspec_sse_fnc_editorAi;
    };
    case "checkAll": {
        private _l = 94510 call _ctrl;
        private _nets = [];
        for "_i" from 0 to (((lnbSize _l) select 0) - 1) do { _nets pushBack (_l lnbData [_i, 0]); };
        _st set ["checked", _nets];
        if !((_st getOrDefault ["cur", ""]) in _nets) then { _st set ["cur", _nets param [0, ""]]; };
        call _render;
        if ((count _nets) isEqualTo 1) then { [_nets select 0] call _loadRow; };
        ["sheet"] call comspec_sse_fnc_editorAi;
    };
    case "checkNone": {
        _st set ["checked", []];
        call _render;
        ["sheet"] call comspec_sse_fnc_editorAi;
    };
    case "sheet": {
        if (isNull _d) exitWith {};
        private _cur = _st getOrDefault ["cur", ""];
        private _row = [_cur] call _rowOf;
        private _has = _row isNotEqualTo [];
        (94517 call _ctrl) ctrlEnable _has;
        _st set ["docs", []];
        _st set ["docsKnown", false];
        if (!_has) exitWith {
            (94530 call _ctrl) ctrlSetText ("SheetNone" call _L);
            (94549 call _ctrl) ctrlSetText "";
            call _renderDocs;
            ["preview"] call comspec_sse_fnc_editorAi;
        };
        _row params ["", "_given", "_true", "_truth", "_side", "", "", "", "", "", "_grp", "_uname"];
        private _nm = [_given, "(" + ("Truth_pending" call _L) + ")"] select (_given isEqualTo "");
        (94530 call _ctrl) ctrlSetText (format ["Sheet" call _L, _nm] + (["", " " + ("SheetUnchecked" call _L)] select !(_cur in (_st getOrDefault ["checked", []]))));
        [94530 call _ctrl] call _fit;
        (94549 call _ctrl) ctrlSetText (format ["SheetTruth" call _L, ("Truth_" + _truth) call _L, _true] + " · " + format ["Unit" call _L, _uname, _grp, ("Side_" + _side) call _L]);
        call _renderDocs;
        private _u = objectFromNetId _cur;
        if (!isNull _u) then { ["docs", [_u]] call _send; };
        ["preview"] call comspec_sse_fnc_editorAi;
    };
    case "docSel": {
        private _on = (lnbCurSelRow (94512 call _ctrl)) >= 0;
        { (_x call _ctrl) ctrlEnable _on; } forEach [94513, 94514, 94515];
    };
    case "keptSel": {
        private _on = (94519 call _data) isNotEqualTo "";
        { (_x call _ctrl) ctrlEnable _on; } forEach [94521, 94520];
    };
    case "profFilter": {
        private _c = 94532 call _ctrl;
        private _keep = 94532 call _data;
        private _q = toLower (trim (ctrlText (94554 call _ctrl)));
        lbClear _c;
        private _i0 = _c lbAdd ("ProfGenerate" call _L);
        _c lbSetData [_i0, ""];
        {
            _x params ["_id", "_ref", "_label", "_name"];
            private _t = format ["%1 · %2%3", _ref, _label, ["", format [" (%1)", _name]] select (_name isNotEqualTo "")];
            if (_q isEqualTo "" || {((toLower _t) find _q) >= 0} || {_id isEqualTo _keep}) then { private _i = _c lbAdd _t; _c lbSetData [_i, _id]; };
        } forEach (_st getOrDefault ["profiles", []]);
        _c lbSetCurSel 0;
        [94532, _keep] call _pick;
    };
    case "preview": {
        if (isNull _d) exitWith {};
        private _t = 94543 call _ctrl;
        private _grp = 94573 call _ctrl;
        private _row = [_st getOrDefault ["cur", ""]] call _rowOf;
        if (_row isEqualTo []) exitWith { _t ctrlSetStructuredText parseText ""; };
        _row params ["", "_given", "_true", "_truth", "", "", "", "_rowPid", "", "", "", "", "_rowCarry", "_rowKind"];
        private _pid = 94532 call _data;
        private _p = (_st getOrDefault ["profiles", []]) select { (_x select 0) isEqualTo _pid };
        private _docs = _st getOrDefault ["docs", []];
        private _who = _given;
        if (_who isEqualTo "" && {_p isNotEqualTo []}) then { _who = (_p select 0) select 3; };
        private _nameLine = if (_who isEqualTo "") then { format ["PrevNamePending" call _L, [_true] call _esc] } else { format ["PrevName" call _L, [_who] call _esc] };
        private _carry = 94534 call _data;
        private _kind = 94536 call _data;
        private _docLine = if (_pid isNotEqualTo "") then { "PrevPapersProfile" call _L } else { format ["%1 · %2", ([_carry] call _papersLabel), ("Kind_" + _kind) call _L] };
        private _bh = ("Beh_" + (94538 call _data)) call _L;
        private _seen = _docs apply { _x params ["", "_type", "_num", "", "", "", ["_country", ""]]; format ["• %1 %2%3", [_type] call _docLabel, [_num] call _esc, ["", " · " + ([_country] call _esc)] select (_country isNotEqualTo "")] };
        private _truthDocs = _docs apply { _x params ["", "_type", "_num", "", "", "_k"]; format ["• %1 %2 : %3", [_type] call _docLabel, [_num] call _esc, ("DocK_" + _k) call _L] };
        private _truthLine = if (_truth isEqualTo "true") then { "PrevSame" call _L } else { format ["PrevTrue" call _L, [_true] call _esc, ("Truth_" + _truth) call _L] };
        // Réglages différents de ce que l'IA porte : ce qui changera à « Appliquer ».
        private _after = if (_pid isNotEqualTo _rowPid || {_pid isEqualTo "" && {_carry isNotEqualTo _rowCarry || {_kind isNotEqualTo _rowKind}}}) then { "<br/>" + format ["PrevAfterApply" call _L, _docLine, _bh] } else { "" };
        _t ctrlSetStructuredText parseText format [
            "<t font='PuristaBold' size='1.05' color='#1b1c19'>%1</t><br/><t font='PuristaBold' size='1.25' color='#1b1c19'>%2</t><br/><t color='#1b1c19'>%3</t><br/><t color='#1b1c19'>%4</t>%5<br/><t size='0.85' color='#4a4c45'>%6</t><br/><br/><t font='PuristaBold' size='1.05' color='#7a2a1e'>%7</t><br/><t color='#1b1c19'>%8</t>%9%10",
            "PrevPlayer" call _L, _nameLine, format ["PrevPapers" call _L, _docLine], format ["PrevBeh" call _L, _bh], ["", "<br/>" + (_seen joinString "<br/>")] select (_seen isNotEqualTo []),
            "PrevN3" call _L, "PrevZeus" call _L, _truthLine, ["", "<br/>" + (_truthDocs joinString "<br/>")] select (_truthDocs isNotEqualTo []), _after
        ];
        // Hauteur réelle du texte : le groupe défile s'il dépasse (aucun texte coupé).
        private _w = (ctrlPosition _t) select 2;
        private _gh = (ctrlPosition _grp) select 3;
        _t ctrlSetPosition [0, 0, _w, _gh];
        _t ctrlCommit 0;
        _t ctrlSetPosition [0, 0, _w, (ctrlTextHeight _t) max _gh];
        _t ctrlCommit 0;
    };
    case "applyChecked": { [false, false] call _apply; };
    case "applyGroup": { [true, false] call _apply; };
    case "keepChecked": { [false, true] call _apply; };
    case "docOpen": {
        private _id = call _selDoc;
        if (_id isEqualTo "") exitWith {};
        ["docOpen", [_id]] call _sendId;
    };
    case "docTrue";
    case "docFalse": {
        private _u = objectFromNetId (_st getOrDefault ["cur", ""]);
        private _id = call _selDoc;
        if (isNull _u || {_id isEqualTo ""}) exitWith {};
        ["docMark", [_u, _id, ["civil", "forged"] select (_op isEqualTo "docFalse")]] call _send;
        ["rescan"] call comspec_sse_fnc_editorAi;
    };
    case "docGive": {
        private _u = objectFromNetId (_st getOrDefault ["cur", ""]);
        if (isNull _u) exitWith {};
        ["docAdd", [_u, 94516 call _data]] call _send;
        ["rescan"] call comspec_sse_fnc_editorAi;
    };
    case "keptReload": {
        private _id = 94519 call _data;
        if (_id isEqualTo "") exitWith {};
        ["restoreOne", [_id]] call _send;
    };
    case "keptForget": {
        private _id = 94519 call _data;
        if (_id isEqualTo "") exitWith {};
        ["unkeep", [_id]] call _send;
    };
    case "refresh": {
        // Le serveur recharge le paquet d'identité (profils et gardés d'Athena), puis la liste est relue.
        ["reload"] call _sendId;
        ["Refreshing" call _L] call _note;
        { [{ if (!isNull (uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull])) then { ["rescan"] call comspec_sse_fnc_editorAi; }; }, [], _x] call CBA_fnc_waitAndExecute; } forEach [4, 12];
    };
    case "help": {
        private _cfg = configFile >> "CfgHints" >> "COMSPEC_SSE" >> "AiZone";
        if (!isClass _cfg || {isNull _d}) exitWith { if (!isNil "comspec_sse_fnc_openGuide") then { ["AiZone"] call comspec_sse_fnc_openGuide; }; };
        // Même rendu que fn_openGuide au Zeus, posé sur cette fenêtre : un chapitre ouvert derrière le dialogue serait invisible.
        private _body = format [getText (_cfg >> "description"), "<br/>", "• ", "<t color='#7fd08a'>", "</t>"];
        private _tip = getText (_cfg >> "tip");
        if (_tip isNotEqualTo "") then { _body = _body + format ["<br/><br/><t color='#c9d3dc' size='0.9'>%1</t>", _tip]; };
        _body = _body + format ["<br/><br/><t color='#8a96a3' size='0.85'>%1</t>", "HelpMore" call _L];
        [parseText (_body call comspec_sse_fnc_tr), getText (_cfg >> "displayName"), true, false, _d, false, false] spawn BIS_fnc_guiMessage;
    };
    // --- Réponses du serveur (fn_editorAiServer ; « docs » peut aussi venir de l'addon identity, sans le pays).
    case "aiReply": {
        if (isNull _d) exitWith {};
        _arg params [["_kind", ""], ["_p1", []], ["_p2", []], ["_p3", []], ["_p4", []]];
        switch (_kind) do {
            case "rows": {
                private _rows = _p1 select { _x isEqualType [] && {(count _x) >= 15} };
                _st set ["rows", _rows];
                _st set ["scanned", true];
                private _nets = _rows apply { _x select 0 };
                private _checked = (_st getOrDefault ["checked", []]) select { _x in _nets };
                // Une seule IA (ouverte depuis le centre Zeus sur elle) : cochée d'office.
                if (_checked isEqualTo [] && {(count _nets) isEqualTo 1}) then { _checked = +_nets; };
                _st set ["checked", _checked];
                if !((_st getOrDefault ["cur", ""]) in _nets) then { _st set ["cur", _checked param [0, _nets param [0, ""]]]; };
                _st set ["profiles", _p2];
                ["profFilter"] call comspec_sse_fnc_editorAi;
                _p3 call _setStatus;
                [_p4] call _keptFill;
                call _render;
                if ((count _checked) <= 1) then { [_st get "cur"] call _loadRow; };
                ["sheet"] call comspec_sse_fnc_editorAi;
            };
            case "status": { _p1 call _setStatus; };
            case "docs": {
                if (_p1 isEqualType "" && {_p1 isEqualTo (_st getOrDefault ["cur", ""])}) then {
                    _st set ["docs", _p2];
                    _st set ["docsKnown", true];
                    call _renderDocs;
                    ["preview"] call comspec_sse_fnc_editorAi;
                };
            };
            case "applied": {
                private _t = format ["Applied" call _L, _p1];
                if (_p2 isEqualType 0 && {_p2 > 0}) then { _t = _t + " " + format ["KeptOk" call _L, _p2]; };
                [_t, "SUCCESS"] call _note;
                ["rescan"] call comspec_sse_fnc_editorAi;
            };
            case "note": {
                if !(_p1 isEqualType "") exitWith {};
                if !(_p2 isEqualType []) then { _p2 = []; };
                if !(_p3 isEqualType "" && {_p3 isNotEqualTo ""}) then { _p3 = "INFO"; };
                [format ([_p1 call _L] + _p2), _p3] call _note;
                if (_p1 in ["KeptAthenaOk", "KeptAthenaKo", "Forgot"]) then { ["rescan"] call comspec_sse_fnc_editorAi; };
            };
            case "kept": { [_p1] call _keptFill; };
            case "athenaPost": {
                // Le serveur n'a pas la liaison : la copie sur Athena part de ce poste (DLL du Zeus), contexte planifié.
                _p1 spawn {
                    private _ok = 0;
                    {
                        _x params ["_payload", "_lid"];
                        private _raw = ["post", _payload] call comspec_sse_fnc_editorAi;
                        if ((_raw select [0, 3]) isEqualTo "OK|") then {
                            _ok = _ok + 1;
                            if (_lid isNotEqualTo "") then { ["athenaDone", [_lid, (_raw splitString "|") param [1, ""]]] call comspec_sse_fnc_editorAi; };
                        };
                    } forEach _this;
                    ["aiReply", ["note", ["KeptAthenaKo", "KeptAthenaOk"] select (_ok isEqualTo (count _this)), [], ["WARNING", "SUCCESS"] select (_ok isEqualTo (count _this))]] call comspec_sse_fnc_editorAi;
                };
            };
        };
    };
    case "athenaDone": { ["athenaDone", _arg] call _send; };
    // --- Athena depuis ce poste (contexte planifié) : élément gardé de l'éditeur (fn_editor « placed »), copie d'une IA gardée.
    case "keepItem": {
        if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_editorAi; };
        _arg params [["_it", createHashMap], ["_where", []]];
        private _pairs = [];
        { if (_x in _it) then { _pairs pushBack [_x, _it get _x]; }; } forEach ["id", "type", "title", "body", "marking", "paper", "nature", "delivery"];
        ["post", createHashMapFromArray [["kind", "ai_keep"], ["place", createHashMapFromArray [
            ["kind", "item"], ["x", _where select 0], ["y", _where select 1], ["label", _it get "title"], ["config", createHashMapFromArray [["item", _pairs]]]
        ]]]] call comspec_sse_fnc_editorAi;
    };
};
true
