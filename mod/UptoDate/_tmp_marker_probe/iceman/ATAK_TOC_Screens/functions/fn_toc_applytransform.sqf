params [["_target", objNull], ["_panel", objNull], ["_settings", []]];

if (isNull _target || {isNull _panel}) exitWith {};

_settings = [_settings] call Iceman_fnc_toc_normalizeSettings;
_settings params ["_x", "_y", "_z", "_width", "_height", "_pipDistance", "_pitch", "_roll"];
detach _panel;
_panel attachTo [_target, [_x, _y, _z]];
_panel setObjectScale ((_width max _height) max 0.05);
if !(isNil "BIS_fnc_setPitchBank") then {
    [_panel, _pitch, _roll] call BIS_fnc_setPitchBank;
};
