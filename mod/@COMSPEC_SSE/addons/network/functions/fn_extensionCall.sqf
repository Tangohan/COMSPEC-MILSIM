/*
    Appel extension COMSPEC générique.
    [_command, _args] call comspec_sse_fnc_extensionCall
*/
params [
    ["_command", "", [""]],
    ["_args", [], [[]]]
];

private _raw = "COMSPECExtension" callExtension [_command, _args];
[format ["extension %1 -> %2", _command, _raw]] call comspec_sse_fnc_log;
_raw
