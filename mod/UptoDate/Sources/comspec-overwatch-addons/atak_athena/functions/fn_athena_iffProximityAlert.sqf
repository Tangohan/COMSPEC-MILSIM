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

if (!isNil "comspec_overwatch_connect_fnc_playAtakVibrate") then {
    [0.7, false] call comspec_overwatch_connect_fnc_playAtakVibrate;
};

["ATHENA", _msg, 5] call comspec_overwatch_connect_fnc_addScreenToast;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;
