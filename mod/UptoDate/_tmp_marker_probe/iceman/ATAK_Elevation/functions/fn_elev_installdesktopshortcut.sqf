#include "..\script_component.hpp"

if (missionNamespace getVariable ["Iceman_ATAK_Elevation_desktopShortcutPFH", -1] >= 0) exitWith {};

Iceman_ATAK_Elevation_desktopShortcutPFH = [{
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};

    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    private _existing = _display displayCtrl 198701;
    if (!isNull _existing) exitWith {};

    private _ctrl = _display ctrlCreate ["ctrlButtonPictureKeepAspect", 198701];
    _ctrl ctrlSetText "\z\BCE\addons\Core\data\route.paa";
    _ctrl ctrlSetTooltip "Elevation App";

    private _phoneW = safezoneW * 0.8;
    private _phoneH = safezoneH * 1.2;
    private _customPhoneH = _phoneW * 4 / 3;
    private _customPhoneX = safezoneX + (safezoneW - _phoneW) / 2;
    private _customPhoneY = safezoneY + (safezoneH - _customPhoneH) / 2;
    private _phoneSizeX = (452 / 2048) * _phoneW + _customPhoneX;
    private _phoneSizeY = ((713 + 60) / 2048) * _customPhoneH + _customPhoneY;

    _ctrl ctrlSetPosition [
        _phoneSizeX + ((150 / 2048) * (_phoneH * 3 / 4)),
        _phoneSizeY + ((25 / 2048) * _phoneH),
        (100 / 2048) * (_phoneH * 3 / 4),
        (100 / 2048) * _phoneH
    ];
    _ctrl ctrlSetBackgroundColor [0, 0, 0, 0];
    _ctrl ctrlSetActiveColor [1, 1, 1, 1];
    _ctrl ctrlShow (_mode == "DESKTOP");
    _ctrl ctrlCommit 0;
    _ctrl ctrlSetEventHandler [
        "ButtonClick",
        "[] spawn {if !(isNil 'BCE_fnc_ATAK_getAPPs') then {[true, true] call BCE_fnc_ATAK_getAPPs}; ['cTab_Android_dlg', [['mode', 'BFT']], true, true] call cTab_fnc_setSettings; uiSleep 0.08; ['cTab_Android_dlg', [['showMenu', ['Iceman_Elevation', true, ['', 0], createHashMap]]], true, true] call cTab_fnc_setSettings; uiSleep 0.12; call Iceman_fnc_elev_updatePanel;};"
    ];

    _display setVariable ["Iceman_ATAK_Elevation_desktopShortcut", _ctrl];
}, 0.25] call CBA_fnc_addPerFrameHandler;

Iceman_ATAK_Elevation_desktopShortcutShowPFH = [{
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};
    private _ctrl = _display getVariable ["Iceman_ATAK_Elevation_desktopShortcut", controlNull];
    if (isNull _ctrl) exitWith {};
    private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    _ctrl ctrlShow (_mode == "DESKTOP");
}, 0.25] call CBA_fnc_addPerFrameHandler;
