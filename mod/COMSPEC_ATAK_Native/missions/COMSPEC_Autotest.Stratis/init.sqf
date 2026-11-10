/*
    Mission de test automatique du mod COMSPEC ATAK et du SSE (tools/INGAME_TEST.md). Solo, hors ligne, sans clic humain.
    Écrit au RPT : AUTOTEST|OK|nom, AUTOTEST|KO|nom|motif, AUTOTEST|SKIP|nom|motif, et prend une capture par écran.
    Elle change des préférences du profil (thème, code, fond de carte) : elle ne tourne que sous le profil de test.
    Hors des PBO publiés : missions/ n'est empaqueté par aucun build.
*/
if (!hasInterface) exitWith {};
[] spawn {
    waitUntil { uiSleep 0.2; !isNull player && {!isNull findDisplay 46} };
    AT_Groupes = [];
    AT_Quitter = true;
    call compile preprocessFileLineNumbers "autotest_params.sqf";
    call compile preprocessFileLineNumbers "scripts\lib.sqf";
    diag_log format ["AUTOTEST|DEBUT|profil=%1|monde=%2|version=%3|mods=%4", profileName, worldName, productVersion select 2,
        (["comspec_atak_native_main", "comspec_sse_main", "ace_main", "cba_main", "acre_main"] select { isClass (configFile >> "CfgPatches" >> _x) }) joinString ","];
    if (profileName isNotEqualTo "COMSPECTest") exitWith {
        diag_log format ["AUTOTEST|KO|profil|profil %1 : la mission ne tourne que sous -name=COMSPECTest (elle change des préférences)", profileName];
        diag_log "AUTOTEST|FIN|ok=0|ko=1|skip=0";
        if (AT_Quitter) then { uiSleep 2; endMission "END1"; };
    };
    // Deuxième sûreté après cba_settings.sqf (profil de test uniquement).
    profileNamespace setVariable ["COMSPEC_AthenaMode", "off"];
    player allowDamage false;
    player addBackpack "B_Carryall_oli"; // place pour les objets donnés : un addItem sans place échoue sans rien dire
    player setPosATL [(getPosATL player) select 0, (getPosATL player) select 1, 0];
    AT_Base = getPosATL player;
    uiSleep 8; // boucles du mod lancées
    {
        call compile preprocessFileLineNumbers format ["scripts\%1.sqf", _x];
    } forEach ["fonctions", "telephone", "sms", "carte", "pilote", "terrain", "sse", "fichiers", "equipement", "ia_rp", "zeus", "radio", "reseau", "mpudiag", "mpuhud", "chiffrement", "relais"];
    diag_log format ["AUTOTEST|FIN|ok=%1|ko=%2|skip=%3", AT_NOk, AT_NKo, AT_NSkip];
    uiSleep 3;
    if (AT_Quitter) then { endMission "END1"; };
};
