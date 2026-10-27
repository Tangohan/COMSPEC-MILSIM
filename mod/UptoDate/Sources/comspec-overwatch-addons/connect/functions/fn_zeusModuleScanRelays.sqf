/*
    Module Zeus/Eden : Scanner et remonter tous les relais dans un rayon
    Style UI relevé terrain avec feedback visuel
    
    Usage: Module Zeus → Scanner les relais → Upload automatique
*/

// Lot mpauth3 (DEF-001) : la fonction entre en liste blanche (config.cpp, allowedTargets = 1, jip = 0) pour le relais
// du serveur dédié vers le poste du Zeus, refusé en silence en mode 1. Une classe de liste blanche la rend appelable
// d'un client vers un autre client : d'un appel distant, la garde n'admet que le serveur ou, sur le serveur, le poste
// propriétaire de la logique (celui qui a posé le module), et jamais un rejeu de la file JIP. L'initialisation du module
// par le moteur n'est pas un appel distant : elle passe, comme une partie solo ou l'éditeur.
// Le poste de la logique n'est admis que si l'objet passé est une logique de ce module (contre-expertise mpauth3 v1,
// R6) : un client ne peut plus y passer sa propre unité pour faire lancer la fonction sur l'hôte.
private _lgReq = _this param [0, objNull];
if (!(_lgReq isEqualType objNull) || {(typeOf _lgReq) isNotEqualTo "COMSPEC_ModuleScanRelays"}) then { _lgReq = objNull; };
if (isRemoteExecutedJIP || {isRemoteExecuted && {!(remoteExecutedOwner in [2, owner _lgReq])}}) exitWith {
    diag_log format ["[COMSPEC ATAK][WARN][ZEUS] Scanner des relais refusé : l'appel ne vient ni du serveur ni du poste du module (poste %1, file JIP : %2)", remoteExecutedOwner, isRemoteExecutedJIP];
};

// isGlobal = 0 : sur serveur dédié, la fonction tourne sans interface. Relayer vers la machine
// du Zeus qui a posé le module (propriétaire de la logique) : retour visuel et liaison Athena y sont.
if (!hasInterface) exitWith {
    private _lg = _this param [0, objNull];
    if (isServer && {_lg isEqualType objNull} && {!isNull _lg} && {(owner _lg) > 2}) then {
        _this remoteExec ["comspec_overwatch_connect_fnc_zeusModuleScanRelays", owner _lg];
    };
};

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
private _scanRadius = _logic getVariable ["ScanRadius", 5000]; // Argument du module (défaut 5000 m)
if (!(_scanRadius isEqualType 0) || {_scanRadius <= 0}) then { _scanRadius = 5000; };

// Relais déjà référencés (placeAtakRelay) : pas de nearestObjects non filtré sur 5 km, qui figeait le jeu.
private _registry = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
if (!(_registry isEqualType [])) then { _registry = []; };
private _allRelays = _registry select {
    (_x isEqualType objNull)
    && {!isNull _x}
    && {_x distance2D _centerPos < _scanRadius}
    && {(_x getVariable ["COMSPEC_AtakRelayUid", ""]) != ""}
};

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
    } else {
        if (!alive _x) then {
            _errors pushBack [_x, "HORS LIGNE"];
        };
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
        private _success = [_x, true] call comspec_overwatch_connect_fnc_syncAtakRelay;
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
