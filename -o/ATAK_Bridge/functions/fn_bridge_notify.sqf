params [
    ["_title", "BRIDGE"],
    ["_text", ""],
    ["_duration", 2]
];

if !(isNil "cTab_fnc_addNotification") exitWith {
    [_title, _text, _duration] call cTab_fnc_addNotification;
    true
};

hint format ["%1\n%2", _title, _text];
true
