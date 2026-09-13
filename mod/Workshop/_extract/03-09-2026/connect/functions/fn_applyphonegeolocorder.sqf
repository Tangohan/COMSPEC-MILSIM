/*
    Applique une demande web de géolocalisation téléphone (hub ATAK).
    Params: [_order hashmap]
*/
params [["_order", createHashMap]];

if (!(_order isEqualType createHashMap)) exitWith { false };

private _type = toUpper (trim (_order getOrDefault ["type", "PHONE_GEOLOC"]));
private _on = !(_type isEqualTo "PHONE_GEOLOC_OFF");
private _payload = toLower (trim (_order getOrDefault ["payload", ""]));
if (_payload in ["off", "0", "stop"]) then { _on = false };
if (_payload in ["on", "1", "start"]) then { _on = true };

private _net = trim (_order getOrDefault ["targetRef", _order getOrDefault ["target_ref", ""]]);
private _name = trim (_order getOrDefault ["target", ""]);
private _needleNet = toLower _net;
private _needleName = toLower _name;

private _unit = objNull;
if (_net isNotEqualTo "") then {
    _unit = objectFromNetId _net;
};

private _matches = {
    params ["_u"];
    if (!(_u isEqualType objNull) || {isNull _u} || {!alive _u}) exitWith { false };
    if (!(_u isKindOf "CAManBase")) exitWith { false };
    if (_needleNet isNotEqualTo "") then {
        if ((toLower (netId _u)) isEqualTo _needleNet) exitWith { true };
        if ((toLower (trim (_u getVariable ["COMSPEC_PhoneTrackId", ""]))) isEqualTo _needleNet) exitWith { true };
        if ((toLower (trim (_u getVariable ["COMSPEC_AllyTrackId", ""]))) isEqualTo _needleNet) exitWith { true };
    };
    if (_needleName isEqualTo "") exitWith { false };
    private _nm = toLower (name _u);
    if (_nm isEqualTo _needleName) exitWith { true };
    if (_nm isNotEqualTo "" && {(_needleName find _nm) >= 0}) exitWith { true };
    false
};

if (isNull _unit || {!([_unit] call _matches)}) then {
    {
        if ([_x] call _matches) exitWith { _unit = _x };
    } forEach allUnits;
};

if (isNull _unit) exitWith { false };

[_unit, _on] call comspec_overwatch_connect_fnc_setPhoneTrack;
[_unit, _on] remoteExecCall ["comspec_overwatch_connect_fnc_setPhoneTrack", 0];

if (_on) then {
    _unit setVariable ["COMSPEC_PhoneTrackLastAt", -1e9, false];
    if (!isNil "comspec_overwatch_connect_fnc_reportPhonePosition") then {
        [_unit] call comspec_overwatch_connect_fnc_reportPhonePosition;
    };
};

true
