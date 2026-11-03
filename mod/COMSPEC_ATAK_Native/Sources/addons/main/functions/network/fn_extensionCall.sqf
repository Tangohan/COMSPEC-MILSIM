/* Appel à la DLL native. callExtension [cmd, args] renvoie [résultat, code, erreur] : on garde le résultat. */
params ["_command", ["_args", []]];
if (!(_command isEqualType "") || {_command isEqualTo ""}) exitWith { "" };
private _pt = diag_tickTime;
private _ret = "COMSPECATAKNativeExtension" callExtension [_command, _args];
if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then { ["DLL native " + _command, _pt] call comspec_atak_native_fnc_perfAcc; };
private _raw = if (_ret isEqualType []) then { _ret param [0, ""] } else { _ret };
if !(_raw isEqualType "") then { _raw = str _raw; };
// Journal de débogage : au plus une ligne par seconde et par commande (avant : une par appel, des dizaines par seconde).
if (profileNamespace getVariable ["COMSPEC_ATAK_Debug", false]) then {
    if (isNil "COMSPEC_ATAK_ExtLogAt") then { COMSPEC_ATAK_ExtLogAt = createHashMap; };
    if (diag_tickTime < (COMSPEC_ATAK_ExtLogAt getOrDefault [_command, 0])) exitWith {};
    COMSPEC_ATAK_ExtLogAt set [_command, diag_tickTime + 1];
    ["DEBUG", "EXT", format ["%1 completed (%2 chars)", _command, count _raw]] call comspec_atak_native_fnc_log;
};
_raw
