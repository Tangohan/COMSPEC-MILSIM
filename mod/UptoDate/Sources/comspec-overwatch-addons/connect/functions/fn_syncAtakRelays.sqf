/*
    Synchronise tous les relais connus (pose Zeus + objets encore présents).
*/
if (!hasInterface && {!isServer}) exitWith {};
private _all = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
private _list = _all select { !isNull _x };
{
    if (!(_x getVariable ["COMSPEC_AtakRelay", false])) then { continue };
    [_x, alive _x] call comspec_overwatch_connect_fnc_syncAtakRelay;
} forEach _list;
// Diffusion seulement si des relais ont disparu : la liste entière repartait toutes les 12 s
// (chaque client + hub serveur) vers tous les clients (+ JIP), même inchangée.
// Serveur seul : un client pas encore à jour effaçait un relais tout juste posé.
if (isServer && {(count _list) != (count _all)}) then {
    missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];
};
true
