// Send current vehicle/unit logistics status to C2. Params: [missionId, assetId, callsign, vehicle]
// vehicle can be unit (infantry) or vehicle object. Builds LOGISTICS_STATUS payload and calls extension.
params [
    ["_missionId", "mission_1_map_1", [""]],
    ["_assetId", "", [""]],
    ["_callsign", "", [""]],
    ["_vehicle", objNull, [objNull]]
];
if (isNull _vehicle) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _vehicleClass = typeOf _vehicle;
private _fuel = 1.0;
private _damage = 0.0;
private _crewCount = 0;
private _cargoFree = 0;
private _slingload = false;
private _magCount = 0;
private _weaponsOnline = true;
private _persFit = 1;
private _persWia = 0;
private _persKia = 0;

if (vehicle _vehicle != _vehicle) then {
    _vehicle = vehicle _vehicle;
    _vehicleClass = typeOf _vehicle;
    _fuel = fuel _vehicle;
    _damage = getDammage _vehicle;
    if (_damage < 0) then { _damage = 0; };
    if (_damage > 1) then { _damage = 1; };
    _crewCount = { alive _x } count (crew _vehicle);
    _cargoFree = (_vehicle emptyPositions "cargo") max 0;
    _slingload = getNumber (configFile >> "CfgVehicles" >> _vehicleClass >> "slingLoadMaxCargoMass") > 0;
    private _mags = magazinesAllTurrets _vehicle;
    _magCount = count _mags;
} else {
    if (_vehicle isKindOf "CAManBase") then {
        _magCount = count (magazines _vehicle);
        _damage = getDammage _vehicle;
        _crewCount = 1;
        _fuel = 1.0;
        // Synthèse personnelle légère (LOGSTAT groupe = 1 opérateur côté client)
        private _med = [_vehicle] call comspec_overwatch_connect_fnc_getMedicalState;
        private _parts = _med splitString "|";
        private _h = if ((count _parts) > 0) then { toLower (_parts select 0) } else { "ok" };
        if (_h in ["unconscious", "cardiac_arrest", "critical"]) then {
            _persFit = 0;
            _persWia = 1;
        };
        if (_h in ["dead", "kia"] || {!alive _vehicle}) then {
            _persFit = 0;
            _persWia = 0;
            _persKia = 1;
        };
    } else {
        _fuel = fuel _vehicle;
        _damage = getDammage _vehicle;
        _crewCount = { alive _x } count (crew _vehicle);
        _cargoFree = (_vehicle emptyPositions "cargo") max 0;
        _slingload = getNumber (configFile >> "CfgVehicles" >> _vehicleClass >> "slingLoadMaxCargoMass") > 0;
        _magCount = count (magazinesAllTurrets _vehicle);
    };
};

if (_assetId isEqualTo "") then { _assetId = _callsign; };
if (_callsign isEqualTo "") then { _callsign = name _vehicle; };

// Delta : ne pas renvoyer un LOGSTAT identique (empreinte locale).
private _sig = format [
    "%1|%2|%3|%4|%5|%6|%7",
    _assetId,
    round (_fuel * 20),
    _magCount,
    round (_damage * 20),
    _crewCount,
    _cargoFree,
    _persWia + (_persKia * 10)
];
private _lastSig = missionNamespace getVariable ["COMSPEC_LogisticsLastSig", ""];
if (_sig isEqualTo _lastSig) exitWith { false };
missionNamespace setVariable ["COMSPEC_LogisticsLastSig", _sig, false];

private _ammoJson = format [
    '{"magazinesCount":%1,"weaponsOnline":%2,"pers_fit":%3,"pers_wia":%4,"pers_kia":%5}',
    _magCount,
    if (_weaponsOnline) then { "true" } else { "false" },
    _persFit,
    _persWia,
    _persKia
];

private _payload = format [
    '{"missionId":"%1","assetId":"%2","callsign":"%3","vehicle_class":"%4","fuel_ratio":%5,"ammo_state_json":%6,"damage_ratio":%7,"crew_count":%8,"cargo_slots_free":%9,"slingload_capable":%10}',
    _missionId,
    _assetId,
    _callsign,
    _vehicleClass,
    _fuel,
    _ammoJson,
    _damage,
    _crewCount,
    _cargoFree,
    if (_slingload) then { "true" } else { "false" }
];

"COMSPECExtension" callExtension ["Logistics.Update", [_payload]];

if (!isNil "comspec_overwatch_connect_fnc_emitTelemetryEvent") then {
    private _pos = getPosASL _vehicle;
    ["logstat", createHashMapFromArray [
        ["missionId", _missionId],
        ["assetId", _assetId],
        ["callsign", _callsign],
        ["call_sign", _callsign],
        ["vehicle_class", _vehicleClass],
        ["fuel_ratio", _fuel],
        ["damage_ratio", _damage],
        ["crew_count", _crewCount],
        ["cargo_slots_free", _cargoFree],
        ["slingload_capable", _slingload],
        ["magazinesCount", _magCount],
        ["pers_fit", _persFit],
        ["pers_wia", _persWia],
        ["pers_kia", _persKia],
        ["x", _pos select 0],
        ["y", _pos select 1]
    ], 2] call comspec_overwatch_connect_fnc_emitTelemetryEvent;
};

true
