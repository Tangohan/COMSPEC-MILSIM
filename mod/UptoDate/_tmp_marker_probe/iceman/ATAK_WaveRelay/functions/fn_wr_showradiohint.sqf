params [
    ["_mode", "TX"],
    ["_talkgroup", 1],
    ["_speaker", ""],
    ["_duration", -1],
    ["_ear", ""]
];

private _modeUpper = toUpperANSI _mode;
if !(missionNamespace getVariable ["Iceman_WR_showRadioPopup", true]) exitWith {true};

private _tg = (round _talkgroup) max 1 min 16;
if (_speaker == "") then {_speaker = name player};
if (_speaker == "") then {_speaker = "Unknown"};

private _id = ["Iceman_WR_RX", "acre_broadcast"] select (_modeUpper == "TX");
private _title = format ["%1: MPU-5 Persistent Systems", _modeUpper];
private _line1 = format ["TG%1 - %2", _tg, _speaker];
private _earUpper = toUpperANSI _ear;
private _earLabel = switch (_earUpper) do {
    case "L": {"L"};
    case "LEFT": {"L"};
    case "R": {"R"};
    case "RIGHT": {"R"};
    case "BOTH": {"BOTH"};
    case "CENTER": {"BOTH"};
    default {"C"};
};
private _line2 = if (_modeUpper == "RX" && {_earLabel != "C"}) then {format ["MON %1", _earLabel]} else {"C"};
private _color = [[0.35, 0.75, 1, 1], [1, 0.78, 0.05, 1]] select (_modeUpper == "TX");

if (!(isNil "acre_sys_list_fnc_displayHint")) exitWith {
    [_id, _title, _line1, _line2, _duration, _color] call acre_sys_list_fnc_displayHint;
    true
};

hintSilent format ["%1\n%2\n%3", _title, _line1, _line2];
true
