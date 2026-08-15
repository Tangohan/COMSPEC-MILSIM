params [
    ["_mode", "TX"],
    ["_record", []],
    ["_duration", -1]
];

private _modeUpper = toUpper _mode;
private _label = "Bridge";
if (_record isEqualType [] && {(count _record) >= 3}) then {
    _label = format ["%1 CH%2 - %3", ["PRC-117F", "PRC-152"] select ((_record # 0) == "ACRE_PRC152"), (_record # 1) + 1, _record # 2];
};

private _id = ["Iceman_Bridge_RX", "acre_broadcast"] select (_modeUpper == "TX");
private _title = format ["%1: MPU-5 Bridge", _modeUpper];
private _color = [[0.35, 0.75, 1, 1], [1, 0.78, 0.05, 1]] select (_modeUpper == "TX");

if (!(isNil "acre_sys_list_fnc_displayHint")) exitWith {
    [_id, _title, _label, "C", _duration, _color] call acre_sys_list_fnc_displayHint;
    true
};

hintSilent format ["%1\n%2", _title, _label];
true
