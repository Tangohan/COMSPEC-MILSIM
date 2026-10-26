/*
    L'app est-elle affichée (lanceur, dock) ? Params : [HashMap de l'app (fn_appList)]
    Masquée si le joueur l'a cachée (Réglages > Applications, profil COMSPEC_ATAK_HiddenApps)
    ou, pour la section Civil, si le serveur coupe les apps civiles, ou les apps Athena si le joueur a répondu Non au service Athéna. Réglages n'est jamais masquable.
    Admin n'apparaît qu'à qui y a droit (Zeus, admin du serveur, gestionnaire désigné, gestion Athena).
*/
params [["_app", createHashMap]];
private _id = _app getOrDefault ["id", ""];
if (_id isEqualTo "Settings") exitWith { true };
// App Admin : Zeus, admin du serveur, gestionnaire désigné ou gestion de la communauté sur Athena (fn_adminClient).
if (_id isEqualTo "Admin") exitWith { ["visible"] call comspec_atak_native_fnc_adminClient };
// Service Athéna refusé (ATAK local) : pas d'apps qui ne marchent qu'avec Athena.
if (_id in ["Athena", "Resynch", "Discord", "Wanted"] && {([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off"}) exitWith { false };
if ((_app getOrDefault ["section", ""]) isEqualTo "Civil" && {!(missionNamespace getVariable ["comspec_atak_native_civil_apps", true])}) exitWith { false };
!(_id in (profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []]))
