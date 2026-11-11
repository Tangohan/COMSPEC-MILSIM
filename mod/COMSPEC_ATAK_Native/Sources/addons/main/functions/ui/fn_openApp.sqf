/*
    Lien profond (4.2.x) : ouvre le téléphone en main directement sur une app, un écran et un contexte pré-rempli.
    Point d'entrée commun de tout ce qui ouvre le téléphone depuis le jeu (actions ACE, fenêtres du SSE, SEEK II, Zeus…).
      [_page, _params, _prep, _after] call comspec_atak_native_fnc_openApp -> Boolean
        _page    page de l'app (IDCHECK, FICHIERS, SALUTE, PHOTOS…)
        _params  HashMap du contexte (lu par l'app avec « take », ou par _prep)
        _prep    code appelé avec _params juste avant le dessin (pré-remplit l'état de l'app)
        _after   code appelé avec _params une fois l'app dessinée (calque, lancement d'une recherche…)
      ["take", _page] call comspec_atak_native_fnc_openApp -> HashMap du contexte en attente pour cette page (vidé),
        HashMap vide sinon. Un contexte vieux de plus de 120 s est oublié.
    Téléphone rangé ou porté : sorti en main, puis l'app est ouverte à l'image suivante (après le dessin d'arrivée de
    fn_displayLoad, qui ne la remplace donc plus). Écran de verrouillage affiché : l'app s'ouvre au déverrouillage
    (COMSPEC_ATAK_LockAfter de fn_lockScreen). La page est empilée dans l'historique : la touche retour ramène à l'écran
    d'avant. Sans téléphone sur soi : false, rien ne s'ouvre (l'appelant le dit dans sa propre fenêtre).
*/
if ((_this param [0, ""]) isEqualTo "take") exitWith {
    private _want = toUpper (_this param [1, ""]);
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    (_st getOrDefault ["deepLink", []]) params [["_lp", ""], ["_lv", createHashMap], ["_lt", -1]];
    if (_lp isEqualTo "" || {_lp isNotEqualTo _want} || {(diag_tickTime - _lt) > 120}) exitWith { createHashMap };
    _st deleteAt "deepLink";
    _lv
};
params [["_page", "", [""]], ["_params", createHashMap, [createHashMap]], ["_prep", {}, [{}]], ["_after", {}, [{}]]];
if (!hasInterface) exitWith { false };
if (isNil { uiNamespace getVariable "COMSPEC_ATAK_State" }) then { [] call comspec_atak_native_fnc_stateInit; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_page = toUpper _page;
if (_page isEqualTo "") exitWith { false };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {
    ["INFO", "UI", format ["Lien profond %1 : pas de téléphone sur soi", _page]] call comspec_atak_native_fnc_log;
    false
};
_s set ["deepLink", [_page, _params, diag_tickTime]];
_params call _prep;
private _go = {
    params ["_page", "_params", "_after"];
    if (uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) exitWith {
        uiNamespace setVariable ["COMSPEC_ATAK_LockAfter", _page];
        // Code d'après ouverture : joué quand la page est enfin dessinée (déverrouillage), 60 s au plus.
        [{ ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo (_this select 0) && {!(uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false])} },
            { (_this select 1) call (_this select 2); }, [_page, _params, _after], 60] call CBA_fnc_waitUntilAndExecute;
        ["INFO", "UI", format ["Lien profond %1 : ouvert au déverrouillage", _page]] call comspec_atak_native_fnc_log;
    };
    [_page] call comspec_atak_native_fnc_navigate;
    _params call _after;
    ["INFO", "UI", format ["Lien profond %1", _page]] call comspec_atak_native_fnc_log;
};
private _shown = !isNull ([] call comspec_atak_native_fnc_display);
if (_shown && {_s getOrDefault ["interactive", false]}) exitWith { [_page, _params, _after] call _go; true };
missionNamespace setVariable ["COMSPEC_ATAK_DeepGo", _go];
[true] call comspec_atak_native_fnc_open;
// Deux images : fn_displayLoad dessine d'abord la dernière page puis pose l'écran de verrouillage.
[{ [{ _this call (missionNamespace getVariable ["COMSPEC_ATAK_DeepGo", {}]); }, _this] call comspec_atak_native_fnc_nextFrame; }, [_page, _params, _after]] call comspec_atak_native_fnc_nextFrame;
true
