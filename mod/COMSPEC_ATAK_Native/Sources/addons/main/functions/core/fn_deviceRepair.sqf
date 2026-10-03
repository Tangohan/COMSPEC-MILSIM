/*
    Remise en état du téléphone natif. Params : ["repair" (trousse à outils : écran remplacé) | "swap" (nouvel appareil porté) | "reboot"]
*/
params [["_how", "repair"]];
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
switch (_how) do {
    case "reboot": { _n set ["offUntil", time + 8]; _n set ["offReason", "Redémarrage manuel"]; };
    default { _n set ["damage", 0]; _n set ["offUntil", -1]; };
};
missionNamespace setVariable ["COMSPEC_ATAK_Device", _n];
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
["SUCCESS", switch (_how) do { case "swap": { "Nouveau téléphone en service" }; case "reboot": { "Redémarrage du téléphone…" }; default { "Téléphone réparé" }; }, 4, 30] call comspec_atak_native_fnc_notify;
[] call comspec_atak_native_fnc_deviceOverlay;
true
