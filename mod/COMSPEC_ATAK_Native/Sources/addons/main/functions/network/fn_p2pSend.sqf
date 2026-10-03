/*
    Message direct à un joueur, sans passer par Athena (principe de l'app message de BCE, Aaren, APL-SA).
    Retourne true si l'événement a été envoyé.
*/
params [["_peer", ""], ["_body", ""]];
_body = trim _body;
if (_body isEqualTo "" || {_peer isEqualTo ""}) exitWith { false };
if ((count _body) > 400) then { _body = _body select [0, 400]; };
private _target = objNull;
{ if ((name _x) isEqualTo _peer) exitWith { _target = _x; }; } forEach allPlayers;
if (isNull _target) exitWith {
    ["WARNING", format ["%1 n'est plus connecté", _peer], 4, 30] call comspec_atak_native_fnc_notify;
    false
};
private _time = [daytime, "HH:MM"] call BIS_fnc_timeToString;
["comspec_atak_native_p2p", [name player, _body, _time], _target] call CBA_fnc_targetEvent;
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _p2p = _data getOrDefault ["p2p", []];
_p2p pushBack createHashMapFromArray [["peer", _peer], ["dir", "out"], ["body", _body], ["time", _time], ["read", true]];
while {(count _p2p) > 200} do { _p2p deleteAt 0; };
_data set ["p2p", _p2p];
true
