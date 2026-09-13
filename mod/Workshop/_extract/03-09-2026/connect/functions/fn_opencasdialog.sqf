/*
    Ouvre l’appui aérien : formulaire de demande joueur (pas la boîte à ordres).
*/
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

[] call comspec_overwatch_connect_fnc_casRequestShow;
