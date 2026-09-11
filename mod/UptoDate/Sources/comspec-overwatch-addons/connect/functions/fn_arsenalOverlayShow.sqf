/*
    Bouton Athena à l’arsenal. Fermé par défaut ; au clic, grande fenêtre
    à deux colonnes (mes tenues / communauté), aperçu d’équipement,
    partage ou import d’une tenue ou de toutes.
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

private _btnW = 0.096 * safezoneW;
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

private _w = (safeZoneW - (2 * _sideW) - (2 * _gap)) max (0.44 * safezoneW);
private _h = (0.74 * safezoneH) min ((safeZoneY + safeZoneH) - (_btnY + _btnH) - 0.032);
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
_bg ctrlSetBackgroundColor [0.032, 0.040, 0.048, 0.975];
_bg ctrlCommit 0;

private _accent = _display ctrlCreate ["RscText", -1, _grp];
_accent ctrlSetPosition [0, 0, _w, 0.004];
_accent ctrlSetBackgroundColor [0.32, 0.78, 0.66, 1];
_accent ctrlCommit 0;

private _pad = 0.012;
private _title = _display ctrlCreate ["RscStructuredText", -1, _grp];
_title ctrlSetPosition [_pad, 0.008, _w - (_pad * 2), 0.028];
_title ctrlSetStructuredText parseText "<t size='1.12' color='#d8f6ec' font='PuristaBold'>Tenues de la communauté</t><t size='0.78' color='#7a9a92'>   Partager vos tenues · importer celles de la communauté</t>";
_title ctrlCommit 0;

private _hint = _display ctrlCreate ["RscStructuredText", 884419, _grp];
_hint ctrlSetPosition [_pad, 0.034, _w - (_pad * 2), 0.022];
_hint ctrlSetStructuredText parseText "<t size='0.78' color='#8aa8a0'>Ouvrez une collection, choisissez une tenue, puis partagez ou importez.</t>";
_hint ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalHint", _hint];

private _colGap = 0.016;
private _colW = (_w - (_pad * 2) - _colGap) / 2;
private _leftX = _pad;
private _rightX = _pad + _colW + _colGap;
private _headY = 0.058;
private _headH = 0.034;
private _previewH = 0.118;
private _actH = 0.028;
private _actStack = (_actH * 2) + 0.005;
private _actY = _h - _previewH - _actStack - 0.014;
private _listY = _headY + _headH + 0.004;
private _listH = (_actY - _listY - 0.008) max 0.16;

private _div = _display ctrlCreate ["RscText", -1, _grp];
_div ctrlSetPosition [_leftX + _colW + (_colGap * 0.35), _headY, 0.0022, (_actY - _headY - 0.006) max 0.2];
_div ctrlSetBackgroundColor [0.22, 0.34, 0.30, 0.55];
_div ctrlCommit 0;

private _leftHeadBg = _display ctrlCreate ["RscText", -1, _grp];
_leftHeadBg ctrlSetPosition [_leftX, _headY, _colW, _headH];
_leftHeadBg ctrlSetBackgroundColor [0.07, 0.14, 0.11, 0.95];
_leftHeadBg ctrlCommit 0;

private _rightHeadBg = _display ctrlCreate ["RscText", -1, _grp];
_rightHeadBg ctrlSetPosition [_rightX, _headY, _colW, _headH];
_rightHeadBg ctrlSetBackgroundColor [0.07, 0.11, 0.18, 0.95];
_rightHeadBg ctrlCommit 0;

private _leftTitle = _display ctrlCreate ["RscStructuredText", 884415, _grp];
_leftTitle ctrlSetPosition [_leftX + 0.004, _headY + 0.004, _colW - 0.008, _headH - 0.006];
_leftTitle ctrlSetStructuredText parseText "<t size='0.92' color='#b8ecc8' font='PuristaBold'>Mes tenues</t><t size='0.70' color='#6d8a82'><br/>Cet ordinateur · collections</t>";
_leftTitle ctrlCommit 0;

private _rightTitle = _display ctrlCreate ["RscStructuredText", 884416, _grp];
_rightTitle ctrlSetPosition [_rightX + 0.004, _headY + 0.004, _colW - 0.008, _headH - 0.006];
_rightTitle ctrlSetStructuredText parseText "<t size='0.92' color='#b0c8e8' font='PuristaBold'>Communauté</t><t size='0.70' color='#6d7a8a'><br/>Poste · collections partagées</t>";
_rightTitle ctrlCommit 0;

private _fnc_setHint = {
    params ["_grp", "_text"];
    private _hCtrl = _grp getVariable ["COMSPEC_ArsenalHint", controlNull];
    if (isNull _hCtrl) exitWith {};
    _hCtrl ctrlSetStructuredText parseText format ["<t size='0.78' color='#8aa8a0'>%1</t>", _text];
};

private _listLocal = _display ctrlCreate ["RscListBox", 884405, _grp];
_listLocal ctrlSetPosition [_leftX, _listY, _colW, _listH];
_listLocal ctrlSetBackgroundColor [0.024, 0.032, 0.038, 0.96];
_listLocal ctrlSetFontHeight (_guiH * 0.90);
_listLocal ctrlAddEventHandler ["LBSelChanged", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    if (isNull _d) exitWith {};
    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    if (isNull _g) exitWith {};
    if (_g getVariable ["COMSPEC_ArsenalUiLock", false]) exitWith {};
    private _kind = _ctrl lbValue _idx;
    if (_kind == 0) exitWith {
        private _key = _ctrl lbData _idx;
        private _map = _d getVariable ["COMSPEC_ArsenalCollapsedLocal", createHashMap];
        _map set [_key, !(_map getOrDefault [_key, true])];
        _d setVariable ["COMSPEC_ArsenalCollapsedLocal", _map];
        _g setVariable ["COMSPEC_ArsenalUiLock", true];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
        for "_i" from 0 to ((lbSize _ctrl) - 1) do {
            if ((_ctrl lbValue _i) == 0 && {(_ctrl lbData _i) isEqualTo _key}) exitWith {
                _ctrl lbSetCurSel _i;
            };
        };
        _g setVariable ["COMSPEC_ArsenalUiLock", false];
        [_g, format ["Collection « %1 » — cliquez une tenue pour l’aperçu, double-clic pour l’enfiler.", _key]] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
    };
    if (_kind != 1) exitWith {};
    private _name = _ctrl lbData _idx;
    private _localMap = _g getVariable ["COMSPEC_ArsenalLocalMap", createHashMap];
    private _data = _localMap getOrDefault [_name, []];
    private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    [_d, _loadout, format ["Aperçu — %1", _name]] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
    [_g, "Tenue locale sélectionnée — Partager cette tenue, ou Supprimer de mon arsenal."] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
}];
_listLocal ctrlAddEventHandler ["LBDblClick", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    if ((_ctrl lbValue _idx) != 1) exitWith {};
    private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    if (isNull _d) exitWith {};
    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    if (isNull _g) exitWith {};
    private _name = _ctrl lbData _idx;
    private _localMap = _g getVariable ["COMSPEC_ArsenalLocalMap", createHashMap];
    private _data = _localMap getOrDefault [_name, []];
    private _loadout = [_data] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
    [_loadout, _name] call comspec_overwatch_connect_fnc_arsenalApplyLoadout;
}];
_listLocal ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalLocalList", _listLocal];
_grp setVariable ["COMSPEC_ArsenalSetHint", _fnc_setHint];

private _listCloud = _display ctrlCreate ["RscListBox", 884404, _grp];
_listCloud ctrlSetPosition [_rightX, _listY, _colW, _listH];
_listCloud ctrlSetBackgroundColor [0.024, 0.032, 0.038, 0.96];
_listCloud ctrlSetFontHeight (_guiH * 0.90);
_listCloud ctrlAddEventHandler ["LBSelChanged", {
    params ["_ctrl", "_idx"];
    if (_idx < 0) exitWith {};
    private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    if (isNull _d) exitWith {};
    private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
    if (isNull _g) exitWith {};
    if (_g getVariable ["COMSPEC_ArsenalUiLock", false]) exitWith {};
    private _kind = _ctrl lbValue _idx;
    if (_kind == 0) exitWith {
        private _key = _ctrl lbData _idx;
        private _map = _d getVariable ["COMSPEC_ArsenalCollapsedCloud", createHashMap];
        _map set [_key, !(_map getOrDefault [_key, true])];
        _d setVariable ["COMSPEC_ArsenalCollapsedCloud", _map];
        _g setVariable ["COMSPEC_ArsenalUiLock", true];
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
        for "_i" from 0 to ((lbSize _ctrl) - 1) do {
            if ((_ctrl lbValue _i) == 0 && {(_ctrl lbData _i) isEqualTo _key}) exitWith {
                _ctrl lbSetCurSel _i;
            };
        };
        _g setVariable ["COMSPEC_ArsenalUiLock", false];
        [_g, format ["Collection communauté « %1 » — cliquez une tenue pour l’aperçu, double-clic pour l’enfiler.", _key]] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
    };
    if (_kind != 2) exitWith {};
    private _id = _ctrl lbData _idx;
    if (_id isEqualTo "") exitWith {};
    [_ctrl, _idx, _id] spawn {
        params ["_ctrl", "_idx", "_id"];
        private _loadout = [_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        private _name = _ctrl lbText _idx;
        [_d, _loadout, format ["Aperçu — %1", _name]] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;
        if (!isNull _g) then {
            [_g, "Tenue communauté sélectionnée — Importer cette tenue, ou Retirer de la communauté si c’est la vôtre."] call (_g getVariable ["COMSPEC_ArsenalSetHint", {}]);
        };
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
    if ((_ctrl lbValue _idx) != 2) exitWith {};
    private _data = _ctrl lbData _idx;
    if (_data isEqualTo "") exitWith {};
    [_data] spawn {
        params ["_id"];
        [_id] call comspec_overwatch_connect_fnc_arsenalApplyCloud;
    };
}];
_listCloud ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalList", _listCloud];

private _half = (_colW - 0.006) / 2;
private _btnPushOne = _display ctrlCreate ["RscButton", 884402, _grp];
_btnPushOne ctrlSetPosition [_leftX, _actY, _half, _actH];
_btnPushOne ctrlSetText "Partager cette tenue";
_btnPushOne ctrlSetTooltip "Enregistre la tenue sélectionnée à gauche pour la communauté.";
_btnPushOne ctrlSetFont "PuristaMedium";
_btnPushOne ctrlSetFontHeight (_guiH * 0.64);
_btnPushOne ctrlSetBackgroundColor [0.10, 0.28, 0.17, 1];
_btnPushOne ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        if (isNull _g) exitWith {};
        private _list = _g getVariable ["COMSPEC_ArsenalLocalList", controlNull];
        private _idx = if (isNull _list) then { -1 } else { lbCurSel _list };
        if (_idx < 0 || {(_list lbValue _idx) != 1}) exitWith {
            ["Ouvrez une collection, choisissez une tenue, puis Partager cette tenue.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        private _name = _list lbData _idx;
        if (_name isEqualTo "") exitWith {
            ["Ouvrez une collection, choisissez une tenue, puis Partager cette tenue.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        [[_name]] call comspec_overwatch_connect_fnc_arsenalPushAll;
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
    };
}];
_btnPushOne ctrlCommit 0;

private _btnPushAll = _display ctrlCreate ["RscButton", 884406, _grp];
_btnPushAll ctrlSetPosition [_leftX + _half + 0.006, _actY, _half, _actH];
_btnPushAll ctrlSetText "Partager toutes";
_btnPushAll ctrlSetTooltip "Enregistre toutes vos tenues locales pour la communauté.";
_btnPushAll ctrlSetFont "PuristaMedium";
_btnPushAll ctrlSetFontHeight (_guiH * 0.64);
_btnPushAll ctrlSetBackgroundColor [0.08, 0.22, 0.14, 1];
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
_btnPullOne ctrlSetText "Importer cette tenue";
_btnPullOne ctrlSetTooltip "Ajoute la tenue sélectionnée à droite dans votre arsenal.";
_btnPullOne ctrlSetFont "PuristaMedium";
_btnPullOne ctrlSetFontHeight (_guiH * 0.64);
_btnPullOne ctrlSetBackgroundColor [0.12, 0.20, 0.34, 1];
_btnPullOne ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        if (isNull _g) exitWith {};
        private _list = _g getVariable ["COMSPEC_ArsenalList", controlNull];
        private _idx = if (isNull _list) then { -1 } else { lbCurSel _list };
        if (_idx < 0 || {(_list lbValue _idx) != 2}) exitWith {
            ["Ouvrez une collection, choisissez une tenue, puis Importer cette tenue.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        private _id = _list lbData _idx;
        if (_id isEqualTo "") exitWith {
            ["Ouvrez une collection, choisissez une tenue, puis Importer cette tenue.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        ["", [_id]] call comspec_overwatch_connect_fnc_arsenalPullAll;
        [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
    };
}];
_btnPullOne ctrlCommit 0;

private _btnPullAll = _display ctrlCreate ["RscButton", 884407, _grp];
_btnPullAll ctrlSetPosition [_rightX + _half + 0.006, _actY, _half, _actH];
_btnPullAll ctrlSetText "Importer toutes";
_btnPullAll ctrlSetTooltip "Ajoute dans cet arsenal toutes les tenues déjà enregistrées par la communauté.";
_btnPullAll ctrlSetFont "PuristaMedium";
_btnPullAll ctrlSetFontHeight (_guiH * 0.64);
_btnPullAll ctrlSetBackgroundColor [0.10, 0.16, 0.30, 1];
_btnPullAll ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_arsenalPullAll;
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh; };
    };
}];
_btnPullAll ctrlCommit 0;

private _delY = _actY + _actH + 0.005;
private _btnDelLocal = _display ctrlCreate ["RscButton", 884417, _grp];
_btnDelLocal ctrlSetPosition [_leftX, _delY, _colW, _actH];
_btnDelLocal ctrlSetText "Supprimer de mon arsenal";
_btnDelLocal ctrlSetTooltip "Retire la tenue choisie de votre arsenal, sur cet ordinateur uniquement.";
_btnDelLocal ctrlSetFont "PuristaMedium";
_btnDelLocal ctrlSetFontHeight (_guiH * 0.64);
_btnDelLocal ctrlSetBackgroundColor [0.30, 0.11, 0.11, 1];
_btnDelLocal ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        if (isNull _g) exitWith {};
        private _list = _g getVariable ["COMSPEC_ArsenalLocalList", controlNull];
        private _idx = if (isNull _list) then { -1 } else { lbCurSel _list };
        if (_idx < 0 || {(_list lbValue _idx) != 1}) exitWith {
            ["Ouvrez une collection et choisissez la tenue à supprimer de votre arsenal.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        private _name = _list lbData _idx;
        if (_name isEqualTo "") exitWith {};
        private _ok = [
            format ["Supprimer « %1 » de votre arsenal sur cet ordinateur ?", _name],
            "Tenues Athena",
            true,
            true,
            _d
        ] call BIS_fnc_guiMessage;
        if (!_ok) exitWith {};
        if ([_name] call comspec_overwatch_connect_fnc_arsenalDeleteLocal) then {
            [format ["« %1 » a été retirée de votre arsenal.", _name], "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
            [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
        } else {
            ["Cette tenue n’est plus dans votre arsenal.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
    };
}];
_btnDelLocal ctrlCommit 0;

private _btnDelCloud = _display ctrlCreate ["RscButton", 884418, _grp];
_btnDelCloud ctrlSetPosition [_rightX, _delY, _colW, _actH];
_btnDelCloud ctrlSetText "Retirer de la communauté";
_btnDelCloud ctrlSetTooltip "Retire de la communauté une tenue que vous avez partagée.";
_btnDelCloud ctrlSetFont "PuristaMedium";
_btnDelCloud ctrlSetFontHeight (_guiH * 0.64);
_btnDelCloud ctrlSetBackgroundColor [0.30, 0.11, 0.11, 1];
_btnDelCloud ctrlAddEventHandler ["ButtonClick", {
    [] spawn {
        private _d = uiNamespace getVariable ["ace_arsenal_display", displayNull];
        if (isNull _d) exitWith {};
        private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
        if (isNull _g) exitWith {};
        private _list = _g getVariable ["COMSPEC_ArsenalList", controlNull];
        private _idx = if (isNull _list) then { -1 } else { lbCurSel _list };
        if (_idx < 0 || {(_list lbValue _idx) != 2}) exitWith {
            ["Ouvrez une collection et choisissez la tenue à retirer de la communauté.", "arsenal", "info", true] call comspec_overwatch_connect_fnc_announce;
        };
        private _id = _list lbData _idx;
        private _label = _list lbText _idx;
        if (_id isEqualTo "") exitWith {};
        private _ok = [
            format ["Retirer « %1 » des tenues de la communauté ?", _label],
            "Tenues Athena",
            true,
            true,
            _d
        ] call BIS_fnc_guiMessage;
        if (!_ok) exitWith {};
        if ([_id] call comspec_overwatch_connect_fnc_arsenalDeleteCloud) then {
            ["La tenue a été retirée de la communauté.", "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
            [_d] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
        };
    };
}];
_btnDelCloud ctrlCommit 0;

private _previewBg = _display ctrlCreate ["RscText", -1, _grp];
_previewBg ctrlSetPosition [_pad, _h - _previewH - 0.006, _w - (_pad * 2), _previewH];
_previewBg ctrlSetBackgroundColor [0.018, 0.026, 0.032, 0.98];
_previewBg ctrlCommit 0;

private _previewAccent = _display ctrlCreate ["RscText", -1, _grp];
_previewAccent ctrlSetPosition [_pad, _h - _previewH - 0.006, 0.003, _previewH];
_previewAccent ctrlSetBackgroundColor [0.32, 0.78, 0.66, 0.85];
_previewAccent ctrlCommit 0;

private _picS = (((_w - 0.26) / 8) min 0.055) max 0.034;
private _picGap = 0.006;
private _picY = _h - _previewH + 0.004;
private _previewPics = [];
for "_i" from 0 to 7 do {
    private _pic = _display ctrlCreate ["RscPicture", 884408 + _i, _grp];
    private _px = _pad + 0.010 + (_i * (_picS + _picGap));
    _pic ctrlSetPosition [_px, _picY, _picS, _picS];
    _pic ctrlSetText "";
    _pic ctrlShow false;
    _pic ctrlCommit 0;
    _previewPics pushBack _pic;
};
_grp setVariable ["COMSPEC_ArsenalPreviewPics", _previewPics];

private _names = _display ctrlCreate ["RscStructuredText", 884414, _grp];
_names ctrlSetPosition [_pad + 0.008, _picY + _picS + 0.002, _w - (_pad * 2) - 0.014, (_previewH - _picS - 0.014) max 0.026];
_names ctrlSetStructuredText parseText "<t size='0.88' color='#9bb0aa'>Sélectionnez une tenue pour afficher l’équipement (arme, tenue, gilet, casque, sac…).</t>";
_names ctrlCommit 0;
_grp setVariable ["COMSPEC_ArsenalPreviewNames", _names];
