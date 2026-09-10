private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _old = _state getOrDefault ["scheduler",-1];
if (_old >= 0) then { [_old] call CBA_fnc_removePerFrameHandler; };
private _id = [{ [] call comspec_atak_native_fnc_schedulerTick },0.2] call CBA_fnc_addPerFrameHandler;
_state set ["scheduler",_id]; _id
