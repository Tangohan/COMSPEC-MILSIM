params ["_group",["_interfaceInit",false],"_isDialog","_settings"];
_settings params ["_page","","",["_PgComponents",createHashMap]];

private _switch_btn = _group controlsGroupCtrl 5;
private _ListGroup = _group controlsGroupCtrl 10;
private _ViewGroup = _group controlsGroupCtrl 20;

private _commitTime = {[_this, 0] select _interfaceInit};
private _hcam = ["cTab_Android_dlg", "hcam"] call cTab_fnc_getSettings;

private _PG_data = _PgComponents getOrDefault [_page,[]];
_PG_data params ["_line", ["_SubSel", parseNumber (_hcam != "")]];

private _ctrl_TrackTG = _ViewGroup controlsGroupCtrl 11;
private _ctrl_TrackInfo = _ViewGroup controlsGroupCtrl 12;
private _ctrl_Vision = _ViewGroup controlsGroupCtrl 13;
private _ctrl_Sync = _ViewGroup controlsGroupCtrl 14;
private _ctrl_View = _ViewGroup controlsGroupCtrl 46310;
private _ctrl_Turret = _ViewGroup controlsGroupCtrl 46320;
private _ctrl_PIP = _ViewGroup controlsGroupCtrl 4632;

private _display = ctrlParent _group;
private _buttonGroup = _display displayCtrl 46600;
private _bottomBack = _buttonGroup controlsGroupCtrl 10;
private _bottomTurret = _buttonGroup controlsGroupCtrl 11;
private _fullButton = _ViewGroup getVariable ["Iceman_ATAK_FullFeedButton", controlNull];
if (isNull _fullButton) then {
    _fullButton = _display ctrlCreate ["ctrlButton", -1, _ViewGroup];
    _ViewGroup setVariable ["Iceman_ATAK_FullFeedButton", _fullButton];
};
private _mapButton = _buttonGroup getVariable ["Iceman_ATAK_MapFeedButton", controlNull];
if (isNull _mapButton) then {
    _mapButton = _display ctrlCreate ["ctrlButton", -1, _buttonGroup];
    _buttonGroup setVariable ["Iceman_ATAK_MapFeedButton", _mapButton];
};
private _fullActionButton = _buttonGroup getVariable ["Iceman_ATAK_FullFeedActionButton", controlNull];
if (isNull _fullActionButton) then {
    _fullActionButton = _display ctrlCreate ["ctrlButton", -1, _buttonGroup];
    _buttonGroup setVariable ["Iceman_ATAK_FullFeedActionButton", _fullActionButton];
};
private _zoomOutButton = _ViewGroup getVariable ["Iceman_ATAK_ZoomOutButton", controlNull];
if (isNull _zoomOutButton) then {
    _zoomOutButton = _display ctrlCreate ["ctrlButton", -1, _ViewGroup];
    _ViewGroup setVariable ["Iceman_ATAK_ZoomOutButton", _zoomOutButton];
};
private _zoomInButton = _ViewGroup getVariable ["Iceman_ATAK_ZoomInButton", controlNull];
if (isNull _zoomInButton) then {
    _zoomInButton = _display ctrlCreate ["ctrlButton", -1, _ViewGroup];
    _ViewGroup setVariable ["Iceman_ATAK_ZoomInButton", _zoomInButton];
};
_fullButton ctrlRemoveAllEventHandlers "ButtonClick";
_fullButton ctrlAddEventHandler ["ButtonClick", {
    [_this # 0, 5] call Iceman_fnc_ATAK_Camera_Controls;
}];
_fullButton ctrlSetBackgroundColor [0.05,0.07,0.08,0.92];
_fullButton ctrlSetTextColor [1,1,1,1];
_fullActionButton ctrlRemoveAllEventHandlers "ButtonClick";
_fullActionButton ctrlAddEventHandler ["ButtonClick", {
    [_this # 0, 5] call Iceman_fnc_ATAK_Camera_Controls;
}];
_fullActionButton ctrlSetBackgroundColor [0.05,0.07,0.08,0.92];
_fullActionButton ctrlSetTextColor [1,1,1,1];
_mapButton ctrlRemoveAllEventHandlers "ButtonClick";
_mapButton ctrlAddEventHandler ["ButtonClick", {
    [_this # 0, 8] call Iceman_fnc_ATAK_Camera_Controls;
}];
_mapButton ctrlSetBackgroundColor [0.05,0.07,0.08,0.92];
_mapButton ctrlSetTextColor [1,1,1,1];
_zoomOutButton ctrlRemoveAllEventHandlers "ButtonClick";
_zoomOutButton ctrlAddEventHandler ["ButtonClick", {
    [_this # 0, 7] call Iceman_fnc_ATAK_Camera_Controls;
}];
_zoomOutButton ctrlSetText "ZOOM -";
_zoomOutButton ctrlSetBackgroundColor [0.05,0.07,0.08,0.92];
_zoomOutButton ctrlSetTextColor [1,1,1,1];
_zoomInButton ctrlRemoveAllEventHandlers "ButtonClick";
_zoomInButton ctrlAddEventHandler ["ButtonClick", {
    [_this # 0, 6] call Iceman_fnc_ATAK_Camera_Controls;
}];
_zoomInButton ctrlSetText "ZOOM +";
_zoomInButton ctrlSetBackgroundColor [0.05,0.07,0.08,0.92];
_zoomInButton ctrlSetTextColor [1,1,1,1];

private _subMenu = _line > 0;
if (_subMenu) then {
    uiNamespace setVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
    uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", false];
    call Iceman_fnc_ATAK_resetFullFeedLayout;
    _fullButton ctrlShow false;
    _mapButton ctrlShow false;
    _fullActionButton ctrlShow false;
    _zoomOutButton ctrlShow false;
    _zoomInButton ctrlShow false;
};

_ListGroup ctrlEnable _subMenu;
_ViewGroup ctrlEnable !_subMenu;

_ListGroup ctrlSetPositionH ([0, (ctrlPosition _group) # 3] select _subMenu);
_ListGroup ctrlSetFade ([1,0] select _subMenu);
_ListGroup ctrlCommit (0.3 call _commitTime);

{
    _x ctrlSetFade ([0,1] select _subMenu);
    _x ctrlCommit ((0.08 * (1 max _forEachIndex)) call _commitTime);
} forEach allControls _ViewGroup;

if (_subMenu) exitWith {
    private _toolbox = _ListGroup controlsGroupCtrl 6;
    private _ls = _ListGroup controlsGroupCtrl 7;

    _toolbox ctrlRemoveAllEventHandlers "ToolBoxSelChanged";
    _ls ctrlRemoveAllEventHandlers "LBSelChanged";

    [_ls,_SubSel] call BCE_fnc_cTab_CreateCameraList;
    _toolbox lbSetCurSel _SubSel;

    _toolbox ctrlAddEventHandler ["ToolBoxSelChanged", {
        [_this # 0,3,_this # 1] call BCE_fnc_ATAK_Camera_Controls
    }];
    _ls ctrlAddEventHandler ["LBSelChanged", {
        [_this # 0,4,_this # 1] call BCE_fnc_ATAK_Camera_Controls
    }];

    _switch_btn ctrlSetStructuredText parseText localize "STR_BCE_Select_Camera";
    _ctrl_View ctrlRemoveAllEventHandlers "MouseEnter";
    _ctrl_View ctrlRemoveAllEventHandlers "MouseExit";
};

private _veh = [
    cTab_player,
    "AIR" call BCE_fnc_get_TaskCateIndex
] call BCE_fnc_get_TaskCurUnit;

private _isHcam = _SubSel == 1;
private _displayOn = (
    !isNull (uiNamespace getVariable ["BCE_HCAM_View",displayNull]) ||
    ((player getVariable ["TGP_View_EHs", -1]) != -1)
);

call {
    if (_isHcam) exitWith {
        _ctrl_Turret ctrlSetText localize "STR_BCE_Helmet_CAM";
        call cTab_fnc_deleteUAVcam;
        player setVariable ["TGP_View_Selected_Optic",[[],objNull],true];
        _veh = ["rendertarget9",_hcam, !_displayOn] call cTab_fnc_createHelmetCam;
    };
    if (!isNull _veh && !_displayOn) exitWith {
        call cTab_fnc_deleteHelmetCam;
        [_veh,[[1,"rendertarget9"]],false] call cTab_fnc_createUavCam;
    };
};

private _null_Connected = isNull _veh;
private _canShowFeed = (!_null_Connected || _isHcam) && !_displayOn;
if (!_canShowFeed) then {
    uiNamespace setVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
    uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", false];
    [false] call Iceman_fnc_ATAK_setMapFeedOverlay;
};
if (_canShowFeed) then {
    [0] call Iceman_fnc_ATAK_applyCameraZoom;
};

_ctrl_PIP ctrlShow _canShowFeed;
_ctrl_View ctrlEnable (!_null_Connected && !_displayOn);
{_x ctrlEnable (!_null_Connected && !_isHcam && !_displayOn)} count [_ctrl_TrackTG,_ctrl_Vision,_ctrl_Sync,_ctrl_Turret];

private _title = if (_null_Connected) then {
    _ctrl_Turret ctrlSetText "- -";
    _ctrl_View ctrlSetText localize "STR_BCE_No_Signal";

    if (_isDialog) then {
        _ctrl_View ctrlSetFade 0;
        _ctrl_View ctrlCommit (0.2 call _commitTime);
        _ctrl_View ctrlRemoveAllEventHandlers "MouseEnter";
        _ctrl_View ctrlRemoveAllEventHandlers "MouseExit";
    } else {
        _ctrl_View ctrlSetBackgroundColor [0,0,0,0.08];
    };

    "  - - <img image='\z\BCE\addons\Core\data\ExpandList.paa'/>"
} else {
    _ctrl_TrackTG ctrlSetBackgroundColor ([[0.5,0,0,0.3],[0,0,0.5,0.3]] select (uiNamespace getVariable ['BCE_ATAK_TRACK_Focus',false]));

    if (_isDialog) then {
        _ctrl_View ctrlSetText localize "STR_BCE_Live_Feed";
        _ctrl_View ctrlSetFade 1;
        _ctrl_View ctrlCommit 0;
        _ctrl_View ctrlAddEventHandler ["MouseEnter", {(_this # 0) ctrlSetFade 0.5; (_this # 0) ctrlCommit 0.2;}];
        _ctrl_View ctrlAddEventHandler ["MouseExit", {(_this # 0) ctrlSetFade 1; (_this # 0) ctrlCommit 0.2;}];
    } else {
        _ctrl_View ctrlSetText "";
        _ctrl_View ctrlSetBackgroundColor [0,0,0,0];
    };

    [groupId group _veh, [_veh] call CBA_fnc_getGroupIndex] joinString " : "
};

_switch_btn ctrlSetStructuredText parseText _title;
_ctrl_TrackInfo ctrlSetText localize "STR_BCE_None";

[_ctrl_TrackTG,0,false] call BCE_fnc_ATAK_Camera_Controls;
[_ctrl_Vision,1,false] call BCE_fnc_ATAK_Camera_Controls;
[_ctrl_Sync,2,false] call BCE_fnc_ATAK_Camera_Controls;

private _storePos = {
    params ["_ctrl"];
    if (!isNull _ctrl && {(_ctrl getVariable ["Iceman_ATAK_FullFeed_origPos", []]) isEqualTo []}) then {
        _ctrl setVariable ["Iceman_ATAK_FullFeed_origPos", ctrlPosition _ctrl];
    };
};

{
    [_x] call _storePos;
} forEach [_group, _ViewGroup, _ListGroup, _switch_btn, _ctrl_TrackTG, _ctrl_TrackInfo, _ctrl_Vision, _ctrl_Sync, _ctrl_Turret, _ctrl_PIP, _ctrl_View, _fullButton, _mapButton, _fullActionButton, _bottomBack, _bottomTurret];
{
    [_x] call _storePos;
} forEach [_zoomOutButton, _zoomInButton];

private _toolMenu = _display displayCtrl (17000 + 4650);
[_toolMenu] call _storePos;

private _fullMode = (uiNamespace getVariable ["BCE_ATAK_VideoFeed_FullATAK", false]) && {!_null_Connected || _isHcam} && {!_displayOn};
private _groupOrig = _group getVariable ["Iceman_ATAK_FullFeed_origPos", ctrlPosition _group];
private _viewOrig = _ViewGroup getVariable ["Iceman_ATAK_FullFeed_origPos", ctrlPosition _ViewGroup];
private _pipOrig = _ctrl_PIP getVariable ["Iceman_ATAK_FullFeed_origPos", ctrlPosition _ctrl_PIP];
private _toolOrig = _toolMenu getVariable ["Iceman_ATAK_FullFeed_origPos", ctrlPosition _toolMenu];

if (_fullMode) then {
    uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", false];
    [false] call Iceman_fnc_ATAK_setMapFeedOverlay;
    if (!isNull _buttonGroup) then {
        _buttonGroup ctrlShow false;
    };

    private _fullW = (_groupOrig # 2) * 2.5;
    private _fullH = _groupOrig # 3;

    _toolMenu ctrlSetPosition [(_toolOrig # 0) - ((_toolOrig # 2) * 1.5), _toolOrig # 1, (_toolOrig # 2) * 2.5, _toolOrig # 3];
    _toolMenu ctrlCommit 0;

    _group ctrlSetPosition [0, 0, _fullW, _fullH];
    _group ctrlCommit 0;
    _ViewGroup ctrlSetPosition [0, 0, _fullW, _fullH];
    _ViewGroup ctrlCommit 0;

    _switch_btn ctrlShow false;
    _ListGroup ctrlShow false;
    {
        _x ctrlShow false;
    } forEach [_ctrl_TrackTG, _ctrl_TrackInfo, _ctrl_Vision, _ctrl_Sync, _ctrl_Turret];

    _ctrl_PIP ctrlSetPosition [0, 0, _fullW, _fullH];
    _ctrl_PIP ctrlCommit 0;
    _ctrl_View ctrlSetPosition [0, 0, _fullW, _fullH];
    _ctrl_View ctrlCommit 0;

    private _buttonW = _fullW * 0.16;
    private _buttonH = _fullH * 0.06;
    private _zoomW = _fullW * 0.12;
    _fullButton ctrlSetText "NORMAL";
    _fullButton ctrlSetPosition [_fullW - _buttonW, 0, _buttonW, _buttonH];
    _fullButton ctrlShow true;
    _fullButton ctrlSetFade 0;
    _fullButton ctrlCommit 0;
    _mapButton ctrlShow false;
    _fullActionButton ctrlShow false;
    _zoomOutButton ctrlSetPosition [0, 0, _zoomW, _buttonH];
    _zoomOutButton ctrlShow true;
    _zoomOutButton ctrlSetFade 0;
    _zoomOutButton ctrlCommit 0;
    _zoomInButton ctrlSetPosition [_zoomW, 0, _zoomW, _buttonH];
    _zoomInButton ctrlShow true;
    _zoomInButton ctrlSetFade 0;
    _zoomInButton ctrlCommit 0;
} else {
    call Iceman_fnc_ATAK_resetFullFeedLayout;
    if (!isNull _buttonGroup) then {
        _buttonGroup ctrlShow true;
    };

    {
        private _orig = _x getVariable ["Iceman_ATAK_FullFeed_origPos", []];
        if !(_orig isEqualTo []) then {
            _x ctrlSetPosition _orig;
            _x ctrlCommit 0;
        };
        _x ctrlShow true;
    } forEach [_group, _ViewGroup, _ListGroup, _switch_btn, _ctrl_TrackTG, _ctrl_TrackInfo, _ctrl_Vision, _ctrl_Sync, _ctrl_Turret, _ctrl_PIP, _ctrl_View];

    private _buttonH = (_pipOrig # 3) * 0.10;
    private _zoomW = (_pipOrig # 2) * 0.22;
    private _barPos = ctrlPosition _buttonGroup;
    private _barW = _barPos # 2;
    private _barH = _barPos # 3;
    private _slotW = _barW / 4;
    private _mapMode = (uiNamespace getVariable ["BCE_ATAK_VideoFeed_MapATAK", false]) && _canShowFeed;
    uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", _mapMode];
    _mapButton ctrlSetText (["MAP CAM", "MAP OFF"] select _mapMode);
    _mapButton ctrlSetPosition [_slotW, 0, _slotW, _barH];
    _mapButton ctrlShow true;
    _mapButton ctrlEnable _canShowFeed;
    _mapButton ctrlSetFade 0;
    _mapButton ctrlCommit 0;
    _fullActionButton ctrlSetText "FULL ATAK";
    _fullActionButton ctrlSetPosition [_slotW * 2, 0, _slotW, _barH];
    _fullActionButton ctrlShow true;
    _fullActionButton ctrlEnable _canShowFeed;
    _fullActionButton ctrlSetFade 0;
    _fullActionButton ctrlCommit 0;
    _fullButton ctrlShow false;
    if (!isNull _bottomBack) then {
        _bottomBack ctrlSetPosition [0, 0, _slotW, _barH];
        _bottomBack ctrlCommit 0;
    };
    if (!isNull _bottomTurret) then {
        _bottomTurret ctrlSetPosition [_slotW * 3, 0, _slotW, _barH];
        _bottomTurret ctrlCommit 0;
    };
    _zoomOutButton ctrlSetPosition [_pipOrig # 0, _pipOrig # 1, _zoomW, _buttonH];
    _zoomOutButton ctrlShow _canShowFeed;
    _zoomOutButton ctrlSetFade 0;
    _zoomOutButton ctrlCommit 0;
    _zoomInButton ctrlSetPosition [(_pipOrig # 0) + _zoomW, _pipOrig # 1, _zoomW, _buttonH];
    _zoomInButton ctrlShow _canShowFeed;
    _zoomInButton ctrlSetFade 0;
    _zoomInButton ctrlCommit 0;

    [_mapMode] call Iceman_fnc_ATAK_setMapFeedOverlay;
};
