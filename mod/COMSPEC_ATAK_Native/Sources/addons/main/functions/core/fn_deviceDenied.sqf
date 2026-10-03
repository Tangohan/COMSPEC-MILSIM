/* Message quand le téléphone ne peut pas s'allumer. Params : [raison "item" | "battery" | "broken"] */
params [["_why", "item"]];
switch (_why) do {
    case "battery": { hintSilent parseText "<t size='1.1' color='#f2ab33'>Batterie vide</t><br/>Rechargez à bord d'un véhicule ou changez la batterie (app Profil, ou action ACE)."; };
    case "broken": { hintSilent parseText "<t size='1.1' color='#e5483a'>Téléphone détruit</t><br/>Il doit être remplacé (Zeus ou nouvel appareil)."; };
    default {
        private _catalog = [] call comspec_atak_native_fnc_deviceCatalog;
        private _names = (_catalog select [0, 4]) apply { private _n = getText (configFile >> "CfgWeapons" >> _x >> "displayName"); [_n, _x] select (_n isEqualTo "") };
        hintSilent parseText format ["<t size='1.1' color='#f2ab33'>Pas de terminal ATAK</t><br/>Il faut porter : %1", _names joinString ", "];
    };
};
false
