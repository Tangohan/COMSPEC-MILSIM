// Send current vehicle/unit logistics status to C2. Params: [missionId, assetId, callsign, vehicle]
// vehicle can be unit (infantry) or vehicle object. Builds LOGISTICS_STATUS payload and calls extension.
params [
    ["_missionId", "mission_1_map_1", [""]],
    ["_assetId", "", [""]],
    ["_callsign", "", [""]],
    ["_vehicle", objNull, [objNull]]
];
if (isNull _vehicle) exitWith {};

private _vehicleClass = typeOf _vehicle;
private _fuel = 1.0;
private _damage = 0.0;
private _crewCount = 0;
private _cargoFree = 0;
private _slingload = false;
private _magCount = 0;
private _weaponsOnline = true;

if (vehicle _vehicle != _vehicle) then {
    _vehicle = vehicle _vehicle;
    _fuel = fuel _vehicle;
    _damage = 1.0 - (getDammage _vehicle);
    if (_damage < 0) then { _damage = 0; };
    _crewCount = count (crew _vehicle);
    _cargoFree = (_vehicle emptyPositions "cargo") max 0;
    _slingload = getNumber (configFile >> "CfgVehicles" >> _vehicleClass >> "slingLoadMaxCargoMass") > 0;
} else {
    _magCount = count (magazines _vehicle);
    _damage = 1.0 - (getDammage _vehicle);
    if (_damage < 0) then { _damage = 0; };
};

if (_assetId isEqualTo "") then { _assetId = _callsign; };
if (_callsign isEqualTo "") then { _callsign = name _vehicle; };

private _payload = format [
    '{"missionId":"%1","assetId":"%2","callsign":"%3","vehicle_class":"%4","fuel_ratio":%5,"ammo_state_json":{"magazinesCount":%6,"weaponsOnline":%7},"damage_ratio":%8,"crew_count":%9,"cargo_slots_free":%10,"slingload_capable":%11}',
    _missionId,
    _assetId,
    _callsign,
    _vehicleClass,
    _fuel,
    _magCount,
    if (_weaponsOnline) then { "true" } else { "false" },
    _damage,
    _crewCount,
    _cargoFree,
    if (_slingload) then { "true" } else { "false" }
];

"COMSPECExtension" callExtension ["Logistics.Update", [_payload]];
