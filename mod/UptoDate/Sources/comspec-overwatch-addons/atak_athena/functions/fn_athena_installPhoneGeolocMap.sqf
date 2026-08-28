/*
    Accroche le dessin des téléphones suivis sur la carte ATAK IceMan / cTab.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_PhoneGeolocMapHooked", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_PhoneGeolocMapHooked", true, false];

private _attach = {
    private _names = ["cTab_Android_dlg", "cTab_Tablet_dlg", "cTab_microDAGR_dlg"];
    {
        private _disp = uiNamespace getVariable [_x, displayNull];
        if (isNull _disp) then { continue };
        if (_disp getVariable ["COMSPEC_PhoneGeolocDraw", false]) then { continue };
        private _map = controlNull;
        {
            private _c = _disp displayCtrl _x;
            if (!isNull _c && {ctrlType _c == 101}) exitWith { _map = _c; };
        } forEach [1200, 1201, 1773, 10, 50, 51, 100, 26109, 26110];
        if (isNull _map) then {
            for "_i" from 1 to 4000 do {
                private _c = _disp displayCtrl _i;
                if (!isNull _c && {ctrlType _c == 101}) exitWith { _map = _c; };
            };
        };
        if (isNull _map) then { continue };
        _map ctrlAddEventHandler ["Draw", {
            _this call comspec_overwatch_atak_athena_fnc_athena_hookPhoneGeolocMap;
        }];
        _disp setVariable ["COMSPEC_PhoneGeolocDraw", true];
    } forEach _names;
};

[] call _attach;
missionNamespace setVariable ["COMSPEC_PhoneGeolocMapAttach", _attach, false];
[{
    [] call (missionNamespace getVariable ["COMSPEC_PhoneGeolocMapAttach", {}]);
}, 2, []] call CBA_fnc_addPerFrameHandler;
