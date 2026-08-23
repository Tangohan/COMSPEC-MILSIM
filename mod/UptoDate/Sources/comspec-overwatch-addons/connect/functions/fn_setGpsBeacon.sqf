/*
    Active ou coupe la balise GPS d’un véhicule (Eden / Zeus).
*/
params [
    ["_vehicle", objNull, [objNull]],
    ["_on", true]
];
if (isNull _vehicle) exitWith { false };
if (_vehicle isKindOf "CAManBase") exitWith { false };
if (!(_vehicle isKindOf "LandVehicle" || {_vehicle isKindOf "Air"} || {_vehicle isKindOf "Ship"})) exitWith { false };

private _flag = _on;
if (_flag isEqualType 0) then { _flag = _flag > 0 };
if (_flag isEqualType "") then { _flag = (toLower (trim _flag)) in ["1", "true", "yes", "oui"] };
if (!(_flag isEqualType true)) then { _flag = false };

_vehicle setVariable ["COMSPEC_GpsBeacon", _flag, true];
if (_flag) then {
    if ((_vehicle getVariable ["COMSPEC_GpsBeaconId", ""]) isEqualTo "") then {
        private _id = format ["GPS-%1", ((netId _vehicle) splitString ":") joinString "-"];
        _vehicle setVariable ["COMSPEC_GpsBeaconId", _id, true];
    };
};

private _list = missionNamespace getVariable ["COMSPEC_GpsBeaconObjects", []];
if (!(_list isEqualType [])) then { _list = []; };
if (_flag) then {
    _list pushBackUnique _vehicle;
} else {
    _list = _list - [_vehicle];
};
missionNamespace setVariable ["COMSPEC_GpsBeaconObjects", _list, false];
true
