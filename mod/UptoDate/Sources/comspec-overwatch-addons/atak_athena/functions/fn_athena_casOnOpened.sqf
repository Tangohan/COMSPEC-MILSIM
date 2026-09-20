/*
    Ouverture Appui aérien (tiroir AtakCas).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Cas_group", _group];
["cas"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _title = _group controlsGroupCtrl 9920;
if (!isNull _title) then {
    _title ctrlSetText "  Appui aérien";
};

[] call comspec_overwatch_connect_fnc_casRequestFill;
