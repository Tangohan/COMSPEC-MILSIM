private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];

[false] call Iceman_fnc_ATAK_setMapFeedOverlay;

if (isNull _display) exitWith {};

private _buttonGroup = _display displayCtrl 46600;
if (!isNull _buttonGroup) then {
    {
        private _ctrl = _buttonGroup getVariable [_x, controlNull];
        if (!isNull _ctrl) then {
            _ctrl ctrlShow false;
        };
    } forEach ["Iceman_ATAK_MapFeedButton", "Iceman_ATAK_FullFeedActionButton"];

    {
        private _orig = _x getVariable ["Iceman_ATAK_FullFeed_origPos", []];
        if !(_orig isEqualTo []) then {
            _x ctrlSetPosition _orig;
            _x ctrlCommit 0;
        };
    } forEach [_buttonGroup controlsGroupCtrl 10, _buttonGroup controlsGroupCtrl 11];
};

private _toolMenu = _display displayCtrl (17000 + 4650);
if (isNull _toolMenu) exitWith {};

private _orig = _toolMenu getVariable ["Iceman_ATAK_FullFeed_origPos", []];
if !(_orig isEqualTo []) then {
    _toolMenu ctrlSetPosition _orig;
    _toolMenu ctrlCommit 0;
};
