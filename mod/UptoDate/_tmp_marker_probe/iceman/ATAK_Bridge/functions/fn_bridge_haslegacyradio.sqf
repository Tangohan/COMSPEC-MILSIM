params [["_radioClass", "ACRE_PRC152"], ["_unit", player]];

if (isNull _unit) exitWith {false};

private _target = toLower _radioClass;
private _matchesRadio = {
    params [["_item", ""]];
    private _itemLower = toLower _item;
    (_itemLower == _target) || {(_itemLower find (_target + "_id_")) == 0}
};

private _gear = [];
_gear append (items _unit);
_gear append (assignedItems _unit);
_gear append (weapons _unit);
_gear append (uniformItems _unit);
_gear append (vestItems _unit);
_gear append (backpackItems _unit);

if ((_gear findIf {[_x] call _matchesRadio}) >= 0) exitWith {true};

private _scanCargo = {
    params [["_container", objNull]];
    private _cargo = [];
    if (isNull _container) exitWith {_cargo};

    _cargo append (itemCargo _container);
    _cargo append (weaponCargo _container);

    {
        if (_x isEqualType [] && {(count _x) > 1}) then {
            private _nested = _x # 1;
            if (!isNull _nested) then {
                _cargo append (itemCargo _nested);
                _cargo append (weaponCargo _nested);
            };
        };
    } forEach (everyContainer _container);

    _cargo
};

private _vehicle = vehicle _unit;
if (!isNull _vehicle && {_vehicle != _unit}) exitWith {
    private _cargo = [_vehicle] call _scanCargo;
    (_cargo findIf {[_x] call _matchesRadio}) >= 0
};

false
