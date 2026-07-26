/*
    Hint ambient (roleplay / dommages) — journal HTML toujours ;
    hint à l’écran seulement si les notifications écran sont autorisées
    (pas en milsim / réalisme / mode discret).
*/
params [
    ["_message", "", [""]],
    ["_type", "system", [""]],
    ["_priority", "info", [""]]
];

if (!hasInterface) exitWith {};
_message = trim _message;
if (_message isEqualTo "") exitWith {};

private _titles = createHashMapFromArray [
    ["link", "Liaison"],
    ["medical", "Médical"],
    ["order", "Ordre"],
    ["system", "Overwatch"]
];
private _title = _titles getOrDefault [toLower _type, "Overwatch"];
[_type, _title, _message, _priority] call comspec_overwatch_connect_fnc_pushHtmlAlert;

if !([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) exitWith {};
hintSilent _message;
