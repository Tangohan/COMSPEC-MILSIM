/*
    Message quand le téléphone ne peut pas s'allumer. Params : [raison "down" | "item" | "battery" | "broken" | "zeus" | "heat"]
    Rien sur l'écran du jeu : notification gardée dans le centre du téléphone (vue à la prochaine ouverture).
*/
params [["_why", "item"]];
switch (_why) do {
    // Mort ou inconscient : rangé sans message.
    case "down": {};
    case "zeus": { ["ERROR", "Terminal désactivé à distance : le commandement a coupé votre ATAK", 8, 90] call comspec_atak_native_fnc_notify; };
    case "heat": { ["WARNING", "Surchauffe critique : arrêt de sécurité", 6, 50] call comspec_atak_native_fnc_notify; };
    case "battery": { ["WARNING", "Batterie vide · Rechargez à bord d'un véhicule ou changez la batterie (app Profil, ou action ACE).", 6, 70] call comspec_atak_native_fnc_notify; };
    case "broken": { ["ERROR", format ["Téléphone détruit · %1 · Remplacez-le (nouvel appareil dans l'inventaire puis action ACE « Changer de téléphone ATAK ») ou réparez-le (kit de réparation ATAK ou caisse à outils, action ACE « Réparer le téléphone ATAK »).", (([] call comspec_atak_native_fnc_deviceHealth) get "reason")], 8, 70] call comspec_atak_native_fnc_notify; };
    default {
        private _catalog = [] call comspec_atak_native_fnc_deviceCatalog;
        private _names = (_catalog select [0, 4]) apply { private _n = getText (configFile >> "CfgWeapons" >> _x >> "displayName"); [_n, _x] select (_n isEqualTo "") };
        ["WARNING", format ["Pas de terminal ATAK · Il faut porter : %1", _names joinString ", "], 6, 50] call comspec_atak_native_fnc_notify;
    };
};
false
