/*
    Écran du choix Athena : langue du mod par défaut (réglage « Langue / Language » de l'ATAK natif, anglais par
    défaut, français s'il est sur français ou sur Auto avec Arma en français), Échap bloqué tant qu'aucun choix n'est fait.
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

private _lang = uiNamespace getVariable ["COMSPEC_AthenaChoice_Lang", ""];
if (!(_lang isEqualType "") || {!(_lang in ["fr", "en"])}) then {
    _lang = ["en", "fr"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr");
};
[_lang] call comspec_overwatch_connect_fnc_athenaChoiceLanguage;

// Premier lancement : pas de bouton Fermer ni d'Échap, il faut répondre.
_display displayAddEventHandler ["KeyDown", {
    params ["", "_key"];
    (_key isEqualTo 1) && {([] call comspec_overwatch_connect_fnc_athenaMode) isEqualTo ""}
}];
