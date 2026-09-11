params ["_command",["_args",[]]]; if (!(_command isEqualType "") || {_command isEqualTo ""}) exitWith {""};
private _raw="COMSPECATAKNativeExtension" callExtension [_command,_args]; if (!(_raw isEqualType "")) then {_raw=str _raw;};
if (profileNamespace getVariable ["COMSPEC_ATAK_Debug",false]) then {["DEBUG","EXT",format ["%1 completed (%2 chars)",_command,count _raw]] call comspec_atak_native_fnc_log;}; _raw
