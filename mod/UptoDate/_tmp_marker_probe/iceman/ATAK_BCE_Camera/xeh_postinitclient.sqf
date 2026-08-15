if (!hasInterface) exitWith {};

private _videoInit = compileFinal preprocessFileLineNumbers "\ATAK_BCE_Camera\functions\fn_ATAK_VideoFeeds_Init.sqf";
private _cameraControls = compileFinal preprocessFileLineNumbers "\ATAK_BCE_Camera\functions\fn_ATAK_Camera_Controls.sqf";
private _resetLayout = compileFinal preprocessFileLineNumbers "\ATAK_BCE_Camera\functions\fn_resetFullFeedLayout.sqf";
private _applyZoom = compileFinal preprocessFileLineNumbers "\ATAK_BCE_Camera\functions\fn_applyCameraZoom.sqf";
private _setMapFeedOverlay = compileFinal preprocessFileLineNumbers "\ATAK_BCE_Camera\functions\fn_setMapFeedOverlay.sqf";
private _cameraKeyAction = compileFinal preprocessFileLineNumbers "\ATAK_BCE_Camera\functions\fn_cameraKeyAction.sqf";

missionNamespace setVariable ["Iceman_fnc_ATAK_VideoFeeds_Init", _videoInit];
missionNamespace setVariable ["Iceman_fnc_ATAK_Camera_Controls", _cameraControls];
missionNamespace setVariable ["Iceman_fnc_ATAK_resetFullFeedLayout", _resetLayout];
missionNamespace setVariable ["Iceman_fnc_ATAK_applyCameraZoom", _applyZoom];
missionNamespace setVariable ["Iceman_fnc_ATAK_setMapFeedOverlay", _setMapFeedOverlay];
missionNamespace setVariable ["Iceman_fnc_ATAK_cameraKeyAction", _cameraKeyAction];

uiNamespace setVariable ["Iceman_fnc_ATAK_VideoFeeds_Init", _videoInit];
uiNamespace setVariable ["Iceman_fnc_ATAK_Camera_Controls", _cameraControls];

[
    "Iceman ATAK",
    "cameraFeedList",
    ["Camera: Feed List", "Open or close the Video Feeds camera selection list."],
    {["feedList"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraMapCam",
    ["Camera: Map Cam", "Replace the ATAK map area with the selected camera feed."],
    {["map"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraFullATAK",
    ["Camera: Full ATAK", "Expand the selected camera feed over the full ATAK screen."],
    {["full"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraZoomIn",
    ["Camera: Zoom In", "Zoom in the active ATAK camera feed."],
    {["zoomIn"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraZoomOut",
    ["Camera: Zoom Out", "Zoom out the active ATAK camera feed."],
    {["zoomOut"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraVision",
    ["Camera: Vision Mode", "Cycle the ATAK camera vision mode."],
    {["vision"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraSyncZoom",
    ["Camera: Sync Zoom", "Toggle ATAK camera zoom syncing."],
    {["sync"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraTrackPoint",
    ["Camera: Track Point", "Toggle ATAK camera track point mode."],
    {["track"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "cameraControlTurret",
    ["Camera: Control Turret", "Switch the controlled camera turret for the active ATAK feed."],
    {["turret"] call Iceman_fnc_ATAK_cameraKeyAction},
    "",
    [],
    false
] call cba_fnc_addKeybind;

if (isNil "Iceman_ATAK_BCE_Camera_fullFeedPFH") then {
    Iceman_ATAK_BCE_Camera_fullFeedPFH = [{
        private _fullActive = uiNamespace getVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
        private _mapActive = uiNamespace getVariable ["BCE_ATAK_VideoFeed_MapATAK", false];
        if (!_fullActive && !_mapActive) exitWith {};
        if (isNil "cTabIfOpen") exitWith {
            uiNamespace setVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
            uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", false];
            call Iceman_fnc_ATAK_resetFullFeedLayout;
        };

        private _settings = ["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings;
        private _page = _settings param [0, ""];
        if (_page != "VideoFeeds") exitWith {
            uiNamespace setVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
            uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", false];
            call Iceman_fnc_ATAK_resetFullFeedLayout;
        };

        if (_mapActive) then {
            [true] call Iceman_fnc_ATAK_setMapFeedOverlay;
        };
    }, 0.5] call CBA_fnc_addPerFrameHandler;
};
