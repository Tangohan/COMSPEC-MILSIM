/*
    Annonce métier : journal HTML + (si notifications à l’écran) chat système.
    Params: [_message, _type, _priority, _forceGameUi]
      type     : link | medical | order | ping | system | tactical
      priority : info | warn | critical
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
    ["medical", "Alerte médicale"],
    ["order", "Ordre"],
    ["ping", "Signal"],
    ["tactical", "Signalement"],
    ["system", "Overwatch"]
];
private _title = _titles getOrDefault [toLower _type, "Overwatch"];

[_type, _title, _message, _priority] call comspec_overwatch_connect_fnc_pushHtmlAlert;

// Milsim / réalisme / discret / notifs OFF → pas de chat confort (journal tablette seulement).
if !([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) exitWith {};

private _prefix = switch (toLower _type) do {
    case "link": { "[Athena] " };
    case "medical": { "[COMSPEC] " };
    case "tactical": { "[Situation] " };
    default { "[COMSPEC] " };
};
systemChat (_prefix + _message);
