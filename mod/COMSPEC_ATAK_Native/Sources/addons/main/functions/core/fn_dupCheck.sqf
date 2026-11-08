/*
    Deux copies du mod chargées (retour de jeu 2.9.24 : pack d'un serveur qui contient main.pbo, plus le mod du Workshop) :
    Arma garde une seule version de chaque fonction, mais la version vue par le joueur dépend de l'ordre de chargement,
    et le support ne sait plus laquelle tourne. Signalé dans le journal et une fois dans les notifications du téléphone.
    Renvoie la liste des mods qui définissent l'ATAK natif.
*/
private _mods = configSourceModList (configFile >> "CfgPatches" >> "comspec_atak_native_main");
if ((count _mods) > 1) then {
    ["WARNING", "BOOT", format ["Deux copies du mod chargées (%1) : gardez seulement le mod COMSPEC ATAK du Workshop", _mods joinString ", "]] call comspec_atak_native_fnc_log;
    [{
        ["WARNING", "Deux copies du mod COMSPEC ATAK sont chargées : gardez seulement celle du Workshop, puis relancez Arma", 10, 60] call comspec_atak_native_fnc_notify;
    }, [], 20] call comspec_atak_native_fnc_waitAndExec;
};
_mods
