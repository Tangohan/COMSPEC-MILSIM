// Réglages CBA (SSE ROLEPLAY › Extensions) : partout, serveur compris.
[] call comspec_sse_fnc_docExtInitSettings;

// Configuration ATHENA (menu Échap) : contrat du registre dans l'en-tête de fn_athenaConfig.
// Traduction : avec l'ATAK natif, son dictionnaire (comspec_sse_fnc_tr) ; sans lui, la stringtable de cet addon.
missionNamespace setVariable ["comspec_sse_athenaCfgKeys", createHashMapFromArray [
    ["ATHENA Configuration", "STR_COMSPEC_SSE_ATHCFG_Button"],
    ["Configuration ATHENA", "STR_COMSPEC_SSE_ATHCFG_Title"],
    ["Ouvrir la configuration ATHENA", "STR_COMSPEC_SSE_ATHCFG_Open"],
    ["Fermer", "STR_COMSPEC_SSE_ATHCFG_Close"],
    ["Fermer et revenir au menu Échap (touche Échap)", "STR_COMSPEC_SSE_ATHCFG_CloseTip"],
    ["ATAK natif %1", "STR_COMSPEC_SSE_ATHCFG_VerAtak"],
    ["SSE %1", "STR_COMSPEC_SSE_ATHCFG_VerSse"],
    ["SSE · Roleplay et extensions", "STR_COMSPEC_SSE_ATHCFG_SseTitle"],
    ["Extensions de documents du SSE : les voir, puis activer ou couper chacune (Zeus, administrateur).", "STR_COMSPEC_SSE_ATHCFG_SseDesc"]
]];
missionNamespace setVariable ["comspec_sse_athenaCfgTr", {
    if (!isNil "comspec_atak_native_fnc_tr") exitWith { _this call comspec_sse_fnc_tr };
    private _k = (missionNamespace getVariable ["comspec_sse_athenaCfgKeys", createHashMap]) getOrDefault [_this, ""];
    if (_k isEqualTo "" || {!isLocalized _k}) then { _this } else { localize _k }
}];
// Hôte du bouton et de la fenêtre : seulement sans l'ATAK natif (avec lui, c'est l'ATAK natif, quel que soit l'ordre des preInit).
if (!isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main")) then {
    missionNamespace setVariable ["comspec_athena_config_host", createHashMapFromArray [
        ["fn", comspec_sse_fnc_athenaConfig],
        ["tr", { _this call (missionNamespace getVariable ["comspec_sse_athenaCfgTr", {_this}]) }],
        ["logo", "\z\comspec_sse\addons\editor\data\logo_athena_ca.paa"],
        ["icon", "\z\comspec_sse\addons\extensions\data\athena_config_ca.paa"],
        ["iconHover", "\z\comspec_sse\addons\extensions\data\athena_config_hover_ca.paa"],
        ["dot", "\z\comspec_sse\addons\extensions\data\athena_dot_ca.paa"],
        ["row", "COMSPEC_SSE_AthenaCfgRow"]
    ]];
};
private _athCfg = (missionNamespace getVariable ["comspec_athena_config_entries", []]) select { (_x select 0) isNotEqualTo "sse_extensions" };
_athCfg pushBack ["sse_extensions",
    { "SSE · Roleplay et extensions" call (missionNamespace getVariable ["comspec_sse_athenaCfgTr", {_this}]) },
    { "Extensions de documents du SSE : les voir, puis activer ou couper chacune (Zeus, administrateur)." call (missionNamespace getVariable ["comspec_sse_athenaCfgTr", {_this}]) },
    ["\z\comspec_atak_native\addons\main\data\app_sse.paa", "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"],
    { hasInterface },
    { [] call comspec_sse_fnc_docExtOpen; },
    30, "menu"];
missionNamespace setVariable ["comspec_athena_config_entries", _athCfg];
