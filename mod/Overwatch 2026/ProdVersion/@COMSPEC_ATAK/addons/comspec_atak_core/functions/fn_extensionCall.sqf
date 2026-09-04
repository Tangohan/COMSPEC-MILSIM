params [
    ["_function", "", [""]],
    ["_args", [], [[]]]
];

if (_function isEqualTo "") exitWith {""};

private _started = diag_tickTime;

if (!isNil "COMSPEC_fnc_log") then
{
    ["DEBUG","EXT",format ["%1 ->",_function],format ["argc=%1",count _args]] call COMSPEC_fnc_log;
}
else
{
    diag_log format ["[COMSPEC ATAK][DEBUG][EXT] %1 -> argc=%2",_function,count _args];
};

private _raw = "";
private _result = "";
private _returnCode = 0;
private _errorCode = 0;

try
{
    _raw = "COMSPECExtension" callExtension [
        _function,
        _args
    ];

    if (_raw isEqualType []) then
    {
        _result = _raw param [0, "", [""]];
        _returnCode = _raw param [1, 0, [0]];
        _errorCode = _raw param [2, 0, [0]];
    }
    else
    {
        _result = if (_raw isEqualType "") then
        {
            _raw
        }
        else
        {
            str _raw
        };
    };
}
catch
{
    _result = "ERR|extension_exception";
    _errorCode = -1;
};

private _elapsedMs = round ((diag_tickTime - _started) * 1000);

private _safeResult = _result;

if (
    _function in [
        "AuthPassword",
        "VerifyOtp",
        "RestoreSession",
        "AuthSteam",
        "RedeemPairingCode",
        "RedeemRecoveryCode",
        "RedeemGameLink",
        "PairStatus",
        "Connect",
        "LinkBySteam",
        "Logout"
    ]
    && {(_safeResult find "OK|") isEqualTo 0}
) then
{
    _safeResult = "OK|<auth response masquée>";
};

private _extLevel = if (
    _result isEqualTo ""
    || {(_result find "ERR|") isEqualTo 0}
    || {_errorCode != 0}
) then {"WARN"} else {"DEBUG"};

if (!isNil "COMSPEC_fnc_log") then
{
    [_extLevel,"EXT",format ["%1 | %2",_function,_safeResult],format ["rc=%1 ec=%2 | %3ms",_returnCode,_errorCode,_elapsedMs]] call COMSPEC_fnc_log;
}
else
{
    diag_log format ["[COMSPEC ATAK][%1][EXT] %2 | %3 | rc=%4 ec=%5 | %6ms",_extLevel,_function,_safeResult,_returnCode,_errorCode,_elapsedMs];
};

if (
    missionNamespace getVariable [
        "COMSPEC_ATAK_PageReady",
        false
    ]
    && {!isNil "COMSPEC_fnc_webExecJS"}
    && {!isNil "COMSPEC_fnc_webJsEscape"}
) then
{
    private _level = _extLevel;

    private _detail = format [
        "%1 | rc=%2 ec=%3 | %4ms",
        _safeResult,
        _returnCode,
        _errorCode,
        _elapsedMs
    ];

    private _js = format [
        "if(window.COMSPEC_ATAK_appendRuntimeLog){window.COMSPEC_ATAK_appendRuntimeLog('EXT','%1','%2','%3');}",
        [_level] call COMSPEC_fnc_webJsEscape,
        [_function] call COMSPEC_fnc_webJsEscape,
        [_detail] call COMSPEC_fnc_webJsEscape
    ];

    [_js] call COMSPEC_fnc_webExecJS;
};

_result
