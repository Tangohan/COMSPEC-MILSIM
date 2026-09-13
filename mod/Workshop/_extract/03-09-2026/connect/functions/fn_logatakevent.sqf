/*
    Événement ATAK → journal fichier session (COMSPEC/logs via fnc_log)
    + journal liaison in-game (appendLinkLog).

    Params:
        0: STRING — niveau INFO | WARN | ERROR
        1: STRING — canal Zeus | Etat | Medical | Terminal
        2: STRING — message lisible
        3: STRING — catégorie liaison system | medical | liaison (défaut system)
        4: ANY    — détail optionnel (nil = omis)
*/
params [
    ["_level", "INFO", [""]],
    ["_channel", "Core", [""]],
    ["_message", "", [""]],
    ["_linkCategory", "system", [""]],
    ["_detail", nil]
];

if (_message isEqualTo "") exitWith {};

// Ne jamais passer _detail nil dans une expression (Variable indéfinie).
if (isNil "_detail") then {
    [_level, _channel, _message] call comspec_overwatch_connect_fnc_log;
} else {
    [_level, _channel, _message, _detail] call comspec_overwatch_connect_fnc_log;
};

private _line = format ["[%1] %2", _channel, _message];
if (!isNil "_detail" && {_detail isNotEqualTo ""}) then {
    _line = _line + format [" — %1", _detail];
};
[_line, _linkCategory] call comspec_overwatch_connect_fnc_appendLinkLog;
