/*
    Applications déclarées dans configFile >> "COMSPEC_ATAK_Apps", triées par "order".
    Retourne un tableau de HashMaps : id, name, page, section, icon, dock, module (app d'un autre addon).
*/
private _cached = uiNamespace getVariable ["COMSPEC_ATAK_AppsCache", []];
if ((count _cached) > 0) exitWith { _cached };

// Apps retirées par le serveur (réglage CBA « Apps désactivées » : noms de classe séparés par des virgules).
private _off = ((missionNamespace getVariable ["comspec_atak_native_apps_off", ""]) splitString ", ;") apply { toLower _x };
private _sorted = (("true" configClasses (configFile >> "COMSPEC_ATAK_Apps")) select { !((toLower configName _x) in _off) }) apply { [getNumber (_x >> "order"), configName _x] };
_sorted sort true;

private _apps = _sorted apply {
    private _cfg = configFile >> "COMSPEC_ATAK_Apps" >> (_x select 1);
    createHashMapFromArray [
        ["id", _x select 1],
        ["name", getText (_cfg >> "name")],
        ["page", toUpper getText (_cfg >> "page")],
        ["section", getText (_cfg >> "section")],
        ["icon", getText (_cfg >> "icon")],
        ["dock", (getNumber (_cfg >> "dock")) > 0],
        ["module", (getText (_cfg >> "function")) isNotEqualTo ""]
    ]
};
uiNamespace setVariable ["COMSPEC_ATAK_AppsCache", _apps];
_apps
