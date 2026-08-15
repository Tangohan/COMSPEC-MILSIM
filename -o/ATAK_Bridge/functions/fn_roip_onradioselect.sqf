params ["_control", "_index"];

private _state = call Iceman_fnc_roip_getState;
if (_state getOrDefault ["updating", false]) exitWith {false};

private _radios = _state getOrDefault ["lastRadios", []];
if (_index >= 0 && {_index < count _radios}) then {
    _state set ["radioSelection", _index];
    _state set ["selectedRadioId", (_radios # _index) # 0];
};
call Iceman_fnc_roip_updatePanel;
true
