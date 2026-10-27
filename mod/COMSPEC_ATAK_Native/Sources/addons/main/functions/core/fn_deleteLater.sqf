/*
    Ménage différé demandé par un client (caisse larguée par UberEats ou par la logistique, jerrican) : fait par le
    serveur, pour survivre au départ du demandeur.
    Client -> serveur : [objets, délai (s)] remoteExecCall ["comspec_atak_native_fnc_deleteLater", 2]
    (fn_foodAction « drop », fn_logisticsAction « drop »).
    Lot mpauth3 (SEC-024, MP2-04) : remplace l'événement CBA comspec_atak_native_deleteLater, qui supprimait plus tard
    n'importe quel objet de la partie (véhicule, bâtiment, unité d'un autre joueur), au délai choisi par l'émetteur,
    même après son départ. D'un client : seulement les objets de son poste (owner = remoteExecutedOwner : ceux qu'il a
    créés et qui lui sont encore locaux), jamais une unité, 4 au plus par demande, 20 en attente au plus par poste ;
    délai borné de 10 min à 2 h ; au moment de supprimer, rien qui a un équipage.
    Params : [objets, délai (s)]
*/
params [["_objs", []], ["_delay", 1800]];
if (!isServer) exitWith {};
if (isRemoteExecutedJIP) exitWith {
    diag_log "[COMSPEC ATAK NATIVE][WARN][CLEANUP] Ménage différé refusé (rejoué depuis la file JIP)";
};
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
if (!(_objs isEqualType []) || {!(_delay isEqualType 0)}) exitWith {};
private _who = [2, remoteExecutedOwner] select _remote;
// Objets du poste qui demande (créés par lui, encore locaux chez lui), jamais une unité ; 4 au plus par demande.
_objs = (_objs select { _x isEqualType objNull && {!isNull _x} && {!(_x isKindOf "CAManBase")} && {!_remote || {(owner _x) isEqualTo _who}} }) select [0, 4];
// 20 objets en attente au plus par poste : la file du serveur ne grossit pas sans fin.
private _now = time;
private _q = localNamespace getVariable ["COMSPEC_ATAK_DeleteLater", createHashMap];
localNamespace setVariable ["COMSPEC_ATAK_DeleteLater", _q];
private _due = (_q getOrDefault [_who, []]) select { _x > _now };
_objs = _objs select [0, (20 - (count _due)) max 0];
if (_objs isEqualTo []) exitWith {
    _q set [_who, _due];
    if (_remote) then { diag_log format ["[COMSPEC ATAK NATIVE][WARN][CLEANUP] Ménage différé refusé au poste %1 (aucun objet à lui, ou 20 déjà en attente)", _who]; };
};
// Délai de 10 min à 2 h : un client ne s'en sert pas pour supprimer tout de suite ni pour remplir la file.
_delay = (_delay max 600) min 7200;
{ _due pushBack (_now + _delay); } forEach _objs;
_q set [_who, _due];
[{ { if (!isNull _x && {(crew _x) isEqualTo []}) then { deleteVehicle _x; }; } forEach _this; }, _objs, _delay] call CBA_fnc_waitAndExecute;
true
