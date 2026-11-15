/*
    Mods chargés (getLoadedModsInfo), pour les mods détectés du back-office Athena et les kits par rôle.
    Arma 3 de base exclu ; DLC officiels marqués.
    Retour: [HashMap {name, dir, workshop, dlc}...] (120 au plus)
*/
// Les mods chargés ne changent pas tant que le jeu tourne : liste calculée une fois par lancement (uiNamespace), avec
// sa clé de comparaison (fn_buildOperatorProfile). Avant : getLoadedModsInfo relu et 120 tables refaites à chaque fiche.
private _cached = uiNamespace getVariable "COMSPEC_OpLoadedMods";
if (!isNil "_cached" && {_cached isEqualType []}) exitWith { _cached };
private _out = [];
{
    _x params [["_name", ""], ["_dir", ""], ["_isDefault", false], ["_isOfficial", false], "", "", "", ["_itemId", ""]];
    if ((_isDefault && {!_isOfficial}) || {_dir isEqualTo "A3"}) then { continue };
    _out pushBack (createHashMapFromArray [["name", _name select [0, 80]], ["dir", _dir select [0, 80]], ["workshop", _itemId], ["dlc", _isOfficial]]);
} forEach getLoadedModsInfo;
_out = _out select [0, 120];
uiNamespace setVariable ["COMSPEC_OpLoadedMods", _out];
uiNamespace setVariable ["COMSPEC_OpLoadedModsKey", str (_out apply { _x get "dir" })];
_out
