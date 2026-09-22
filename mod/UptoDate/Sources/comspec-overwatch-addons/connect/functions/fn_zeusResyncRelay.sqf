/*
    Module Zeus : Resync rapide d'un relais
    Place le module sur un relais pour le resynchroniser immédiatement
*/

if (!hasInterface) exitWith {};

params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith {};
if (isNull _logic) exitWith {
    ["Module invalide", "ERREUR", 3] call BIS_fnc_curatorHint;
};

// Trouver le relais le plus proche
private _pos = getPos _logic;
private _nearestRelay = objNull;
private _nearestDist = 50; // Max 50m

{
    if (_x getVariable ["COMSPEC_AtakRelayUid", ""] != "") then {
        private _dist = _x distance2D _pos;
        if (_dist < _nearestDist) then {
            _nearestRelay = _x;
            _nearestDist = _dist;
        };
    };
} forEach (nearestObjects [_pos, [], 50]);

if (isNull _nearestRelay) exitWith {
    ["Aucun relais trouvé à proximité (< 50m)", "RESYNC", 5] call BIS_fnc_curatorHint;
};

// Info du relais
private _name = _nearestRelay getVariable ["COMSPEC_AtakRelayName", "Relais"];
private _uid = _nearestRelay getVariable ["COMSPEC_AtakRelayUid", "?"];

// Message de démarrage
[format ["Resynchronisation de %1...", _name], "RESYNC", 2] call BIS_fnc_curatorHint;

// Effet visuel de scan
private _relayPos = getPosATL _nearestRelay;
private _light = "#particlesource" createVehicleLocal _relayPos;
_light setParticleParams [
    ["\A3\Data_F\ParticleEffects\Universal\Universal", 16, 7, 48],
    "", "Billboard", 1, 2,
    [0, 0, 2], [0, 0, 0.5],
    0, 0.1, 0.08, 0.3,
    [0.5, 1, 1.5, 1], 
    [[1, 1, 0, 0.5], [1, 1, 0, 0.3], [1, 0.5, 0, 0.2], [0, 0, 0, 0]],
    [1], 1, 0, "", "", _nearestRelay
];
_light setParticleRandom [0.2, [0.5, 0.5, 0], [0.1, 0.1, 0.1], 0, 0.1, [0, 0, 0, 0], 0, 0];
_light setDropInterval 0.03;

// Exécuter le resync
[{
    params ["_relay", "_name", "_light"];
    
    private _success = [_relay, true] call ATHENA_fnc_syncAtakRelay;
    
    if (_success) then {
        _relay setVariable ["COMSPEC_AtakRelayLastSync", time, true];
        
        // Effet de succès (vert)
        private _relayPos = getPosATL _relay;
        private _successLight = "#particlesource" createVehicleLocal _relayPos;
        _successLight setParticleParams [
            ["\A3\Data_F\ParticleEffects\Universal\Universal", 16, 7, 48],
            "", "Billboard", 1, 1,
            [0, 0, 2], [0, 0, 1],
            0, 0.2, 0.1, 0.5,
            [1, 2], 
            [[0, 1, 0, 1], [0, 1, 0, 0]],
            [1], 1, 0, "", "", _relay
        ];
        _successLight setParticleRandom [0.3, [1, 1, 0], [0.2, 0.2, 0.2], 0, 0.2, [0, 0, 0, 0], 0, 0];
        _successLight setDropInterval 0.05;
        
        [{deleteVehicle _this}, _successLight, 3] call CBA_fnc_waitAndExecute;
        
        [format ["✅ %1 resynchronisé avec succès", _name], "RESYNC", 5] call BIS_fnc_curatorHint;
    } else {
        [format ["❌ Échec de resynchronisation de %1", _name], "RESYNC", 5] call BIS_fnc_curatorHint;
    };
    
    deleteVehicle _light;
    
}, [_nearestRelay, _name, _light], 1] call CBA_fnc_waitAndExecute;

diag_log format ["[COMSPEC ATAK][Zeus] Resync manuel : %1", _uid];

true
