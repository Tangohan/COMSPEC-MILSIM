/*
 * Auteur: COMSPEC
 * Update position et statut véhicule (appelé automatiquement toutes les 5-10s)
 *
 * Arguments:
 * 0: Véhicule <OBJECT>
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Exemple:
 * [vehicle player] call comspec_overwatch_connect_fnc_updateVehicleTracking;
 * 
 * Note: Cette fonction est appelée automatiquement par le système de tracking
 */

params [
    ["_vehicle", objNull, [objNull]]
];

// Validation
if (isNull _vehicle) exitWith {false};
if (_vehicle isEqualTo player) exitWith {false}; // Pas un véhicule

// Debounce / grâce REAPP (évite flood extension pendant spike ACE+MRH)
if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {false};
private _lastPost = _vehicle getVariable ["COMSPEC_VehTrackLastAt", -1e9];
if ((diag_tickTime - _lastPost) < 5) exitWith {false};
_vehicle setVariable ["COMSPEC_VehTrackLastAt", diag_tickTime, false];

// Préparer données
private _vehicleData = createHashMap;

// Identification (balise GPS : clé unique + nom lisible)
private _pretty = getText (configOf _vehicle >> "displayName");
private _customName = _vehicle getVariable ["COMSPEC_GpsCallsign", ""];
if (!(_customName isEqualType "")) then { _customName = str _customName };
_customName = trim _customName;
if (_customName isNotEqualTo "") then { _pretty = _customName };
if (_pretty isEqualTo "") then { _pretty = typeOf _vehicle };
if (_pretty isEqualTo "") then { _pretty = "Véhicule" };
_vehicleData set ["vehicle_callsign", [_vehicle] call comspec_overwatch_connect_fnc_vehicleTrackCallsign];
_vehicleData set ["vehicle_name", _pretty];
_vehicleData set ["vehicle_type", typeOf _vehicle];

private _vehicleClass = [_vehicle] call comspec_overwatch_connect_fnc_bftPlatform;
if (_vehicleClass isEqualTo "INFANTRY") then { _vehicleClass = "LIGHT_VEHICLE" };
_vehicleData set ["vehicle_class", _vehicleClass];

// Côté
private _side = side _vehicle;
private _sideStr = "BLUFOR";
if (_side isEqualTo EAST) then {_sideStr = "OPFOR"};
if (_side isEqualTo INDEPENDENT) then {_sideStr = "INDEPENDENT"};
if (_side isEqualTo CIVILIAN) then {_sideStr = "CIVILIAN"};
_vehicleData set ["side", _sideStr];

// Équipage
private _crew = crew _vehicle;
private _commander = commander _vehicle;
_vehicleData set ["crew_count", count _crew];
_vehicleData set ["crew_max", ([_vehicle, true] call bis_fnc_crewCount) select 0];
if (!isNull _commander) then {
    _vehicleData set ["crew_commander_callsign", name _commander];
};

// Passagers
private _cargoCount = {_x in _vehicle && !(_x in [driver _vehicle, gunner _vehicle, commander _vehicle])} count _crew;
_vehicleData set ["passenger_count", _cargoCount];
_vehicleData set ["passenger_max", ([_vehicle, true] call bis_fnc_crewCount) select 2];

// Position et mouvement
private _pos = getPosWorld _vehicle;
_vehicleData set ["pos_x", _pos select 0];
_vehicleData set ["pos_y", _pos select 1];
_vehicleData set ["heading", getDir _vehicle];
_vehicleData set ["speed", speed _vehicle];

// Fuel et munitions
_vehicleData set ["fuel_percent", (fuel _vehicle) * 100];
private _ammoPct = 0;
private _gunner = gunner _vehicle;
if (!isNull _gunner) then {
    private _w = currentWeapon _vehicle;
    if (_w != "") then {
        private _a = _vehicle ammo _w;
        if (!isNil "_a") then { _ammoPct = (_a min 100) max 0; };
    };
};
_vehicleData set ["ammo_percent", _ammoPct];

// Santé composants
_vehicleData set ["engine_health", (1 - (_vehicle getHitPointDamage "HitEngine")) * 100];
_vehicleData set ["hull_health", (1 - (damage _vehicle)) * 100];
_vehicleData set ["tracks_wheels_health", (1 - (_vehicle getHitPointDamage "HitLTrack")) * 100];
_vehicleData set ["turret_health", (1 - (_vehicle getHitPointDamage "HitTurret")) * 100];

// Statut
private _status = "OPERATIONAL";
if (!canMove _vehicle) then {_status = "IMMOBILIZED"};
if (!alive _vehicle) then {_status = "DESTROYED"};
if (damage _vehicle > 0.8) then {_status = "DAMAGED"};
_vehicleData set ["status", _status];

// Mission type (si défini par variable)
private _isBeacon = [_vehicle, "COMSPEC_GpsBeacon"] call comspec_overwatch_connect_fnc_isObjectFlag;
private _missionType = _vehicle getVariable ["COMSPEC_MissionType", ""];
if (!(_missionType isEqualType "")) then { _missionType = str _missionType };
if (_missionType isEqualTo "") then {
    _missionType = if (_isBeacon) then { "GPS_BEACON" } else { "PATROL" };
};
_vehicleData set ["mission_type", _missionType];

// Destination (si waypoint actif)
private _group = group (effectiveCommander _vehicle);
if (!isNull _group) then {
    private _waypoint = currentWaypoint _group;
    if (_waypoint > 0) then {
        private _wpPos = waypointPosition [_group, _waypoint];
        if (count _wpPos > 0) then {
            _vehicleData set ["destination_pos_x", _wpPos select 0];
            _vehicleData set ["destination_pos_y", _wpPos select 1];
        };
    };
};

// Envoyer via extension (upsert)
private _jsonString = [_vehicleData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["UpdateVehicleTracking", [_jsonString]];

// Vérifier alertes critiques (fuel/ammo)
if ((fuel _vehicle) < 0.15) then {
    // Fuel critique - demander service auto si pas déjà fait
    private _lastFuelAlert = _vehicle getVariable ["COMSPEC_LastFuelAlert", 0];
    if (time - _lastFuelAlert > 300) then { // Max 1 alerte tous les 5min
        [_vehicle, "REFUEL", "Carburant critique"] spawn {
            params ["_veh", "_type", "_details"];
            sleep 2; // Délai pour éviter spam
            [_veh, _type, "HIGH", _details] call comspec_overwatch_connect_fnc_requestVehicleService;
        };
        _vehicle setVariable ["COMSPEC_LastFuelAlert", time];
    };
};

if (!isNull (gunner _vehicle) && {(ammo (gunner _vehicle)) < 0.2}) then {
    // Munitions critiques
    private _lastAmmoAlert = _vehicle getVariable ["COMSPEC_LastAmmoAlert", 0];
    if (time - _lastAmmoAlert > 300) then {
        [_vehicle, "REARM", "Munitions critiques"] spawn {
            params ["_veh", "_type", "_details"];
            sleep 2;
            [_veh, _type, "MEDIUM", _details] call comspec_overwatch_connect_fnc_requestVehicleService;
        };
        _vehicle setVariable ["COMSPEC_LastAmmoAlert", time];
    };
};

(_result select 0) isEqualTo "OK"
