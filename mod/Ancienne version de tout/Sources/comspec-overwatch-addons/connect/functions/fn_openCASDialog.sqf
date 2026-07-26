/*
    Ouvre la vue Appui aérien de la tablette Athena (plus de dialog CAS).
*/
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

["cas"] call comspec_overwatch_connect_fnc_openTabletView;
