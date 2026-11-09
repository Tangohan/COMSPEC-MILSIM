/*
    Slot virtuel COMSPEC (expérimental) : retirer les lunettes portées.
    _giveBack : remettre l'objet <classe>_Item dans l'inventaire (par terre à côté s'il n'y a pas la place).
    Renvoie l'id retiré ("" si rien n'était porté).
*/
params [["_unit", objNull, [objNull]], ["_giveBack", true, [true]]];
if (isNull _unit) exitWith { "" };
private _id = _unit getVariable ["comspec_sse_eyewear_worn", ""];
if (_id isEqualTo "") exitWith { "" };
_unit setVariable ["comspec_sse_eyewear_worn", "", true];
if (_giveBack) then {
    private _e = (([] call comspec_sse_fnc_eyewearCatalog) select 0) getOrDefault [_id, createHashMap];
    private _item = _e getOrDefault ["item", ""];
    if (_item isNotEqualTo "") then {
        if (_unit canAdd _item) then {
            _unit addItem _item;
        } else {
            private _holder = createVehicle ["GroundWeaponHolder", getPosATL _unit, [], 0.4, "CAN_COLLIDE"];
            _holder addItemCargoGlobal [_item, 1];
        };
    };
};
if (_unit isEqualTo player) then { [] call comspec_sse_fnc_eyewearRender; };
_id
