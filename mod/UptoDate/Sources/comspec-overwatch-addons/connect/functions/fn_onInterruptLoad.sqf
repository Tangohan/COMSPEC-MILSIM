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
    _iso ctrlSetText (("Dépannage liaison") call comspec_overwatch_connect_fnc_tr);
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

// Service Athéna : revoir le choix « mon unité utilise Athéna » (Oui / Non).
if (isNull (_display displayCtrl 9607)) then {
    private _fr = (toLower language) isEqualTo "french";
    private _mode = [] call comspec_overwatch_connect_fnc_athenaMode;
    private _btn = _display ctrlCreate ["RscButton", 9607];
    _btn ctrlSetText ((format ["%1 : %2", ["Athena service", "Service Athéna"] select _fr, [["no", "yes"], ["non", "oui"]] select _fr select (_mode isEqualTo "on")]) call comspec_overwatch_connect_fnc_tr);
    _btn ctrlSetFont "PuristaMedium";
    _btn ctrlSetFontHeight _fontH;
    _btn ctrlSetTextColor [0.85, 0.95, 0.92, 1];
    _btn ctrlSetBackgroundColor [0.05, 0.16, 0.15, 0.95];
    _btn ctrlSetPosition [
        safezoneX + 0.19 * safezoneW,
        _y,
        0.14 * safezoneW,
        _h
    ];
    _btn ctrlCommit 0;
    _btn ctrlAddEventHandler ["ButtonClick", { [true] call comspec_overwatch_connect_fnc_athenaChoiceShow; }];
};

["DEBUG", "Esc", "Bouton Dépannage liaison injecté"] call comspec_overwatch_connect_fnc_log;
