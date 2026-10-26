/*
    Retour visuel au chef de mission (Zeus / éditeur).
    [_message, _kind] call comspec_sse_fnc_zeusNotify
      _message : texte brut ; retour à la ligne = "\n" (comme hint) ou endl
      _kind    : "info" (défaut) | "warn" | "error"
    Notification du téléphone ATAK (comspec_sse_fnc_notify), jamais de bulle par-dessus l'écran de jeu.
    Interface Zeus ouverte : aussi dans l'interface du curateur (erreur : message rouge natif ; sinon bandeau ZEN).
*/
params [
    ["_message", "", [""]],
    ["_kind", "info", [""]]
];

if (!hasInterface || {_message isEqualTo ""}) exitWith { false };
_kind = toLower _kind;

// Interface Zeus ouverte (le téléphone est fermé) : retour affiché dans l'interface du curateur pour tous les types,
// erreur en message rouge natif, info et avertissement par le bandeau ZEN (sinon message natif du curateur).
if (!isNull (findDisplay 312)) then {
    if (_kind isNotEqualTo "error" && {!isNil "zen_common_fnc_showMessage"}) then {
        [(_message call comspec_sse_fnc_tr) regexReplace ["%", "%%"]] call zen_common_fnc_showMessage;
    } else {
        [objNull, _message call comspec_sse_fnc_tr] call BIS_fnc_showCuratorFeedbackMessage;
    };
};

// Règle « tout dans l'ATAK » : plus de bulle à l'écran, notification du téléphone (traduite au rendu, ligne par ligne).
if (!isNil "comspec_sse_fnc_notify") then {
    [_message, switch (_kind) do { case "error": { "ERROR" }; case "warn": { "WARNING" }; default { "INFO" }; }, "COMSPEC SSE", "SSE"] call comspec_sse_fnc_notify;
};

["[ZEUS] " + _message, if (_kind isEqualTo "info") then { "INFO" } else { "WARNING" }] call comspec_sse_fnc_log;
true
