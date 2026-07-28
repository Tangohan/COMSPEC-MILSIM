/*
    Vibration / alerte haptique sur le terminal ATAK du joueur.
    Signal TOC (type VIBRATE) — buzz réel, pas un ordre C2 à traiter manuellement.
    Volume réglable via l’app Sons (canal vibration × volume général).
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};

private _issuer = "Athena";
private _orderId = format ["vib_%1", diag_tickTime];
if (_order isEqualType createHashMap) then {
    _issuer = _order getOrDefault ["issuer", "Athena"];
    private _oid = trim (_order getOrDefault ["id", ""]);
    if (_oid isNotEqualTo "") then { _orderId = _oid; };
};

private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;

private _vol = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
if (_vol > 0.01) then {
    playSoundUI ["COMSPEC_ATAK_Vibrate", _vol, 1];
    [_vol] spawn {
        params ["_vol"];
        uiSleep 0.35;
        private _v2 = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
        if (_v2 > 0.01) then {
            playSoundUI ["COMSPEC_ATAK_Vibrate", _v2, 1];
        };
    };
};

private _msg = format ["Votre terminal vibre — appel de %1", _issuer];
["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
["ATHENA", _msg, 6] call comspec_overwatch_connect_fnc_addScreenToast;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;

private _detail = format [
    "<t color='#ffcc66'>Vibration</t><br/><t color='#8aa0b4'>Émetteur</t>  %1<br/><t color='#b8c8d4'>L’état-major a demandé l’attention sur votre terminal.</t>",
    _issuer
];

[
    "vibrate",
    "Terminal",
    format ["Vibration — de %1", _issuer],
    _detail,
    _orderId,
    _timeStr
] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
_inbox pushBack ["VIBRATE", "Vibration", "Appel d’attention depuis Athena", "", _timeStr, _issuer, _orderId];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
