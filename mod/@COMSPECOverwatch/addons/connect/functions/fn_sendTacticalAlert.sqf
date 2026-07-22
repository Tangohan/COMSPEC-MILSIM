/*
    Author: COMSPEC
    Description:
        Envoie une alerte tactique structurée (inspiré Iceman ATAK_Alerts :
        TIC / CLEAR / FRAGO / SALUTE / Eagle Down).
        Transit via le canal messagerie Athena — préfixe « ALERTE TACTIQUE ».

    Params: [_kind, _body, _pos]
      _kind : "TIC"|"CLEAR"|"TIC_CLEAR"|"FRAGO"|"SALUTE"|"EAGLE_DOWN"|"PANIC"
      _body : texte libre (déjà structuré pour FRAGO/SALUTE)
      _pos  : position (défaut = joueur)
*/
params [
    ["_kind", "TIC", [""]],
    ["_body", "", [""]],
    ["_pos", [], [[]]]
];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _kindKey = toUpper (trim _kind);
if (_kindKey in ["CLEAR", "TIC CLEAR", "TICCLEAR"]) then { _kindKey = "TIC_CLEAR"; };
if (_kindKey in ["PANIC", "EAGLEDOWN", "EAGLE DOWN"]) then { _kindKey = "EAGLE_DOWN"; };

private _kindLabel = switch (_kindKey) do {
    case "TIC": { "Contact" };
    case "TIC_CLEAR": { "Fin de contact" };
    case "FRAGO": { "Ordre fragmentaire" };
    case "SALUTE": { "Compte rendu SALUTE" };
    case "EAGLE_DOWN": { "Opérateur à terre" };
    default { "Alerte" };
};

if !(_kindKey in ["TIC", "TIC_CLEAR", "FRAGO", "SALUTE", "EAGLE_DOWN"]) then {
    _kindKey = "TIC";
    _kindLabel = "Contact";
};

if ((count _pos) < 2) then { _pos = getPos player; };
private _grid = mapGridPosition _pos;
private _callSign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callSign isEqualTo "") then { _callSign = name player; };

private _summary = if ((trim _body) isEqualTo "") then {
    format ["%1 — %2 — Grille %3", _kindLabel, _callSign, _grid]
} else {
    format ["%1 — %2 — Grille %3 — %4", _kindLabel, _callSign, _grid, trim _body]
};

// toFixed : séparateur décimal point (évite « 1850,12 » localisé que PHP is_numeric refuse).
private _posX = (_pos select 0) toFixed 2;
private _posY = (_pos select 1) toFixed 2;

private _msg = format [
    "ALERTE TACTIQUE|%1|%2|%3|%4|%5|%6",
    _kindKey,
    _callSign,
    _grid,
    _posX,
    _posY,
    _summary
];

[player, "CHAT", _msg, "", "INFANTRY", 0.95] call comspec_overwatch_connect_fnc_sendIntel;

private _prio = if (_kindKey in ["EAGLE_DOWN", "TIC"]) then { "warn" } else { "info" };
[format ["%1 transmis.", _kindLabel], "tactical", _prio] call comspec_overwatch_connect_fnc_announce;
["urgent"] call comspec_overwatch_connect_fnc_playAtakNotification;

_kindKey
