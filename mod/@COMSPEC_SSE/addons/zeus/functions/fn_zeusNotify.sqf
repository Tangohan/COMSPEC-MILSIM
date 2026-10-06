/*
    Retour visuel au chef de mission (Zeus / éditeur).
    [_message, _kind] call comspec_sse_fnc_zeusNotify
      _message : texte brut ; retour à la ligne = "\n" (comme hint) ou endl
      _kind    : "info" (défaut) | "warn" | "error"
    Erreur avec l'interface Zeus ouverte : message rouge natif du curateur.
*/
params [
    ["_message", "", [""]],
    ["_kind", "info", [""]]
];

if (!hasInterface || {_message isEqualTo ""}) exitWith { false };
_kind = toLower _kind;

if (_kind isEqualTo "error" && {!isNull (findDisplay 312)}) then {
    [objNull, _message] call BIS_fnc_showCuratorFeedbackMessage;
};

private _color = switch (_kind) do {
    case "error": { "#E0574F" };
    case "warn": { "#F2B84B" };
    default { "#73CC80" };
};

private _safe = _message regexReplace ["&", "&amp;"];
_safe = _safe regexReplace ["<", "&lt;"];
_safe = _safe regexReplace [">", "&gt;"];
_safe = _safe regexReplace ["\r\n|\n|\\n", "<br/>"];

hintSilent parseText format [
    "<t font='PuristaMedium' size='1.15' color='%1'>COMSPEC SSE</t><br/><t size='0.95' align='left'>%2</t>",
    _color,
    _safe
];

["[ZEUS] " + _message, if (_kind isEqualTo "info") then { "INFO" } else { "WARNING" }] call comspec_sse_fnc_log;
true
