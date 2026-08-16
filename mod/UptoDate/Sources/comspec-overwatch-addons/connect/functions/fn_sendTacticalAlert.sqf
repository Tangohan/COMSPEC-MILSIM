/*

    Author: COMSPEC

    Description:

        Envoie une alerte tactique structurée (inspiré Iceman ATAK_Alerts :

        TIC / CLEAR / FRAGO / SALUTE / Eagle Down / BDA).

        Transit via le canal messagerie Athena — préfixe « ALERTE TACTIQUE ».



    Params: [_kind, _body, _pos]

      _kind : "TIC"|"CLEAR"|"TIC_CLEAR"|"FRAGO"|"SALUTE"|"EAGLE_DOWN"|"PANIC"|"BDA"

      _body : texte libre (déjà structuré pour FRAGO/SALUTE/BDA) — SANS répéter type/indicatif/grille

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



// Corps = texte métier uniquement (type / indicatif / grille sont déjà dans les champs 1–5)
// Jamais de « | » dans le corps métier — sauf préfixe ORDER_ID=… conservé comme champ dédié.
private _orderIdField = "";
private _bodyWork = trim _body;
if (_bodyWork find "ORDER_ID=" == 0 || {_bodyWork find "ATHENA_ORDER_ID=" == 0}) then {
    private _sep = _bodyWork find "|";
    if (_sep >= 0) then {
        _orderIdField = _bodyWork select [0, _sep];
        _bodyWork = trim (_bodyWork select [_sep + 1]);
    } else {
        _orderIdField = _bodyWork;
        _bodyWork = "";
    };
};

private _payloadTail = trim ((_bodyWork splitString "|") joinString " · ");
_payloadTail = (_payloadTail splitString toString [10]) joinString " · ";
_payloadTail = (_payloadTail splitString toString [13]) joinString "";
_payloadTail = trim _payloadTail;
if (_orderIdField isNotEqualTo "") then {
    _payloadTail = if (_payloadTail isEqualTo "") then {
        _orderIdField
    } else {
        format ["%1|%2", _orderIdField, _payloadTail]
    };
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



// Inbox locale : détail lisible (corps ou libellé court)

private _detailShow = if (_payloadTail isEqualTo "") then {

    format ["%1 — grille %2", _kindLabel, _grid]

} else {

    _payloadTail

};



if (!(missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false])) then {

    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];

    if (!(_inbox isEqualType [])) then { _inbox = []; };

    private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;

    _inbox pushBack [_kindKey, _kindLabel, _detailShow, _grid, _timeStr, _callSign];

    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

    ["COMSPEC_TacticalAlertSent", [_kindKey, _payloadTail, _pos, _kindLabel, _callSign]] call CBA_fnc_localEvent;

} else {

    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];

    if (!(_inbox isEqualType [])) then { _inbox = []; };

    private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;

    _inbox pushBack [_kindKey, _kindLabel, _detailShow, _grid, _timeStr, _callSign];

    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

};



private _prio = if (_kindKey in ["EAGLE_DOWN", "TIC"]) then { "warn" } else { "info" };

[format ["%1 transmis.", _kindLabel], "tactical", _prio] call comspec_overwatch_connect_fnc_announce;

// Panic / opérateur à terre → même son que l’alerte santé « inconscient »
if (_kindKey isEqualTo "EAGLE_DOWN") then {
    ["unconscious"] call comspec_overwatch_connect_fnc_playAtakNotification;
} else {
    if (_kindKey isEqualTo "TIC") then {
        ["urgent"] call comspec_overwatch_connect_fnc_playAtakNotification;
    } else {
        ["chat"] call comspec_overwatch_connect_fnc_playAtakNotification;
    };
};

_kindKey

