/*
    Message quand le téléphone ne peut pas s'allumer. Params : [raison "down" | "item" | "battery" | "broken" | "zeus" | "heat"]
    Rien sur l'écran du jeu : notification gardée dans le centre du téléphone (vue à la prochaine ouverture).
*/
params [["_why", "item"]];
if (_why isEqualTo "down") exitWith { false };
// Téléphone fermé par définition : retour sonore immédiat (local, rien à l'écran), sauf en mode discrétion.
if !((profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]) || {["mute", false] call comspec_atak_native_fnc_zeusFx}) then { playSound "addItemFailed"; };
// Touche pressée plusieurs fois : une seule notification par raison toutes les 30 s (compteur de non lus).
(uiNamespace getVariable ["COMSPEC_ATAK_DeniedLast", ["", -1e9]]) params ["_w0", "_t0"];
if (_w0 isEqualTo _why && {(diag_tickTime - _t0) < 30}) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_DeniedLast", [_why, diag_tickTime]];
switch (_why) do {
    // Mort ou inconscient : rangé sans message.
    case "down": {};
    case "zeus": { ["ERROR", "Terminal désactivé à distance : le commandement a coupé votre ATAK", 8, 90] call comspec_atak_native_fnc_notify; };
    case "heat": { ["WARNING", "Surchauffe critique : arrêt de sécurité", 6, 50] call comspec_atak_native_fnc_notify; };
    case "battery": { ["WARNING", "Batterie vide · Rechargez dans un véhicule moteur allumé, avec une batterie externe (ACE : Téléphone ATAK > Accessoires) ou sur un socle CS-10, ou changez la batterie (ACE : Téléphone ATAK > Entretien).", 8, 70] call comspec_atak_native_fnc_notify; };
    case "broken": { ["ERROR", format ["Téléphone détruit · %1 · Remplacez-le (nouvel appareil dans l'inventaire puis action ACE « Changer de téléphone ATAK ») ou réparez-le (kit de réparation ATAK ou caisse à outils, action ACE « Réparer le téléphone ATAK »).", (([] call comspec_atak_native_fnc_deviceHealth) get "reason")], 8, 70] call comspec_atak_native_fnc_notify; };
    default {
        private _catalog = [] call comspec_atak_native_fnc_deviceCatalog;
        private _names = (_catalog select [0, 4]) apply { private _n = getText (configFile >> "CfgWeapons" >> _x >> "displayName"); [_n, _x] select (_n isEqualTo "") };
        ["WARNING", format ["Pas de terminal ATAK · Il faut porter : %1", _names joinString ", "], 6, 50] call comspec_atak_native_fnc_notify;
    };
};
false
