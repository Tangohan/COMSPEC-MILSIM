/*
    Active ou coupe la géolocalisation téléphone d’une personne (Eden / Zeus).
*/
params [
    ["_unit", objNull, [objNull]],
    ["_on", true],
    ["_reveal", nil]
];
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith { false };

private _flag = _on;
if (_flag isEqualType 0) then { _flag = _flag > 0 };
if (_flag isEqualType "") then { _flag = (toLower (trim _flag)) in ["1", "true", "yes", "oui"] };
if (!(_flag isEqualType true)) then { _flag = false };

_unit setVariable ["COMSPEC_PhoneTrack", _flag, true];
if (_flag) then {
    if ((_unit getVariable ["COMSPEC_PhoneTrackId", ""]) isEqualTo "") then {
        private _id = format ["TEL-%1", ((netId _unit) splitString ":") joinString "-"];
        _unit setVariable ["COMSPEC_PhoneTrackId", _id, true];
    };
    if (!isNil "_reveal" && {_reveal isEqualType []}) then {
        _unit setVariable ["COMSPEC_PhoneReveal", _reveal, true];
    } else {
        if (isNil {_unit getVariable "COMSPEC_PhoneReveal"}) then {
            _unit setVariable ["COMSPEC_PhoneReveal", [], true];
        };
    };
} else {
    _unit setVariable ["COMSPEC_PhoneReveal", [], true];
};

private _list = missionNamespace getVariable ["COMSPEC_PhoneTrackUnits", []];
if (!(_list isEqualType [])) then { _list = []; };
if (_flag) then {
    _list pushBackUnique _unit;
} else {
    _list = _list - [_unit];
};
missionNamespace setVariable ["COMSPEC_PhoneTrackUnits", _list, false];
true
