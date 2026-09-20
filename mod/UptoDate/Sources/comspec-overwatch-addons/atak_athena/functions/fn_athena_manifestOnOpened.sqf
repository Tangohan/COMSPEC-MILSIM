/*
    Ouverture Manifeste de vol (tiroir AtakManifest).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Manifest_group", _group];
["manifest"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _title = _group controlsGroupCtrl 9930;
if (!isNull _title) then {
    _title ctrlSetText "  Manifeste de vol";
};

[] call comspec_overwatch_connect_fnc_fillFlightManifest;
