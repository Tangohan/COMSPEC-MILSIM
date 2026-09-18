/*
    Ajoute le bouton « COMSPEC Overwatch » au menu Échap (SP / MP).
    Injection SQF via DisplayLoad — évite d’hériter RscDisplayInterrupt en config
    (erreurs au démarrage du launcher).
*/
params ["_display"];

if (!hasInterface) exitWith {};
if (isNull _display) exitWith {};

private _h = 0.032 * safezoneH;
private _y = safezoneY + 0.02 * safezoneH;
private _fontH = (((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85);

if (isNull (_display displayCtrl 9605)) then {
    private _btn = _display ctrlCreate ["RscButton", 9605];
    _btn ctrlSetText "COMSPEC Overwatch";
    _btn ctrlSetFont "PuristaMedium";
    _btn ctrlSetFontHeight _fontH;
    _btn ctrlSetTextColor [0.85, 0.95, 0.9, 1];
    _btn ctrlSetBackgroundColor [0.02, 0.16, 0.14, 0.95];
    _btn ctrlSetPosition [
        safezoneX + 0.02 * safezoneW,
        _y,
        0.16 * safezoneW,
        _h
    ];
    _btn ctrlCommit 0;
    _btn ctrlAddEventHandler ["ButtonClick", {
        ["INFO", "Esc", "Bouton COMSPEC Overwatch cliqué"] call comspec_overwatch_connect_fnc_log;
        [] call comspec_overwatch_connect_fnc_pauseManagerShow;
    }];
};

if (isNull (_display displayCtrl 9606)) then {
    private _iso = _display ctrlCreate ["RscButton", 9606];
    _iso ctrlSetText "Dépannage liaison";
    _iso ctrlSetFont "PuristaMedium";
    _iso ctrlSetFontHeight _fontH;
    _iso ctrlSetTextColor [1, 0.9, 0.65, 1];
    _iso ctrlSetBackgroundColor [0.22, 0.14, 0.04, 0.95];
    _iso ctrlSetPosition [
        safezoneX + 0.19 * safezoneW,
        _y,
        0.16 * safezoneW,
        _h
    ];
    _iso ctrlCommit 0;
    _iso ctrlAddEventHandler ["ButtonClick", {
        ["INFO", "Esc", "Bouton Dépannage liaison cliqué"] call comspec_overwatch_connect_fnc_log;
        [] call comspec_overwatch_connect_fnc_diagIsolateLaunch;
    }];
};

["DEBUG", "Esc", "Bouton menu pause injecté"] call comspec_overwatch_connect_fnc_log;
