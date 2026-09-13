/*
    Injecte des effets visuels roleplay dans le navigateur cTab / tablette Athena.
*/

if (!hasInterface) exitWith {};

private _packetLossStats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _disconnectInfo = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
private _zoneInfo = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;

private _isDisconnected = false;
if (_disconnectInfo isEqualType createHashMap) then {
    _isDisconnected = _disconnectInfo getOrDefault ["is_disconnected", false];
};
private _packetLoss = 0;
if (_packetLossStats isEqualType createHashMap) then {
    _packetLoss = _packetLossStats getOrDefault ["packet_loss_percent", 0];
};

private _jsCode = "";

if (_isDisconnected) then {
    private _remaining = 0;
    if (_disconnectInfo isEqualType createHashMap) then {
        _remaining = _disconnectInfo getOrDefault ["remaining_seconds", 0];
    };
    _jsCode = _jsCode + format [
        "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.showConnectionError('Liaison perdue', 'Reconnexion dans %1 s'); }",
        _remaining
    ];
} else {
    _jsCode = _jsCode + "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.hideConnectionError(); }";
};

if (_packetLoss > 5) then {
    private _intensity = (_packetLoss / 100) min 0.4;
    _jsCode = _jsCode + format [
        "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.applyGlitchEffect(%1); AtakRoleplayEffects.applyMapInterference(%1); }",
        _intensity
    ];
};

if (!isNil "_zoneInfo" && {_zoneInfo isEqualType createHashMap}) then {
    private _zoneNameRaw = _zoneInfo getOrDefault ["name", ""];
    if (!(_zoneNameRaw isEqualType "")) then { _zoneNameRaw = ""; };
    private _zoneName = if (!isNil "comspec_overwatch_connect_fnc_webBrowserJsEscape") then {
        [_zoneNameRaw] call comspec_overwatch_connect_fnc_webBrowserJsEscape
    } else {
        _zoneNameRaw
    };
    private _intensity = _zoneInfo getOrDefault ["intensity", 0];
    _jsCode = _jsCode + format [
        "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.showZoneWarning('%1', %2); }",
        _zoneName,
        _intensity
    ];
} else {
    _jsCode = _jsCode + "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.hideZoneWarning(); }";
};

_jsCode = _jsCode + format [
    "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.updateNetworkQualityIndicators(null, null, %1); }",
    _packetLoss
];

if (_jsCode isEqualTo "") exitWith {};
missionNamespace setVariable ["COMSPEC_RoleplayJS", _jsCode, false];

private _displays = [];
{
    private _d = uiNamespace getVariable [_x, displayNull];
    if (!isNull _d) then { _displays pushBackUnique _d; };
} forEach [
    "cTab_Android_dlg",
    "cTab_Tablet_dlg",
    "COMSPEC_WebBrowser_Display",
    "COMSPEC_WebBrowser",
    "RscCustomInfoMiniMap"
];

{
    private _disp = _x;
    {
        private _browser = _disp displayCtrl _x;
        if (!isNull _browser) then {
            _browser ctrlWebBrowserAction ["ExecJS", _jsCode];
        };
    } forEach [9401, 1100];
} forEach _displays;
