params [["_show", false], ["_target", ""]];

private _displayNames = ["cTab_Android_dlg", "cTab_Android_dsp"];
private _targetIsDisplay = _target isEqualType displayNull;

if (_target isEqualType "" && {_target != ""}) then {
    _displayNames = [_target];
};

if (_targetIsDisplay) then {
    _displayNames = _displayNames select {
        private _display = uiNamespace getVariable [_x, displayNull];
        !isNull _display && {_display isEqualTo _target}
    };
};

{
    private _displayName = _x;
    private _display = if (_targetIsDisplay) then {_target} else {uiNamespace getVariable [_displayName, displayNull]};
    private _overlayVar = format ["Iceman_ATAK_MapFeedCtrl_%1", _displayName];
    private _overlay = uiNamespace getVariable [_overlayVar, controlNull];

    if (isNull _display) then {
        if (!isNull _overlay) then {
            _overlay ctrlShow false;
        };
    } else {
        if (isNull _overlay || {ctrlParent _overlay != _display}) then {
            _overlay = _display ctrlCreate ["RscPicture", -1];
            uiNamespace setVariable [_overlayVar, _overlay];
        };

        if (!_show) then {
            _overlay ctrlShow false;
        } else {
            private _mapType = [_displayName, "mapType"] call cTab_fnc_getSettings;
            private _mapTypes = [_displayName, "mapTypes"] call cTab_fnc_getSettings;
            private _mapIDC = [_mapTypes, _mapType] call cTab_fnc_getFromPairs;
            if (isNil "_mapIDC") then {
                _mapIDC = 1201;
            };

            private _mapCtrl = _display displayCtrl _mapIDC;
            if (isNull _mapCtrl) then {
                _overlay ctrlShow false;
            } else {
                _overlay ctrlSetText "#(argb,512,512,1)r2t(rendertarget9,1.1896551724)";
                _overlay ctrlSetPosition (ctrlPosition _mapCtrl);
                _overlay ctrlSetFade 0;
                _overlay ctrlShow true;
                _overlay ctrlCommit 0;
            };
        };
    };
} forEach _displayNames;
