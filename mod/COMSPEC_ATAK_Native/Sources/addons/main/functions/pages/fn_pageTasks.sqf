/*
    Tâches : ordres Athena en cartes (statut en couleur, boutons REÇU / EN COURS / TERMINÉ / IMPOSSIBLE,
    suivi de qui a vu, reçu, exécute avec photo ; le chef répond pour son groupe)
    et tâches de la mission (état, distance, carte, tâche suivie). Reprend le visionneur de tâches de BCE (Aaren, APL-SA).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _icon = "\z\comspec_atak_native\addons\main\data\app_tasks.paa";
// DELIVERED = « vu » côté Athena (transmis au téléphone) ; TERMINÉ = DONE.
private _labels = createHashMapFromArray [["ACK", "REÇU"], ["EXEC", "EN COURS"], ["DONE", "TERMINÉ"], ["DELIVERED", "VU"], ["PENDING", "TRANSMIS"], ["CANCELLED", "ANNULÉ"], ["FAILED", "IMPOSSIBLE"], ["SUCCEEDED", "RÉUSSIE"], ["CANCELED", "ANNULÉE"], ["CREATED", "NOUVELLE"], ["ASSIGNED", "ASSIGNÉE"], ["NEW", "NOUVEAU"]];
private _colorOf = {
    params ["_st"];
    switch (true) do {
        case (_st in ["DONE", "SUCCEEDED"]): { [[0.36, 0.78, 0.42, 1], "#5cc76b"] };
        case (_st in ["FAILED", "CANCELED", "CANCELLED"]): { [[0.58, 0.64, 0.60, 1], "#94a399"] };
        case (_st in ["ACK", "EXEC", "ASSIGNED"]): { [[0.95, 0.67, 0.20, 1], "#f2ab33"] };
        default { [[0.88, 0.25, 0.22, 1], "#e04038"] };
    }
};
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _s0 = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _isLead = (leader group player) isEqualTo player && {({isPlayer _x} count (units group player)) > 1};
// Destinataires attendus d'un ordre : groupe, joueur ou indicatif visé ; tout mon camp si l'ordre est pour tous.
private _expected = {
    params ["_o"];
    private _tg = _o getOrDefault ["target", ""];
    if !(_tg isEqualType "") then { _tg = str _tg; };
    _tg = toLower trim _tg;
    private _tt = toLower (_o getOrDefault ["targetType", "all"]);
    private _pl = allPlayers select { side group _x isEqualTo side group player && {alive _x} };
    if (_tg in ["", "-", "all", "tous"] || {_tt isEqualTo "all" && {_tg isEqualTo ""}}) exitWith { _pl };
    _pl select { (toLower groupId group _x) isEqualTo _tg || {(toLower name _x) isEqualTo _tg} || {(toLower ([_x, true] call comspec_atak_native_fnc_unitCallsign)) isEqualTo _tg} }
};
if (_s0 getOrDefault ["orderCompose", false]) exitWith { [] call comspec_atak_native_fnc_pageOrderCompose };
private _orders = [] call comspec_atak_native_fnc_tasksAll;
private _open = 0;
{ if !((toUpper (_y getOrDefault ["status", "NEW"])) in ["DONE", "FAILED", "SUCCEEDED", "CANCELED", "CANCELLED"]) then { _open = _open + 1; }; } forEach _orders;
private _canOrder = ([] call comspec_atak_native_fnc_bridge) && {[] call comspec_overwatch_connect_fnc_canIssueOrder};
private _rows = [];
if (_canOrder) then { _rows pushBack ["buttons", [["NOUVEL ORDRE", { ['open'] call comspec_atak_native_fnc_orderAction; }, true]]]; };
_rows append [["section", "Ordres du TOC", [format ["%1 ordre(s), %2 à traiter", count _orders, _open], "Aucun ordre reçu d'Athena"] select ((count _orders) isEqualTo 0)]];
{
    private _id = _x;
    private _ord = _y;
    private _st = toUpper (_y getOrDefault ["status", "NEW"]);
    ([_st] call _colorOf) params ["_rgb", "_hex"];
    private _label = _y getOrDefault ["typeLabel", ""];
    if (_label isEqualTo "") then { _label = _y getOrDefault ["type", "ORDRE"]; };
    private _prio = toUpper (_y getOrDefault ["priority", "NORMAL"]);
    private _payload = _y getOrDefault ["payload", ""];
    if !(_payload isEqualType "") then { _payload = str _payload; };
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='%2' size='0.85'>%3</t>%4<br/><t size='0.8' color='#8a9a93'>De %5 · cible %6</t>%7",
        [_label] call _esc, _hex, _labels getOrDefault [_st, _st], ["", "  <t color='#e04038' size='0.85'>PRIORITAIRE</t>"] select (_prio in ["HIGH", "URGENT", "FLASH", "PRIORITY"]),
        [_y getOrDefault ["issuer", "TOC"]] call _esc, [_y getOrDefault ["target", "-"]] call _esc,
        ["", format ["<br/><t size='0.85'>%1</t>", [_payload select [0, 220]] call _esc]] select (_payload isNotEqualTo "")], [], _rgb];
    private _btn = {
        params ["_k", "_t"];
        [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['selectedTask', %1]; ['%2'] call comspec_atak_native_fnc_taskAction;", str _id, _k], _st isEqualTo _k]
    };
    _rows pushBack ["segment", ["", format ["Chef : votre réponse vaut pour le groupe %1", [groupId group player] call _esc]] select _isLead, [["ACK", "REÇU"] call _btn, ["EXEC", "EN COURS"] call _btn, ["DONE", "TERMINÉ"] call _btn, ["FAILED", "IMPOSSIBLE"] call _btn]];
    // Suivi : qui a vu, reçu, exécute (réponses du camp, fn_orderAck) ; « VU » envoyé à l'affichage.
    ["seen", [_id]] call comspec_atak_native_fnc_orderAck;
    private _acks = ["list", [_id]] call comspec_atak_native_fnc_orderAck;
    private _who = [_ord] call _expected;
    private _uids = _who apply { getPlayerUID _x };
    private _extra = (keys _acks) select { !(_x in _uids) };
    private _cnt = createHashMap;
    { private _k = _y select 1; _cnt set [_k, (_cnt getOrDefault [_k, 0]) + 1]; } forEach _acks;
    private _total = (count _who) + (count _extra);
    private _sum = format ["<t size='0.8' color='#8a9a93'>Vu %1/%5 · reçu %2 · en cours %3 · terminé %4</t>",
        count _acks, (_cnt getOrDefault ["ACK", 0]), (_cnt getOrDefault ["EXEC", 0]), (_cnt getOrDefault ["DONE", 0]) + (_cnt getOrDefault ["FAILED", 0]), _total max (count _acks)];
    private _whoOpen = (_s0 getOrDefault ["taskWho", ""]) isEqualTo _id;
    _rows pushBack ["person", "\a3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa", _sum,
        [[["QUI ?", "MASQUER"] select _whoOpen, compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['taskWho', [%1, ''] select ((_s getOrDefault ['taskWho', '']) isEqualTo %1)]; ['TASK'] call comspec_atak_native_fnc_pageRender;", str _id], _whoOpen]], [0.55, 0.62, 0.58, 1]];
    if (_whoOpen) then {
        private _list = (_who apply { [getPlayerUID _x, name _x, groupId group _x, _x] }) + (_extra apply { private _a = _acks get _x; [_x, _a select 0, _a select 4, objNull] });
        {
            _x params ["_uid", "_nm", "_gn", "_u"];
            if (_forEachIndex >= 16) exitWith { _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>… et %1 autre(s)</t>", (count _list) - 16]]; };
            private _a = _acks getOrDefault [_uid, []];
            private _st2 = _a param [1, ""];
            ([switch (_st2) do { case "": { "NEW" }; case "SEEN": { "CANCELED" }; default { _st2 }; }] call _colorOf) params ["_rgb2", "_hex2"];
            private _stTxt = switch (true) do {
                case (_st2 isEqualTo ""): { "pas encore vu" };
                case (_st2 isEqualTo "SEEN"): { "vu" };
                default { toLower (_labels getOrDefault [_st2, _st2]) };
            };
            private _pic = "";
            if (!isNull _u) then { _pic = [_u] call comspec_atak_native_fnc_avatarPath; if (_pic isEqualTo "") then { _pic = [_u, "texture"] call BIS_fnc_rankParams; }; };
            if (_pic isEqualTo "") then { _pic = "\a3\ui_f\data\map\vehicleicons\iconman_ca.paa"; };
            _rows pushBack ["person", _pic, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='%2' size='0.85'>%3</t><br/><t size='0.8' color='#8a9a93'>%4%5%6</t>",
                [_nm] call _esc, _hex2, _stTxt, [_gn] call _esc, ["", format [" · %1", _a param [2, ""]]] select ((_a param [2, ""]) isNotEqualTo ""),
                ["", format [" · par le chef %1", [_a param [3, ""]] call _esc]] select ((_a param [3, ""]) isNotEqualTo "")], [], [1, 1, 1, 1]];
        } forEach _list;
    };
} forEach _orders;

// Tâches de la mission : la tâche suivie est en tête.
private _tasks = simpleTasks player;
private _curT = currentTask player;
_rows pushBack ["section", "Tâches de la mission", [format ["%1 tâche(s)", count _tasks], "Aucune tâche de mission"] select ((count _tasks) isEqualTo 0)];
{
    private _t = _x;
    private _st = toUpper taskState _t;
    ([_st] call _colorOf) params ["_rgb", "_hex"];
    private _desc = taskDescription _t;
    private _dest = taskDestination _t;
    private _has = (_dest isEqualType [] && {(count _dest) >= 2} && {(_dest select 0) != 0 || {(_dest select 1) != 0}});
    private _dist = ["", format [" · %1 m", round (player distance2D _dest)]] select _has;
    private _body = (_desc select 0) regexReplace ["<[^>]*>", ""];
    private _isCur = _t isEqualTo _curT;
    private _idx = _forEachIndex;
    private _btns = [];
    if (_has) then { _btns pushBack ["CARTE", compile format ["private _p = taskDestination ((simpleTasks player) select %1); ['MAP'] call comspec_atak_native_fnc_navigate; [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;", _idx]]; };
    _btns pushBack [["SUIVRE", "SUIVIE"] select _isCur, compile format ["player setCurrentTask ((simpleTasks player) select %1); ['TASK'] call comspec_atak_native_fnc_pageRender;", _idx], !_isCur, !_isCur];
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold' %1>%2</t>  <t color='%3' size='0.85'>%4</t><br/><t size='0.8' color='#8a9a93'>Tâche de mission%5</t>%6",
        ["", format ["color='%1'", _accHex]] select _isCur, [_desc select 1] call _esc, _hex, _labels getOrDefault [_st, _st], _dist,
        ["", format ["<br/><t size='0.8'>%1</t>", [_body select [0, 200]] call _esc]] select (_body isNotEqualTo "")], _btns, _rgb];
} forEach _tasks;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
