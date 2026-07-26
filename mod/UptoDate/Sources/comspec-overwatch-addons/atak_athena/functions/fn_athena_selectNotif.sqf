/*
    Sélection d’une notification dans la zone dédiée (panneau Athena cTab).
*/
params ["_control", "_lbCurSel"];

if (_control getVariable ["COMSPEC_AthenaNotifUpdating", false]) exitWith {};
if (_control getVariable ["COMSPEC_AthenaNotifSkipSel", false]) exitWith {};
if (_lbCurSel < 0) exitWith {};

private _notifs = missionNamespace getVariable ["COMSPEC_Athena_Notifications", []];
if (!(_notifs isEqualType [])) exitWith {};

private _disp = +_notifs;
reverse _disp;
if (_lbCurSel >= count _disp) exitWith {};

private _n = _disp select _lbCurSel;
_n params ["_id", "_kind", "_typeLabel", "_brief", "_time", "_unread", "_detail"];

private _idx = _notifs findIf { (_x select 0) isEqualTo _id };
if (_idx >= 0) then {
    private _u = +(_notifs select _idx);
    _u set [5, false];
    _notifs set [_idx, _u];
    missionNamespace setVariable ["COMSPEC_Athena_Notifications", _notifs, false];
};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (isNull _group) exitWith {};

private _detailCtrl = _group controlsGroupCtrl 9711;
if (!isNull _detailCtrl) then {
    if (_detail isNotEqualTo "") then {
        _detailCtrl ctrlSetStructuredText parseText _detail;
    } else {
        _detailCtrl ctrlSetStructuredText parseText format [
            "<t color='#e8f4f0'>%1</t><br/><t color='#8aa0b4'>Heure</t>  %2<br/><br/>%3",
            _typeLabel,
            _time,
            _brief
        ];
    };
};

private _tab = switch (_kind) do {
    case "order": { "order" };
    case "bda": { "bda" };
    case "photo": { "photo" };
    case "notify";
    case "hq";
    case "messages";
    case "vibrate";
    case "alert": { "notif" };
    default { "notif" };
};
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", _tab, false];

private _notifCtrl = _group controlsGroupCtrl 9715;
if (!isNull _notifCtrl) then {
    _notifCtrl setVariable ["COMSPEC_AthenaNotifSkipSel", true];
};
[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
if (!isNull _notifCtrl) then {
    _notifCtrl setVariable ["COMSPEC_AthenaNotifSkipSel", false];
};

private _listCtrl = _group controlsGroupCtrl 9710;
if (isNull _listCtrl) exitWith {};

private _entries = _listCtrl getVariable ["COMSPEC_Athena_Entries", []];
private _match = _entries findIf {
    private _sortKey = _x select 3;
    (_sortKey isEqualTo _id) || {((_x select 1) find _brief) >= 0}
};
if (_match >= 0) then {
    _listCtrl lbSetCurSel _match;
    [_listCtrl, _match] call comspec_overwatch_atak_athena_fnc_athena_selectInbox;
};
