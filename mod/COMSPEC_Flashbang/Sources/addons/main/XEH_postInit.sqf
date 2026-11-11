/*
    COMSPEC Flashbang : initialisation. Aucune boucle permanente : les effets ne tournent que pendant une détonation.
*/
if (hasInterface) then {
    // Changement d'unité (réapparition, Zeus « contrôler l'unité », sélection d'une autre unité) : on remet tout à zéro.
    ["unit", { [] call comspec_fb_fnc_playerReset }] call CBA_fnc_addPlayerEventHandler;
    // Modules Zeus (ZEN, facultatif).
    if (isClass (configFile >> "CfgPatches" >> "zen_custom_modules")) then {
        [] call comspec_fb_fnc_zenRegister;
    };
};
["init", format ["v%1 serveur=%2 interface=%3 ACE=%4 ACRE=%5 TFAR=%6 ZEN=%7",
    getText (configFile >> "CfgPatches" >> "comspec_flashbang_main" >> "versionStr"), isServer, hasInterface,
    isClass (configFile >> "CfgPatches" >> "ace_common"), isClass (configFile >> "CfgPatches" >> "acre_main"),
    isClass (configFile >> "CfgPatches" >> "tfar_core") || {isClass (configFile >> "CfgPatches" >> "task_force_radio")},
    isClass (configFile >> "CfgPatches" >> "zen_custom_modules")]] call comspec_fb_fnc_log;
