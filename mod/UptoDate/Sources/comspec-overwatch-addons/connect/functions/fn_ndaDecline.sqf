/*
    Second bouton de la note de service.
    Licence déjà acceptée (relecture) ou en mission : « Fermer », simple fermeture.
    Licence pas encore acceptée, au menu principal : « Quitter le jeu » -> message de confirmation, puis sortie par le
    bouton Quitter du menu Arma (idc 106) ; « Revenir à la note » la rouvre. Si la sortie n'a pas lieu, la note
    réapparaît au prochain retour au menu principal (rien n'est enregistré).
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
if (!isNull _display) then {
    _display closeDisplay 2;
} else {
    closeDialog 0;
};

if ((([] call comspec_overwatch_connect_fnc_licenceStatus) select 1) || {!isNull findDisplay 46} || {isNull findDisplay 0}) exitWith {};

private _pack = [uiNamespace getVariable ["COMSPEC_NDA_Lang", "fr"]] call comspec_overwatch_connect_fnc_ndaTexts;
[_pack] spawn {
    params ["_pack"];
    _pack params ["", "", "", "", "", "", "", "", ["_qTitle", ""], ["_qText", ""], ["_qBtn", ""], ["_back", ""]];
    uiSleep 0.1;
    private _quit = if (isNil "BIS_fnc_guiMessage") then { true } else {
        [parseText _qText, _qTitle, _qBtn, _back, findDisplay 0] call BIS_fnc_guiMessage
    };
    if (!(_quit isEqualTo true)) exitWith {
        [true] call comspec_overwatch_connect_fnc_showBetaAccessNote;
    };
    missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", false, false];
    uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", false];
    // Sortie propre : le bouton Quitter du menu principal d'Arma (le moteur gère la fermeture).
    private _exit = (findDisplay 0) displayCtrl 106;
    if (!isNull _exit && {(ctrlType _exit) in [1, 11, 16]}) then {
        ctrlActivate _exit;
    };
};
