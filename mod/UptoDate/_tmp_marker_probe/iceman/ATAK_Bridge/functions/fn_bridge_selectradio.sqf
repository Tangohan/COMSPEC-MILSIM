params [["_radioClass", "ACRE_PRC152"]];

if !(_radioClass in ["ACRE_PRC152", "ACRE_PRC117F"]) then {
    _radioClass = "ACRE_PRC152";
};

private _state = call Iceman_fnc_bridge_getstate;
_state set ["radioClass", _radioClass];
_state set ["selection", 0];
call Iceman_fnc_bridge_updatepanel;
true
