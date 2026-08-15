params [["_display", displayNull]];

if (isNull _display) exitWith {};

private _controls = _display getVariable ["Iceman_TOC_dynamicControls", []];
{
    if (!isNull _x) then {
        ctrlDelete _x;
    };
} forEach _controls;

_display setVariable ["Iceman_TOC_dynamicControls", []];
