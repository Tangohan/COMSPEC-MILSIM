#include "script_component.hpp"
/*
    Équipement, joueur : actions ACE (caisse de kits, « Copier ma tenue pour Athena »), puis liste de ses kits Athena
    demandée au serveur (de nouveau envoyée par le serveur à chaque paquet chargé).
*/
if (!hasInterface) exitWith {};
[] call comspec_sse_fnc_loadoutInitACE;
[{ !isNull player }, {
    if (isServer) then { ["hello", [player]] call comspec_sse_fnc_loadoutTake; } else { ["hello", [player]] remoteExecCall ["comspec_sse_fnc_loadoutTake", 2]; };
}, [], 60] call CBA_fnc_waitUntilAndExecute;
