/*
    Author: COMSPEC
    Description:
        Envoie une alerte tactique structurée (inspiré Iceman ATAK_Alerts :
        TIC / CLEAR / FRAGO / SALUTE / Eagle Down / BDA).
        Transit via le canal messagerie Athena — préfixe « ALERTE TACTIQUE ».

    Params: [_kind, _body, _pos]
      _kind : "TIC"|"CLEAR"|"TIC_CLEAR"|"FRAGO"|"SALUTE"|"EAGLE_DOWN"|"PANIC"|"BDA"
      _body : texte libre (déjà structuré pour FRAGO/SALUTE/BDA)
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
if (_kindKey in ["BDA_REPORT", "BDAREPORT"]) then { _kindKey = "BDA"; };

private _kindLabel = switch (_kindKey) do {
    case "TIC": { "Contact" };
    case "TIC_CLEAR": { "Fin de contact" };
    case "FRAGO": { "Ordre fragmentaire" };
    case "SALUTE": { "Compte rendu SALUTE" };
    case "EAGLE_DOWN": { "Opérateur à terre" };
    case "BDA": { "Bilan des dégâts" };
    default { "Alerte" };
};

if !(_kindKey in ["TIC", "TIC_CLEAR", "FRAGO", "SALUTE", "EAGLE_DOWN", "BDA"]) then {
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
    // SALUTE structuré (S=…|A=…) : ne pas dupliquer le gabarit dans le résumé.
    if (_kindKey isEqualTo "SALUTE" && {(_body find "S=") >= 0 || {(_body find "A=") >= 0}}) then {
        format ["%1 — %2 — Grille %3 — %4", _kindLabel, _callSign, _grid, trim _body]
    } else {
        format ["%1 — %2 — Grille %3 — %4", _kindLabel, _callSign, _grid, trim _body]
    }
};

// Pour SALUTE structuré : le corps remplace le résumé libre (parts 6+).
private _payloadTail = if (_kindKey isEqualTo "SALUTE" && {(trim _body) isNotEqualTo ""}) then {
    trim _body
} else {
    _summary
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
    _payloadTail
];

[player, "CHAT", _msg, "", "INFANTRY", 0.95] call comspec_overwatch_connect_fnc_sendIntel;

// Inbox locale + event pont ATAK Enhanced (sauf si déjà en miroir anti-boucle)
if (!(missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false])) then {
    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
    if (!(_inbox isEqualType [])) then { _inbox = []; };
    private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
    _inbox pushBack [_kindKey, _kindLabel, _payloadTail, _grid, _timeStr, _callSign];
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
    ["COMSPEC_TacticalAlertSent", [_kindKey, _payloadTail, _pos, _kindLabel, _callSign]] call CBA_fnc_localEvent;
} else {
    // Dual-send Iceman→Athena : journaliser sans remirror
    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
    if (!(_inbox isEqualType [])) then { _inbox = []; };
    private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
    _inbox pushBack [_kindKey, _kindLabel, _payloadTail, _grid, _timeStr, _callSign];
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};

private _prio = if (_kindKey in ["EAGLE_DOWN", "TIC"]) then { "warn" } else { "info" };
[format ["%1 transmis.", _kindLabel], "tactical", _prio] call comspec_overwatch_connect_fnc_announce;
["urgent"] call comspec_overwatch_connect_fnc_playAtakNotification;

_kindKey
