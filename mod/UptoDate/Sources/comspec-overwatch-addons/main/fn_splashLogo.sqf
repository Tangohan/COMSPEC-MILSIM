/*
    Ajoute le logo COMSPEC sur l'ecran de demarrage (RscDisplayStart).
    Runtime ctrlCreate — pas de patch config LoadingStart (conflits pack).
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};

// Evite double injection si le display reload / mod charge 2 fois
if (_display getVariable ["COMSPEC_SplashLogoDone", false]) exitWith {};
_display setVariable ["COMSPEC_SplashLogoDone", true];

private _logo = _display ctrlCreate ["RscPictureKeepAspect", 88001];
_logo ctrlSetText "\z\comspec_overwatch\addons\main\img\comspec_atak_logo.paa";
// ~1/3 ecran, centre (proportions type VTN)
_logo ctrlSetPosition [
    0.33375 * safezoneW + safezoneX,
    0.29 * safezoneH + safezoneY,
    0.3325 * safezoneW,
    0.39375 * safezoneH
];
_logo ctrlCommit 0;
_logo ctrlShow true;
