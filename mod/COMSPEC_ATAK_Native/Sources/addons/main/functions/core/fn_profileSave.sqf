/*
    Écriture du profil sur le disque. saveProfileNamespace réécrit tout le profil d'un coup (sauvegarde hors ligne,
    montre, carnet de distances, apps…) et fige une image : quatre boucles l'appelaient chacune de leur côté (hors ligne
    toutes les 2 min, montre, distances et temps d'écran toutes les 5 min), soit 6 à 7 à-coups toutes les 5 min.
    Ces sauvegardes périodiques marquent seulement le profil modifié ; une seule échéance l'écrit, au plus toutes les
    2 min (délai de la sauvegarde hors ligne, qui doit survivre à un crash du jeu). Les sauvegardes déclenchées par une
    action du joueur (réglages, effacement…) restent immédiates et ne passent pas ici.
    Params : "dirty" (profil modifié) | "tick" (échéance, boucle réseau toutes les 2 s de XEH_postInitClient)
             | "now" (écrit tout de suite) | "flush" (fin de mission : écrit s'il reste une modification)
*/
params [["_mode", "dirty"]];
switch (_mode) do {
    case "dirty": { missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirty", true]; };
    case "tick": {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirty", false]) exitWith {};
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_ProfileSaveAt", 0])) exitWith {};
        ["now"] call comspec_atak_native_fnc_profileSave;
    };
    case "flush": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirty", false]) then { ["now"] call comspec_atak_native_fnc_profileSave; };
    };
    case "now": {
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirty", false];
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileSaveAt", diag_tickTime + 120];
        // Écriture regroupée en attente (fn_saveProfile, ATAK et Overwatch : même fichier) servie par celle-ci.
        missionNamespace setVariable ["COMSPEC_ProfileSavePending", false];
        private _t0 = diag_tickTime;
        saveProfileNamespace;
        private _ms = round ((diag_tickTime - _t0) * 1000);
        if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then { ["Écriture du profil", _t0] call comspec_atak_native_fnc_perfAcc; };
        if (_ms >= 100) then { diag_log format ["[COMSPEC ATAK NATIVE][PERF] Écriture du profil (sauvegardes périodiques) : %1 ms", _ms]; };
    };
};
true
