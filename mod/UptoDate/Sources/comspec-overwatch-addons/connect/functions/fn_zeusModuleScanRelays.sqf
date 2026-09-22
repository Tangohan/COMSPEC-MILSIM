/*
    Module Zeus/Eden : Scanner et remonter tous les relais dans un rayon
    Style UI relevé terrain avec feedback visuel
    
    Usage: Module Zeus → Scanner les relais → Upload automatique
*/

if (!hasInterface) exitWith {};

// Paramètres du module
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith {};

// Vérifier que le module Zeus est bien initialisé
if (isNull _logic) exitWith {
    ["Module Zeus invalide", "ERREUR", 5] call BIS_fnc_curatorHint;
};

// Position centrale pour le scan
private _centerPos = getPos _logic;
private _scanRadius = 5000; // Rayon de scan par défaut

// Trouver tous les objets de type relais dans le rayon
private _allRelays = [];
{
    if (_x getVariable ["COMSPEC_AtakRelayUid", ""] != "") then {
        _allRelays pushBack _x;
    };
} forEach (nearestObjects [_centerPos, [], _scanRadius]);

if (count _allRelays == 0) exitWith {
    ["Aucun relais trouvé dans un rayon de " + str _scanRadius + "m", "SCANNER RELAIS", 5] call BIS_fnc_curatorHint;
};

// Créer l'UI de style relevé terrain
private _display = findDisplay 312; // Display Zeus
if (isNull _display) then {
    _display = findDisplay 46; // Fallback vers display principal
};

// Compter relais à remonter et déjà remontés
private _toUpload = [];
private _alreadyUploaded = [];
private _errors = [];

{
    private _uid = _x getVariable ["COMSPEC_AtakRelayUid", ""];
    private _lastSync = _x getVariable ["COMSPEC_AtakRelayLastSync", 0];
    private _timeSinceSync = time - _lastSync;
    
    // Si jamais sync ou > 5 minutes
    if (_lastSync == 0 || _timeSinceSync > 300) then {
        _toUpload pushBack _x;
    } else {
        _alreadyUploaded pushBack _x;
    };
    
    // Détecter erreurs
    if (damage _x > 0.95) then {
        _errors pushBack [_x, "DÉTRUIT"];
    } else if (!alive _x) then {
        _errors pushBack [_x, "HORS LIGNE"];
    };
} forEach _allRelays;

// Message de statut style relevé terrain
private _statusMsg = format [
    "╔════════════════════════════════════╗
     ║  SCANNER RÉSEAU RELAIS ATAK       ║
     ╠════════════════════════════════════╣
     ║  Total détecté : %1               ║
     ║  À remonter    : %2               ║
     ║  Déjà sync     : %3               ║
     ║  Erreurs       : %4               ║
     ╚════════════════════════════════════╝",
    count _allRelays,
    count _toUpload,
    count _alreadyUploaded,
    count _errors
];

// Afficher dans hint Zeus
[_statusMsg, "SCANNER RELAIS", 10] call BIS_fnc_curatorHint;

// Créer un marqueur temporaire pour visualiser
private _marker = createMarker ["COMSPEC_RelayScan_" + str time, _centerPos];
_marker setMarkerShape "ELLIPSE";
_marker setMarkerSize [_scanRadius, _scanRadius];
_marker setMarkerColor "ColorYellow";
_marker setMarkerAlpha 0.3;
_marker setMarkerText "Zone scan relais";

// Upload automatique avec feedback
if (count _toUpload > 0) then {
    ["Upload de " + str (count _toUpload) + " relais en cours...", "UPLOAD", 3] call BIS_fnc_curatorHint;
    
    private _uploadCount = 0;
    private _failCount = 0;
    
    {
        private _success = [_x, true] call ATHENA_fnc_syncAtakRelay;
        if (_success) then {
            _uploadCount = _uploadCount + 1;
            _x setVariable ["COMSPEC_AtakRelayLastSync", time, true];
            
            // Effet visuel de confirmation
            private _pos = getPosATL _x;
            private _light = "#particlesource" createVehicleLocal _pos;
            _light setParticleParams [
                ["\A3\Data_F\ParticleEffects\Universal\Universal", 16, 7, 48],
                "", "Billboard", 1, 1,
                [0, 0, 2], [0, 0, 0.5],
                0, 0.1, 0.08, 0.3,
                [0.5, 1, 1.5], 
                [[0, 1, 0, 0.5], [0, 1, 0, 0.3], [0, 1, 0, 0]],
                [1], 1, 0, "", "", _x
            ];
            _light setParticleRandom [0.2, [0.5, 0.5, 0], [0.1, 0.1, 0.1], 0, 0.1, [0, 0, 0, 0], 0, 0];
            _light setDropInterval 0.05;
            
            [{deleteVehicle _this}, _light, 2] call CBA_fnc_waitAndExecute;
        } else {
            _failCount = _failCount + 1;
        };
        
        sleep 0.1; // Petit délai entre chaque upload
    } forEach _toUpload;
    
    // Message final
    private _finalMsg = format [
        "╔════════════════════════════════════╗
         ║  UPLOAD TERMINÉ                    ║
         ╠════════════════════════════════════╣
         ║  Succès  : %1 / %2                ║
         ║  Échecs  : %3                     ║
         ╚════════════════════════════════════╝",
        _uploadCount,
        count _toUpload,
        _failCount
    ];
    
    [{
        params ["_msg"];
        [_msg, "UPLOAD", 8] call BIS_fnc_curatorHint;
    }, [_finalMsg], 2] call CBA_fnc_waitAndExecute;
} else {
    ["Tous les relais sont déjà synchronisés", "SCAN", 5] call BIS_fnc_curatorHint;
};

// Supprimer le marqueur après 30 secondes
[{deleteMarker _this}, _marker, 30] call CBA_fnc_waitAndExecute;

// Log pour debug
diag_log format ["[COMSPEC ATAK][Scanner] Scan terminé : %1 relais détectés, %2 remontés", count _allRelays, count _toUpload];

true
