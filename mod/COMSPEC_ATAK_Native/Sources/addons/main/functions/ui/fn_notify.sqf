/*
    Notification du téléphone : toast quelques secondes, gardée dans le centre de notifications (60 dernières).
    Params : [type (INFO, SUCCESS, WARNING, ERROR, MESSAGE, TACTICAL), texte, durée en s, priorité]
*/
params [["_type","INFO"],["_message",""],["_duration",5],["_priority",10]];
if (_message isEqualTo "" || {missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]}) exitWith {false};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _hist = _s getOrDefault ["notifHistory", []];
_hist pushBack createHashMapFromArray [["type", toUpper _type], ["message", _message], ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
while {count _hist > 60} do { _hist deleteAt 0; };
_s set ["notifHistory", _hist];
_s set ["notifUnread", (_s getOrDefault ["notifUnread", 0]) + 1];
if ((_s getOrDefault ["activePage", ""]) isEqualTo "NOTIFS") then { [{ ["NOTIFS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
if !(profileNamespace getVariable ["COMSPEC_ATAK_Notifications",true]) exitWith {false};
private _q = _s getOrDefault ["notifications",[]];
_q pushBack createHashMapFromArray [["type",toUpper _type],["message",_message],["expires",diag_tickTime+(_duration max 1)],["priority",_priority]];
while {count _q>12} do {_q deleteAt 0;};
_s set ["notifications",_q];
[] call comspec_atak_native_fnc_notificationsRender;
true
