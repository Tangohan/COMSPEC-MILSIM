/*
    Remonte une balise GPS véhicule comme contact ATAK distinct
    (en plus du suivi véhicule). Ne réutilise pas l’identité Steam du relais.
*/
params [
    ["_vehicle", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull _vehicle) exitWith { false };
if (
    !isNil "comspec_overwatch_connect_fnc_shouldSkipEnemyAiTransmit"
    && { [_vehicle] call comspec_overwatch_connect_fnc_shouldSkipEnemyAiTransmit }
) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if !([_vehicle, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag) exitWith { false };

private _pos = getPosWorld _vehicle;
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith { false };

private _heading = getDir _vehicle;
private _aslZ = (getPosASL _vehicle) select 2;

// Au plus toutes les 3 s, et seulement si le véhicule a bougé (3 m) ou tourné (15°) ; sinon battement à 30 s.
// Une remise à -1e9 de COMSPEC_GpsBeaconLastAt (réglage Zeus) force toujours l'envoi.
private _last = _vehicle getVariable ["COMSPEC_GpsBeaconLastAt", -1e9];
private _dt = diag_tickTime - _last;
if (_dt < 3) exitWith { false };
private _sig = [
    round ((_pos select 0) / 3),
    round ((_pos select 1) / 3),
    round (_aslZ / 3),
    round (_heading / 15),
    alive _vehicle,
    missionNamespace getVariable ["COMSPEC_TrackSigEpoch", 0]
];
if (_dt < 30 && {_sig isEqualTo (_vehicle getVariable ["COMSPEC_GpsBeaconSig", []])}) exitWith { false };
_vehicle setVariable ["COMSPEC_GpsBeaconLastAt", diag_tickTime, false];
_vehicle setVariable ["COMSPEC_GpsBeaconSig", _sig, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _callSign = [_vehicle] call comspec_overwatch_connect_fnc_vehicleTrackCallsign;
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
