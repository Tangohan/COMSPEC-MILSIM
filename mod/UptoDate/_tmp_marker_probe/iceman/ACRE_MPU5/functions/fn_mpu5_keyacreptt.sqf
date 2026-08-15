params [
    ["_radioId", "", [""]],
    ["_down", true, [true]],
    ["_source", "MPU-5", [""]]
];

if (!hasInterface) exitWith {[false, "No local interface"]};
if (isNil "acre_sys_core_fnc_handleMultiPttKeyPress" || {isNil "acre_sys_core_fnc_handleMultiPttKeyPressUp"}) exitWith {
    [false, "ACRE PTT functions unavailable"]
};

private _restoreActiveRadio = {
    params [["_token", -1]];

    [{
        params ["_token"];

        if ((missionNamespace getVariable ["Iceman_MPU5_TX_restoreToken", -2]) != _token) exitWith {};
        if (missionNamespace getVariable ["acre_sys_core_pttKeyDown", false]) exitWith {};

        private _previousRadio = missionNamespace getVariable ["Iceman_MPU5_TX_previousActiveRadio", ""];
        if (_previousRadio == "") exitWith {};

        private _currentRadios = [];
        if !(isNil "acre_api_fnc_getCurrentRadioList") then {
            _currentRadios = [] call acre_api_fnc_getCurrentRadioList;
        };

        if (_previousRadio in _currentRadios && {!(isNil "acre_api_fnc_setCurrentRadio")}) then {
            [_previousRadio] call acre_api_fnc_setCurrentRadio;
        };
    }, [_token], 0.35] call CBA_fnc_waitAndExecute;
};

if (!_down) exitWith {
    if (missionNamespace getVariable ["acre_sys_core_pttKeyDown", false]) then {
        call acre_sys_core_fnc_handleMultiPttKeyPressUp;
    };

    private _token = (missionNamespace getVariable ["Iceman_MPU5_TX_restoreToken", 0]) + 1;
    missionNamespace setVariable ["Iceman_MPU5_TX_restoreToken", _token, false];
    [_token] call _restoreActiveRadio;

    [true, ""]
};

if (_radioId == "") exitWith {[false, "No MPU-5 radio"]};

if (missionNamespace getVariable ["acre_sys_core_pttKeyDown", false]) exitWith {
    [false, "Another ACRE transmit is already active"]
};

private _currentRadios = [];
if !(isNil "acre_api_fnc_getCurrentRadioList") then {
    _currentRadios = [] call acre_api_fnc_getCurrentRadioList;
};
if !(_radioId in _currentRadios) exitWith {
    diag_log format ["[Iceman MPU5] %1 TX rejected: %2 is not in ACRE current radio list %3", _source, _radioId, _currentRadios];
    [false, "MPU-5 is not in ACRE radio list"]
};

private _previousRadio = "";
if !(isNil "acre_api_fnc_getCurrentRadio") then {
    _previousRadio = [] call acre_api_fnc_getCurrentRadio;
};
missionNamespace setVariable ["Iceman_MPU5_TX_previousActiveRadio", _previousRadio, false];
missionNamespace setVariable ["Iceman_MPU5_TX_activeRadio", _radioId, false];

if !(isNil "acre_api_fnc_setCurrentRadio") then {
    [_radioId] call acre_api_fnc_setCurrentRadio;
};

private _currentRadio = "";
if !(isNil "acre_api_fnc_getCurrentRadio") then {
    _currentRadio = [] call acre_api_fnc_getCurrentRadio;
};
if (_currentRadio != _radioId) exitWith {
    diag_log format ["[Iceman MPU5] %1 TX rejected: setCurrentRadio failed. wanted=%2 current=%3", _source, _radioId, _currentRadio];
    [false, "ACRE could not select the MPU-5"]
};

[-1] call acre_sys_core_fnc_handleMultiPttKeyPress;

private _broadcasting = missionNamespace getVariable ["ACRE_BROADCASTING_RADIOID", ""];
private _pttDown = missionNamespace getVariable ["acre_sys_core_pttKeyDown", false];
if (!_pttDown || {_broadcasting != _radioId}) exitWith {
    if (_pttDown) then {
        call acre_sys_core_fnc_handleMultiPttKeyPressUp;
    };

    private _token = (missionNamespace getVariable ["Iceman_MPU5_TX_restoreToken", 0]) + 1;
    missionNamespace setVariable ["Iceman_MPU5_TX_restoreToken", _token, false];
    [_token] call _restoreActiveRadio;

    diag_log format ["[Iceman MPU5] %1 TX rejected by ACRE. wanted=%2 broadcasting=%3 ptt=%4", _source, _radioId, _broadcasting, _pttDown];
    [false, "ACRE rejected MPU-5 voice transmit"]
};

diag_log format ["[Iceman MPU5] %1 TX started on %2", _source, _radioId];
[true, ""]
