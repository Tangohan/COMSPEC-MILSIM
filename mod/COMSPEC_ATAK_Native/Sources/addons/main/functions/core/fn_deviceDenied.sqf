/* Message quand le téléphone ne peut pas s'allumer. Params : [raison "down" | "item" | "battery" | "broken"] */
params [["_why", "item"]];
switch (_why) do {
    // Mort ou inconscient : rangé sans message.
    case "down": {};
    case "battery": { hintSilent parseText "<t size='1.1' color='#f2ab33'>Batterie vide</t><br/>Rechargez à bord d'un véhicule ou changez la batterie (app Profil, ou action ACE)."; };
    case "broken": { hintSilent parseText format ["<t size='1.1' color='#e5483a'>Téléphone détruit</t><br/>%1<br/>Remplacez-le (nouvel appareil dans l'inventaire puis action ACE « Changer de téléphone ATAK ») ou réparez-le (kit de réparation ATAK ou caisse à outils, action ACE « Réparer le téléphone ATAK »).", (([] call comspec_atak_native_fnc_deviceHealth) get "reason")]; };
    default {
        private _catalog = [] call comspec_atak_native_fnc_deviceCatalog;
        private _names = (_catalog select [0, 4]) apply { private _n = getText (configFile >> "CfgWeapons" >> _x >> "displayName"); [_n, _x] select (_n isEqualTo "") };
        hintSilent parseText format ["<t size='1.1' color='#f2ab33'>Pas de terminal ATAK</t><br/>Il faut porter : %1", _names joinString ", "];
    };
};
false
