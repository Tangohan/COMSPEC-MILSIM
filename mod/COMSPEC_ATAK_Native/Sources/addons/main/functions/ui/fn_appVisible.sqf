/*
    L'app est-elle affichée (lanceur, dock) ? Params : [HashMap de l'app (fn_appList)]
    Masquée si le joueur l'a désinstallée (App Store, profil COMSPEC_ATAK_HiddenApps) ou si l'encadrement l'interdit
    (fn_appInstall), ou, pour la section Civil, si le serveur coupe les apps civiles, ou les apps Athena si le joueur a
    répondu Non au service Athéna. Une app obligatoire reste affichée. Réglages est toujours là, l'App Store aussi sauf en
    mode Essentiel (4.18.0, fn_essentialMode : seules les apps de base restent ; rien n'est désinstallé, c'est ici, à la source,
    que le mode filtre l'accueil, les apps regroupées, la recherche, le dock, le rail de la carte et les dossiers).
    Params : [HashMap de l'app, ignorer le mode Essentiel (inventaire envoyé au site : les apps installées, pas l'affichage)]
    Admin n'apparaît qu'à qui y a droit (Zeus, admin du serveur, gestionnaire désigné, gestion Athena).
*/
params [["_app", createHashMap], ["_ignoreEssential", false]];
private _id = _app getOrDefault ["id", ""];
if (_id isEqualTo "Settings") exitWith { true };
// Mode Essentiel : toute app hors de la table du mode est masquée (comme une app interdite, sans rien désinstaller).
if (!_ignoreEssential && {["hides", _id] call comspec_atak_native_fnc_essentialMode}) exitWith { false };
if (_id isEqualTo "Store") exitWith { true };
// Mode pilote : tuile seulement s'il est activé (réglage du serveur ou du joueur).
if (_id isEqualTo "Pilot" && {!(["on"] call comspec_atak_native_fnc_pilotMode)}) exitWith { false };
// App Admin : Zeus, admin du serveur, gestionnaire désigné ou gestion de la communauté sur Athena (fn_adminClient).
if (_id isEqualTo "Admin") exitWith { ["visible"] call comspec_atak_native_fnc_adminClient };
private _inst = ["state", _id] call comspec_atak_native_fnc_appInstall;
if (_inst isEqualTo "forbidden") exitWith { false };
// Service Athéna refusé (ATAK local) : pas d'apps qui ne marchent qu'avec Athena.
if (_id in ["Athena", "Resynch", "Discord", "Wanted", "Fiches"] && {([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off"}) exitWith { false };
// Poste TOC : seulement au PC portable de TOC (fn_tocPost).
if (_id isEqualTo "Toc") exitWith { ([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo "toc" };
if ((_app getOrDefault ["section", ""]) isEqualTo "Civil" && {!(missionNamespace getVariable ["comspec_atak_native_civil_apps", true])}) exitWith { false };
_inst isNotEqualTo "removed"
