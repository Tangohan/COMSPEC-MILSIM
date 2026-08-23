/*
    Remplit la liste TASK avec les ordres C2 (COMSPEC_Orders).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
if (isNull _group) exitWith {};

// Ne pas bloquer si showMenu a un nom légèrement différent (BCE / cTab).
private _page = (["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""];
if (
    _page isNotEqualTo ""
    && {!ctrlShown _group}
    && {!(_page in ["AtakTask", "COMSPEC_ATAK_Task", "atak_task", "task"])}
) exitWith {};

[] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;

private _list = _group controlsGroupCtrl 9902;
private _sum = _group controlsGroupCtrl 9901;
private _detail = _group controlsGroupCtrl 9903;
if (isNull _list) exitWith {};

private _prevId = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
private _selKeep = -1;

lbClear _list;

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
if (!(_orders isEqualType [])) then { _orders = []; };

private _rows = [];
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _id = _x getOrDefault ["id", ""];
    if (_id isEqualTo "") then { continue };
    private _type = toUpper (_x getOrDefault ["type", "MOVE"]);
    if (_type in ["VIBRATE", "NOTIFY", "HELMET_SNAP", "HELMET_SNAP_HD", "HELMET_STREAM"]) then { continue };
    if (!isNil "comspec_overwatch_connect_fnc_orderConcernsPlayer") then {
        if (!([_x] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) then { continue };
    };
    _rows pushBack _x;
} forEach _orders;

reverse _rows;

private _pending = 0;
{
    private _st = toUpper (_x getOrDefault ["status", "PENDING"]);
    if (!(_st in ["ACK", "EXEC", "DONE", "CLOSED", "FAILED", "CANCELLED", "DELIVERED"])) then {
        _pending = _pending + 1;
    };
} forEach _rows;

{
    private _id = _x getOrDefault ["id", ""];
    private _kind = "Ordre";
    if (!isNil "comspec_overwatch_connect_fnc_orderTypeLabel") then {
        private _lbl = [_x] call comspec_overwatch_connect_fnc_orderTypeLabel;
        if (_lbl isEqualType "" && {_lbl isNotEqualTo ""}) then { _kind = _lbl; };
    };
    private _who = _x getOrDefault ["issuer", "C2"];
    if (!(_who isEqualType "") || {_who isEqualTo ""}) then { _who = "C2"; };
    private _st = toUpper (_x getOrDefault ["status", "PENDING"]);
    private _stTxt = "À traiter";
    if (_st isEqualTo "ACK") then { _stTxt = "Accepté"; };
    if (_st isEqualTo "EXEC") then { _stTxt = "En cours"; };
    if (_st in ["DONE", "CLOSED"]) then { _stTxt = "Terminé"; };
    if (_st isEqualTo "FAILED") then { _stTxt = "Refusé"; };
    if (_st isEqualTo "CANCELLED") then { _stTxt = "Annulé"; };
    if (_st isEqualTo "DELIVERED") then { _stTxt = "Remis"; };
    private _mark = "";
    if ((toUpper (_x getOrDefault ["priority", "IMPORTANT"])) isEqualTo "URGENT") then { _mark = "! "; };
    private _idx = _list lbAdd format ["%1%2 · %3 · %4", _mark, _kind, _who, _stTxt];
    _list lbSetData [_idx, _id];
    if (_id isEqualTo _prevId) then { _selKeep = _idx; };
} forEach _rows;

if (!isNull _sum) then {
    private _n = count _rows;
    private _txt = if (_n < 1) then {
        "<t color='#c8d0d8'>Aucun ordre C2 pour le moment</t>"
    } else {
        format [
            "<t color='#ffd27a'>%1</t> ordre%2 · <t color='#9ed8b4'>%3</t> à traiter",
            _n,
            if (_n > 1) then { "s" } else { "" },
            _pending
        ]
    };
    _sum ctrlSetStructuredText parseText _txt;
};

if (_selKeep >= 0) then {
    _list lbSetCurSel _selKeep;
} else {
    if ((lbSize _list) > 0) then {
        _list lbSetCurSel 0;
    } else {
        if (!isNull _detail) then {
            _detail ctrlSetStructuredText parseText "<t color='#8aa0b4'>Les ordres du commandement apparaîtront ici dès leur réception.</t>";
        };
        uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", ""];
        [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
    };
};

if ((lbCurSel _list) >= 0) then {
    [_list, lbCurSel _list] call comspec_overwatch_atak_athena_fnc_athena_taskSelect;
} else {
    [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
};
