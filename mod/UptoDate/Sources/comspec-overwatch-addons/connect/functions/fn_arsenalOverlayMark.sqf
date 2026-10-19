/*
    Case à cocher d'une ligne de la liste Tenues Athena (sélection multiple, Mes tenues).
    Params : [liste, ligne, cochée]
*/
params ["_list", "_r", ["_on", false]];
private _pic = ["\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_unchecked_ca.paa", "\a3\ui_f\data\GUI\RscCommon\RscCheckBox\CheckBox_checked_ca.paa"] select _on;
private _rgba = [[0.56, 0.65, 0.59, 1], [0.36, 0.78, 0.42, 1]] select _on;
_list lnbSetPicture [[_r, 0], _pic];
_list lnbSetPictureColor [[_r, 0], _rgba];
_list lnbSetPictureColorSelected [[_r, 0], _rgba];
