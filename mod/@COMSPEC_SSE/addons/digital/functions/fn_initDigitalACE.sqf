/*
    Cache exploitation numérique (insertChildren sous la racine SSE).
    Pas de re-install per-entité — la racine lit le cache à l’ouverture.
*/
if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_ensureDebugApi;

["comspec_sse_fnc_initDigitalACE", _this] call comspec_debug_fnc_enter;
private _t0 = diag_tickTime;

if !(["digital"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

if !(["COMSPEC_SSE_INIT_DIGITAL_ACE_DONE", "initDigitalACE"] call comspec_debug_fnc_guardOnce) exitWith {
    ["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
};

// Plus de sous-menu « Exploitation numérique » : les supports générés sont extractibles au câble DL-7
// du téléphone ATAK (compat_atak/fn_dl7Auto), résultats versés au dossier en fin de téléchargement.
// Fonction gardée pour les missions et addons qui l'appellent encore.
private _cache = missionNamespace getVariable ["comspec_sse_aceMenuCache", createHashMap];
if (_cache isEqualType createHashMap) then { _cache deleteAt "digitalRoot"; };
["comspec_sse_fnc_initDigitalACE"] call comspec_debug_fnc_exit;
