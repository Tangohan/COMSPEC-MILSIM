/*
    Notification du téléphone : toast quelques secondes, gardée dans le centre de notifications (60 dernières).
    Params : [type (INFO, SUCCESS, WARNING, ERROR, MESSAGE, TACTICAL), texte, durée en s, priorité]
    File des toasts (état « notifications ») : un seul affiché à la fois (fn_notificationsRender) ; la durée ne court
    qu'à partir de son affichage, et un toast qui attend depuis plus de 30 s est abandonné (il reste dans le centre).
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
// Préférences réglées depuis le web (Mon ATAK) : types muets (historique seulement), durée imposée des bandeaux.
private _muted = profileNamespace getVariable ["COMSPEC_ATAK_NotifMuted", []];
if ((_muted isEqualType []) && {(toUpper _type) in _muted}) exitWith {false};
private _forced = profileNamespace getVariable ["COMSPEC_ATAK_NotifToastSec", 0];
if ((_forced isEqualType 0) && {_forced > 0}) then { _duration = _forced; };
private _q = _s getOrDefault ["notifications",[]];
// Même texte déjà en file : on le remet en avant au lieu de l'empiler (alertes répétées chaque seconde).
private _dup = _q findIf { (_x getOrDefault ["message", ""]) isEqualTo _message };
if (_dup >= 0) then { _q deleteAt _dup; };
private _id = (_s getOrDefault ["notifSeq", 0]) + 1;
_s set ["notifSeq", _id];
_q pushBack createHashMapFromArray [
    ["id", _id], ["type", toUpper _type], ["message", _message], ["priority", _priority],
    ["duration", (_duration max 2) min 12], ["created", diag_tickTime], ["expires", -1]
];
while {count _q > 12} do {_q deleteAt 0;};
_s set ["notifications",_q];
[] call comspec_atak_native_fnc_notificationsRender;
true
