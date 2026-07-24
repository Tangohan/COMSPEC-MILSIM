/*
    Calcule le taux de perte de paquets effectif en suivant les requêtes envoyées vs réussies.
    Retourne un tableau [packets_sent, packets_received, loss_percent]
*/

// Initialisation si nécessaire
if (isNil {missionNamespace getVariable "COMSPEC_PacketStats"}) then {
    missionNamespace setVariable ["COMSPEC_PacketStats", createHashMap, false];
    private _stats = missionNamespace getVariable "COMSPEC_PacketStats";
    _stats set ["total_sent", 0];
    _stats set ["total_received", 0];
    _stats set ["window_sent", []];
    _stats set ["window_received", []];
    _stats set ["window_size", 100]; // Dernières 100 requêtes
    _stats set ["last_cleanup", time];
};

private _stats = missionNamespace getVariable ["COMSPEC_PacketStats", createHashMap];

// Nettoyage périodique (toutes les 5 minutes)
if ((time - (_stats getOrDefault ["last_cleanup", 0])) > 300) then {
    private _sent = _stats getOrDefault ["window_sent", []];
    private _received = _stats getOrDefault ["window_received", []];
    
    // Garder seulement les 100 dernières entrées
    private _windowSize = _stats getOrDefault ["window_size", 100];
    if (count _sent > _windowSize) then {
        _stats set ["window_sent", _sent select [(count _sent - _windowSize), _windowSize]];
    };
    if (count _received > _windowSize) then {
        _stats set ["window_received", _received select [(count _received - _windowSize), _windowSize]];
    };
    
    _stats set ["last_cleanup", time];
};

// Calculer le taux de perte sur la fenêtre glissante
private _windowSent = _stats getOrDefault ["window_sent", []];
private _windowReceived = _stats getOrDefault ["window_received", []];
private _sentCount = count _windowSent;
private _receivedCount = count _windowReceived;

private _lossPercent = 0;
if (_sentCount > 0) then {
    private _lostCount = _sentCount - _receivedCount;
    _lossPercent = (_lostCount / _sentCount) * 100;
};

// Retourner les statistiques
[
    _stats getOrDefault ["total_sent", 0],
    _stats getOrDefault ["total_received", 0],
    _lossPercent max 0 min 100
]
