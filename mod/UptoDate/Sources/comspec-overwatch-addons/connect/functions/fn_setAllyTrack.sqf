/*
    Active ou coupe le suivi ATAK d’une IA (Eden / Zeus).
*/
params [
    ["_unit", objNull, [objNull]],
    ["_on", true]
];
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith { false };
if (isPlayer _unit) exitWith { false };

private _flag = _on;
if (_flag isEqualType 0) then { _flag = _flag > 0 };
if (_flag isEqualType "") then { _flag = (toLower (trim _flag)) in ["1", "true", "yes", "oui"] };
if (!(_flag isEqualType true)) then { _flag = false };

_unit setVariable ["COMSPEC_AllyTrack", _flag, true];
if (_flag) then {
    if ((_unit getVariable ["COMSPEC_AllyTrackId", ""]) isEqualTo "") then {
        private _id = format ["ALLY-%1", ((netId _unit) splitString ":") joinString "-"];
        _unit setVariable ["COMSPEC_AllyTrackId", _id, true];
    };
};

private _list = missionNamespace getVariable ["COMSPEC_AllyTrackUnits", []];
if (!(_list isEqualType [])) then { _list = []; };
if (_flag) then {
    _list pushBackUnique _unit;
} else {
    _list = _list - [_unit];
};
missionNamespace setVariable ["COMSPEC_AllyTrackUnits", _list, false];
true
