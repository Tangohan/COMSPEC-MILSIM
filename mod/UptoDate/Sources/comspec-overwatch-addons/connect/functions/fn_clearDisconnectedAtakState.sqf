/*
    Supprime l'état ATAK mémorisé pour un UID (serveur).
*/
if (!isServer) exitWith {};

params ["_uid"];

if (_uid isEqualTo "") exitWith {};
if (isNil "COMSPEC_DisconnectedAtakState") exitWith {};

COMSPEC_DisconnectedAtakState deleteAt _uid;
// Plus de publicVariable : seul le serveur lit cette table (restauration JIP via remoteExec) ;
// la diffuser envoyait tout l'état ATAK des déconnectés à chaque client à chaque départ.
