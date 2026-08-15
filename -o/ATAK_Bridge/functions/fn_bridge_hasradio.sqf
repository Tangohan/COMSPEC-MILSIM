params [["_unit", player]];

if (isNil "acre_api_fnc_getRadioByType") exitWith {false};
private _radio = ["ACRE_MPU5", _unit] call acre_api_fnc_getRadioByType;
!(isNil "_radio") && {_radio isEqualType ""} && {_radio != ""}
