/*
    Ouverture Relais AT (tiroir Wave Relay / Relais AT).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Relay_group", _group];
["relay"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _title = _group controlsGroupCtrl 9910;
if (isNull _title) then { _title = _group controlsGroupCtrl 9000; };
if (!isNull _title) then {
    _title ctrlSetText "  Relais AT";
};
{
    private _idc = ctrlIDC _x;
    if (_idc in [9010, 9011, 9012, 9013, 9014, 9015, 9020, 9030, 9031, 9032, 9033, 9034, 9035, 9040, 9041, 9043, 9044]) then {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach (allControls _group);
private _detail = _group controlsGroupCtrl 9021;
if (!isNull _detail) then {
    private _pos = ctrlPosition _detail;
    private _oldBottom = (_pos select 1) + (_pos select 3);
    private _status = _group controlsGroupCtrl 9001;
    if (!isNull _status) then {
        private _sp = ctrlPosition _status;
        _pos set [1, _sp select 1];
        _pos set [3, (_oldBottom - (_sp select 1)) max (_pos select 3)];
    };
    _detail ctrlSetPosition _pos;
    _detail ctrlCommit 0;
    _detail ctrlShow true;
};
private _token = diag_tickTime + random 1;
uiNamespace setVariable ["COMSPEC_ATAK_Relay_token", _token];

[] call comspec_overwatch_atak_athena_fnc_athena_updateRelay;

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Relay_token", -1]) isEqualTo _token } do {
        uiSleep 3;
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Relay_token", -1]) isNotEqualTo _token) exitWith {};
        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Relay_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Relay_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Relay_group", controlNull];
            };
        };
        private _page = (["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""];
        if (_page isNotEqualTo "" && {!(_page in ["WaveRelay", "AtakRelay", "COMSPEC_ATAK_Relay", "waverelay", "atakrelay"])}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Relay_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Relay_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Relay_group", controlNull];
            };
        };
        [] call comspec_overwatch_atak_athena_fnc_athena_updateRelay;
    };
};
