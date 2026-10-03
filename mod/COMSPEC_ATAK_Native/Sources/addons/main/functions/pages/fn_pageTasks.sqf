/*
    Tâches : ordres Athena (actions REÇU / EN COURS / TERMINÉ / IMPOSSIBLE) et tâches de la mission (lecture seule).
    Reprend le visionneur de tâches de BCE (Aaren, APL-SA).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _rowH = _font * 1.6;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];

private _labels = createHashMapFromArray [["ACK", "REÇU"], ["EXEC", "EN COURS"], ["DELIVERED", "TERMINÉ"], ["FAILED", "IMPOSSIBLE"], ["SUCCEEDED", "RÉUSSIE"], ["CANCELED", "ANNULÉE"], ["CREATED", "CRÉÉE"], ["ASSIGNED", "ASSIGNÉE"]];
private _colorOf = {
    params ["_st"];
    switch (true) do {
        case (_st in ["DELIVERED", "SUCCEEDED"]): { [0.36, 0.78, 0.42, 1] };
        case (_st in ["FAILED", "CANCELED"]): { [0.58, 0.64, 0.60, 1] };
        case (_st in ["ACK", "EXEC", "ASSIGNED"]): { [0.95, 0.67, 0.20, 1] };
        default { [0.88, 0.25, 0.22, 1] };
    }
};

private _listH = (_bh - 2 * _pad) * 0.48;
private _list = ["COMSPEC_RscListBox", [_pad, _pad, _bw - 2 * _pad, _listH]] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _font;
private _rows = [];
{
    private _st = toUpper (_y getOrDefault ["status", "NEW"]);
    private _label = _y getOrDefault ["typeLabel", ""];
    if (_label isEqualTo "") then { _label = _y getOrDefault ["type", "ORDRE"]; };
    private _i = _list lbAdd format ["[%1] %2", _y getOrDefault ["priority", "NORMAL"], _label];
    _list lbSetTextRight [_i, _labels getOrDefault [_st, _st]];
    _list lbSetColor [_i, [_st] call _colorOf];
    _rows pushBack ["athena", _x, format ["<t color='#5cc76b'>%1</t><br/>Émis par %2 · priorité %3<br/>Cible : %4<br/><br/>%5", _label, _y getOrDefault ["issuer", "TOC"], _y getOrDefault ["priority", "NORMAL"], _y getOrDefault ["target", "-"], _y getOrDefault ["payload", ""]]];
} forEach (_data getOrDefault ["tasks", createHashMap]);
{
    private _st = toUpper taskState _x;
    private _desc = taskDescription _x;
    private _i = _list lbAdd format ["[MISSION] %1", _desc select 1];
    _list lbSetTextRight [_i, _labels getOrDefault [_st, _st]];
    _list lbSetColor [_i, [_st] call _colorOf];
    _rows pushBack ["mission", str _x, format ["<t color='#5cc76b'>%1</t><br/>Tâche de mission · statut géré par la mission<br/><br/>%2", _desc select 1, _desc select 0]];
} forEach (simpleTasks player);

private _detailY = _pad * 2 + _listH;
private _detailH = _bh - _detailY - _rowH - _pad * 2;
private _detail = ["COMSPEC_RscCard", [_pad, _detailY, _bw - 2 * _pad, _detailH]] call comspec_atak_native_fnc_pageCtrl;
uiNamespace setVariable ["COMSPEC_ATAK_TaskDetail", _detail];
uiNamespace setVariable ["COMSPEC_ATAK_TaskRows", _rows];

private _btnW = (_bw - 5 * _pad) / 4;
{
    _x params ["_status", "_text"];
    private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_btnW + _pad), _bh - _rowH - _pad, _btnW, _rowH], _text] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight (_l get "fontSmall");
    _b setVariable ["status", _status];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [_c getVariable "status"] call comspec_atak_native_fnc_taskAction; }];
} forEach [["ACK", "REÇU"], ["EXEC", "EN COURS"], ["DELIVERED", "TERMINÉ"], ["FAILED", "IMPOSSIBLE"]];

private _select = {
    params ["_index"];
    private _row = (uiNamespace getVariable ["COMSPEC_ATAK_TaskRows", []]) param [_index, []];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _detail = uiNamespace getVariable ["COMSPEC_ATAK_TaskDetail", controlNull];
    if ((count _row) isEqualTo 0) exitWith {
        _st set ["selectedTask", ""];
        _detail ctrlSetStructuredText parseText "<t color='#8a9a93'>Aucune tâche. Les ordres du TOC et les tâches de mission apparaîtront ici.</t>";
    };
    _st set ["selectedTask", ["", _row select 1] select ((_row select 0) isEqualTo "athena")];
    _st set ["selectedTaskKey", _row select 1];
    _detail ctrlSetStructuredText parseText (_row select 2);
};
uiNamespace setVariable ["COMSPEC_ATAK_TaskSelect", _select];

private _cur = (_rows findIf { (_x select 1) isEqualTo (_s getOrDefault ["selectedTaskKey", ""]) }) max 0;
if ((count _rows) > 0) then { _list lbSetCurSel _cur; };
[[-1, _cur] select ((count _rows) > 0)] call _select;
_list ctrlAddEventHandler ["LBSelChanged", { params ["", "_index"]; [_index] call (uiNamespace getVariable ["COMSPEC_ATAK_TaskSelect", {}]); }];
true
