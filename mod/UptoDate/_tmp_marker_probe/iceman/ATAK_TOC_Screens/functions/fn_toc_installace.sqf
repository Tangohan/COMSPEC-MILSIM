if (missionNamespace getVariable ["Iceman_TOC_aceInstalled", false]) exitWith {};
if (isNil "ace_interact_menu_fnc_createAction" || {isNil "ace_interact_menu_fnc_addActionToClass"}) exitWith {};

missionNamespace setVariable ["Iceman_TOC_aceInstalled", true];

private _action = [
    "Iceman_TOC_ScreenFeed",
    "TOC Video Feed",
    "",
    {
        params ["_target", "_player"];
        [_target, _player] call Iceman_fnc_toc_openDialog;
    },
    {
        params ["_target", "_player"];
        [_target] call Iceman_fnc_toc_isScreenCandidate
    }
] call ace_interact_menu_fnc_createAction;
_action resize 11;

{
    if (isClass (configFile >> "CfgVehicles" >> _x)) then {
        [_x, 0, ["ACE_MainActions"], _action, true] call ace_interact_menu_fnc_addActionToClass;
    };
} forEach [
    "Land_PCSet_01_screen_F",
    "Land_Laptop_F",
    "Land_Laptop_unfolded_F",
    "Land_Laptop_device_F",
    "Land_Laptop_02_unfolded_F",
    "Land_Laptop_03_black_F",
    "Land_Laptop_03_sand_F",
    "Land_Tablet_02_F",
    "Land_FlatTV_01_F",
    "Land_FlatTV_01_sand_F",
    "Land_FlatTV_01_black_F",
    "Land_Projector_01_F",
    "Land_ProjectorScreen_01_F",
    "Land_TripodScreen_01_large_VIDEO_F",
    "TripodScreen_01_large_VIDEO_placeholder",
    "Land_TripodScreen_01_large_black_F",
    "Land_TripodScreen_01_dual_v1_black_F",
    "Land_TripodScreen_01_dual_v2_black_F",
    "Land_TripodScreen_01_large_F",
    "Land_TripodScreen_01_dual_v1_F",
    "Land_TripodScreen_01_dual_v2_F",
    "Land_TripodScreen_01_large_sand_F",
    "TripodScreen_CTRG_large_01",
    "TripodScreen_CTRG_large_02",
    "TripodScreen_CTRG_large_03",
    "TripodScreen_CTRG_large_04",
    "TripodScreen_CTRG_large_05",
    "Land_DataTerminal_01_F",
    "Land_InfoStand_V1_F",
    "Land_InfoStand_V2_F",
    "Land_MapBoard_F",
    "Land_MapBoard_01_F",
    "Land_MapBoard_01_Wall_F"
];

private _selfAction = [
    "Iceman_TOC_ScreenFeed_Self",
    "Configure TOC Screen",
    "",
    {
        private _target = cursorObject;
        if (isNull _target) then {
            _target = cursorTarget;
        };
        [_target, player] call Iceman_fnc_toc_openDialog;
    },
    {
        private _target = cursorObject;
        if (isNull _target) then {
            _target = cursorTarget;
        };
        !isNull _target && {[_target] call Iceman_fnc_toc_isScreenCandidate}
    }
] call ace_interact_menu_fnc_createAction;
_selfAction resize 11;

["CAManBase", 1, ["ACE_SelfActions"], _selfAction, true] call ace_interact_menu_fnc_addActionToClass;

private _viewDeviceAction = [
    "Iceman_TOC_ViewDevice_Open",
    "Open TOC View Device",
    "",
    {
        call Iceman_fnc_toc_openViewDevice;
    },
    {
        params ["_target", "_player"];
        "Iceman_TOC_ViewDevice" in ((items _player) + (assignedItems _player))
    }
] call ace_interact_menu_fnc_createAction;
_viewDeviceAction resize 11;

["CAManBase", 1, ["ACE_SelfActions"], _viewDeviceAction, true] call ace_interact_menu_fnc_addActionToClass;
