/*
    Accusé d'un SMS envoyé : ["DELIVERED" | "READ", heure]. Ne fait jamais reculer l'état (lu reste lu).
    Params : [id, état, heure]
*/
params [["_id", ""], ["_state", ""], ["_time", ""]];
if (_id isEqualTo "") exitWith {};
private _rank = createHashMapFromArray [["QUEUED", 0], ["SENDING", 1], ["DELIVERED", 2], ["READ", 3]];
private _p2p = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["p2p", []];
private _i = _p2p findIf { (_x getOrDefault ["id", ""]) isEqualTo _id && {(_x getOrDefault ["dir", ""]) isEqualTo "out"} };
if (_i < 0) exitWith {};
private _m = _p2p select _i;
if ((_rank getOrDefault [_state, 0]) <= (_rank getOrDefault [_m getOrDefault ["status", "SENDING"], 1])) exitWith {};
_m set ["status", _state];
_m set ["statusTime", _time];
if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then { [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
