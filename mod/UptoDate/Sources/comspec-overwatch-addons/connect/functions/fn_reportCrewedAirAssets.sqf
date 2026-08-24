/*
    Remonte les aéronefs occupés (joueur ou IA) vers Athena, sans manifeste de vol.
    Un aéronef = un véhicule aérien avec au moins un vivant à bord.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith { false };

private _now = diag_tickTime;
private _sent = 0;

{
    if (_sent >= 12) then { break };
    if (isNull _x || {!alive _x}) then { continue };
    if (_x isKindOf "ParachuteBase") then { continue };
    if !(
        _x isKindOf "Air"
        || {_x isKindOf "Helicopter"}
        || {_x isKindOf "Plane"}
        || {_x isKindOf "UAV"}
        || {unitIsUAV _x}
    ) then { continue };

    private _crew = (crew _x) select { alive _x && {_x isKindOf "CAManBase"} };
    if (_crew isEqualTo []) then { continue };

    private _last = _x getVariable ["COMSPEC_AirOccLastAt", -1e9];
    private _pos = getPosWorld _x;
    if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) then { continue };
    private _heading = getDir _x;
    private _crewN = count _crew;
    private _sig = format [
        "%1|%2|%3|%4",
        round (_pos select 0),
        round (_pos select 1),
        round _heading,
        _crewN
    ];
    private _lastSig = _x getVariable ["COMSPEC_AirOccSig", ""];
    private _heartbeatOk = (_now - _last) >= 18;
    private _changed = _sig isNotEqualTo _lastSig;
    if (!_heartbeatOk && {!_changed}) then { continue };
    if ((_now - _last) < 7) then { continue };

    _x setVariable ["COMSPEC_AirOccLastAt", _now, false];
    _x setVariable ["COMSPEC_AirOccSig", _sig, false];

    private _model = getText (configFile >> "CfgVehicles" >> typeOf _x >> "displayName");
    if (_model isEqualTo "") then { _model = getText (configOf _x >> "displayName"); };
    if (_model isEqualTo "") then { _model = typeOf _x; };
    if (_model isEqualTo "") then { _model = "Aeronef"; };

    private _callsign = _x getVariable ["COMSPEC_Callsign", ""];
    if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then {
        _callsign = _x getVariable ["COMSPEC_GpsCallsign", ""];
    };
    if (!(_callsign isEqualType "")) then { _callsign = str _callsign; };
    _callsign = trim _callsign;
    if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
        private _grp = group (effectiveCommander _x);
        if (isNull _grp && {_crew isNotEqualTo []}) then { _grp = group (_crew select 0); };
        _callsign = trim (groupId _grp);
    };
    if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
        _callsign = _model;
    };
    if (_callsign isEqualTo "") then { _callsign = "Aeronef"; };

    private _aircraftType = [_x] call comspec_overwatch_connect_fnc_getAircraftType;
    if ((toLower _aircraftType) isEqualTo "unknown" || {_aircraftType isEqualTo "ground"}) then {
        if (_x isKindOf "Helicopter") then {
            _aircraftType = "helicopter";
        } else {
            if (_x isKindOf "UAV" || {unitIsUAV _x}) then {
                _aircraftType = "uav";
            } else {
                _aircraftType = "plane";
            };
        };
    };

    private _airborne = !(isTouchingGround _x) && {((getPosASL _x) select 2) > 5};
    private _status = if (_airborne) then { "IN-FLIGHT" } else { "AVAILABLE" };

    private _side = side _x;
    if (_side isEqualTo sideUnknown || {_side isEqualTo civilian}) then {
        _side = side (effectiveCommander _x);
    };
    private _sideStr = switch (_side) do {
        case east: { "EAST" };
        case independent: { "GUER" };
        case civilian: { "CIV" };
        default { "WEST" };
    };

    private _posAsl = getPosASL _x;
    private _vehicleId = netId _x;
    private _fuelPct = (round ((fuel _x) * 100)) max 0 min 100;
    private _pilotName = name (effectiveCommander _x);
    if (!(_pilotName isEqualType "")) then { _pilotName = ""; };

    private _payload = createHashMapFromArray [
        ["mapId", 1],
        ["callsign", _callsign],
        ["call_sign", _callsign],
        ["model", _model],
        ["aircraft_type", _aircraftType],
        ["source", "occupancy"],
        ["inferred", true],
        ["vehicle_id", _vehicleId],
        ["alt", _posAsl select 2],
        ["altitude", _posAsl select 2],
        ["heading", _heading],
        ["pos", [_pos select 0, _pos select 1]],
        ["pos_x", _pos select 0],
        ["pos_y", _pos select 1],
        ["side", _sideStr],
        ["aircraft_count", 1],
        ["status", _status],
        ["fuel_pct", _fuelPct],
        ["pilot", _pilotName],
        ["lastUpdate", floor time]
    ];

    private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
    if (!(_json isEqualType "") || {_json isEqualTo ""}) then { continue };

    "COMSPECExtension" callExtension ["SendFlightManifest", [_json]];
    _sent = _sent + 1;
} forEach vehicles;

_sent > 0
