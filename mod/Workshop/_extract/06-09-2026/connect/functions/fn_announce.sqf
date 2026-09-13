/*
    Annonce métier : journal HTML tablette / téléphone uniquement.
    Jamais de chat natif Arma. Les bandeaux BIS passent par showNotification.
    Params: [_message, _type, _priority, _forceGameUi]
      type     : link | medical | order | ping | system | tactical
      priority : info | warn | critical
      _forceGameUi : conservé pour compatibilité, ignoré (pas de chat jeu).
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
