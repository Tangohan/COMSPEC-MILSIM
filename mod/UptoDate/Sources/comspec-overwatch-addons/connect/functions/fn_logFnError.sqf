/*
    Journal d’erreur de fonction / ACE (côté Overwatch uniquement).

    Params:
        0: STRING — nom de fonction ou action (ex. "initACE", "ACE Ping")
        1: STRING — message lisible
        2: ANY    — détail technique (optionnel)
        3: STRING — canal "ACE"|"Fn"|"Athena"|"Compat" (défaut "Fn")
        4: STRING — niveau "ERROR"|"WARN"|"INFO" (défaut "ERROR")
*/
params [
    ["_fn", "", [""]],
    ["_message", "", [""]],
    ["_raw", nil],
    ["_channel", "Fn", [""]],
    ["_level", "ERROR", [""]]
];

if (_fn isEqualTo "" && {_message isEqualTo ""}) exitWith {};

private _msg = if (_message isEqualTo "") then { _fn } else {
    if (_fn isEqualTo "") then { _message } else { format ["%1 — %2", _fn, _message] }
};

[_level, _channel, _msg, if (isNil "_raw") then { nil } else { _raw }] call comspec_overwatch_connect_fnc_log;

private _line = format ["[%1] %2", toUpper _channel, _msg];
[_line, "system"] call comspec_overwatch_connect_fnc_appendLinkLog;

if ((toUpper _channel) isEqualTo "ACE") then {
    [_line] call comspec_overwatch_connect_fnc_appendModuleLog;
};
