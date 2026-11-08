/*
    Remplit l’écran NDA selon la langue (fr / en) et ajuste la hauteur du texte.
    Licence courante pas encore acceptée : « J’accepte » attend la lecture jusqu’à la fin (fn_ndaWatch), une fois par version.
    Échap fait comme le second bouton (fn_ndaDecline) : avant, il fermait la note sans rien demander.
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

uiNamespace setVariable ["COMSPEC_NDA_ReadDone", ([] call comspec_overwatch_connect_fnc_licenceStatus) select 1];

// Logo carré : largeur convertie par le rapport des pixels.
private _logo = _display displayCtrl 9517;
(ctrlPosition _logo) params ["_lx", "_ly", "", "_lh"];
_logo ctrlSetPosition [_lx, _ly, _lh * pixelW / pixelH, _lh];
_logo ctrlCommit 0;

_display displayAddEventHandler ["KeyDown", {
    params ["", "_key"];
    if (_key isEqualTo 1) exitWith { [] call comspec_overwatch_connect_fnc_ndaDecline; true };
    false
}];

[_lang] call comspec_overwatch_connect_fnc_ndaSetLanguage;
[_display] spawn comspec_overwatch_connect_fnc_ndaWatch;
