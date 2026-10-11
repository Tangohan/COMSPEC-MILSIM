params [["_page", "LAUNCHER"], ["_push", true]];
_page = toUpper _page;
private _allowed = ["LAUNCHER", "RECENTS", "NOTIFS"] + (([] call comspec_atak_native_fnc_appList) apply { _x get "page" });
if !(_page in _allowed) then { _page = "LAUNCHER"; };

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Touche accueil (coque ou barre d'app) sur l'écran de verrouillage : déverrouille.
if (_page isEqualTo "LAUNCHER" && {uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]}) then { ["unlock"] call comspec_atak_native_fnc_lockScreen; };
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

[_page] call comspec_atak_native_fnc_pageRender;
// Mouvement (fn_fx) : l'app s'ouvre en grandissant depuis le point touché ; l'accueil revient en fondu.
// Retour arrière : glissé joué par fn_back. Redessin de la même page : rien.
if (_push && {_page isNotEqualTo _prevPage}) then { [["open", "home"] select (_page isEqualTo "LAUNCHER")] call comspec_atak_native_fnc_fx; };
["INFO", "UI", format ["Navigate %1", _page]] call comspec_atak_native_fnc_log;
true
