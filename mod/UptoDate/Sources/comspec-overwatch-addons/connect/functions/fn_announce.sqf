/*
    Annonce métier : journal HTML + (si notifications à l’écran) chat système.
    Params: [_message, _type, _priority, _forceGameUi]
      type     : link | medical | order | ping | system | tactical
      priority : info | warn | critical
      forceGameUi : réservé (compat) — le chat système suit toujours
                    « Afficher les notifications à l’écran » (défaut OFF).
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
    ["tactical", "Signalement"],
    ["system", "Overwatch"]
];
private _title = _titles getOrDefault [toLower _type, "Overwatch"];

[_type, _title, _message, _priority] call comspec_overwatch_connect_fnc_pushHtmlAlert;

// Chat système (systemChat) : même porte que les bandeaux — défaut OFF.
// Mode milsim : pas de chat « confort » (anomalies / infos / signalements).
private _milsim = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];
if (_milsim && {(toLower _type) in ["system", "ping", "tactical"]}) exitWith {};
if !([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) exitWith {};

private _prefix = switch (toLower _type) do {
    case "link": { "[Athena] " };
    case "medical": { "[COMSPEC] " };
    case "tactical": { "[Situation] " };
    default { "[COMSPEC] " };
};
systemChat (_prefix + _message);
