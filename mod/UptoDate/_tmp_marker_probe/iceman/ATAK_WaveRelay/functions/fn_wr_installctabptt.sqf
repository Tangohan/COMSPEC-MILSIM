params [
    ["_display", displayNull],
    ["_displayName", ""]
];

if (isNull _display) exitWith {false};
if !(_displayName in ["cTab_Android_dlg", "cTab_Android_dsp"]) exitWith {false};

private _guardKey = format ["Iceman_WR_ctabPtt_%1", _displayName];
if ((uiNamespace getVariable [_guardKey, displayNull]) isEqualTo _display) exitWith {true};
uiNamespace setVariable [_guardKey, _display];

call Iceman_fnc_wr_cachePttKeybinds;
private _state = call Iceman_fnc_wr_getState;
_state set ["ctabPttActive", []];

_display displayAddEventHandler ["KeyDown", {
    params ["_display", "_key", "_shift", "_control", "_alt"];
    [_key, [_shift, _control, _alt], true] call Iceman_fnc_wr_handleCtabPttInput
}];

_display displayAddEventHandler ["KeyUp", {
    params ["_display", "_key", "_shift", "_control", "_alt"];
    [_key, [_shift, _control, _alt], false] call Iceman_fnc_wr_handleCtabPttInput
}];

_display displayAddEventHandler ["MouseButtonDown", {
    params ["_display", "_button", "_xPos", "_yPos", "_shift", "_control", "_alt"];
    [240 + _button, [_shift, _control, _alt], true] call Iceman_fnc_wr_handleCtabPttInput
}];

_display displayAddEventHandler ["MouseButtonUp", {
    params ["_display", "_button", "_xPos", "_yPos", "_shift", "_control", "_alt"];
    [240 + _button, [_shift, _control, _alt], false] call Iceman_fnc_wr_handleCtabPttInput
}];

_display displayAddEventHandler ["Unload", {
    params ["_display"];
    private _state = call Iceman_fnc_wr_getState;
    private _slots = +(_state getOrDefault ["txKeysDown", []]);
    {[_x, false] call Iceman_fnc_wr_keyTx} forEach _slots;
    _state set ["ctabPttActive", []];
    {
        if ((uiNamespace getVariable [_x, displayNull]) isEqualTo _display) then {
            uiNamespace setVariable [_x, displayNull];
        };
    } forEach ["Iceman_WR_ctabPtt_cTab_Android_dlg", "Iceman_WR_ctabPtt_cTab_Android_dsp"];
    false
}];

true
