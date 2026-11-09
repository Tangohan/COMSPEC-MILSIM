/*
    Message direct à un joueur, sans passer par Athena.
    Retourne true si l'événement a été envoyé.
*/
params [["_peer", ""], ["_body", ""]];
_body = trim _body;
if (_body isEqualTo "" || {_peer isEqualTo ""}) exitWith { false };
// Commandes du tchat (/urgent, /contact…) : puces en tête du SMS.
([_body] call comspec_atak_native_fnc_chatCommand) params ["", "", "_text", "_unknown", "_tags", "_help"];
if (_help) exitWith { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatWiki", true]; [{ [["CHAT", "SMS"] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SMS")] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; true };
if ((count _unknown) > 0) exitWith { ["WARNING", format ["Commande inconnue : %1 (tapez /aide)", _unknown joinString " "], 4, 30] call comspec_atak_native_fnc_notify; false };
if (_text isEqualTo "") exitWith { false };
_body = ((_tags apply { format ["[%1]", _x] }) joinString "") + ([" ", ""] select ((count _tags) isEqualTo 0)) + _text;
if ((count _body) > 400) then { _body = _body select [0, 400]; };
// Fil d'un groupe de conversation : réponse au groupe, s'il est encore écouté.
if ((_peer find "GROUPE ") isEqualTo 0) exitWith {
    private _gi = (["names"] call comspec_atak_native_fnc_meshGroups) find (_peer select [7]);
    if (_gi >= 0 && {(_gi + 1) in (["mine"] call comspec_atak_native_fnc_meshGroups)}) then {
        private _keep = uiNamespace getVariable ["COMSPEC_ATAK_MeshTx", []];
        uiNamespace setVariable ["COMSPEC_ATAK_MeshTx", [_gi + 1]];
        ["send", [_body]] call comspec_atak_native_fnc_meshGroups;
        uiNamespace setVariable ["COMSPEC_ATAK_MeshTx", _keep];
    };
    true
};
private _target = objNull;
{ if ((name _x) isEqualTo _peer) exitWith { _target = _x; }; } forEach allPlayers;
// Réponse à un message du poste (« TOC · OVERWATCH », fn_tocAction) : remise à l'opérateur assis au PC de TOC de mon camp.
if (isNull _target && {(_peer find "TOC · ") isEqualTo 0}) then {
    private _ops = allPlayers select { (_x getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "" && {side group _x isEqualTo side group player} && {_x isNotEqualTo player} };
    if (_ops isNotEqualTo []) then { _target = _ops select 0; };
};
private _time = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _date = [] call comspec_atak_native_fnc_p2pDate;
private _id = format ["%1-%2-%3", getPlayerUID player, round (time * 1000), floor random 1e5];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _p2p = _data getOrDefault ["p2p", []];
private _m = createHashMapFromArray [["peer", _peer], ["dir", "out"], ["body", _body], ["time", _time], ["date", _date], ["read", true], ["id", _id], ["status", "SENDING"], ["sentAt", time]];
_p2p pushBack _m;
while {(count _p2p) > 200} do { _p2p deleteAt 0; };
_data set ["p2p", _p2p];
// Archive sur le site (fn_p2pArchive) : le champ « à » porte l'indicatif et le nom du destinataire. Jamais depuis le téléphone d'un autre.
_m set ["noSite", (["active"] call comspec_atak_native_fnc_phoneOwn) isEqualTo true];
if (!isNull _target) then {
    private _toNames = [name _target];
    private _toCs = [_target, true] call comspec_atak_native_fnc_unitCallsign;
    if (_toCs isEqualType "" && {_toCs isNotEqualTo ""}) then { _toNames pushBackUnique _toCs; };
    _m set ["siteTo", _toNames joinString ","];
};
// Destinataire déconnecté : le SMS reste « non transmis » dans la conversation.
if (isNull _target) exitWith {
    _m set ["status", "FAILED"];
    ["WARNING", format ["%1 n'est plus connecté : SMS non transmis", _peer], 4, 30] call comspec_atak_native_fnc_notify;
    true
};
// Liaison réaliste : destinataire joint par le maillage, même sans passerelle (fn_meshGraph "peer", lot 10). Latence du chemin.
private _path = ["peer", [_target]] call comspec_atak_native_fnc_meshGraph;
if (_path isNotEqualTo [] && {(_path select 2) < 3}) exitWith {
    [{ params ["_args", "_target"]; ["comspec_atak_native_p2p", _args, _target] call CBA_fnc_targetEvent; }, [[name player, _body, _time, _id, player, _date], _target],
        0.1 + (_path select 0) * (missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15]) / 1000] call comspec_atak_native_fnc_waitAndExec;
    true
};
// Débit simulé : le SMS part après la latence ; sans réseau il attend dans la file (« en attente de réseau »).
private _r = [{ params ["_args", "_target"]; ["comspec_atak_native_p2p", _args, _target] call CBA_fnc_targetEvent; }, [[name player, _body, _time, _id, player, _date], _target], "SMS", 1] call comspec_atak_native_fnc_netSend;
if (_r isEqualTo "QUEUED") then { _m set ["status", "QUEUED"]; };
true
