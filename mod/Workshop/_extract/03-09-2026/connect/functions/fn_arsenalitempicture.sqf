/*
    Chemin d’icône d’un objet (arme, tenue, sac, lunettes) pour l’arsenal.
*/
params [["_class", "", [""]]];

if (_class isEqualTo "") exitWith { "" };

private _pic = getText (configFile >> "CfgWeapons" >> _class >> "picture");
if (_pic isEqualTo "") then {
    _pic = getText (configFile >> "CfgVehicles" >> _class >> "picture");
};
if (_pic isEqualTo "") then {
    _pic = getText (configFile >> "CfgGlasses" >> _class >> "picture");
};
if (_pic isEqualTo "" || {_pic == "pictureThing"}) exitWith { "" };

_pic
