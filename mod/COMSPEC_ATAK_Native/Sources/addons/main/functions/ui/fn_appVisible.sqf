/*
    L'app est-elle affichée (lanceur, dock) ? Params : [HashMap de l'app (fn_appList)]
    Masquée si le joueur l'a cachée (Réglages > Applications, profil COMSPEC_ATAK_HiddenApps)
    ou, pour la section Civil, si le serveur coupe les apps civiles. Réglages n'est jamais masquable.
*/
params [["_app", createHashMap]];
private _id = _app getOrDefault ["id", ""];
if (_id isEqualTo "Settings") exitWith { true };
if ((_app getOrDefault ["section", ""]) isEqualTo "Civil" && {!(missionNamespace getVariable ["comspec_atak_native_civil_apps", true])}) exitWith { false };
!(_id in (profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []]))
