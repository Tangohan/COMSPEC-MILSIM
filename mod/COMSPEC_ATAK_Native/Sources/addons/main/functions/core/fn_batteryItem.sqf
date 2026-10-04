/*
    Batterie de rechange portée par le joueur. Retourne le nom de classe de la première trouvée, "" sinon.
    Acceptées : COMSPEC_ATAK_Battery (« Batterie ATAK »), les classes du réglage serveur « Batteries de rechange »
    (noms exacts) et tout objet d'un autre mod dont le nom de classe contient un mot du réglage
    « Batteries d'autres mods » (battery, batterie, pile par défaut, ex. ACE_UAVBattery).
    Params : [unité (player)]
*/
params [["_unit", player]];
private _exact = ((missionNamespace getVariable ["comspec_atak_native_battery_items", "COMSPEC_ATAK_Battery,ACE_UAVBattery"]) splitString ", ;") apply { toLower _x };
_exact pushBackUnique "comspec_atak_battery";
private _pats = ((missionNamespace getVariable ["comspec_atak_native_battery_patterns", "battery,batterie,pile"]) splitString ", ;") apply { toLower _x };
// Le téléphone lui-même n'est jamais une batterie.
private _phones = [] call comspec_atak_native_fnc_deviceCatalog;
private _items = items _unit;
private _hit = _items findIf { (toLower _x) in _exact };
if (_hit < 0) then {
    _hit = _items findIf { private _c = toLower _x; !(_c in _phones) && {(_pats findIf { _x isNotEqualTo "" && {(_c find _x) >= 0} }) >= 0} };
};
if (_hit < 0) exitWith { "" };
_items select _hit
