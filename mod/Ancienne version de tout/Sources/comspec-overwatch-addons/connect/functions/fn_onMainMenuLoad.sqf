/*
    Hook menu principal Arma (RscDisplayMain) — NDA accès anticipé + enregistrement
    + badge version bas-droite (façon ACE/CBA). Recréé à chaque ouverture du menu
    (le display natif est détruit/recréé), donc gardé séparément de COMSPEC_MainMenuBetaBooted.
*/
params [["_display", displayNull]];

if (!hasInterface) exitWith {};

if (!isNull _display && {isNull (_display displayCtrl 9611)}) then {
    private _version = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
    if (_version isEqualTo "") then { _version = "?"; };

    private _badgeH = 0.02 * safezoneH;
    private _iconX = safezoneX + safezoneW - _badgeH - (0.015 * safezoneW);
    private _badgeY = safezoneY + safezoneH - _badgeH - (0.075 * safezoneH);
    private _textW = 0.11 * safezoneW;

    private _icon = _display ctrlCreate ["RscPicture", 9610];
    _icon ctrlSetText "\z\comspec_overwatch\addons\connect\img\comspec_atak_logo.paa";
    _icon ctrlSetPosition [_iconX, _badgeY, _badgeH, _badgeH];
    _icon ctrlCommit 0;

    private _text = _display ctrlCreate ["RscStructuredText", 9611];
    _text ctrlSetStructuredText parseText format [
        "<t align='right' size='0.45' color='#8aa0b4'>COMSPEC Overwatch<br/>v%1</t>", _version
    ];
    _text ctrlSetPosition [_iconX - _textW - (0.006 * safezoneW), _badgeY - (0.2 * _badgeH), _textW, _badgeH * 1.6];
    _text ctrlCommit 0;
};

if (uiNamespace getVariable ["COMSPEC_MainMenuBetaBooted", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", true];

0 spawn {
    // Laisser le menu et l’extension se stabiliser avant le dialogue natif
    uiSleep 1.5;
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    "COMSPECExtension" callExtension "Warmup";
    uiSleep 0.35;
    [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
};
