/*
    Panneau Athena superposé à ACE Arsenal (displayOpened).
    params: [display]
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};

// Nettoyer un panneau précédent
private _old = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (!isNull _old) then {
    ctrlDelete _old;
};

private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [
    safeZoneX + safeZoneW - 0.28 * 3 / 4,
    safeZoneY + 0.12,
    0.26 * 3 / 4,
    0.62
];
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];

private _bg = _display ctrlCreate ["RscText", -1, _grp];
_bg ctrlSetPosition [0, 0, 0.26 * 3 / 4, 0.62];
_bg ctrlSetBackgroundColor [0.05, 0.07, 0.09, 0.88];
_bg ctrlCommit 0;

private _title = _display ctrlCreate ["RscStructuredText", -1, _grp];
_title ctrlSetPosition [0.008, 0.01, 0.24 * 3 / 4, 0.05];
_title ctrlSetStructuredText parseText "<t size='0.9' color='#e8eef2' font='PuristaBold'>ATHENA · WARDROBES</t>";
_title ctrlCommit 0;

private _hint = _display ctrlCreate ["RscStructuredText", -1, _grp];
_hint ctrlSetPosition [0.008, 0.055, 0.24 * 3 / 4, 0.06];
_hint ctrlSetStructuredText parseText "<t size='0.65' color='#8aa0ad'>Sync cloud + collections d’équipement</t>";
_hint ctrlCommit 0;

private _btnPush = _display ctrlCreate ["RscButton", 884402, _grp];
_btnPush ctrlSetPosition [0.012, 0.12, 0.22 * 3 / 4, 0.045];
_btnPush ctrlSetText "Sauvegarder tout → Athena";
_btnPush ctrlSetBackgroundColor [0.12, 0.35, 0.28, 1];
_btnPush ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPushAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPush ctrlCommit 0;

private _btnPull = _display ctrlCreate ["RscButton", 884403, _grp];
_btnPull ctrlSetPosition [0.012, 0.175, 0.22 * 3 / 4, 0.045];
_btnPull ctrlSetText "Récupérer ← Athena";
_btnPull ctrlSetBackgroundColor [0.18, 0.28, 0.42, 1];
_btnPull ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPullAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPull ctrlCommit 0;

private _listTitle = _display ctrlCreate ["RscStructuredText", -1, _grp];
_listTitle ctrlSetPosition [0.008, 0.235, 0.24 * 3 / 4, 0.035];
_listTitle ctrlSetStructuredText parseText "<t size='0.7' color='#b8c8d0'>Cloud (cliquer pour équiper)</t>";
_listTitle ctrlCommit 0;

private _list = _display ctrlCreate ["RscListBox", 884404, _grp];
_list ctrlSetPosition [0.01, 0.275, 0.23 * 3 / 4, 0.30];
_list ctrlCommit 0;
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

_grp setVariable ["COMSPEC_ArsenalList", _list];
[_display] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
