/*
    Petit bouton Athena à l’arsenal. La fenêtre des tenues reste fermée
    tant qu’on ne l’ouvre pas ; elle ne recouvre pas « Mes équipements ».
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};

private _oldGrp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (!isNull _oldGrp) then { ctrlDelete _oldGrp; };
private _oldTog = _display getVariable ["COMSPEC_ArsenalToggle", controlNull];
if (!isNull _oldTog) then { ctrlDelete _oldTog; };

uiNamespace setVariable ["ace_arsenal_display", _display];

private _gridW = ((safeZoneW / safeZoneH) min 1.2) / 40;
private _sideW = 13 * _gridW;
private _gap = 0.008;
private _guiH = ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25);

private _btnW = 0.078 * safezoneW;
private _btnH = 0.028 * safezoneH;
private _btnX = safeZoneX + safeZoneW - _sideW - _gap - _btnW;
private _btnY = safeZoneY + 0.010;

private _tog = _display ctrlCreate ["RscButton", 884400];
_tog ctrlSetPosition [_btnX, _btnY, _btnW, _btnH];
_tog ctrlSetText "Athena";
_tog ctrlSetTooltip "Tenues de la communauté — ouvrir ou fermer";
_tog ctrlSetFont "PuristaMedium";
_tog ctrlSetFontHeight (_guiH * 0.78);
_tog ctrlSetBackgroundColor [0.08, 0.18, 0.16, 0.94];
_tog ctrlSetTextColor [0.82, 0.96, 0.90, 1];
_tog ctrlAddEventHandler ["ButtonClick", {
    private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    if (isNull _d) exitWith {};
    private _grp = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    private _btn = _d getVariable ["COMSPEC_ArsenalToggle", controlNull];
    if (isNull _grp) exitWith {};
    private _open = !(_d getVariable ["COMSPEC_ArsenalOverlayOpen", false]);
    _d setVariable ["COMSPEC_ArsenalOverlayOpen", _open];
    _grp ctrlShow _open;
    if (!isNull _btn) then {
        _btn ctrlSetText (if (_open) then { "Fermer" } else { "Athena" });
    };
    if (_open) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
}];
_tog ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalToggle", _tog];

private _w = (0.27 * safezoneW) min ((safeZoneW - (2 * _sideW) - (2 * _gap)) max 0.22);
private _h = 0.268;
private _x = (safeZoneX + safeZoneW - _sideW - _gap - _w) max (safeZoneX + _sideW + _gap);
private _y = _btnY + _btnH + 0.006;

private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [_x, _y, _w, _h];
_grp ctrlShow false;
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];
_display setVariable ["COMSPEC_ArsenalOverlayOpen", false];

private _bg = _display ctrlCreate ["RscText", -1, _grp];
_bg ctrlSetPosition [0, 0, _w, _h];
_bg ctrlSetBackgroundColor [0.045, 0.055, 0.06, 0.96];
_bg ctrlCommit 0;

private _accent = _display ctrlCreate ["RscText", -1, _grp];
_accent ctrlSetPosition [0, 0, _w, 0.0036];
_accent ctrlSetBackgroundColor [0.32, 0.78, 0.66, 1];
_accent ctrlCommit 0;

private _title = _display ctrlCreate ["RscStructuredText", -1, _grp];
_title ctrlSetPosition [0.008, 0.008, _w - 0.016, 0.028];
_title ctrlSetStructuredText parseText "<t size='1.05' color='#d8f6ec' font='PuristaBold'>Tenues de la communauté</t>";
_title ctrlCommit 0;

private _btnRowW = (_w - 0.024) / 2;
private _btnPush = _display ctrlCreate ["RscButton", 884402, _grp];
_btnPush ctrlSetPosition [0.008, 0.040, _btnRowW, 0.030];
_btnPush ctrlSetText "Envoyer vers Athena";
_btnPush ctrlSetTooltip "Enregistre vos tenues locales pour toute la communauté.";
_btnPush ctrlSetFont "PuristaMedium";
_btnPush ctrlSetFontHeight (_guiH * 0.72);
_btnPush ctrlSetBackgroundColor [0.10, 0.26, 0.16, 1];
_btnPush ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPushAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPush ctrlCommit 0;

private _btnPull = _display ctrlCreate ["RscButton", 884403, _grp];
_btnPull ctrlSetPosition [0.016 + _btnRowW, 0.040, _btnRowW, 0.030];
_btnPull ctrlSetText "Récupérer";
_btnPull ctrlSetTooltip "Ajoute dans cet arsenal les tenues déjà enregistrées par la communauté.";
_btnPull ctrlSetFont "PuristaMedium";
_btnPull ctrlSetFontHeight (_guiH * 0.72);
_btnPull ctrlSetBackgroundColor [0.12, 0.20, 0.32, 1];
_btnPull ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPullAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPull ctrlCommit 0;

private _list = _display ctrlCreate ["RscListBox", 884404, _grp];
_list ctrlSetPosition [0.008, 0.076, _w - 0.016, 0.154];
_list ctrlSetBackgroundColor [0.03, 0.04, 0.045, 0.94];
_list ctrlSetFontHeight (_guiH * 0.78);
_list ctrlAddEventHandler ["LBDblClick", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _data = _ctrl lbData _idx;
    if (_data isEqualTo "") exitWith {};
    [_data] spawn {
        params ["_id"];
        [_id] call comspec_overwatch_connect_fnc_arsenalApplyCloud;
    };
}];
_list ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalList", _list];

private _hint = _display ctrlCreate ["RscStructuredText", -1, _grp];
_hint ctrlSetPosition [0.008, 0.234, _w - 0.016, 0.028];
_hint ctrlSetStructuredText parseText "<t size='0.92' color='#b7c9c4'>Double-clic pour enfiler. Liste de toute la communauté.</t>";
_hint ctrlCommit 0;
