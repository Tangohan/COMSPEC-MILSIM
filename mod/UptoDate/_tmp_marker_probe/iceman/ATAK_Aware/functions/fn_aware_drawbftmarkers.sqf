params ["_ctrlScreen", ["_mode", 0]];

private _awareMode = call Iceman_fnc_aware_getMode;
if (_awareMode == "default" || {_mode != 0} || {isNil "cTab_player"} || {isNull cTab_player}) exitWith {
    false
};

private _validSides = call cTab_fnc_getPlayerSides;
private _blue = missionNamespace getVariable ["cTabColorBlue", [0,0.35,1,0.95]];
private _manSize = missionNamespace getVariable ["cTabIconManSize", 18];
private _textSize = missionNamespace getVariable ["cTabTxtSize", 0.04];
private _personnelDevices = missionNamespace getVariable ["ctab_core_personnelDevices", []];

{
    private _unit = _x;
    if (
        alive _unit
        && {side group _unit in _validSides}
        && {_unit == cTab_player || {isPlayer _unit}}
        && {_unit == cTab_player || {[_unit, _personnelDevices] call cTab_fnc_checkGear}}
    ) then {
        private _veh = vehicle _unit;
        private _pos = getPosASL _veh;
        private _dir = if (_veh isEqualTo _unit) then {getDirVisual _unit} else {direction _veh};
        private _label = name _unit;
        if (_label == "") then {
            _label = format ["%1:%2", groupID group _unit, [_unit] call CBA_fnc_getGroupIndex];
        };

        _ctrlScreen drawIcon ["\A3\ui_f\data\map\VehicleIcons\iconmanvirtual_ca.paa", _blue, _pos, _manSize, _manSize, _dir, "", 0, _textSize, "TahomaB", "center"];
        _ctrlScreen drawIcon ["\A3\ui_f\data\map\Markers\System\dummy_ca.paa", _blue, _pos, _manSize, _manSize, 0, _label, 0, _textSize, "TahomaB", "right"];
    };
} forEach allUnits;

true
