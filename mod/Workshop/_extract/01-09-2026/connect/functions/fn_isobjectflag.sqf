/*
    Lit un drapeau objet Eden / Zeus (bool, 0/1, chaîne).
*/
params [
    ["_obj", objNull, [objNull]],
    ["_var", "", [""]]
];
if (isNull _obj || {_var isEqualTo ""}) exitWith { false };
private _v = _obj getVariable [_var, false];
if (_v isEqualType true) exitWith { _v };
if (_v isEqualType 0) exitWith { _v > 0 };
if (_v isEqualType "") exitWith { (toLower (trim _v)) in ["1", "true", "yes", "oui"] };
false
