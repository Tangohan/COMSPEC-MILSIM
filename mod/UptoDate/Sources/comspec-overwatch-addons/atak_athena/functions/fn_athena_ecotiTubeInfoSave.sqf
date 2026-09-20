/*
    Persiste les indications du tube JVN depuis Paramètres ATAK.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AtakEcotiTubeFilling", false]) exitWith { true };

private _mode = "full";
private _gps = missionNamespace getVariable ["comspec_overwatch_ecoti_gps_hud", true];
if (!(_gps isEqualType true)) then { _gps = true; };
private _only = missionNamespace getVariable ["comspec_overwatch_ecoti_compass_only", false];
if (!(_only isEqualType true)) then { _only = false; };
if (_only) then { _mode = "compass"; };
if (!_gps && {!_only}) then { _mode = "off"; };

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (!isNull _group) then {
    private _body = _group controlsGroupCtrl 9839;
    if (!isNull _body) then { _group = _body; };
    private _cb = _group controlsGroupCtrl 9877;
    if (isNull _cb) then {
        private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (!isNull _disp) then { _cb = _disp displayCtrl 9877; };
    };
    if (!isNull _cb) then {
        private _ix = lbCurSel _cb;
        if (_ix >= 0) then {
            private _raw = _cb lbData _ix;
            if (_raw isNotEqualTo "") then { _mode = _raw; };
        };
    };
};

[_mode, true] call comspec_overwatch_connect_fnc_ecotiApplyTubeInfoSetting;
true
