/*
    Retourne les statistiques de perte de paquets formatées pour l'envoi au serveur.
    Intègre le plancher d'intensité des zones roleplay (sinon perte toujours à 0).
    Pas de mesure réelle des paquets (recordPacketSent / recordPacketReceived retirés, sans appelant) :
    la perte part de 0, seuls le plancher de zone et la simulation de dégradation la font monter.
*/

private _lossPercent = 0;

private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!isNil "_zoneFx" && {_zoneFx isEqualType createHashMap}) then {
    private _floor = _zoneFx getOrDefault ["packet_loss_floor", 0];
    if (!(_floor isEqualType 0)) then { _floor = 0; };
    private _mult = _zoneFx getOrDefault ["packet_loss_multiplier", 1];
    if (!(_mult isEqualType 0) || {_mult <= 0}) then { _mult = 1; };
    _lossPercent = ((_lossPercent * _mult) max _floor) min 100;
};

if (!isNil "comspec_overwatch_connect_fnc_isLinkDegradeSimActive"
    && {[] call comspec_overwatch_connect_fnc_isLinkDegradeSimActive}
) then {
    private _sim = missionNamespace getVariable ["COMSPEC_LinkDegradeSimState", createHashMap];
    if (!(_sim isEqualType createHashMap)) then { _sim = createHashMap; };
    private _simFloor = _sim getOrDefault ["loss_floor", 18];
    if (!(_simFloor isEqualType 0)) then { _simFloor = 18; };
    _lossPercent = (_lossPercent max _simFloor) min 100;
};

private _result = createHashMap;
_result set ["packet_loss_percent", _lossPercent max 0 min 100];
_result set ["packets_sent_total", 0];
_result set ["packets_received_total", 0];
_result set ["packets_sent_window", 0];
_result set ["packets_received_window", 0];
_result set ["measurement_duration", 0];

_result
