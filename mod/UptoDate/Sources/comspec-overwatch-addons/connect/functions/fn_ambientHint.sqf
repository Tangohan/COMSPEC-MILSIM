/*
    Hint ambient (roleplay / dommages) — journal HTML toujours ;
    notification du téléphone ATAK natif seulement si les notifications écran sont autorisées
    (pas en milsim / réalisme / mode discret) ; sans ATAK natif, RPT. Jamais le hint d'Arma.
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
if (!isNil "comspec_atak_native_fnc_notify") exitWith {
    [["INFO", "WARNING"] select ((toLower _priority) in ["warn", "critical"]), _message, 6, 20, "ATHENA"] call comspec_atak_native_fnc_notify;
};
diag_log text format ["[COMSPEC Overwatch][%1] %2", _title, _message];
