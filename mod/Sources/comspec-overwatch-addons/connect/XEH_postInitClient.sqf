// CBA Extended Event Handler - Post Init Client
// Lancé automatiquement après chargement mission côté client

if (!hasInterface) exitWith {};

// Lancer initialisation ATAK avec délai pour s'assurer que tout est chargé
[{
    [] call comspec_overwatch_connect_fnc_initATAK;
}, [], 3] call CBA_fnc_waitAndExecute;
