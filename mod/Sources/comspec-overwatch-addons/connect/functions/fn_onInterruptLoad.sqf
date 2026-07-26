/*
    Ajoute le bouton « COMSPEC Overwatch » au menu Échap (SP / MP).
    Injection SQF via DisplayLoad — évite d’hériter RscDisplayInterrupt en config
    (erreurs au démarrage du launcher).
*/
params ["_display"];

if (!hasInterface) exitWith {};
if (isNull _display) exitWith {};
if (!isNull (_display displayCtrl 9605)) exitWith {};

private _btn = _display ctrlCreate ["RscButton", 9605];
_btn ctrlSetText "COMSPEC Overwatch";
_btn ctrlSetFont "PuristaMedium";
_btn ctrlSetFontHeight (((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85);
_btn ctrlSetTextColor [0.85, 0.95, 0.9, 1];
_btn ctrlSetBackgroundColor [0.02, 0.16, 0.14, 0.95];
_btn ctrlSetPosition [
    safezoneX + 0.02 * safezoneW,
    safezoneY + 0.02 * safezoneH,
    0.16 * safezoneW,
    0.032 * safezoneH
];
_btn ctrlCommit 0;
_btn ctrlAddEventHandler ["ButtonClick", {
    [] call comspec_overwatch_connect_fnc_pauseManagerShow;
}];
