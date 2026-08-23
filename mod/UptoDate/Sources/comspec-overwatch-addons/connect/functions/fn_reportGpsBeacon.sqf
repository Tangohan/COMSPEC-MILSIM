/*
    Remonte une balise GPS véhicule comme contact ATAK distinct
    (en plus du suivi véhicule). Ne réutilise pas l’identité Steam du relais.
*/
params [
    ["_vehicle", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull _vehicle) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if !([_vehicle, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag) exitWith { false };

private _pos = getPosWorld _vehicle;
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith { false };

private _last = _vehicle getVariable ["COMSPEC_GpsBeaconLastAt", -1e9];
if ((diag_tickTime - _last) < 6) exitWith { false };
_vehicle setVariable ["COMSPEC_GpsBeaconLastAt", diag_tickTime, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _callSign = [_vehicle] call comspec_overwatch_connect_fnc_vehicleTrackCallsign;
private _heading = getDir _vehicle;
private _aslZ = (getPosASL _vehicle) select 2;
private _pretty = getText (configOf _vehicle >> "displayName");
if (_pretty isEqualTo "") then { _pretty = typeOf _vehicle };
if (_pretty isEqualTo "") then { _pretty = "Véhicule" };

private _escCs = (_callSign splitString """" joinString "");
private _prettyEsc = (_pretty splitString """" joinString "");
private _beaconId = _vehicle getVariable ["COMSPEC_GpsBeaconId", ""];
if (!(_beaconId isEqualType "")) then { _beaconId = str _beaconId };
if (_beaconId isEqualTo "") then {
    _beaconId = format ["GPS-%1", ((netId _vehicle) splitString ":") joinString "-"];
    _vehicle setVariable ["COMSPEC_GpsBeaconId", _beaconId, true];
};
_beaconId = (_beaconId splitString """" joinString "");

private _extra = format [
    "{""gps_beacon"":true,""source"":""gps"",""beacon_id"":""%1"",""vehicle_name"":""%2"",""in_vehicle"":true,""military_id"":""""}",
    _beaconId,
    _prettyEsc
];

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    [_heading, 2] call _fnc_num,
    _escCs,
    "Balise GPS",
    "stable",
    "",
    "",
    "",
    _extra,
    "",
    "",
    [_aslZ, 3] call _fnc_num
]];
true
