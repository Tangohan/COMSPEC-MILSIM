/*
    Vibration + bandeau quand un ATAK allié entre dans le rayon d’un téléphone suivi.
    Params: [_displayName, _distanceM]
*/
params [
    ["_displayName", "Téléphone suivi", [""]],
    ["_distanceM", 0, [0]]
];

if (!hasInterface) exitWith {};

private _name = trim _displayName;
if (_name isEqualTo "") then { _name = "Téléphone suivi"; };
_name = (_name splitString "<") joinString "";
_name = (_name splitString ">") joinString "";

private _distTxt = if (_distanceM >= 1000) then {
    private _km = (round (_distanceM / 100)) / 10;
    private _s = str _km;
    _s = (_s splitString ".") joinString ",";
    format ["%1 km", _s]
} else {
    format ["%1 m", round _distanceM]
};

private _msg = format ["Téléphone proche — %1 (%2)", _name, _distTxt];
private _style = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
if (!(_style isEqualType "")) then { _style = "silent_vib"; };
private _muted = (toLower _style) isEqualTo "mute";

if (!_muted) then {
    private _vol = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
    if (_vol > 0.01) then {
        playSoundUI ["COMSPEC_ATAK_Vibrate", _vol, 1];
        [_vol] spawn {
            params ["_vol"];
            uiSleep 0.28;
            playSoundUI ["COMSPEC_ATAK_Vibrate", _vol, 1];
            uiSleep 0.32;
            private _v2 = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
            if (_v2 > 0.01) then {
                playSoundUI ["COMSPEC_ATAK_Vibrate", _v2, 1];
            };
        };
    };
};

["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
["ATHENA", _msg, 7] call comspec_overwatch_connect_fnc_addScreenToast;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;

private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _orderId = format ["prox_%1_%2", diag_tickTime, _name];
private _detail = format [
    "<t color='#67e8f9'>Téléphone suivi</t><br/><t color='#8aa0b4'>Contact</t>  %1<br/><t color='#8aa0b4'>Distance</t>  %2<br/><t color='#b8c8d4'>Un téléphone suivi est entré dans le rayon d’alerte de votre terminal.</t>",
    _name,
    _distTxt
];

[
    "phone_proximity",
    "Téléphone",
    _msg,
    _detail,
    _orderId,
    _timeStr
] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
_inbox pushBack ["PHONE_PROX", "Téléphone proche", _msg, "", _timeStr, _name, _orderId];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
