params [["_tab", "home"]];

private _state = call Iceman_fnc_wr_getState;
_state set ["tab", _tab];
_state set ["selection", 0];
call Iceman_fnc_wr_updatePanel;
true
