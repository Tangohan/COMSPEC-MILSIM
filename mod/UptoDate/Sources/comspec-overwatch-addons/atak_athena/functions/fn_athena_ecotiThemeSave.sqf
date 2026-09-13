/*
    Persiste le thème de couleurs ECOTI depuis Paramètres ATAK.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AtakEcotiThemeFilling", false]) exitWith { true };

private _theme = missionNamespace getVariable ["comspec_overwatch_ecoti_theme", "nvg"];
if (!(_theme isEqualType "")) then { _theme = "nvg"; };

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (!isNull _group) then {
    private _body = _group controlsGroupCtrl 9839;
    if (!isNull _body) then { _group = _body; };
    private _cb = _group controlsGroupCtrl 9873;
    if (isNull _cb) then {
        private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (!isNull _disp) then { _cb = _disp displayCtrl 9873; };
    };
    if (!isNull _cb) then {
        private _ix = lbCurSel _cb;
        if (_ix >= 0) then {
            private _raw = _cb lbData _ix;
            if (_raw isNotEqualTo "") then { _theme = _raw; };
        };
    };
};

[_theme, true] call comspec_overwatch_connect_fnc_ecotiApplyThemeSetting;
true
