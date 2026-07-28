/*
    Supprime l'état ATAK mémorisé pour un UID (serveur).
*/
if (!isServer) exitWith {};

params ["_uid"];

if (_uid isEqualTo "") exitWith {};
if (isNil "COMSPEC_DisconnectedAtakState") exitWith {};

COMSPEC_DisconnectedAtakState deleteAt _uid;
publicVariable "COMSPEC_DisconnectedAtakState";
