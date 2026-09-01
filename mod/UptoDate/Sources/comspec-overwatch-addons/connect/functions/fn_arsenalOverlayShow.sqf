/*
    Bandeau Athena en haut de l’arsenal, dans la colonne centrale :
    tenues de toute la communauté, sans recouvrir « Mes équipements ».
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};

private _old = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (!isNull _old) then {
    ctrlDelete _old;
};

uiNamespace setVariable ["ace_arsenal_display", _display];

private _gridW = ((safeZoneW / safeZoneH) min 1.2) / 40;
private _sideW = 13 * _gridW;
private _gap = 0.008;
private _x = safeZoneX + _sideW + _gap;
private _w = (safeZoneW - (2 * _sideW) - (2 * _gap)) max 0.34;
private _y = safeZoneY + 0.01;
private _h = 0.205;

private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [_x, _y, _w, _h];
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];

private _bg = _display ctrlCreate ["RscText", -1, _grp];
_bg ctrlSetPosition [0, 0, _w, _h];
_bg ctrlSetBackgroundColor [0.045, 0.055, 0.06, 0.94];
_bg ctrlCommit 0;

private _accent = _display ctrlCreate ["RscText", -1, _grp];
_accent ctrlSetPosition [0, 0, _w, 0.0038];
_accent ctrlSetBackgroundColor [0.32, 0.78, 0.66, 1];
_accent ctrlCommit 0;

private _title = _display ctrlCreate ["RscStructuredText", -1, _grp];
_title ctrlSetPosition [0.008, 0.006, _w - 0.016, 0.022];
_title ctrlSetStructuredText parseText "<t size='0.86' color='#c7f4e6' font='PuristaBold'>ATHENA  ·  Tenues de la communauté</t>";
_title ctrlCommit 0;

private _btnW = (_w - 0.024) / 2;
private _btnPush = _display ctrlCreate ["RscButton", 884402, _grp];
_btnPush ctrlSetPosition [0.008, 0.030, _btnW, 0.026];
_btnPush ctrlSetText "Envoyer vers Athena";
_btnPush ctrlSetTooltip "Enregistre vos tenues locales pour toute la communauté.";
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
_btnPull ctrlSetPosition [0.016 + _btnW, 0.030, _btnW, 0.026];
_btnPull ctrlSetText "Récupérer";
_btnPull ctrlSetTooltip "Ajoute dans cet arsenal les tenues déjà enregistrées par la communauté.";
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
_list ctrlSetPosition [0.008, 0.060, _w - 0.016, 0.118];
_list ctrlSetBackgroundColor [0.03, 0.04, 0.045, 0.92];
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
_hint ctrlSetPosition [0.008, 0.180, _w - 0.016, 0.022];
_hint ctrlSetStructuredText parseText "<t size='0.62' color='#9aada8'>Double-clic pour enfiler. La liste reprend les tenues de toute la communauté.</t>";
_hint ctrlCommit 0;

[_display] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
