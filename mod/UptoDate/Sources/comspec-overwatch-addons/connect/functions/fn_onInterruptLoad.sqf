/*
    Menu Échap (SP / MP) : bouton « Dépannage liaison » uniquement.
*/
params ["_display"];

if (!hasInterface) exitWith {};
if (isNull _display) exitWith {};

private _old = _display displayCtrl 9605;
if (!isNull _old) then { ctrlDelete _old; };

private _h = 0.032 * safezoneH;
private _y = safezoneY + 0.02 * safezoneH;
private _fontH = (((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 0.85);

if (isNull (_display displayCtrl 9606)) then {
    private _iso = _display ctrlCreate ["RscButton", 9606];
    _iso ctrlSetText "Dépannage liaison";
    _iso ctrlSetFont "PuristaMedium";
    _iso ctrlSetFontHeight _fontH;
    _iso ctrlSetTextColor [1, 0.9, 0.65, 1];
    _iso ctrlSetBackgroundColor [0.22, 0.14, 0.04, 0.95];
    _iso ctrlSetPosition [
        safezoneX + 0.02 * safezoneW,
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

["DEBUG", "Esc", "Bouton Dépannage liaison injecté"] call comspec_overwatch_connect_fnc_log;
