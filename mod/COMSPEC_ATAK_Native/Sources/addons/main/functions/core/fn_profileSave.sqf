/*
    Écriture du profil sur le disque : la SEULE fonction de l'ATAK natif qui écrit le profil. L'écriture réécrit tout le
    fichier du joueur d'un coup (sauvegarde hors ligne, montre, carnet de distances, apps, réglages…) et fige une image :
    14 gels de 100 à 325 ms en 128 min de jeu dans les journaux du 15/11.

    Personne n'écrit plus de son côté : les sauvegardes périodiques ("dirty") et les écritures regroupées (fn_saveProfile :
    réglages, apps, file hors ligne…) marquent seulement le profil modifié. Une seule échéance ("tick", boucle de 2 s de
    XEH_postInitClient) l'écrit, à un moment calme :
      - 30 s au moins après la première modification (une rafale de réglages ne fait qu'une écriture) ;
      - 120 s au moins après l'écriture précédente ;
      - pas de tir du joueur, de tir proche ni de coup reçu depuis 10 s, pas de suppression ;
      - pas dans un véhicule qui roule ou vole (plus de 5 km/h) ;
      - téléphone rangé depuis 5 s au moins (ni en main, ni en miniature) ;
      - tout de suite si le menu Échap est ouvert, si le jeu est en arrière-plan ou si le joueur est mort (personne ne
        voit l'image figée).
    Modification en attente depuis plus de 10 min (téléphone affiché ou véhicule en mouvement sans arrêt) : écrite dès
    qu'aucun tir n'a eu lieu depuis 3 s. Jamais pendant un échange de tirs.
    Rien n'est perdu entre-temps : le profil reste à jour en mémoire, il est écrit en fin de mission, quand le joueur
    quitte la partie (fermeture de l'écran de jeu) et par Arma en quittant le jeu. Seul un plantage du jeu peut perdre
    les modifications pas encore écrites (10 min au plus hors échange de tirs).
    Hors boucle du téléphone (serveur dédié, menu principal) : fn_saveProfile garde son échéance de 60 s.

    Chaque écriture laisse une ligne dans le RPT : [PERF] Écriture du profil (raison, en attente depuis N s) : N ms.
    Même clé COMSPEC_ProfileSavePending que le module connect d'Overwatch : les deux addons partagent le même fichier.

    Params : "dirty" (profil modifié) | "tick" (échéance) | "calm" (moment calme ? booléen) | "init" (suivi des tirs du
             joueur, une fois au postInit client) | "now" (écrit tout de suite ; 2e paramètre : raison pour le journal)
             | "flush" (fin de mission, départ : écrit s'il reste une modification)
*/
params [["_mode", "dirty"], ["_why", "immédiate"]];
// Moment calme ? Avant le switch : c'est la seule action qui rend autre chose que true.
if (_mode isEqualTo "calm") exitWith {
    if (!hasInterface) exitWith { true };
    if (isNull player || {!alive player}) exitWith { true };
    if (!isNull (findDisplay 49) || {!isGameFocused}) exitWith { true };
    private _quiet = diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_LastShotAt", -100]);
    // 2e paramètre vrai : attente trop longue, seul l'échange de tirs retient encore l'écriture.
    if (_why isEqualTo true) exitWith { _quiet >= 3 };
    if (_quiet < 10 || {(getSuppression player) > 0.05}) exitWith { false };
    private _veh = vehicle player;
    if (_veh isNotEqualTo player && {(vectorMagnitude (velocity _veh)) > 1.4}) exitWith { false };
    // Téléphone affiché (en main ou en miniature), ou rangé depuis moins de 5 s (relevé par "tick", toutes les 2 s).
    if (!isNull ([] call comspec_atak_native_fnc_display) || {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_PhoneShownAt", -100]) < 5}) exitWith { false };
    true
};
switch (_mode) do {
    case "dirty": {
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirty", true];
        if ((missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirtySince", -1]) < 0) then { missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirtySince", diag_tickTime]; };
    };
    case "init": {
        if (!hasInterface) exitWith {};
        // L'échéance tourne : fn_saveProfile n'arme plus son propre délai de 60 s.
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileTickOn", true];
        // Dernier tir (le sien, un tir à moins de 70 m, un coup reçu) : un seul instant, lu par "calm". Reposé sur l'unité
        // du joueur à chaque changement (réapparition, prise de contrôle).
        ["unit", {
            params ["_unit"];
            if (isNull _unit || {_unit getVariable ["COMSPEC_ATAK_CalmEh", false]}) exitWith {};
            _unit setVariable ["COMSPEC_ATAK_CalmEh", true];
            { _unit addEventHandler [_x, { missionNamespace setVariable ["COMSPEC_ATAK_LastShotAt", diag_tickTime]; }]; } forEach ["FiredMan", "FiredNear", "Hit"];
        }, true] call CBA_fnc_addPlayerEventHandler;
    };
    case "tick": {
        if (!isNull ([] call comspec_atak_native_fnc_display)) then { missionNamespace setVariable ["COMSPEC_ATAK_PhoneShownAt", diag_tickTime]; };
        private _since = missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirtySince", -1];
        if !((missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirty", false]) || {missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]}) exitWith {
            // Écrit entre-temps par Overwatch (même fichier) : plus rien en attente.
            if (_since >= 0) then { missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirtySince", -1]; };
        };
        private _now = diag_tickTime;
        if (_since < 0) then { _since = _now; missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirtySince", _now]; };
        if (_now < (missionNamespace getVariable ["COMSPEC_ATAK_ProfileSaveAt", 0])) exitWith {};
        private _age = _now - _since;
        if (_age < 30) exitWith {};
        private _late = _age >= 600;
        if !(["calm", _late] call comspec_atak_native_fnc_profileSave) exitWith {};
        ["now", ["moment calme", "attente de plus de 10 min"] select _late] call comspec_atak_native_fnc_profileSave;
    };
    case "flush": {
        if ((missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirty", false]) || {missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]}) then {
            ["now", "fin de mission ou départ"] call comspec_atak_native_fnc_profileSave;
        };
    };
    case "now": {
        private _since = missionNamespace getVariable ["COMSPEC_ATAK_ProfileDirtySince", -1];
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirty", false];
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileDirtySince", -1];
        missionNamespace setVariable ["COMSPEC_ATAK_ProfileSaveAt", diag_tickTime + 120];
        // Écriture regroupée en attente (fn_saveProfile, ATAK et Overwatch : même fichier) servie par celle-ci.
        missionNamespace setVariable ["COMSPEC_ProfileSavePending", false];
        private _t0 = diag_tickTime;
        saveProfileNamespace;
        ["Écriture du profil", _t0] call comspec_atak_native_fnc_perfAcc;
        diag_log format ["[COMSPEC ATAK NATIVE][PERF] Écriture du profil (%1, en attente depuis %2 s) : %3 ms",
            _why, [round (_t0 - _since), 0] select (_since < 0), round ((diag_tickTime - _t0) * 1000)];
    };
};
true
