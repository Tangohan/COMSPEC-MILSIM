private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _id = _state getOrDefault ["scheduler",-1];
if (_id >= 0) then { [_id] call CBA_fnc_removePerFrameHandler; };
_state set ["scheduler",-1]; true
