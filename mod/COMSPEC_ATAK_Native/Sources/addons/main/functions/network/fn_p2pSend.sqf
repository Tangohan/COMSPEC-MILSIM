/*
    Message direct à un joueur, sans passer par Athena (principe de l'app message de BCE, Aaren, APL-SA).
    Retourne true si l'événement a été envoyé.
*/
params [["_peer", ""], ["_body", ""]];
_body = trim _body;
if (_body isEqualTo "" || {_peer isEqualTo ""}) exitWith { false };
// Commandes du tchat (/urgent, /contact…) : puces en tête du SMS.
([_body] call comspec_atak_native_fnc_chatCommand) params ["", "", "_text", "_unknown", "_tags", "_help"];
if (_help) exitWith { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatWiki", true]; [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; true };
if ((count _unknown) > 0) exitWith { ["WARNING", format ["Commande inconnue : %1 (tapez /aide)", _unknown joinString " "], 4, 30] call comspec_atak_native_fnc_notify; false };
if (_text isEqualTo "") exitWith { false };
_body = ((_tags apply { format ["[%1]", _x] }) joinString "") + ([" ", ""] select ((count _tags) isEqualTo 0)) + _text;
if ((count _body) > 400) then { _body = _body select [0, 400]; };
private _target = objNull;
{ if ((name _x) isEqualTo _peer) exitWith { _target = _x; }; } forEach allPlayers;
private _time = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _date = [] call comspec_atak_native_fnc_p2pDate;
private _id = format ["%1-%2-%3", getPlayerUID player, round (time * 1000), floor random 1e5];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _p2p = _data getOrDefault ["p2p", []];
private _m = createHashMapFromArray [["peer", _peer], ["dir", "out"], ["body", _body], ["time", _time], ["date", _date], ["read", true], ["id", _id], ["status", "SENDING"], ["sentAt", time]];
_p2p pushBack _m;
while {(count _p2p) > 200} do { _p2p deleteAt 0; };
_data set ["p2p", _p2p];
// Destinataire déconnecté : le SMS reste « non transmis » dans la conversation.
if (isNull _target) exitWith {
    _m set ["status", "FAILED"];
    ["WARNING", format ["%1 n'est plus connecté : SMS non transmis", _peer], 4, 30] call comspec_atak_native_fnc_notify;
    true
};
// Débit simulé : le SMS part après la latence ; sans réseau il attend dans la file (« en attente de réseau »).
private _r = [{ params ["_args", "_target"]; ["comspec_atak_native_p2p", _args, _target] call CBA_fnc_targetEvent; }, [[name player, _body, _time, _id, player, _date], _target], "SMS", 1] call comspec_atak_native_fnc_netSend;
if (_r isEqualTo "QUEUED") then { _m set ["status", "QUEUED"]; };
true
