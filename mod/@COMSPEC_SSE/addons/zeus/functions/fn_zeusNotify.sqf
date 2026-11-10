/*
    Retour visuel au chef de mission (Zeus / éditeur).
    [_message, _kind] call comspec_sse_fnc_zeusNotify
      _message : texte brut ; retour à la ligne = "\n" ou endl
      _kind    : "info" (défaut) | "warn" | "error"
    Notification du téléphone ATAK (comspec_sse_fnc_notify) et RPT, jamais de bulle par-dessus l'écran de jeu
    ni de message natif du curateur (BIS_fnc_showCuratorFeedbackMessage : interface d'Arma, proscrite).
    Interface Zeus ouverte avec Zeus Enhanced : aussi le bandeau de ZEN (interface du mod ZEN) pour tous les types.
*/
params [
    ["_message", "", [""]],
    ["_kind", "info", [""]]
];

if (!hasInterface || {_message isEqualTo ""}) exitWith { false };
_kind = toLower _kind;

// Interface Zeus ouverte (le téléphone est fermé) : bandeau ZEN pour tous les types ; sans ZEN, téléphone + RPT seuls.
if (!isNull (findDisplay 312) && {!isNil "zen_common_fnc_showMessage"}) then {
    [(_message call comspec_sse_fnc_tr) regexReplace ["%", "%%"]] call zen_common_fnc_showMessage;
};

// Règle « tout dans l'ATAK » : plus de bulle à l'écran, notification du téléphone (traduite au rendu, ligne par ligne).
if (!isNil "comspec_sse_fnc_notify") then {
    [_message, switch (_kind) do { case "error": { "ERROR" }; case "warn": { "WARNING" }; default { "INFO" }; }, "COMSPEC SSE", "SSE"] call comspec_sse_fnc_notify;
};

["[ZEUS] " + _message, if (_kind isEqualTo "info") then { "INFO" } else { "WARNING" }] call comspec_sse_fnc_log;
true
