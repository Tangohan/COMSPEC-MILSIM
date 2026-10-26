params [["_page", "LAUNCHER"], ["_push", true]];
_page = toUpper _page;
// Sous-pages sans icône au lanceur : PATCHES (Réglages > Patch au dos), VIEW3D (carte > Vue 3D ici).
private _allowed = ["LAUNCHER", "RECENTS", "NOTIFS", "PATCHES", "VIEW3D"] +(([] call comspec_atak_native_fnc_appList) apply { _x get "page" });
if !(_page in _allowed) then { _page = "LAUNCHER"; };
// App en chantier (config wip=1, ex. Brèche) : affichée grisée au lanceur, ne s'ouvre d'aucune façon (tuile, dock, recherche).
if ((([] call comspec_atak_native_fnc_appList) findIf { (_x get "page") isEqualTo _page && {_x getOrDefault ["wip", false]} }) >= 0) exitWith {
    ["Application en chantier (W.I.P), bientôt disponible", 2] call comspec_atak_native_fnc_toast;
    false
};
// App Store (fn_appInstall) : une app interdite par l'encadrement ne s'ouvre d'aucune façon ; une app désinstallée
// (raccourci, notification) ouvre sa fiche dans l'App Store pour la réinstaller.
private _appOf = (([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page }) param [0, createHashMap];
if ((count _appOf) > 0 && {!((_appOf get "id") in ["Admin", "Toc"])}) then {
    private _inst = ["state", _appOf get "id"] call comspec_atak_native_fnc_appInstall;
    if (_inst isEqualTo "forbidden") exitWith {
        ["Cette app est interdite par l'encadrement", 2.5] call comspec_atak_native_fnc_toast;
        _page = "";
    };
    if (_inst isEqualTo "removed") then {
        [(_appOf get "name") + " n'est pas installée : App Store", 2.5] call comspec_atak_native_fnc_toast;
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _st set ["storeQuery", _appOf get "name"];
        _st set ["storeTab", "ALL"];
        _page = "STORE";
    };
};
if (_page isEqualTo "") exitWith { false };

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Touche accueil (coque ou barre d'app) sur l'écran de verrouillage : déverrouille.
if (_page isEqualTo "LAUNCHER" && {uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]}) then { ["unlock"] call comspec_atak_native_fnc_lockScreen; };
// Téléphone verrouillé : aucune app ne s'ouvre par-dessus l'écran de verrouillage (dock, raccourci, notification).
// Un redessin sans empilement (mode, orientation) passe : fn_pageRender remet l'écran de verrouillage devant.
if (_push && {uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]}) exitWith {
    ["Déverrouillez d'abord le téléphone", 2] call comspec_atak_native_fnc_toast;
    false
};
private _prevPage = _s getOrDefault ["activePage", ""];
private _history = _s getOrDefault ["history", []];
private _last = if ((count _history) > 0) then { _history select ((count _history) - 1) } else { "" };
if ((_push || {(count _history) isEqualTo 0}) && {_last isNotEqualTo _page}) then {
    _history pushBack _page;
    while {(count _history) > 20} do { _history deleteAt 0; };
};
_s set ["history", _history];
// Retour arrière ou redessin sans empilement : fn_pageRender retrouve le défilement de la page.
_s set ["scrollRestore", !_push];
_s set ["activePage", _page];
// Lanceur : apps récentes (profil, gardées d'une partie à l'autre) et apps déjà ouvertes (fin de la pastille NOUVEAU).
if (_push && {!(_page in ["LAUNCHER", "RECENTS", "NOTIFS", "PATCHES", "VIEW3D"])}) then {
    private _rec = profileNamespace getVariable ["COMSPEC_ATAK_AppRecent", []];
    if !(_rec isEqualType []) then { _rec = []; };
    _rec = [_page] + (_rec - [_page]);
    profileNamespace setVariable ["COMSPEC_ATAK_AppRecent", _rec select [0, 12]];
    private _seen = profileNamespace getVariable ["COMSPEC_ATAK_AppSeen", []];
    if (_seen isEqualType [] && {!(_page in _seen)}) then { _seen pushBack _page; profileNamespace setVariable ["COMSPEC_ATAK_AppSeen", _seen]; };
};

[_page] call comspec_atak_native_fnc_pageRender;
// Mouvement (fn_fx) : l'app s'ouvre en grandissant depuis le point touché ; l'accueil revient en fondu.
// Retour arrière : glissé joué par fn_back. Redessin de la même page : rien.
if (_push && {_page isNotEqualTo _prevPage}) then { [["open", "home"] select (_page isEqualTo "LAUNCHER")] call comspec_atak_native_fnc_fx; };
["INFO", "UI", format ["Navigate %1", _page]] call comspec_atak_native_fnc_log;
true
