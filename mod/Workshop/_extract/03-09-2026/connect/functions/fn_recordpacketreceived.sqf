/*
    Enregistre la réception d'un paquet pour le calcul du packet loss.
    Appelé quand une requête HTTP réussit.
    
    Params:
        _requestId - Identifiant de la requête
*/

params [["_requestId", ""]];

if (_requestId isEqualTo "") exitWith {};

private _stats = missionNamespace getVariable ["COMSPEC_PacketStats", createHashMap];

// Vérifier si la requête était en attente
private _pending = _stats getOrDefault ["pending_requests", createHashMap];
if (!(_requestId in _pending)) exitWith {
    // Requête inconnue ou déjà traitée
};

// Retirer de la liste en attente
_pending deleteAt _requestId;
_stats set ["pending_requests", _pending];

// Incrémenter compteur reçus
private _totalReceived = _stats getOrDefault ["total_received", 0];
_stats set ["total_received", _totalReceived + 1];

// Ajouter à la fenêtre glissante
private _windowReceived = _stats getOrDefault ["window_received", []];
_windowReceived pushBack [_requestId, time];
_stats set ["window_received", _windowReceived];

true
