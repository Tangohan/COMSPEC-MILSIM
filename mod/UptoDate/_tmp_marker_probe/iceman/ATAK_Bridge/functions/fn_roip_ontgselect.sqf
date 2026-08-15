params ["_control", "_index"];

private _state = call Iceman_fnc_roip_getState;
if (_state getOrDefault ["updating", false]) exitWith {false};

if (_index >= 0) then {
    _state set ["tgSelection", (_index + 1) max 1 min 16];
};
call Iceman_fnc_roip_updatePanel;
true
