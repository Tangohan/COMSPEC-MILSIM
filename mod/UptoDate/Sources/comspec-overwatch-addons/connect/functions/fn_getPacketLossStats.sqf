/*
    Retourne les statistiques de perte de paquets formatées pour l'envoi au serveur.
    Intègre le plancher d'intensité des zones roleplay (sinon perte toujours à 0).
*/

private _stats = missionNamespace getVariable ["COMSPEC_PacketStats", createHashMap];

private _windowSent = _stats getOrDefault ["window_sent", []];
private _windowReceived = _stats getOrDefault ["window_received", []];

private _measurementDuration = 0;
if (count _windowSent > 1) then {
    private _oldest = (_windowSent select 0) select 1;
    private _newest = (_windowSent select (count _windowSent - 1)) select 1;
    _measurementDuration = _newest - _oldest;
};

private _sentCount = count _windowSent;
private _receivedCount = count _windowReceived;
private _lossPercent = 0;

if (_sentCount > 0) then {
    private _lostCount = _sentCount - _receivedCount;
    _lossPercent = (_lostCount / _sentCount) * 100;
};

// Plancher zone (intensité) — le compteur réseau réel est souvent vide
private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!isNil "_zoneFx" && {_zoneFx isEqualType createHashMap}) then {
    private _floor = _zoneFx getOrDefault ["packet_loss_floor", 0];
    if (!(_floor isEqualType 0)) then { _floor = 0; };
    private _mult = _zoneFx getOrDefault ["packet_loss_multiplier", 1];
    if (!(_mult isEqualType 0) || {_mult <= 0}) then { _mult = 1; };
    _lossPercent = ((_lossPercent * _mult) max _floor) min 100;
};

private _result = createHashMap;
_result set ["packet_loss_percent", _lossPercent max 0 min 100];
_result set ["packets_sent_total", _stats getOrDefault ["total_sent", 0]];
_result set ["packets_received_total", _stats getOrDefault ["total_received", 0]];
_result set ["packets_sent_window", _sentCount];
_result set ["packets_received_window", _receivedCount];
_result set ["measurement_duration", _measurementDuration];

_result
