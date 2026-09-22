/*
    Alerte discrète : un contact non identifié entre dans le rayon IFF.
    Params: [_distanceM]
*/
params [
    ["_distanceM", 0, [0]]
];

if (!hasInterface) exitWith {};

private _distTxt = if (_distanceM >= 1000) then {
    private _km = (round (_distanceM / 100)) / 10;
    private _s = str _km;
    _s = (_s splitString ".") joinString ",";
    format ["%1 km", _s]
} else {
    format ["%1 m", round _distanceM]
};

private _msg = format ["Contact non identifié — %1", _distTxt];
private _style = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
if (!(_style isEqualType "")) then { _style = "silent_vib"; };
private _muted = (toLower _style) isEqualTo "mute";

if (!_muted) then {
    private _vol = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
    if (_vol > 0.01) then {
        playSoundUI ["COMSPEC_ATAK_Vibrate", _vol * 0.7, 1];
    };
};

["ATHENA", _msg, 5] call comspec_overwatch_connect_fnc_addScreenToast;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;
