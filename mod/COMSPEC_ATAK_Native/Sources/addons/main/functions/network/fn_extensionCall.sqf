/* Appel à la DLL native. callExtension [cmd, args] renvoie [résultat, code, erreur] : on garde le résultat. */
params ["_command", ["_args", []]];
if (!(_command isEqualType "") || {_command isEqualTo ""}) exitWith { "" };
private _ret = "COMSPECATAKNativeExtension" callExtension [_command, _args];
private _raw = if (_ret isEqualType []) then { _ret param [0, ""] } else { _ret };
if !(_raw isEqualType "") then { _raw = str _raw; };
if (profileNamespace getVariable ["COMSPEC_ATAK_Debug", false]) then { ["DEBUG", "EXT", format ["%1 completed (%2 chars)", _command, count _raw]] call comspec_atak_native_fnc_log; };
_raw
