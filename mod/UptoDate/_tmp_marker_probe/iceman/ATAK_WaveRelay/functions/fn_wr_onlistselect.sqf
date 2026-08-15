params ["_control", "_index"];

private _state = call Iceman_fnc_wr_getState;
if (_state getOrDefault ["updating", false]) exitWith {false};

_state set ["selection", _index max 0];
call Iceman_fnc_wr_updatePanel;
true
