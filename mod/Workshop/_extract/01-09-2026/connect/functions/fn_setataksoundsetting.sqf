/*
    Persiste un réglage sonore ATAK (CBA client + missionNamespace).
    Params: [_name, _value]
*/
params [
    ["_name", "", [""]],
    "_value"
];

if (_name isEqualTo "") exitWith { false };

missionNamespace setVariable [_name, _value, false];
if (!isNil "cba_settings_fnc_set") then {
    [_name, _value, 2, "client"] call cba_settings_fnc_set;
};
true
