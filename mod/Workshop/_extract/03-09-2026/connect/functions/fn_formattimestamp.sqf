/*
 * Auteur: COMSPEC
 * Formate timestamp système en string SQL datetime
 *
 * Arguments:
 * 0: System time array <ARRAY> - [year, month, day, hour, minute, second]
 *
 * Valeur de retour:
 * <STRING> - "YYYY-MM-DD HH:MM:SS"
 *
 * Exemple:
 * private _timestamp = [systemTime] call comspec_overwatch_connect_fnc_formatTimestamp;
 */

params [
    ["_systemTime", systemTime, [[]]]
];

_systemTime params ["_year", "_month", "_day", "_hour", "_minute", "_second"];

// Formatter avec leading zeros
private _monthStr = if (_month < 10) then {format ["0%1", _month]} else {str _month};
private _dayStr = if (_day < 10) then {format ["0%1", _day]} else {str _day};
private _hourStr = if (_hour < 10) then {format ["0%1", _hour]} else {str _hour};
private _minuteStr = if (_minute < 10) then {format ["0%1", _minute]} else {str _minute};
private _secondStr = if (_second < 10) then {format ["0%1", _second]} else {str _second};

format ["%1-%2-%3 %4:%5:%6", _year, _monthStr, _dayStr, _hourStr, _minuteStr, _secondStr]
