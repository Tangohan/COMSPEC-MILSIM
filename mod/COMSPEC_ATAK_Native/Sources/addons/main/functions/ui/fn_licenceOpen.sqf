/*
    Ouvre la note de service (licence COMSPEC d'Overwatch connect : fn_showBetaAccessNote, texte de fn_ndaTexts) depuis
    n'importe où en jeu : page du téléphone, Configuration ATHENA (menu Échap), app du SSE. Point d'entrée commun de tous
    les écrans qui disent « licence pas encore acceptée ».
    Rien ne part vers Athena ici : la note est le texte local du mod ; l'acceptation (« J'accepte », après lecture jusqu'à
    la fin, fn_ndaWatch) est enregistrée sur le profil par Overwatch connect (fn_ndaAccept). Le texte ne change pas.
    Téléphone en main : rangé le temps de la note (la souris va à la note), puis rouvert sur la page d'où l'on venait.
    À la fermeture de la note (acceptée ou non), _then est appelé avec [acceptée] : l'appelant relance ce qui attendait
    la licence (exemple : la liste de Mes couvertures sur Athena). Aucune alerte de l'interface d'Arma.
    Params : [_then (Code, reçoit [acceptée]), _page (page du téléphone à rouvrir ; "" : la page active)] -> Boolean
             (true : la note est demandée)
             ["ok"]  -> true si la licence courante est acceptée (ou Overwatch connect absent : aucune licence à demander)
             ["can"] -> true si la note peut s'ouvrir d'ici (Overwatch connect chargé, machine avec écran)
*/
private _op = _this param [0, ""];
if (_op isEqualTo "ok") exitWith {
    isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]}
};
if (_op isEqualTo "can") exitWith { hasInterface && {!isNil "comspec_overwatch_connect_fnc_showBetaAccessNote"} };
params [["_then", {}, [{}]], ["_page", "", [""]]];
if (!hasInterface || {isNil "comspec_overwatch_connect_fnc_showBetaAccessNote"}) exitWith { false };
// Une note à la fois : déjà ouverte, rien de plus.
if (!isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull])) exitWith { true };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _hand = !isNull ([] call comspec_atak_native_fnc_display) && {_s getOrDefault ["interactive", false]};
if (_page isEqualTo "") then { _page = _s getOrDefault ["activePage", ""]; };
if (_hand) then { [] call comspec_atak_native_fnc_close; };
// Temps réel (uiSleep) : la note s'ouvre et se referme aussi jeu en pause (menu Échap en solo).
[_then, _page, _hand] spawn {
    params ["_then", "_page", "_hand"];
    uiSleep 0.1;
    isNil { [true] call comspec_overwatch_connect_fnc_showBetaAccessNote; };
    private _t = diag_tickTime;
    waitUntil { uiSleep 0.1; !isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull]) || {(diag_tickTime - _t) > 3} };
    waitUntil { uiSleep 0.2; isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull]) };
    uiSleep 0.1;
    isNil {
        private _ok = ["ok"] call comspec_atak_native_fnc_licenceOpen;
        ["INFO", "UI", format ["Note de service fermée (licence %1)", ["pas acceptée", "acceptée"] select _ok]] call comspec_atak_native_fnc_log;
        // Retour à la page d'où l'on venait : seulement si le joueur n'a rien ouvert entre-temps.
        if (_hand && {_page isNotEqualTo ""} && {alive player} && {isNull ([] call comspec_atak_native_fnc_display)}) then {
            [_page] call comspec_atak_native_fnc_openApp;
        };
        [_ok] call _then;
    };
};
true
