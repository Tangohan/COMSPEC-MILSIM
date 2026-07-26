/*
    Notification TOC sur le terminal ATAK — cliquable dans le fil Athena.
    Déclenchée par un signal web de type NOTIFY (pas un ordre C2).
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};

private _issuer = "Athena";
private _payload = "";
private _orderId = format ["ntf_%1", diag_tickTime];
if (_order isEqualType createHashMap) then {
    _issuer = _order getOrDefault ["issuer", "Athena"];
    _payload = trim (_order getOrDefault ["payload", ""]);
    private _oid = trim (_order getOrDefault ["id", ""]);
    if (_oid isNotEqualTo "") then { _orderId = _oid; };
};

if (_payload isEqualTo "") then {
    _payload = "Message de l’état-major";
};

// Échapper caractères qui casseraient le structured text
private _safePayload = _payload;
_safePayload = (_safePayload splitString "<") joinString "‹";
_safePayload = (_safePayload splitString ">") joinString "›";
_safePayload = (_safePayload splitString "&") joinString " et ";

private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _brief = if ((count _safePayload) > 48) then {
    (_safePayload select [0, 45]) + "…"
} else {
    _safePayload
};

private _detail = format [
    "<t color='#7dffb0'>Notification</t><br/><t color='#8aa0b4'>Émetteur</t>  %1<br/><t color='#8aa0b4'>Heure</t>  %2<br/><br/><t color='#e8f4f0'>%3</t>",
    _issuer,
    _timeStr,
    _safePayload
];

[
    "notify",
    "Notification",
    format ["%1 — %2", _issuer, _brief],
    _detail,
    _orderId,
    _timeStr
] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;

// Entrée journal pour sélection / détail (clic sur la notif)
private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
_inbox pushBack ["NOTIFY", "Notification", _safePayload, "", _timeStr, _issuer, _orderId];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

["ATHENA", format ["Notification — %1", _issuer], 7] call comspec_overwatch_connect_fnc_addScreenToast;
["COMSPEC_Warning", [format ["Notification Athena — de %1", _issuer]]] call comspec_overwatch_connect_fnc_showNotification;
if (!isNil "comspec_overwatch_connect_fnc_playAtakNotification") then {
    ["urgent"] call comspec_overwatch_connect_fnc_playAtakNotification;
};
if (!isNil "cTab_phoneVibrate") then {
    playSound "cTab_phoneVibrate";
};

["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    missionNamespace setVariable ["COMSPEC_Athena_PanelTab", "notif", false];
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
