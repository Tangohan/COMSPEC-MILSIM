/*
    Ouvre la vue "Téléphone" de la tablette Athena (code + QR) depuis l'app Athena
    d'ATAK Enhanced — scanner avec un vrai téléphone donne accès à l'ATAK Athena dédié.
*/
if (!hasInterface) exitWith {};
["phone", true] call comspec_overwatch_connect_fnc_openTabletView;
