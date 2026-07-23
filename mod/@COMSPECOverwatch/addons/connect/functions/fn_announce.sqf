/*
    Annonce métier : journal HTML + (sauf mode discret / milsim) chat système.
    Params: [_message, _type, _priority, _forceGameUi]
      type     : link | medical | order | ping | system | tactical
      priority : info | warn | critical
      forceGameUi : true pour toujours afficher le chat (dialogues critiques ; ignoré en mode milsim)
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

private _quiet = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
private _milsim = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];
// Mode milsim : pas de chat système « confort » (anomalies / infos), même avec forceGameUi.
if (_milsim && {(toLower _type) in ["system", "ping", "tactical"]}) exitWith {};
if ((!_quiet && {!_milsim}) || {_forceGameUi && {!_milsim}}) then {
    private _prefix = switch (toLower _type) do {
        case "link": { "[Athena] " };
        case "medical": { "[COMSPEC] " };
        case "tactical": { "[Situation] " };
        default { "[COMSPEC] " };
    };
    systemChat (_prefix + _message);
};
