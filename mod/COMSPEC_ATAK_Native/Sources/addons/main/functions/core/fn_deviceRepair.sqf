/*
    Remise en état du téléphone natif. Params : ["repair" (kit de réparation ATAK ou caisse à outils, fn_repairStart) | "swap" (nouvel appareil porté) | "reboot"]
    Chaque cas passe par un redémarrage : écran de démarrage COMSPEC ATAK (fn_deviceOverlay) ; « reboot » joue d'abord l'arrêt (fn_powerFx).
*/
params [["_how", "repair"]];
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
switch (_how) do {
    case "reboot": { _n set ["offUntil", time + 8]; _n set ["offFrom", time]; _n set ["offReason", "Redémarrage manuel"]; };
    default {
        _n set ["damage", 0];
        _n set ["offUntil", time + ([6, 8] select (_how isEqualTo "swap"))]; _n set ["offFrom", time];
        _n set ["offReason", ["Écran remplacé", "Nouvel appareil"] select (_how isEqualTo "swap")];
        _n deleteAt "brokenReason";
        // Nouvel appareil : nouvel IMEI et nouvelle adresse MAC (le numéro suit la carte SIM).
        if (_how isEqualTo "swap") then { player setVariable ["COMSPEC_ATAK_PhoneGen", (player getVariable ["COMSPEC_ATAK_PhoneGen", 0]) + 1, true]; };
    };
};
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
// Réalisme ATAK d'Overwatch : son état (COMSPEC_AtakState, local au joueur) prime dans fn_deviceHealth.
// Sa propre réparation refuse un appareil détruit (« irréparable ») : la réparation native le remet aussi en état.
if (_how in ["repair", "swap"]) then {
    private _ow = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
    if (_ow isEqualType createHashMap && {(count _ow) > 0}) then {
        _ow set ["screen_destroyed", false];
        _ow set ["device_destroyed", false];
        _ow set ["device_crashed", false];
        _ow set ["crash_until", -1];
        _ow set ["powered_on", true];
        missionNamespace setVariable ["COMSPEC_AtakState", _ow, false];
        if (!isNil "comspec_overwatch_connect_fnc_logAtakStateChange") then { [true] call comspec_overwatch_connect_fnc_logAtakStateChange; };
    };
};
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
uiNamespace setVariable ["COMSPEC_ATAK_DevLook", createHashMap];
["SUCCESS", switch (_how) do { case "swap": { "Nouveau téléphone en service" }; case "reboot": { "Redémarrage du téléphone…" }; default { "Téléphone réparé" }; }, 4, 30] call comspec_atak_native_fnc_notify;
if (_how isEqualTo "reboot") exitWith {
    // Image suivante (l'appel vient souvent d'un bouton de la page, qu'on ne supprime pas pendant son clic) :
    // fenêtres ouvertes par l'app (menus, éditeurs) refermées en redessinant la page, puis animation d'arrêt
    // et démarrage, que rien ne recouvre plus.
    [{
        if (!isNull ([] call comspec_atak_native_fnc_display)) then {
            [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"]] call comspec_atak_native_fnc_pageRender;
        };
        ["shutdown"] call comspec_atak_native_fnc_powerFx;
        [] call comspec_atak_native_fnc_deviceOverlay;
    }] call CBA_fnc_execNextFrame;
    true
};
[] call comspec_atak_native_fnc_deviceOverlay;
true
