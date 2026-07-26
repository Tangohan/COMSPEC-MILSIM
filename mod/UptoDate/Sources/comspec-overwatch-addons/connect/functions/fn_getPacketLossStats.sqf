/*
    Retourne les statistiques de perte de paquets formatées pour l'envoi au serveur.
    
    Returns:
        HashMap avec les clés:
            packet_loss_percent - Pourcentage de perte (0-100)
            packets_sent_total - Total de paquets envoyés
            packets_received_total - Total de paquets reçus
            packets_sent_window - Paquets envoyés dans la fenêtre
            packets_received_window - Paquets reçus dans la fenêtre
            measurement_duration - Durée de mesure en secondes
*/

private _stats = missionNamespace getVariable ["COMSPEC_PacketStats", createHashMap];

private _windowSent = _stats getOrDefault ["window_sent", []];
private _windowReceived = _stats getOrDefault ["window_received", []];

// Calculer la durée de mesure
private _measurementDuration = 0;
if (count _windowSent > 1) then {
    private _oldest = (_windowSent select 0) select 1;
    private _newest = (_windowSent select (count _windowSent - 1)) select 1;
    _measurementDuration = _newest - _oldest;
};

// Calculer le taux de perte
private _sentCount = count _windowSent;
private _receivedCount = count _windowReceived;
private _lossPercent = 0;

if (_sentCount > 0) then {
    private _lostCount = _sentCount - _receivedCount;
    _lossPercent = (_lostCount / _sentCount) * 100;
};

// Créer le résultat
private _result = createHashMap;
_result set ["packet_loss_percent", _lossPercent max 0 min 100];
_result set ["packets_sent_total", _stats getOrDefault ["total_sent", 0]];
_result set ["packets_received_total", _stats getOrDefault ["total_received", 0]];
_result set ["packets_sent_window", _sentCount];
_result set ["packets_received_window", _receivedCount];
_result set ["measurement_duration", _measurementDuration];

_result
