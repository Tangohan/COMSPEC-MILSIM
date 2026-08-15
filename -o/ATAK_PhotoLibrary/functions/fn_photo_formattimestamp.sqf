params [["_time", []]];

if !(_time isEqualType []) exitWith {"Unknown time"};

private _pad = {
    params ["_value"];
    (["", "0"] select (_value < 10)) + str _value
};

private _year = _time param [0, 0];
private _month = [_time param [1, 0]] call _pad;
private _day = [_time param [2, 0]] call _pad;
private _hour = [_time param [3, 0]] call _pad;
private _minute = [_time param [4, 0]] call _pad;

format ["%1-%2-%3 %4:%5", _year, _month, _day, _hour, _minute]
