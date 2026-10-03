/*
    Tâches : ordres Athena en cartes (statut en couleur, boutons REÇU / EN COURS / TERMINÉ / IMPOSSIBLE)
    et tâches de la mission (état, distance, carte, tâche suivie). Reprend le visionneur de tâches de BCE (Aaren, APL-SA).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _icon = "\z\comspec_atak_native\addons\main\data\app_tasks.paa";
private _labels = createHashMapFromArray [["ACK", "REÇU"], ["EXEC", "EN COURS"], ["DELIVERED", "TERMINÉ"], ["FAILED", "IMPOSSIBLE"], ["SUCCEEDED", "RÉUSSIE"], ["CANCELED", "ANNULÉE"], ["CREATED", "NOUVELLE"], ["ASSIGNED", "ASSIGNÉE"], ["NEW", "NOUVEAU"]];
private _colorOf = {
    params ["_st"];
    switch (true) do {
        case (_st in ["DELIVERED", "SUCCEEDED"]): { [[0.36, 0.78, 0.42, 1], "#5cc76b"] };
        case (_st in ["FAILED", "CANCELED"]): { [[0.58, 0.64, 0.60, 1], "#94a399"] };
        case (_st in ["ACK", "EXEC", "ASSIGNED"]): { [[0.95, 0.67, 0.20, 1], "#f2ab33"] };
        default { [[0.88, 0.25, 0.22, 1], "#e04038"] };
    }
};
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _s0 = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (_s0 getOrDefault ["orderCompose", false]) exitWith { [] call comspec_atak_native_fnc_pageOrderCompose };
private _orders = [] call comspec_atak_native_fnc_tasksAll;
private _open = 0;
{ if !((toUpper (_y getOrDefault ["status", "NEW"])) in ["DELIVERED", "FAILED", "SUCCEEDED", "CANCELED"]) then { _open = _open + 1; }; } forEach _orders;
private _canOrder = ([] call comspec_atak_native_fnc_bridge) && {[] call comspec_overwatch_connect_fnc_canIssueOrder};
private _rows = [];
if (_canOrder) then { _rows pushBack ["buttons", [["NOUVEL ORDRE", { ['open'] call comspec_atak_native_fnc_orderAction; }, true]]]; };
_rows append [["section", "Ordres du TOC", [format ["%1 ordre(s), %2 à traiter", count _orders, _open], "Aucun ordre reçu d'Athena"] select ((count _orders) isEqualTo 0)]];
{
    private _id = _x;
    private _st = toUpper (_y getOrDefault ["status", "NEW"]);
    ([_st] call _colorOf) params ["_rgb", "_hex"];
    private _label = _y getOrDefault ["typeLabel", ""];
    if (_label isEqualTo "") then { _label = _y getOrDefault ["type", "ORDRE"]; };
    private _prio = toUpper (_y getOrDefault ["priority", "NORMAL"]);
    private _payload = _y getOrDefault ["payload", ""];
    if !(_payload isEqualType "") then { _payload = str _payload; };
    _rows pushBack ["person", _icon, format ["<t font='RobotoCondensedBold'>%1</t>  <t color='%2' size='0.85'>%3</t>%4<br/><t size='0.8' color='#8a9a93'>De %5 · cible %6</t>%7",
        [_label] call _esc, _hex, _labels getOrDefault [_st, _st], ["", "  <t color='#e04038' size='0.85'>PRIORITAIRE</t>"] select (_prio in ["HIGH", "URGENT", "FLASH", "PRIORITY"]),
        [_y getOrDefault ["issuer", "TOC"]] call _esc, [_y getOrDefault ["target", "-"]] call _esc,
        ["", format ["<br/><t size='0.85'>%1</t>", [_payload select [0, 220]] call _esc]] select (_payload isNotEqualTo "")], [], _rgb];
    private _btn = {
        params ["_k", "_t"];
        [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['selectedTask', %1]; ['%2'] call comspec_atak_native_fnc_taskAction;", str _id, _k], _st isEqualTo _k]
    };
    _rows pushBack ["segment", "", [["ACK", "REÇU"] call _btn, ["EXEC", "EN COURS"] call _btn, ["DELIVERED", "TERMINÉ"] call _btn, ["FAILED", "IMPOSSIBLE"] call _btn]];
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
    _rows pushBack ["person", _icon, format ["<t font='RobotoCondensedBold' %1>%2</t>  <t color='%3' size='0.85'>%4</t><br/><t size='0.8' color='#8a9a93'>Tâche de mission%5</t>%6",
        ["", format ["color='%1'", _accHex]] select _isCur, [_desc select 1] call _esc, _hex, _labels getOrDefault [_st, _st], _dist,
        ["", format ["<br/><t size='0.8'>%1</t>", [_body select [0, 200]] call _esc]] select (_body isNotEqualTo "")], _btns, _rgb];
} forEach _tasks;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
