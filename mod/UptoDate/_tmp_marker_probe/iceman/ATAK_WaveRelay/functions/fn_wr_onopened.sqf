params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

uiNamespace setVariable ["Iceman_ATAK_WaveRelay_group", _group];
private _state = call Iceman_fnc_wr_getState;
private _controls = call Iceman_fnc_wr_findControls;
private _freqCtrl = _controls getOrDefault ["frequency", controlNull];
private _profileCtrl = _controls getOrDefault ["profile", controlNull];
if (!isNull _freqCtrl) then {_freqCtrl ctrlSetText (_state getOrDefault ["frequency", "32.0"])};
if (!isNull _profileCtrl) then {_profileCtrl ctrlSetText (_state getOrDefault ["profileName", "Default"])};
call Iceman_fnc_wr_updatePanel;

[{
    call Iceman_fnc_wr_updatePanel;
}, 0.05] call CBA_fnc_waitAndExecute;
