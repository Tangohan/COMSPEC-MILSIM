/*
    Touche retour (barre d'app ou coque).
    1. Sous-écran d'une app (fiche ouverte, vue drone, réglages du briefing...) : la page l'a déclaré pendant son dessin
       dans l'état « backSub » = [page, code] ; le retour referme ce sous-écran et reste dans l'app.
    2. Sinon : page précédente de l'historique, ou l'accueil quand l'app a été ouverte directement (dernière app à
       l'ouverture du téléphone, notification, raccourci) : avant, le retour ne faisait rien dans ce cas.
    Téléphone verrouillé : rien (le déverrouillage se fait sur l'écran de verrouillage).
    _defer (4.2.1) : appui du joueur (flèche de la barre d'app, touche de la coque) : la page précédente est dessinée à
    l'image suivante, le glissé démarre tout de suite (fn_navigate).
*/
params [["_defer", false, [false]]];
if (uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _prev = _s getOrDefault ["activePage", ""];
(_s getOrDefault ["backSub", []]) params [["_subPage", ""], ["_subCode", {}]];
if (_subPage isNotEqualTo "" && {_subPage isEqualTo _prev}) exitWith {
    _s deleteAt "backSub";
    call _subCode;
    ["back"] call comspec_atak_native_fnc_fx;
    true
};
private _history = _s getOrDefault ["history", []];
if ((count _history) > 1) then { _history deleteAt ((count _history) - 1); } else { _s set ["history", ["LAUNCHER"]]; _history = ["LAUNCHER"]; };
private _page = if ((count _history) > 0) then { _history select ((count _history) - 1) } else { "LAUNCHER" };
if (_defer) exitWith { [_page, false, true, "back"] call comspec_atak_native_fnc_navigate };
private _r = [_page, false] call comspec_atak_native_fnc_navigate;
// La page quittée glisse vers la droite (fn_fx).
if (_page isNotEqualTo _prev) then { ["back"] call comspec_atak_native_fnc_fx; };
_r
