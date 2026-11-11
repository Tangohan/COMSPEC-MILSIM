/*
    COMSPEC Flashbang : réglages CBA (Options > Réglages de l'addon > COMSPEC Flashbang) et état local.
    Valeurs par défaut = estimation réaliste (voir README, section « Réalisme et sources »).
    isGlobal 1 : la valeur du serveur s'impose à tous ; 0 : réglage propre à chaque joueur (qualité, volume).
*/
comspec_fb_events = createHashMap;     // détonations en cours sur cette machine : id -> contexte
comspec_fb_state = createHashMap;      // effets en cours sur le joueur local
comspec_fb_seq = 0;                    // compteur d'identifiants (serveur)
comspec_fb_owners = createHashMap;     // serveur : id -> machines destinataires (annulation)
comspec_fb_lastSound = createHashMap;  // dernier fichier joué par effet (pas deux fois de suite le même)

// Messages réseau : événements CBA (publicVariable), pas remoteExec : rien à ajouter à la liste blanche CfgRemoteExec
// d'une mission, et un filtre BattlEye remoteexec.txt ne les bloque pas.
if (isServer) then {
    ["comspec_fb_serverDetonate", { _this call comspec_fb_fnc_serverDetonate }] call CBA_fnc_addEventHandler;
    ["comspec_fb_serverArea", { _this call comspec_fb_fnc_serverArea }] call CBA_fnc_addEventHandler;
};
["comspec_fb_timeline", { _this call comspec_fb_fnc_timeline }] call CBA_fnc_addEventHandler;
["comspec_fb_cancel", { _this call comspec_fb_fnc_cancel }] call CBA_fnc_addEventHandler;
["comspec_fb_aiRestore", { _this call comspec_fb_fnc_aiRestore }] call CBA_fnc_addEventHandler;

private _cat = "COMSPEC Flashbang";
private _add = {
    params ["_name", "_type", "_key", "_sub", "_values", ["_global", 1]];
    [
        _name, _type,
        [localize format ["STR_COMSPEC_FB_Set_%1", _key], localize format ["STR_COMSPEC_FB_Set_%1_Tip", _key]],
        [_cat, localize format ["STR_COMSPEC_FB_SetCat_%1", _sub]],
        _values, _global
    ] call CBA_fnc_addSetting;
};

// 1. Général
["comspec_fb_preset", "LIST", "Preset", "General", [[0, 1, 2], [
    localize "STR_COMSPEC_FB_Preset_Real", localize "STR_COMSPEC_FB_Preset_Arcade", localize "STR_COMSPEC_FB_Preset_Light"
], 0]] call _add;
["comspec_fb_intensity", "SLIDER", "Intensity", "General", [0, 2, 1, 2]] call _add;
["comspec_fb_radiusFull", "SLIDER", "RadiusFull", "General", [1, 15, 3, 1]] call _add;
["comspec_fb_radiusMax", "SLIDER", "RadiusMax", "General", [5, 60, 25, 0]] call _add;
["comspec_fb_maxStack", "SLIDER", "MaxStack", "General", [5, 30, 15, 0]] call _add;
["comspec_fb_soundRange", "SLIDER", "SoundRange", "General", [50, 500, 250, 0]] call _add;
["comspec_fb_indoorDetection", "CHECKBOX", "IndoorDetection", "General", true] call _add;
["comspec_fb_indoorMult", "SLIDER", "IndoorMult", "General", [1, 2, 1.4, 2]] call _add;
["comspec_fb_damage", "CHECKBOX", "Damage", "General", false] call _add;
// 2. Vision
["comspec_fb_flash", "CHECKBOX", "Flash", "Vision", true] call _add;
["comspec_fb_blur", "CHECKBOX", "Blur", "Vision", true] call _add;
["comspec_fb_nightMult", "SLIDER", "NightMult", "Vision", [1, 2, 1.3, 2]] call _add;
["comspec_fb_nvgMult", "SLIDER", "NvgMult", "Vision", [1, 4, 2, 1]] call _add;
// 3. Audition
["comspec_fb_tinnitus", "CHECKBOX", "Tinnitus", "Hearing", true] call _add;
["comspec_fb_tinnitusMax", "SLIDER", "TinnitusMax", "Hearing", [10, 58, 45, 0]] call _add;
["comspec_fb_muffle", "CHECKBOX", "Muffle", "Hearing", true] call _add;
["comspec_fb_radioMuffle", "CHECKBOX", "RadioMuffle", "Hearing", true] call _add;
["comspec_fb_earProtectionDb", "SLIDER", "EarProtection", "Hearing", [0, 40, 25, 0]] call _add;
// 4. IA et équipe
["comspec_fb_aiAffected", "CHECKBOX", "AiAffected", "Ai", true] call _add;
["comspec_fb_friendly", "CHECKBOX", "Friendly", "Ai", true] call _add;
// 5. Mèche et détonations
["comspec_fb_fuse", "SLIDER", "Fuse", "Timing", [0.5, 4, 1.5, 1]] call _add;
["comspec_fb_interval", "SLIDER", "Interval", "Timing", [0.2, 1.5, 0.5, 2]] call _add;
["comspec_fb_jitter", "SLIDER", "Jitter", "Timing", [0, 0.3, 0.12, 2]] call _add;
// 6. Entraînement
["comspec_fb_drillPops", "SLIDER", "DrillPops", "Drill", [1, 9, 1, 0]] call _add;
["comspec_fb_drillFlash", "CHECKBOX", "DrillFlash", "Drill", false] call _add;
// Son de la DRILL : valeurs = clés de la table CfgSons.hpp (drillChoix[]), libellés STR_COMSPEC_FB_DrillSound_<clé>.
private _drillKeys = getArray (configFile >> "COMSPEC_FB_Sons" >> "drillChoix");
["comspec_fb_drillSound", "LIST", "DrillSound", "Drill", [_drillKeys,
    _drillKeys apply { localize format ["STR_COMSPEC_FB_DrillSound_%1", _x] }, 0]] call _add;
["comspec_fb_drillVolume", "SLIDER", "DrillVolume", "Drill", [0.2, 1.5, 1, 0, true]] call _add;
// 7. Client (propre à chaque joueur)
["comspec_fb_quality", "LIST", "Quality", "Client", [[0, 1, 2], [
    localize "STR_COMSPEC_FB_Quality_Low", localize "STR_COMSPEC_FB_Quality_Med", localize "STR_COMSPEC_FB_Quality_High"
], 1], 0] call _add;
["comspec_fb_bangVolume", "SLIDER", "BangVolume", "Client", [0.5, 3, 2, 0, true], 0] call _add;
["comspec_fb_tinnitusVolume", "SLIDER", "TinnitusVolume", "Client", [0.5, 3, 1.5, 0, true], 0] call _add;
["comspec_fb_tinnitusSound", "LIST", "TinnitusSound", "Client", [[0, 1], [
    localize "STR_COMSPEC_FB_TinnitusSound_Rec", localize "STR_COMSPEC_FB_TinnitusSound_Synth"
], 0], 0] call _add;
["comspec_fb_debug", "CHECKBOX", "Debug", "Client", false] call _add;
