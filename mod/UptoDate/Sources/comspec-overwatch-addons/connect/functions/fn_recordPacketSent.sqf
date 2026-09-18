/*
    Enregistre l'envoi d'un paquet pour le calcul du packet loss.
    Appelé avant chaque requête HTTP.
    
    Params:
        _requestId - Identifiant unique de la requête (optionnel)
    
    Returns:
        _requestId - L'ID de la requête (généré si non fourni)
*/

params [["_requestId", ""]];

if (_requestId isEqualTo "") then {
    _requestId = format ["req_%1_%2", time, floor (random 99999)];
};

private _stats = missionNamespace getVariable ["COMSPEC_PacketStats", createHashMap];

// Incrémenter compteur total
private _totalSent = _stats getOrDefault ["total_sent", 0];
_stats set ["total_sent", _totalSent + 1];

// Ajouter à la fenêtre glissante
private _windowSent = _stats getOrDefault ["window_sent", []];
_windowSent pushBack [_requestId, time];
if ((count _windowSent) > 100) then {
    _windowSent = _windowSent select [((count _windowSent) - 100) max 0, 100];
};
_stats set ["window_sent", _windowSent];

// Enregistrer l'ID dans une hashmap pour vérification rapide
private _pending = _stats getOrDefault ["pending_requests", createHashMap];
_pending set [_requestId, time];
if ((count _pending) > 100) then {
    private _cutoff = time - 60;
    {
        if (_y < _cutoff) then { _pending deleteAt _x; };
    } forEach _pending;
};
_stats set ["pending_requests", _pending];
missionNamespace setVariable ["COMSPEC_PacketStats", _stats, false];

_requestId
