/*
    Ouverture de l’app Reco dans le tiroir ATAK.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Recon_group", _group];
["recon"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _title = _group controlsGroupCtrl 9780;
if (!isNull _title) then {
    _title ctrlSetText "  Reco";
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateRecon;
