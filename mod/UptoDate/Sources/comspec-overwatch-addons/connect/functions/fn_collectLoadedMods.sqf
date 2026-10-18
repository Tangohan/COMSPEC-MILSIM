/*
    Mods chargés (getLoadedModsInfo), pour les mods détectés du back-office Athena et les kits par rôle.
    Arma 3 de base exclu ; DLC officiels marqués.
    Retour: [HashMap {name, dir, workshop, dlc}...] (120 au plus)
*/
private _out = [];
{
    _x params [["_name", ""], ["_dir", ""], ["_isDefault", false], ["_isOfficial", false], "", "", "", ["_itemId", ""]];
    if ((_isDefault && {!_isOfficial}) || {_dir isEqualTo "A3"}) then { continue };
    _out pushBack (createHashMapFromArray [["name", _name select [0, 80]], ["dir", _dir select [0, 80]], ["workshop", _itemId], ["dlc", _isOfficial]]);
} forEach getLoadedModsInfo;
_out select [0, 120]
