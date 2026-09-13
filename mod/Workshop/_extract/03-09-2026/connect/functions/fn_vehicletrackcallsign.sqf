/*
    Indicatif unique pour le suivi véhicule Athena.
    Balise GPS : identifiant stable. Sinon : nom du modèle (comportement historique).
*/
params [
    ["_vehicle", objNull, [objNull]]
];
if (isNull _vehicle) exitWith { "Vehicule" };

private _custom = _vehicle getVariable ["COMSPEC_GpsCallsign", ""];
if (!(_custom isEqualType "")) then { _custom = str _custom };
_custom = trim _custom;
if (_custom isNotEqualTo "") exitWith { _custom };

if ([_vehicle, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag) then {
    private _id = _vehicle getVariable ["COMSPEC_GpsBeaconId", ""];
    if (!(_id isEqualType "")) then { _id = str _id };
    if (_id isEqualTo "") then {
        _id = format ["GPS-%1", ((netId _vehicle) splitString ":") joinString "-"];
        _vehicle setVariable ["COMSPEC_GpsBeaconId", _id, true];
    };
    _id
} else {
    private _n = getText (configOf _vehicle >> "displayName");
    if (_n isEqualTo "") then { _n = typeOf _vehicle };
    if (_n isEqualTo "") then { _n = "Vehicule" };
    _n
}
