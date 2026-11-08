/*
    Écriture du profil regroupée. saveProfileNamespace réécrit tout le fichier .vars du joueur : sur certains PC
    (Linux/Proton, profil volumineux) chaque appel fige l'image près d'une seconde, et le mod en faisait à chaque
    réglage, file d'attente hors ligne ou étape de dépannage. Les écritures sont maintenant regroupées : une seule
    toutes les 60 s au plus, et une écriture immédiate en fin de mission (fermeture de l'écran de jeu, Ended/MPEnded).
    Le profil reste à jour en mémoire entre-temps, et Arma l'enregistre de toute façon en quittant le jeu.
    Même clé (COMSPEC_ProfileSavePending) que le module connect : les deux addons partagent la même écriture.
    [] call comspec_atak_native_fnc_saveProfile;      // regroupée
    [true] call comspec_atak_native_fnc_saveProfile;  // tout de suite (fin de mission)
*/
params [["_now", false]];
if (_now || {isNil "comspec_atak_native_fnc_waitAndExec"}) exitWith {
    missionNamespace setVariable ["COMSPEC_ProfileSavePending", false];
    // Même fichier que l'échéance des sauvegardes périodiques (fn_profileSave) : elle est servie aussi.
    missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirty", false];
    missionNamespace setVariable ["COMSPEC_ATAK_ProfileSaveAt", diag_tickTime + 120];
    private _t0 = diag_tickTime;
    saveProfileNamespace;
    if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then { ["Écriture du profil", _t0] call comspec_atak_native_fnc_perfAcc; };
    private _ms = round ((diag_tickTime - _t0) * 1000);
    if (_ms >= 100) then { diag_log format ["[COMSPEC ATAK NATIVE][PERF] Écriture du profil : %1 ms", _ms]; };
};
if (missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ProfileSavePending", true];
[{
    if !(missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) exitWith {};
    [true] call comspec_atak_native_fnc_saveProfile;
}, [], 60] call comspec_atak_native_fnc_waitAndExec;
