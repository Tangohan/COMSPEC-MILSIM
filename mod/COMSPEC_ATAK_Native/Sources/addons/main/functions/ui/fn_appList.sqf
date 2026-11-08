/*
    Applications déclarées dans configFile >> "COMSPEC_ATAK_Apps", triées par "order".
    Retourne un tableau de HashMaps : id, name, desc (une ligne pour l'App Store), page, section, icon, dock, module (app d'un autre addon),
    wip (app en chantier : affichée grisée, ne s'ouvre pas), new (app récente : pastille NOUVEAU au lanceur tant
    que le joueur ne l'a pas ouverte), system (fait partie du téléphone : ne se désinstalle pas de l'App Store).
*/
// Cache lié à la valeur du réglage : gardé dans uiNamespace d'une mission à l'autre, il ne resservait jamais la liste d'un
// autre serveur, et une app retirée en cours de partie disparaît (avant : retirée seulement au redémarrage du jeu).
private _offRaw = missionNamespace getVariable ["comspec_atak_native_apps_off", ""];
if !(_offRaw isEqualType "") then { _offRaw = ""; };
private _cached = uiNamespace getVariable ["COMSPEC_ATAK_AppsCache", []];
if ((count (_cached param [1, []])) > 0 && {(_cached select 0) isEqualTo _offRaw}) exitWith { _cached select 1 };

// Apps retirées par le serveur (réglage CBA « Apps retirées » : noms de classe séparés par des virgules).
// Réglages et App Store restent toujours : sans eux, plus aucun moyen de régler ou de réinstaller.
private _off = ((_offRaw splitString ", ;") apply { toLower _x }) - ["settings", "store"];
private _sorted = (("true" configClasses (configFile >> "COMSPEC_ATAK_Apps")) select { !((toLower configName _x) in _off) }) apply { [getNumber (_x >> "order"), configName _x] };
_sorted sort true;

private _apps = _sorted apply {
    private _cfg = configFile >> "COMSPEC_ATAK_Apps" >> (_x select 1);
    createHashMapFromArray [
        ["id", _x select 1],
        ["name", getText (_cfg >> "name")],
        // Abréviation du titre (4.1.1), quand le nom ne tient pas dans la barre d'app à 14 px (fn_titleFit).
        ["short", getText (_cfg >> "short")],
        ["desc", getText (_cfg >> "desc")],
        ["page", toUpper getText (_cfg >> "page")],
        ["section", getText (_cfg >> "section")],
        ["icon", getText (_cfg >> "icon")],
        ["dock", (getNumber (_cfg >> "dock")) > 0],
        ["module", (getText (_cfg >> "function")) isNotEqualTo ""],
        ["wip", (getNumber (_cfg >> "wip")) > 0],
        ["new", (getNumber (_cfg >> "new")) > 0],
        ["system", (getNumber (_cfg >> "system")) > 0]
    ]
};
uiNamespace setVariable ["COMSPEC_ATAK_AppsCache", [_offRaw, _apps]];
_apps
