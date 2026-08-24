/*
    Active ou coupe le suivi ATAK d’une IA (Eden / Zeus).
    Le drapeau est public sur l’objet ; un registre netId survit au transfert
    de localité Zeus → serveur (sinon le suivi disparaissait en quittant Zeus).
*/
params [
    ["_unit", objNull, [objNull]],
    ["_on", true],
    ["_callsign", nil]
];
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith { false };
if (isPlayer _unit) exitWith { false };

private _flag = _on;
if (_flag isEqualType 0) then { _flag = _flag > 0 };
if (_flag isEqualType "") then { _flag = (toLower (trim _flag)) in ["1", "true", "yes", "oui"] };
if (!(_flag isEqualType true)) then { _flag = false };

private _nid = netId _unit;
private _id = _unit getVariable ["COMSPEC_AllyTrackId", ""];
if (!(_id isEqualType "")) then { _id = str _id };
_id = trim _id;

_unit setVariable ["COMSPEC_AllyTrack", _flag, true];
if (!isNil "_callsign" && {_callsign isEqualType ""}) then {
    private _cs = trim _callsign;
    private _low = toLower _cs;
    if (_cs isNotEqualTo "" && {_low regexMatch "^ally-[0-9]+-[0-9]+(-[0-9]+)*$"}) then {
        _cs = "";
    };
    if (_cs isNotEqualTo "") then {
        _unit setVariable ["COMSPEC_AllyCallsign", _cs, true];
    } else {
        _unit setVariable ["COMSPEC_AllyCallsign", "", true];
    };
};
if (_flag) then {
    if (_id isEqualTo "" || {(toLower _id) find "ally-" != 0}) then {
        _id = format ["ALLY-%1", (_nid splitString ":") joinString "-"];
        _unit setVariable ["COMSPEC_AllyTrackId", _id, true];
    };
    if (isNil {_unit getVariable "COMSPEC_AllyTrackLocalEH"}) then {
        private _eh = _unit addEventHandler ["Local", {
            params ["_obj"];
            if (isNull _obj) exitWith {};
            if !([_obj, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag) exitWith {};
            _obj setVariable ["COMSPEC_AllyTrack", true, true];
            private _keep = _obj getVariable ["COMSPEC_AllyTrackId", ""];
            if (_keep isEqualType "" && {_keep isNotEqualTo ""}) then {
                _obj setVariable ["COMSPEC_AllyTrackId", _keep, true];
            };
            private _keepCs = _obj getVariable ["COMSPEC_AllyCallsign", ""];
            if (_keepCs isEqualType "" && {_keepCs isNotEqualTo ""}) then {
                _obj setVariable ["COMSPEC_AllyCallsign", _keepCs, true];
            };
        }];
        _unit setVariable ["COMSPEC_AllyTrackLocalEH", _eh];
    };
};

private _ids = missionNamespace getVariable ["COMSPEC_AllyTrackNetIds", []];
if (!(_ids isEqualType [])) then { _ids = []; };
if (_flag) then {
    _ids pushBackUnique _nid;
} else {
    _ids = _ids - [_nid];
};
missionNamespace setVariable ["COMSPEC_AllyTrackNetIds", _ids, true];

private _list = missionNamespace getVariable ["COMSPEC_AllyTrackUnits", []];
if (!(_list isEqualType [])) then { _list = []; };
if (_flag) then {
    _list pushBackUnique _unit;
} else {
    _list = _list - [_unit];
};
missionNamespace setVariable ["COMSPEC_AllyTrackUnits", _list, false];

if (_flag && {!isNil "CBA_fnc_waitAndExecute"}) then {
    {
        [{
            params ["_u", "_aid"];
            if (isNull _u || {!alive _u}) exitWith {};
            _u setVariable ["COMSPEC_AllyTrack", true, true];
            if (_aid isNotEqualTo "") then {
                _u setVariable ["COMSPEC_AllyTrackId", _aid, true];
            };
            private _keepCs = _u getVariable ["COMSPEC_AllyCallsign", ""];
            if (_keepCs isEqualType "" && {_keepCs isNotEqualTo ""}) then {
                _u setVariable ["COMSPEC_AllyCallsign", _keepCs, true];
            };
        }, [_unit, _id], _x] call CBA_fnc_waitAndExecute;
    } forEach [0.4, 2, 6];
};

true
