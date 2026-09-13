/*
    Collecte le journal de session pour un signalement joueur.
    Returns: texte multi-lignes (sans secrets), tronqué ~12 Ko.
*/
if (!hasInterface) exitWith { "" };

private _fnc_safeLine = {
    params ["_line"];
    if (_line isNotEqualType "") exitWith { "" };
    if (_line find "api_key" >= 0) exitWith { "" };
    if (_line find "Bearer" >= 0) exitWith { "" };
    if (_line find "token" >= 0) exitWith { "" };
    _line
};

private _lines = [];

private _ext = if (!isNil "comspec_overwatch_connect_fnc_extensionStatus") then {
    [] call comspec_overwatch_connect_fnc_extensionStatus
} else {
    [false, "n/a", -1]
};
_ext params [["_extOk", false], ["_extCode", ""], ["_ping", -1]];

_lines append [
    format ["--- Instantané signalement (t=%1) ---", diag_tickTime toFixed 1],
    format ["mission=%1", missionName],
    format ["productVersion=%1", productVersion],
    format ["linkState=%1 athenaReady=%2", missionNamespace getVariable ["COMSPEC_LinkState", "?"], missionNamespace getVariable ["COMSPEC_AthenaReady", false]],
    format ["extOk=%1 code=%2 ping=%3", _extOk, _extCode, _ping],
    format ["callsign=%1", [] call comspec_overwatch_connect_fnc_getCallsign],
    "--- Journal fichier ---"
];

private _raw = ["COMSPECExtension" callExtension ["GetLogTail", ["14000"]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
    private _payload = _raw select [3, count _raw - 3];
    {
        private _safe = [_x] call _fnc_safeLine;
        if (_safe isNotEqualTo "") then { _lines pushBack _safe; };
    } forEach (_payload splitString toString [9]);
};

_lines pushBack "--- Tampon session ---";

private _buf = missionNamespace getVariable ["COMSPEC_DiagLog", []];
if (_buf isEqualType [] && {(count _buf) > 0}) then {
    {
        private _safe = [_x] call _fnc_safeLine;
        if (_safe isNotEqualTo "") then { _lines pushBack _safe; };
    } forEach _buf;
};

private _text = _lines joinString toString [10];
private _max = 12000;
if (count _text > _max) then {
    _text = "...[debut tronque]..." + toString [10] + (_text select [(count _text) - _max, _max]);
};

_text
