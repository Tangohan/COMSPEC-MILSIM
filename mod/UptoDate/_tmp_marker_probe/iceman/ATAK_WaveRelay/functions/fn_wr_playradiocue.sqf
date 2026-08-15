params [["_cue", "tx"], ["_ear", "BOTH"]];

private _sound = switch (_cue) do {
    case "txUp": {"Acre_GenericClickOff"};
    case "rx": {"Acre_GenericClickOn"};
    default {"Acre_GenericBeep"};
};

private _volume = 0.45;
private _earUpper = toUpperANSI _ear;
private _position = switch (_earUpper) do {
    case "L": {[-2, 0, 0]};
    case "LEFT": {[-2, 0, 0]};
    case "R": {[2, 0, 0]};
    case "RIGHT": {[2, 0, 0]};
    default {[0, 0, 0]};
};

private _radio = "";
if !(isNil "acre_api_fnc_getRadioByType") then {
    private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
    if (!isNil "_candidate" && {_candidate isEqualType ""}) then {
        _radio = _candidate;
    };
};
if (_radio != "" && {!(isNil "acre_api_fnc_getRadioVolume")}) then {
    private _acreVolume = [_radio] call acre_api_fnc_getRadioVolume;
    if (!isNil "_acreVolume" && {_acreVolume isEqualType 0}) then {
        _volume = 0.2 max (1 min _acreVolume);
    };
};

if (!(isNil "acre_sys_sounds_fnc_playSound")) exitWith {
    [_sound, _position, [0,1,0], _volume, false] call acre_sys_sounds_fnc_playSound;
    true
};

playSound _sound;
true
