/*
    Appel extension COMSPEC générique.
    [_command, _args] call comspec_sse_fnc_extensionCall

    Arma 2.18+ : callExtension renvoie ["texte", code, err] — pas une string seule.
    Sans unwrap, _extOk voit un tableau et traite tout succès comme échec → fallback sendIntel.
*/
params [
    ["_command", "", [""]],
    ["_args", [], [[]]]
];

private _raw = "COMSPECExtension" callExtension [_command, _args];

// Même normalisation que Overwatch fn_extResult
if (_raw isEqualType []) then {
    private _ec = if ((count _raw) >= 3) then { _raw select 2 } else { 0 };
    private _rc = if ((count _raw) >= 2) then { _raw select 1 } else { 0 };
    missionNamespace setVariable ["comspec_sse_lastExtError", _ec, false];
    missionNamespace setVariable ["comspec_sse_lastExtReturn", _rc, false];
    _raw = if ((count _raw) > 0) then {
        private _v = _raw select 0;
        if (_v isEqualType "") then { _v } else { format ["%1", _v] }
    } else {
        ""
    };
} else {
    if (!(_raw isEqualType "")) then { _raw = format ["%1", _raw]; };
};

[format ["extension %1 -> %2", _command, _raw]] call comspec_sse_fnc_log;
missionNamespace setVariable ["comspec_sse_lastExtRaw", _raw, false];
_raw
