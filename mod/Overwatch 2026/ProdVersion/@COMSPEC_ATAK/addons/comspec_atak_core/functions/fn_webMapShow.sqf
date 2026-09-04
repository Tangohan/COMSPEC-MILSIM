private _display = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
if (isNull _display) exitWith {false};

missionNamespace setVariable ["COMSPEC_ATAK_MapVisible", true, false];
["activeApp", "overwatch"] call COMSPEC_fnc_setState;

private _browser = _display displayCtrl 1100;
if (!isNull _browser) then
{
    _browser ctrlShow true;
    _browser ctrlEnable true;
};

{
    private _ctrl = _display displayCtrl _x;
    if (!isNull _ctrl) then
    {
        _ctrl ctrlShow false;
        _ctrl ctrlEnable false;
    };
} forEach [
    2201, 2202,
    2209, 2210, 2211, 2212, 2213, 2214, 2215, 2216,
    2220, 2221,
    1110, 1111,
    1090, 9430,
    1150, 1151, 1152, 1153
];

private _world = worldName;
[
    format [
        "if(window.COMSPEC_ATAK_liveMapShow){window.COMSPEC_ATAK_liveMapShow('%1',%2);}if(window.COMSPEC_ATAK_setNativeMap){window.COMSPEC_ATAK_setNativeMap(false);}",
        [_world] call COMSPEC_fnc_webJsEscape,
        worldSize
    ]
] call COMSPEC_fnc_webExecJS;

[] call COMSPEC_fnc_webPushState;
[] call COMSPEC_fnc_webPushTelemetry;

private _version = getText (configFile >> "CfgPatches" >> "comspec_atak_core" >> "versionStr");
[
    "INFO",
    "MAP",
    "Carte du terrain affichée dans l’écran du téléphone.",
    format ["mod=%1 world=%2", _version, _world]
] call COMSPEC_fnc_log;

true
