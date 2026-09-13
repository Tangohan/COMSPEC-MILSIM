/*
    Personne à régler (unité, ou pilote / chef d’équipage d’un véhicule).
*/
params [["_obj", objNull, [objNull]]];
if (isNull _obj) exitWith { objNull };
if (_obj isKindOf "CAManBase") exitWith { _obj };

private _crew = crew _obj;
if (!(_crew isEqualType [])) exitWith { objNull };
private _lead = objNull;
if (!isNull (commander _obj)) then { _lead = commander _obj };
if (isNull _lead && {!isNull (driver _obj)}) then { _lead = driver _obj };
if (isNull _lead) then {
    { if (_x isKindOf "CAManBase" && {alive _x}) exitWith { _lead = _x }; } forEach _crew;
};
_lead
