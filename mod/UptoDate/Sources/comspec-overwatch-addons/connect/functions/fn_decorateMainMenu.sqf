/*
    Bandeau « bêta publique » en haut du menu principal Arma, sous la barre de menus du jeu (il la masquait).
    Un clic ouvre la note complète ; la croix le ferme jusqu'à la prochaine version du mod.
*/
params [["_display", displayNull]];
if (!hasInterface || {isNull _display}) exitWith {};
private _ver = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
if ((profileNamespace getVariable ["comspec_overwatch_beta_banner_closed", ""]) isEqualTo _ver) exitWith {};

private _bg = _display displayCtrl 9612;
if (isNull _bg) then {
    private _barW = 0.64 * safezoneW;
    private _barH = 0.046 * safezoneH;
    private _barX = safezoneX + ((safezoneW - _barW) / 2);
    private _barY = safezoneY + (0.105 * safezoneH);

    _bg = _display ctrlCreate ["RscText", 9612];
    _bg ctrlSetPosition [_barX, _barY, _barW, _barH];
    _bg ctrlSetBackgroundColor [0.015, 0.04, 0.08, 0.94];
    _bg ctrlCommit 0;

    private _accent = _display ctrlCreate ["RscText", 9613];
    _accent ctrlSetPosition [_barX, _barY, _barW, 0.0035 * safezoneH];
    _accent ctrlSetBackgroundColor [0.91, 0.72, 0.29, 0.95];
    _accent ctrlCommit 0;

    private _icon = _display ctrlCreate ["RscPicture", 9610];
    _icon ctrlSetText (("\z\comspec_overwatch\addons\connect\img\comspec_atak_logo.paa") call comspec_overwatch_connect_fnc_tr);
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
    _hit ctrlSetPosition [_barX, _barY, _barW - _barH, _barH];
    _hit ctrlSetText (("") call comspec_overwatch_connect_fnc_tr);
    _hit ctrlSetBackgroundColor [0, 0, 0, 0];
    _hit ctrlSetTextColor [0, 0, 0, 0];
    _hit ctrlSetTooltip (("") call comspec_overwatch_connect_fnc_tr);
    _hit ctrlAddEventHandler ["ButtonClick", {
        [true] call comspec_overwatch_connect_fnc_showBetaAccessNote;
    }];
    _hit ctrlCommit 0;

    // Croix de fermeture (à droite) : masque le bandeau jusqu'à la prochaine version.
    private _close = _display ctrlCreate ["RscButton", 9615];
    _close ctrlSetPosition [_barX + _barW - _barH, _barY, _barH, _barH];
    _close ctrlSetText (("×") call comspec_overwatch_connect_fnc_tr);
    _close ctrlSetBackgroundColor [0, 0, 0, 0];
    _close ctrlSetTextColor [0.78, 0.85, 0.9, 1];
    _close ctrlSetTooltip (("Fermer") call comspec_overwatch_connect_fnc_tr);
    _close ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        private _d = ctrlParent _c;
        { (_d displayCtrl _x) ctrlShow false; } forEach [9610, 9611, 9612, 9613, 9614, 9615];
        profileNamespace setVariable ["comspec_overwatch_beta_banner_closed", getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr")];
        saveProfileNamespace;
    }];
    _close ctrlCommit 0;
};

[_display] call comspec_overwatch_connect_fnc_refreshMainMenuBetaBanner;
