/*
    Détecte les mods compagnons chargés côté client (cTab, ATAK Enhanced).
    Retourne un HashMap : has_ctab, has_atak_enhanced, has_athena_ctab (bool).
*/
private _hasCtab = isClass (configFile >> "CfgPatches" >> "ctab_core")
    || { isClass (configFile >> "CfgPatches" >> "ctab_main") }
    || { isClass (configFile >> "CfgPatches" >> "cTab") }
    || { !isNil "cTab_fnc_addNotification" }
    || { !isNil "cTabUserMarkerList" };

private _hasEnhanced = isClass (configFile >> "CfgPatches" >> "Iceman_ATAK_Weather")
    || { isClass (configFile >> "CfgPatches" >> "Iceman_ATAK_WaveRelay") }
    || { isClass (configFile >> "CfgPatches" >> "Iceman_ATAK_Alerts") }
    || { !isNil "Iceman_fnc_alerts_send" }
    || { !isNil "Iceman_fnc_alerts_receive" }
    || { !isNil "Iceman_fnc_bda_send" }
    || { !isNil "Iceman_fnc_bda_receive" }
    || { !isNil "Iceman_fnc_photo_getRecords" };

private _hasAthenaCtab = isClass (configFile >> "CfgPatches" >> "comspec_overwatch_atak_athena");

createHashMapFromArray [
    ["has_ctab", _hasCtab],
    ["has_atak_enhanced", _hasEnhanced],
    ["has_athena_ctab", _hasAthenaCtab]
]
