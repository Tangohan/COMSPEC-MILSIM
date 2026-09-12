/*
    Persiste le choix Affichage situation (JVN) depuis Paramètres ATAK.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AtakEcotiHudFilling", false]) exitWith { true };

private _enabled = missionNamespace getVariable ["comspec_overwatch_ecoti_hud", false];
if (!(_enabled isEqualType true)) then { _enabled = false; };

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (!isNull _group) then {
    private _body = _group controlsGroupCtrl 9839;
    if (!isNull _body) then { _group = _body; };
    private _cb = _group controlsGroupCtrl 9862;
    if (isNull _cb) then {
        private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (!isNull _disp) then { _cb = _disp displayCtrl 9862; };
    };
    if (!isNull _cb) then {
        private _ix = lbCurSel _cb;
        if (_ix >= 0) then {
            private _raw = _cb lbData _ix;
            _enabled = (_raw isEqualTo "1");
        };
    };
};

[_enabled, true] call comspec_overwatch_connect_fnc_ecotiApplyHudSetting;
true
