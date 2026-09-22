/*
    Fiche du bâtiment désigné : nom, étage, curseur — sur la carte du téléphone.
*/
if (!hasInterface) exitWith { false };

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (isNull _disp) exitWith { false };

private _bldg = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
private _cutaway = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
if (!(_cutaway isEqualType true)) then { _cutaway = false; };

private _fnc_hide = {
    {
        private _c = uiNamespace getVariable [_x, controlNull];
        if (!isNull _c) then {
            _c ctrlShow false;
            _c ctrlCommit 0;
        };
    } forEach [
        "COMSPEC_EcotiSheet_Bg",
        "COMSPEC_EcotiSheet_Body",
        "COMSPEC_EcotiSheet_Slider",
        "COMSPEC_EcotiSheet_Minus",
        "COMSPEC_EcotiSheet_Plus"
    ];
};

if (isNull _bldg) exitWith {
    call _fnc_hide;
    false
};

private _mapCtrl = controlNull;
if (!isNil "cTab_fnc_getSettings" && {!isNil "cTab_fnc_getFromPairs"}) then {
    private _mapName = ["cTab_Android_dlg", "mapType"] call cTab_fnc_getSettings;
    private _mapTypes = ["cTab_Android_dlg", "mapTypes"] call cTab_fnc_getSettings;
    private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
    if (_mapIdc isEqualType 0) then {
        _mapCtrl = _disp displayCtrl _mapIdc;
        if (isNull _mapCtrl) then { _mapCtrl = _disp displayCtrl (17000 + _mapIdc); };
    };
};
if (isNull _mapCtrl) then {
    {
        private _c = _disp displayCtrl _x;
        if (!isNull _c && {ctrlShown _c}) exitWith { _mapCtrl = _c; };
    } forEach [1201, 1202, 16, 18201, 18202, 17016];
};
if (isNull _mapCtrl || {!ctrlShown _mapCtrl}) exitWith {
    call _fnc_hide;
    false
};

(ctrlPosition _mapCtrl) params ["_mx", "_my", "_mw", "_mh"];
private _pad = 0.006;
private _w = (_mw * 0.46) min 0.22;
private _h = 0.078;
private _x0 = _mx + _pad;
private _y0 = _my + _pad;

private _fnc_ctrl = {
    params ["_key", "_class", "_idc"];
    private _c = uiNamespace getVariable [_key, controlNull];
    if (isNull _c || {ctrlParent _c isNotEqualTo _disp}) then {
        if (!isNull _c) then { ctrlDelete _c; };
        _c = _disp ctrlCreate [_class, _idc];
        uiNamespace setVariable [_key, _c];
    };
    _c
};

private _bg = ["COMSPEC_EcotiSheet_Bg", "RscText", 99880] call _fnc_ctrl;
private _body = ["COMSPEC_EcotiSheet_Body", "RscStructuredText", 99881] call _fnc_ctrl;
private _slider = ["COMSPEC_EcotiSheet_Slider", "RscXSliderH", 99882] call _fnc_ctrl;
if (isNull _slider) then {
    _slider = ["COMSPEC_EcotiSheet_Slider", "RscSlider", 99882] call _fnc_ctrl;
};
private _minus = uiNamespace getVariable ["COMSPEC_EcotiSheet_Minus", controlNull];
private _plus = uiNamespace getVariable ["COMSPEC_EcotiSheet_Plus", controlNull];
if (isNull _minus || {ctrlParent _minus isNotEqualTo _disp}) then {
    if (!isNull _minus) then { ctrlDelete _minus; };
    _minus = controlNull;
    {
        if (!isNull _minus) then { continue };
        _minus = _disp ctrlCreate [_x, 99883];
    } forEach ["RscButton", "RscButtonMenu", "BCE_RscButtonMenu"];
    uiNamespace setVariable ["COMSPEC_EcotiSheet_Minus", _minus];
};
if (isNull _plus || {ctrlParent _plus isNotEqualTo _disp}) then {
    if (!isNull _plus) then { ctrlDelete _plus; };
    _plus = controlNull;
    {
        if (!isNull _plus) then { continue };
        _plus = _disp ctrlCreate [_x, 99884];
    } forEach ["RscButton", "RscButtonMenu", "BCE_RscButtonMenu"];
    uiNamespace setVariable ["COMSPEC_EcotiSheet_Plus", _plus];
};

if (isNull _bg || {isNull _body}) exitWith { false };

private _floors = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloorCount", 1];
if (!(_floors isEqualType 0) || {_floors < 1}) then { _floors = 1; };
_floors = (round _floors) max 1;
private _sel = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
if (!(_sel isEqualType 0)) then { _sel = 0; };
_sel = (round _sel) max 0 min (_floors - 1);

private _dn = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuildingName", ""];
if (!(_dn isEqualType "") || {_dn isEqualTo ""}) then {
    _dn = getText (configFile >> "CfgVehicles" >> typeOf _bldg >> "displayName");
};
if (_dn isEqualTo "") then { _dn = "Bâtiment"; };

_bg ctrlSetPosition [_x0, _y0, _w, _h];
_bg ctrlSetBackgroundColor [0.04, 0.07, 0.08, 0.86];
_bg ctrlEnable false;
_bg ctrlShow true;
_bg ctrlCommit 0;

private _rowH = _h * 0.38;
_body ctrlSetPosition [_x0 + 0.004, _y0 + 0.002, _w - 0.008, _rowH];
_body ctrlSetBackgroundColor [0, 0, 0, 0];
_body ctrlSetStructuredText parseText format [
    "<t size='0.58' shadow='0'><t color='#7CFF9A'>%1</t><br/><t color='%2'>%3</t></t>",
    _dn,
    if (_cutaway) then { "#FFE08A" } else { "#A0A0A0" },
    if (_cutaway) then {
        format ["Étage %1 / %2", _sel + 1, _floors]
    } else {
        "Activez le découpage d’étage"
    }
];
_body ctrlEnable false;
_body ctrlShow true;
_body ctrlCommit 0;

private _btnW = 0.018;
private _btnH = _h * 0.42;
private _btnY = _y0 + _h - _btnH - 0.004;
if (!isNull _minus) then {
    _minus ctrlSetPosition [_x0 + 0.004, _btnY, _btnW, _btnH];
    _minus ctrlSetText "−";
    _minus ctrlSetTooltip "Étage inférieur";
    _minus ctrlSetTextColor [1, 1, 1, 1];
    _minus ctrlSetBackgroundColor [0.12, 0.16, 0.18, 1];
    _minus ctrlEnable _cutaway;
    _minus ctrlShow true;
    _minus ctrlRemoveAllEventHandlers "ButtonClick";
    _minus ctrlAddEventHandler ["ButtonClick", {
        private _floors = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloorCount", 1];
        if (!(_floors isEqualType 0) || {_floors < 1}) then { _floors = 1; };
        private _sel = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
        if (!(_sel isEqualType 0)) then { _sel = 0; };
        [((_sel - 1) max 0), false] call comspec_overwatch_connect_fnc_ecotiSetFloor;
    }];
    _minus ctrlCommit 0;
};
if (!isNull _plus) then {
    _plus ctrlSetPosition [_x0 + _w - _btnW - 0.004, _btnY, _btnW, _btnH];
    _plus ctrlSetText "+";
    _plus ctrlSetTooltip "Étage supérieur";
    _plus ctrlSetTextColor [1, 1, 1, 1];
    _plus ctrlSetBackgroundColor [0.12, 0.16, 0.18, 1];
    _plus ctrlEnable _cutaway;
    _plus ctrlShow true;
    _plus ctrlRemoveAllEventHandlers "ButtonClick";
    _plus ctrlAddEventHandler ["ButtonClick", {
        private _floors = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloorCount", 1];
        if (!(_floors isEqualType 0) || {_floors < 1}) then { _floors = 1; };
        private _sel = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
        if (!(_sel isEqualType 0)) then { _sel = 0; };
        [((_sel + 1) min ((_floors - 1) max 0)), false] call comspec_overwatch_connect_fnc_ecotiSetFloor;
    }];
    _plus ctrlCommit 0;
};

if (!isNull _slider) then {
    private _sx = _x0 + _btnW + 0.008;
    private _sw = _w - (_btnW * 2) - 0.016;
    _slider ctrlSetPosition [_sx, _btnY, _sw, _btnH];
    missionNamespace setVariable ["COMSPEC_AtakEcotiFloorFilling", true, false];
    _slider sliderSetRange [0, (_floors - 1) max 0];
    _slider sliderSetPosition _sel;
    _slider sliderSetSpeed [1, 1];
    _slider ctrlSetTooltip "Choisir l’étage du découpage";
    _slider ctrlEnable (_cutaway && {_floors > 1});
    _slider ctrlShow true;
    _slider ctrlRemoveAllEventHandlers "SliderPosChanged";
    _slider ctrlAddEventHandler ["SliderPosChanged", {
        params ["_ctrl", "_pos"];
        if (missionNamespace getVariable ["COMSPEC_AtakEcotiFloorFilling", false]) exitWith {};
        [round _pos, false] call comspec_overwatch_connect_fnc_ecotiSetFloor;
    }];
    missionNamespace setVariable ["COMSPEC_AtakEcotiFloorFilling", false, false];
    _slider ctrlCommit 0;
};

true
