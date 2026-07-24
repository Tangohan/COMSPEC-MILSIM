/*
    Annonce métier : journal HTML + (sauf mode discret) chat système.
    Params: [_message, _type, _priority, _forceGameUi]
      type     : link | medical | order | ping | system
      priority : info | warn | critical
      forceGameUi : true pour toujours afficher le chat (dialogues critiques)
*/
params [
    ["_message", "", [""]],
    ["_type", "system", [""]],
    ["_priority", "info", [""]],
    ["_forceGameUi", false, [true]]
];

if (!hasInterface) exitWith {};
_message = trim _message;
if (_message isEqualTo "") exitWith {};

private _titles = createHashMapFromArray [
    ["link", "Liaison Athena"],
    ["medical", "Medical alert"],
    ["order", "Ordre"],
    ["ping", "Signal"],
    ["system", "Overwatch"]
];
private _title = _titles getOrDefault [toLower _type, "Overwatch"];

[_type, _title, _message, _priority] call comspec_overwatch_connect_fnc_pushHtmlAlert;

private _quiet = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
if (!_quiet || {_forceGameUi}) then {
    private _prefix = switch (toLower _type) do {
        case "link": { "[Athena] " };
        case "medical": { "[COMSPEC] " };
        default { "[COMSPEC] " };
    };
    systemChat (_prefix + _message);
};
