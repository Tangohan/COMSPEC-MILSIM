/*
    Bandeau « bêta publique » en haut du menu principal Arma.
    Toujours visible ; un clic ouvre la note complète.
*/
params [["_display", displayNull]];
if (!hasInterface || {isNull _display}) exitWith {};

private _bg = _display displayCtrl 9612;
if (isNull _bg) then {
    private _barW = 0.64 * safezoneW;
    private _barH = 0.046 * safezoneH;
    private _barX = safezoneX + ((safezoneW - _barW) / 2);
    private _barY = safezoneY + (0.018 * safezoneH);

    _bg = _display ctrlCreate ["RscText", 9612];
    _bg ctrlSetPosition [_barX, _barY, _barW, _barH];
    _bg ctrlSetBackgroundColor [0.015, 0.04, 0.08, 0.94];
    _bg ctrlCommit 0;

    private _accent = _display ctrlCreate ["RscText", 9613];
    _accent ctrlSetPosition [_barX, _barY, _barW, 0.0035 * safezoneH];
    _accent ctrlSetBackgroundColor [0.91, 0.72, 0.29, 0.95];
    _accent ctrlCommit 0;

    private _icon = _display ctrlCreate ["RscPicture", 9610];
    _icon ctrlSetText "\z\comspec_overwatch\addons\connect\img\comspec_atak_logo.paa";
    private _iconS = 0.028 * safezoneH;
    _icon ctrlSetPosition [
        _barX + (0.008 * safezoneW),
        _barY + ((_barH - _iconS) / 2),
        _iconS,
        _iconS
    ];
    _icon ctrlCommit 0;

    private _text = _display ctrlCreate ["RscStructuredText", 9611];
    _text ctrlSetPosition [
        _barX + _iconS + (0.014 * safezoneW),
        _barY + (0.004 * safezoneH),
        _barW - _iconS - (0.022 * safezoneW),
        _barH - (0.006 * safezoneH)
    ];
    _text ctrlCommit 0;

    private _hit = _display ctrlCreate ["COMSPEC_RscInvisibleButton", 9614];
    _hit ctrlSetPosition [_barX, _barY, _barW, _barH];
    _hit ctrlSetText "";
    _hit ctrlSetBackgroundColor [0, 0, 0, 0];
    _hit ctrlSetTextColor [0, 0, 0, 0];
    _hit ctrlSetTooltip "";
    _hit ctrlAddEventHandler ["ButtonClick", {
        [true] call comspec_overwatch_connect_fnc_showBetaAccessNote;
    }];
    _hit ctrlCommit 0;
};

[_display] call comspec_overwatch_connect_fnc_refreshMainMenuBetaBanner;
