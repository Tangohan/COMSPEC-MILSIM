/*
    Bouton Athena à l’arsenal. Fermé par défaut ; au clic, grande fenêtre
    à deux colonnes (mes tenues / communauté), icônes, envoi ou récupération
    d’une tenue ou de toutes.
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
private _gap = 0.010;
private _guiH = ((((safezoneW / safezoneH) min 1.2) / 1.2) / 25);

private _btnW = 0.090 * safezoneW;
private _btnH = 0.030 * safezoneH;
private _btnX = safeZoneX + safeZoneW - _sideW - _gap - _btnW;
private _btnY = safeZoneY + 0.010;

private _tog = _display ctrlCreate ["RscButton", 884400];
_tog ctrlSetPosition [_btnX, _btnY, _btnW, _btnH];
_tog ctrlSetText "Athena";
_tog ctrlSetTooltip "Tenues de la communauté — ouvrir ou fermer";
_tog ctrlSetFont "PuristaMedium";
_tog ctrlSetFontHeight (_guiH * 0.82);
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

private _w = (safeZoneW - (2 * _sideW) - (2 * _gap)) max (0.42 * safezoneW);
private _h = (0.72 * safezoneH) min ((safeZoneY + safeZoneH) - (_btnY + _btnH) - 0.036);
private _x = (safeZoneX + safeZoneW - _sideW - _gap - _w) max (safeZoneX + _sideW + _gap);
private _y = _btnY + _btnH + 0.008;

private _grp = _display ctrlCreate ["RscControlsGroupNoScrollbars", 884401];
_grp ctrlSetPosition [_x, _y, _w, _h];
_grp ctrlShow false;
_grp ctrlCommit 0;
_display setVariable ["COMSPEC_ArsenalOverlay", _grp];
_display setVariable ["COMSPEC_ArsenalOverlayOpen", false];

private _bg = _display ctrlCreate ["RscText", -1, _grp];
_bg ctrlSetPosition [0, 0, _w, _h];
_bg ctrlSetBackgroundColor [0.038, 0.048, 0.055, 0.97];
_bg ctrlCommit 0;

private _accent = _display ctrlCreate ["RscText", -1, _grp];
_accent ctrlSetPosition [0, 0, _w, 0.0042];
_accent ctrlSetBackgroundColor [0.32, 0.78, 0.66, 1];
_accent ctrlCommit 0;

private _pad = 0.012;
private _title = _display ctrlCreate ["RscStructuredText", -1, _grp];
_title ctrlSetPosition [_pad, 0.010, _w - (_pad * 2), 0.032];
_title ctrlSetStructuredText parseText "<t size='1.15' color='#d8f6ec' font='PuristaBold'>Tenues de la communauté</t>";
_title ctrlCommit 0;

private _colGap = 0.014;
private _colW = (_w - (_pad * 2) - _colGap) / 2;
private _leftX = _pad;
private _rightX = _pad + _colW + _colGap;
private _subY = 0.044;
private _subH = 0.026;
private _previewH = 0.128;
private _actH = 0.034;
private _actY = _h - _previewH - _actH - 0.016;
private _listY = _subY + _subH + 0.004;
private _listH = (_actY - _listY - 0.010) max 0.18;

private _leftTitle = _display ctrlCreate ["RscStructuredText", 884415, _grp];
_leftTitle ctrlSetPosition [_leftX, _subY, _colW, _subH];
_leftTitle ctrlSetStructuredText parseText "<t size='0.95' color='#9ed8b4'>Mes tenues</t>";
_leftTitle ctrlCommit 0;

private _rightTitle = _display ctrlCreate ["RscStructuredText", 884416, _grp];
_rightTitle ctrlSetPosition [_rightX, _subY, _colW, _subH];
_rightTitle ctrlSetStructuredText parseText "<t size='0.95' color='#9eb8d8'>Communauté</t>";
_rightTitle ctrlCommit 0;

private _listLocal = _display ctrlCreate ["RscListBox", 884405, _grp];
_listLocal ctrlSetPosition [_leftX, _listY, _colW, _listH];
_listLocal ctrlSetBackgroundColor [0.028, 0.036, 0.042, 0.96];
_listLocal ctrlSetFontHeight (_guiH * 0.92);
_listLocal ctrlAddEventHandler ["LBSelChanged", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    if (isNull _d) exitWith {};
    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    if (isNull _g) exitWith {};
    private _rows = _g getVariable ["COMSPEC_ArsenalLocalRows", []];
    if (_idx >= count _rows) exitWith {};
    (_rows select _idx) params ["_name", "_data"];
    private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    [_d, _loadout, format ["Aperçu — %1", _name]] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
}];
_listLocal ctrlAddEventHandler ["LBDblClick", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    if (isNull _d) exitWith {};
    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    if (isNull _g) exitWith {};
    private _rows = _g getVariable ["COMSPEC_ArsenalLocalRows", []];
    if (_idx >= count _rows) exitWith {};
    (_rows select _idx) params ["_name", "_data"];
    private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    [_loadout, _name] call comspec_overwatch_connect_fnc_arsenalApplyLoadout;
}];
_listLocal ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalLocalList", _listLocal];

private _listCloud = _display ctrlCreate ["RscListBox", 884404, _grp];
_listCloud ctrlSetPosition [_rightX, _listY, _colW, _listH];
_listCloud ctrlSetBackgroundColor [0.028, 0.036, 0.042, 0.96];
_listCloud ctrlSetFontHeight (_guiH * 0.92);
_listCloud ctrlAddEventHandler ["LBSelChanged", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _id = _ctrl lbData _idx;
    if (_id isEqualTo "") exitWith {};
    [_ctrl, _idx, _id] spawn {
        params ["_ctrl", "_idx", "_id"];
        private _loadout = [_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _name = _ctrl lbText _idx;
        [_d, _loadout, format ["Aperçu — %1", _name]] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
        if (!(_loadout isEqualTo [])) then {
            private _icons = [_loadout] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
            private _pic = "";
            {
                _x params ["", "", "_p"];
                if (_p isNotEqualTo "") exitWith { _pic = _p; };
            } forEach _icons;
            if (_pic isNotEqualTo "") then { _ctrl lbSetPicture [_idx, _pic]; };
        };
    };
}];
_listCloud ctrlAddEventHandler ["LBDblClick", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _data = _ctrl lbData _idx;
    if (_data isEqualTo "") exitWith {};
    [_data] spawn {
        params ["_id"];
        [_id] call comspec_overwatch_connect_fnc_arsenalApplyCloud;
    };
}];
_listCloud ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalList", _listCloud];

private _half = (_colW - 0.008) / 2;
private _btnPushOne = _display ctrlCreate ["RscButton", 884402, _grp];
_btnPushOne ctrlSetPosition [_leftX, _actY, _half, _actH];
_btnPushOne ctrlSetText "Envoyer cette";
_btnPushOne ctrlSetTooltip "Enregistre uniquement la tenue sélectionnée à gauche, pour la communauté.";
_btnPushOne ctrlSetFont "PuristaMedium";
_btnPushOne ctrlSetFontHeight (_guiH * 0.72);
_btnPushOne ctrlSetBackgroundColor [0.10, 0.26, 0.16, 1];
_btnPushOne ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        if (isNull _g) exitWith {};
        private _list = _g getVariable ["COMSPEC_ArsenalLocalList", controlNull];
        private _idx = if (isNull _list) then { -1 } else { lbCurSel _list };
        if (_idx < 0) exitWith {
            ["Choisissez une tenue à gauche, puis Envoyer cette.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        private _name = _list lbData _idx;
        if (_name isEqualTo "") exitWith {
            ["Choisissez une tenue à gauche, puis Envoyer cette.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        [[_name]] call comspec_overwatch_connect_fnc_arsenalPushAll;
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
    };
}];
_btnPushOne ctrlCommit 0;

private _btnPushAll = _display ctrlCreate ["RscButton", 884406, _grp];
_btnPushAll ctrlSetPosition [_leftX + _half + 0.008, _actY, _half, _actH];
_btnPushAll ctrlSetText "Envoyer toutes";
_btnPushAll ctrlSetTooltip "Enregistre toutes vos tenues locales pour la communauté.";
_btnPushAll ctrlSetFont "PuristaMedium";
_btnPushAll ctrlSetFontHeight (_guiH * 0.72);
_btnPushAll ctrlSetBackgroundColor [0.08, 0.20, 0.14, 1];
_btnPushAll ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPushAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPushAll ctrlCommit 0;

private _btnPullOne = _display ctrlCreate ["RscButton", 884403, _grp];
_btnPullOne ctrlSetPosition [_rightX, _actY, _half, _actH];
_btnPullOne ctrlSetText "Récupérer cette";
_btnPullOne ctrlSetTooltip "Ajoute uniquement la tenue sélectionnée à droite dans votre arsenal.";
_btnPullOne ctrlSetFont "PuristaMedium";
_btnPullOne ctrlSetFontHeight (_guiH * 0.72);
_btnPullOne ctrlSetBackgroundColor [0.12, 0.20, 0.32, 1];
_btnPullOne ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        if (isNull _g) exitWith {};
        private _list = _g getVariable ["COMSPEC_ArsenalList", controlNull];
        private _idx = if (isNull _list) then { -1 } else { lbCurSel _list };
        if (_idx < 0) exitWith {
            ["Choisissez une tenue à droite, puis Récupérer cette.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        private _id = _list lbData _idx;
        if (_id isEqualTo "") exitWith {
            ["Choisissez une tenue à droite, puis Récupérer cette.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        ["", [_id]] call comspec_overwatch_connect_fnc_arsenalPullAll;
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
    };
}];
_btnPullOne ctrlCommit 0;

private _btnPullAll = _display ctrlCreate ["RscButton", 884407, _grp];
_btnPullAll ctrlSetPosition [_rightX + _half + 0.008, _actY, _half, _actH];
_btnPullAll ctrlSetText "Récupérer toutes";
_btnPullAll ctrlSetTooltip "Ajoute dans cet arsenal toutes les tenues déjà enregistrées par la communauté.";
_btnPullAll ctrlSetFont "PuristaMedium";
_btnPullAll ctrlSetFontHeight (_guiH * 0.72);
_btnPullAll ctrlSetBackgroundColor [0.10, 0.16, 0.28, 1];
_btnPullAll ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPullAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPullAll ctrlCommit 0;

private _previewBg = _display ctrlCreate ["RscText", -1, _grp];
_previewBg ctrlSetPosition [_pad, _h - _previewH - 0.008, _w - (_pad * 2), _previewH];
_previewBg ctrlSetBackgroundColor [0.022, 0.030, 0.036, 0.98];
_previewBg ctrlCommit 0;

private _picS = (((_w - 0.24) / 6) min 0.068) max 0.040;
private _picGap = 0.008;
private _picY = _h - _previewH + 0.002;
private _previewPics = [];
for "_i" from 0 to 5 do {
    private _pic = _display ctrlCreate ["RscPicture", 884408 + _i, _grp];
    private _px = _pad + 0.006 + (_i * (_picS + _picGap));
    _pic ctrlSetPosition [_px, _picY, _picS, _picS];
    _pic ctrlSetText "";
    _pic ctrlShow false;
    _pic ctrlCommit 0;
    _previewPics pushBack _pic;
};
_grp setVariable ["COMSPEC_ArsenalPreviewPics", _previewPics];

private _names = _display ctrlCreate ["RscStructuredText", 884414, _grp];
_names ctrlSetPosition [_pad + 0.004, _picY + _picS + 0.002, _w - (_pad * 2) - 0.008, (_previewH - _picS - 0.012) max 0.028];
_names ctrlSetStructuredText parseText "<t size='0.92' color='#b7c9c4'>Clic : aperçu des équipements. Double-clic : enfiler. À gauche vos tenues, à droite la communauté.</t>";
_names ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalPreviewNames", _names];
