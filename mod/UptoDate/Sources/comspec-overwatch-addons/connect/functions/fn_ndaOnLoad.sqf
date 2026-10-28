/*
    Remplit l’écran NDA selon la langue (fr / en) et ajuste la hauteur du texte.
*/
params [["_display", displayNull]];

if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
};
if (isNull _display) exitWith {};

private _lang = uiNamespace getVariable ["COMSPEC_NDA_Lang", ""];
if (!(_lang isEqualType "") || {_lang isEqualTo ""}) then {
    // Langue du mod (réglage « Langue / Language » de l'ATAK natif) ; sans l'ATAK natif, langue du jeu.
    _lang = ["en", "fr"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", ["en", "fr"] select ((toLower language) isEqualTo "french")]) isEqualTo "fr");
    uiNamespace setVariable ["COMSPEC_NDA_Lang", _lang];
};

[_lang] call comspec_overwatch_connect_fnc_ndaSetLanguage;
