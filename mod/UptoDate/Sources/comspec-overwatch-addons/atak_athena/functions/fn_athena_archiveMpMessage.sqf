/*
    Archive un message privé cTab (P2P) vers le fil ATAK web (TOC).
    Affichage jeu = cTab natif ; web = MP|from|to|texte.
    Params: [_text, _toLabels]
*/
params [
    ["_text", "", [""]],
    ["_toLabels", [], [[]]]
];

if (!hasInterface) exitWith { false };
_text = trim _text;
if (_text isEqualTo "") exitWith { false };
if (!(_toLabels isEqualType []) || {(count _toLabels) < 1}) exitWith { false };

if (isNil "comspec_overwatch_connect_fnc_sendIntel") exitWith { false };

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

private _toJoined = (_toLabels apply { trim _x }) select { _x isNotEqualTo "" };
if ((count _toJoined) < 1) exitWith { false };
private _toStr = _toJoined joinString ",";

// Pas de « | » dans les champs (séparateur du protocole MP).
private _safeCs = (_cs splitString "|") joinString " · ";
private _safeTo = (_toStr splitString "|") joinString " · ";
private _safeBody = (_text splitString "|") joinString " · ";
_safeBody = (_safeBody splitString toString [10]) joinString " · ";
_safeBody = (_safeBody splitString toString [13]) joinString "";

private _msg = format ["MP|%1|%2|%3", _safeCs, _safeTo, trim _safeBody];
[player, "CHAT", _msg, "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;

["Message privé archivé côté poste de commandement"] call comspec_overwatch_connect_fnc_appendModuleLog;
true
