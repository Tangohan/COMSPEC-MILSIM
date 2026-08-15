if (!hasInterface) exitWith {false};

{
    private _display = uiNamespace getVariable [_x, displayNull];
    if (!isNull _display) then {
        {
            private _ctrl = _display displayCtrl _x;
            if (!isNull _ctrl && {(_ctrl getVariable ["Iceman_Aware_drawEH", -1]) < 0}) then {
                private _eh = _ctrl ctrlAddEventHandler ["Draw", {
                    params ["_map"];
                    private _mode = call Iceman_fnc_aware_getMode;
                    if (_mode == "individual") then {
                        private _miniDisplay = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
                        private _isMini = !isNull _miniDisplay && {((ctrlParent _map) isEqualTo _miniDisplay)};

                        if (_isMini) then {
                            [_map] call Iceman_fnc_aware_followMiniMap;
                        };
                        [_map, 0] call Iceman_fnc_aware_drawBftMarkers;
                        [_map, 0] call Iceman_fnc_aware_drawOwnCursor;
                        if (!_isMini) then {
                            [_map, 0] call Iceman_fnc_aware_drawHook;
                        };
                    };
                }];
                _ctrl setVariable ["Iceman_Aware_drawEH", _eh];
            };
        } forEach [1201, 1202, 1203];
    };
} forEach ["cTab_Android_dlg", "cTab_Android_dsp"];

true
