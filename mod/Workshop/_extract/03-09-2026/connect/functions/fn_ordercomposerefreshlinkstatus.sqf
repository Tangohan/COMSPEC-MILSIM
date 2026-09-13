/*
    Bandeau état liaison ATAK dans la fenêtre rapide d’émission (in-game).
    Affiche fréquence, débit, stabilité, pertes de paquets, latence.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_OrderCompose_Display", displayNull];
if (isNull _disp) exitWith {};

private _ctrl = _disp displayCtrl 9550;
if (isNull _ctrl) exitWith {};

[] call comspec_overwatch_connect_fnc_measureLatency;

private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
if (!(_ms isEqualType 0)) then { _ms = -1; };

private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
private _sentWin = _pkt getOrDefault ["packets_sent_window", 0];
private _measDur = _pkt getOrDefault ["measurement_duration", 0];

private _mapId = missionNamespace getVariable ["comspec_overwatch_map_id", 1];
private _posInterval = missionNamespace getVariable ["comspec_overwatch_position_interval", 3];
if (!(_posInterval isEqualType 0)) then { _posInterval = 3; };
_posInterval = (_posInterval max 1) min 60;

private _radioRaw = [player] call comspec_overwatch_connect_fnc_getRadioState;
private _radioParts = _radioRaw splitString "|";
private _radioType = if ((count _radioParts) > 0) then { _radioParts select 0 } else { "N/A" };
private _radioFreq = if ((count _radioParts) > 1) then { _radioParts select 1 } else { "N/A" };

private _dataChannel = ((floor ((abs _mapId) mod 11)) + 1);
private _dataFreqMhz = 2400 + (_dataChannel * 5);

private _bitrateKbps = 0;
if (_measDur > 0.5 && {_sentWin > 0}) then {
    _bitrateKbps = ((_sentWin * 0.45) / _measDur) * 8;
};
if (_bitrateKbps < 0.05 && {_state isEqualTo "linked"}) then {
    _bitrateKbps = (0.45 / _posInterval) * 8;
};

private _stability = 100;
_stability = _stability - ((_loss min 100) * 0.85);
if (_ms >= 0) then {
    if (_ms > 120) then { _stability = _stability - ((_ms - 120) / 18); };
} else {
    if (_state isNotEqualTo "linked") then { _stability = _stability - 35; };
};
_stability = (_stability max 0) min 100;

private _stabLabel = switch (true) do {
    case (_stability >= 85): { "Excellente" };
    case (_stability >= 70): { "Bonne" };
    case (_stability >= 50): { "Correcte" };
    case (_stability >= 30): { "Faible" };
    default { "Critique" };
};
private _stabColor = switch (true) do {
    case (_stability >= 70): { "#7dffb0" };
    case (_stability >= 50): { "#ffd27a" };
    default { "#ff8a7a" };
};

private _stateLabel = switch (_state) do {
    case "linked": { "En liaison" };
    case "connecting": { "Connexion…" };
    case "disabled": { "Désactivé" };
    default { "Hors liaison" };
};
private _stateColor = switch (_state) do {
    case "linked": { "#7dffb0" };
    case "connecting": { "#ffd27a" };
    default { "#ff8a7a" };
};

private _latencyTxt = if (_ms < 0) then { "—" } else { format ["%1 ms", round _ms]; };
private _lossColor = switch (true) do {
    case (_loss <= 2): { "#7dffb0" };
    case (_loss <= 8): { "#ffd27a" };
    default { "#ff8a7a" };
};

private _freqTxt = if (_radioFreq isEqualTo "N/A" || {_radioType isEqualTo "N/A"}) then {
    format ["Données %1 MHz · ch.%2", _dataFreqMhz, _dataChannel]
} else {
    format ["Radio %1 MHz · données %2 MHz", _radioFreq, _dataFreqMhz]
};

private _bitrateTxt = if (_bitrateKbps < 0.1) then {
    "—"
} else {
    if (_bitrateKbps < 10) then {
        format ["%1 kbit/s", (_bitrateKbps toFixed 1)]
    } else {
        format ["%1 kbit/s", round _bitrateKbps]
    };
};

private _txt = format [
    "<t align='left' size='0.52' color='#8aa0b4'>ÉTAT ATAK</t><br/>" +
    "<t align='left' size='0.58'>" +
    "<t color='%1'>%2</t>" +
    "  ·  <t color='#c8e8ff'>%3</t>" +
    "  ·  débit <t color='#e8f4f0'>%4</t>" +
    "  ·  stabilité <t color='%5'>%6 (%7%%)</t>" +
    "  ·  latence <t color='#e8f4f0'>%8</t>" +
    "  ·  pertes <t color='%9'>%10%%</t>" +
    "</t>",
    _stateColor,
    _stateLabel,
    _freqTxt,
    _bitrateTxt,
    _stabColor,
    _stabLabel,
    round _stability,
    _latencyTxt,
    _lossColor,
    (_loss toFixed 1)
];

_ctrl ctrlSetStructuredText parseText _txt;
