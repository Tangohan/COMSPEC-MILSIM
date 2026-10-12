/*
    Écran du choix Athena : langue du jeu par défaut, Échap bloqué tant qu'aucun choix n'est fait.
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

private _lang = uiNamespace getVariable ["COMSPEC_AthenaChoice_Lang", ""];
if (!(_lang isEqualType "") || {!(_lang in ["fr", "en"])}) then {
    _lang = ["en", "fr"] select ((toLower language) isEqualTo "french");
};
[_lang] call comspec_overwatch_connect_fnc_athenaChoiceLanguage;

// Premier lancement : pas de bouton Fermer ni d'Échap, il faut répondre.
_display displayAddEventHandler ["KeyDown", {
    params ["", "_key"];
    (_key isEqualTo 1) && {([] call comspec_overwatch_connect_fnc_athenaMode) isEqualTo ""}
}];
