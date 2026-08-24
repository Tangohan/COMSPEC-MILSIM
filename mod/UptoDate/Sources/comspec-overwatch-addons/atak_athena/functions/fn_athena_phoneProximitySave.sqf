/*
    Persiste le rayon d’alerte « téléphones suivis » (combo Paramètres ATAK).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AtakPhoneProxFilling", false]) exitWith { true };

private _presets = [0, 50, 100, 200, 500, 1000, 2000];
private _radius = missionNamespace getVariable ["COMSPEC_AtakPhoneProximityM", 200];
if (!(_radius isEqualType 0)) then { _radius = 200; };

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (!isNull _group) then {
    private _cb = _group controlsGroupCtrl 9849;
    if (isNull _cb) then {
        private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (!isNull _disp) then { _cb = _disp displayCtrl 9849; };
    };
    if (!isNull _cb) then {
        private _ix = lbCurSel _cb;
        if (_ix >= 0) then {
            private _raw = _cb lbData _ix;
            if (_raw isNotEqualTo "") then {
                _radius = parseNumber _raw;
            };
        };
    };
};

if ((_presets find _radius) < 0) then {
    if (_radius <= 0) then {
        _radius = 0;
    } else {
        private _best = 200;
        private _delta = 1e9;
        {
            private _d = abs (_x - _radius);
            if (_d < _delta) then {
                _best = _x;
                _delta = _d;
            };
        } forEach _presets;
        _radius = _best;
    };
};

private _prev = missionNamespace getVariable ["COMSPEC_AtakPhoneProximityM", 200];
missionNamespace setVariable ["COMSPEC_AtakPhoneProximityM", _radius, false];
profileNamespace setVariable ["COMSPEC_AtakPhoneProximityM", _radius];
saveProfileNamespace;
if (_radius isNotEqualTo _prev) then {
    missionNamespace setVariable ["COMSPEC_AtakPhoneProxInside", createHashMap, false];
};

true
