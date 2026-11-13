/*
    Retour visuel au chef de mission (Zeus / éditeur).
    [_message, _kind] call comspec_sse_fnc_zeusNotify
      _message : texte brut ; retour à la ligne = "\n" ou endl
      _kind    : "info" (défaut) | "warn" | "error"
    Notification du téléphone ATAK (comspec_sse_fnc_notify) et RPT, jamais de bulle par-dessus l'écran de jeu,
    ni de message natif du curateur (BIS_fnc_showCuratorFeedbackMessage), ni de bandeau de ZEN (zen_common_fnc_showMessage :
    une seule ligne en haut de l'écran, coupée aux bords dès que le texte est long). Ce qui doit se lire tout de suite
    va dans une fenêtre (ex. « Voir les vrais papiers » : fenêtre Papiers de l'IA, fn_idZeusPapers).
*/
params [
    ["_message", "", [""]],
    ["_kind", "info", [""]]
];

if (!hasInterface || {_message isEqualTo ""}) exitWith { false };
_kind = toLower _kind;

// Règle « tout dans l'ATAK » : plus de bulle à l'écran, notification du téléphone (traduite au rendu, ligne par ligne).
if (!isNil "comspec_sse_fnc_notify") then {
    [_message, switch (_kind) do { case "error": { "ERROR" }; case "warn": { "WARNING" }; default { "INFO" }; }, "COMSPEC SSE", "SSE"] call comspec_sse_fnc_notify;
};

["[ZEUS] " + _message, if (_kind isEqualTo "info") then { "INFO" } else { "WARNING" }] call comspec_sse_fnc_log;
true
