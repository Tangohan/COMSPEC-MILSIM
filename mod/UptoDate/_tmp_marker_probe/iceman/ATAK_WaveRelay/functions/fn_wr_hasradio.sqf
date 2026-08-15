params [["_unit", player]];

if (isNull _unit) exitWith {false};

private _gear = [];
_gear append (items _unit);
_gear append (assignedItems _unit);
_gear append (weapons _unit);

(_gear findIf {((toLower _x) find "acre_mpu5") == 0}) > -1
