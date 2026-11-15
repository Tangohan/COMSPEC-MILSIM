/*
    Écriture du profil regroupée. L'écriture du profil réécrit tout le fichier .vars du joueur : sur certains PC
    (Linux/Proton, profil volumineux) chaque appel fige l'image près d'une seconde, et le mod en faisait à chaque
    réglage, file d'attente hors ligne ou étape de dépannage. Cette fonction n'écrit plus rien elle-même : elle marque
    le profil modifié, et fn_profileSave (seule fonction qui écrit) le range à un moment calme (hors tir, hors véhicule en
    mouvement, téléphone rangé), puis en fin de mission et au départ du joueur.
    Le profil reste à jour en mémoire entre-temps, et Arma l'enregistre de toute façon en quittant le jeu.
    Même clé (COMSPEC_ProfileSavePending) que le module connect : les deux addons partagent la même écriture.
    [] call comspec_atak_native_fnc_saveProfile;      // regroupée, à un moment calme
    [true] call comspec_atak_native_fnc_saveProfile;  // tout de suite (fin de mission)
*/
params [["_now", false]];
if (_now) exitWith { ["now", "demandée tout de suite"] call comspec_atak_native_fnc_profileSave; };
if (missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ProfileSavePending", true];
if ((missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirtySince", -1]) < 0) then { missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirtySince", diag_tickTime]; };
// Boucle du téléphone lancée (client en mission) : son échéance choisit le moment (fn_profileSave "tick").
if (missionNamespace getVariable ["COMSPEC_ATAK_ProfileTickOn", false]) exitWith {};
// Sans elle (serveur dédié, menu principal, avant le postInit) : une écriture 60 s plus tard, comme avant, sauf si la
// boucle a démarré entre-temps.
if (isNil "comspec_atak_native_fnc_waitAndExec") exitWith { ["now", "sans file d'attente"] call comspec_atak_native_fnc_profileSave; };
[{
    if !(missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_ATAK_ProfileTickOn", false]) exitWith {};
    ["now", "regroupée, 60 s"] call comspec_atak_native_fnc_profileSave;
}, [], 60] call comspec_atak_native_fnc_waitAndExec;
