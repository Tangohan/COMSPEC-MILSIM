/*
    Autorité de mission (à appeler sur le serveur) : vrai si l'unité est un Zeus (curateur assigné), l'administrateur
    connecté (connecté ou vote), ou l'hôte d'une partie hébergée. Sert aux événements serveur qui modifient l'état de la
    mission (campagnes : charger, supprimer, importer, remettre à zéro…), qu'un client quelconque pouvait déclencher.
    [_unit] call comspec_sse_fnc_isAuthority -> Boolean
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) exitWith { false };
if (!isNull (getAssignedCuratorLogic _unit)) exitWith { true };
// Hôte d'une partie hébergée : son unité est locale au serveur.
if (isServer && {local _unit} && {hasInterface}) exitWith { true };
if (!isMultiplayer) exitWith { true };
(admin (owner _unit)) > 0
