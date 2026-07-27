/*
    Construit et affiche le diagnostic complet de liaison ATAK / Athena.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Status_group", controlNull];
if (isNull _group) exitWith {};

// Mesures à jour
[] call comspec_overwatch_connect_fnc_measureLatency;
private _ext = [] call comspec_overwatch_connect_fnc_extensionStatus;
_ext params [["_extOk", false], ["_extCode", "not_loaded"]];

private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
if (!(_ms isEqualType 0)) then { _ms = -1; };

private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
private _sentWin = _pkt getOrDefault ["packets_sent_window", 0];
private _recvWin = _pkt getOrDefault ["packets_received_window", 0];
private _sentTot = _pkt getOrDefault ["packets_sent_total", 0];
private _recvTot = _pkt getOrDefault ["packets_received_total", 0];
private _measDur = _pkt getOrDefault ["measurement_duration", 0];

private _lastSync = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
private _athenaReady = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _mapId = missionNamespace getVariable ["comspec_overwatch_map_id", 1];
private _posInterval = missionNamespace getVariable ["comspec_overwatch_position_interval", 3];
if (!(_posInterval isEqualType 0)) then { _posInterval = 3; };
_posInterval = (_posInterval max 1) min 60;

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

private _atakDev = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
private _powered = _atakDev getOrDefault ["powered_on", true];
private _screenOk = _atakDev getOrDefault ["screen_ok", true];
private _canSend = _atakDev getOrDefault ["can_send", true];

// Radio (TFAR / ACRE)
private _radioRaw = [player] call comspec_overwatch_connect_fnc_getRadioState;
private _radioParts = _radioRaw splitString "|";
private _radioType = if ((count _radioParts) > 0) then { _radioParts select 0 } else { "N/A" };
private _radioFreq = if ((count _radioParts) > 1) then { _radioParts select 1 } else { "N/A" };
private _radioCh = if ((count _radioParts) > 2) then { _radioParts select 2 } else { "N/A" };

// Bande données ATAK (lien Athena) — canal dérivé de la carte
private _dataChannel = ((floor ((abs _mapId) mod 11)) + 1);
private _dataFreqMhz = 2400 + (_dataChannel * 5);

// Débit estimé (ko/s) à partir de la fenêtre de paquets
private _bitrateKbps = 0;
if (_measDur > 0.5 && {_sentWin > 0}) then {
    // ~0,45 Ko par trame position / sync
    _bitrateKbps = ((_sentWin * 0.45) / _measDur) * 8;
};
if (_bitrateKbps < 0.05 && {_state isEqualTo "linked"}) then {
    // Plancher selon intervalle de position quand la fenêtre est encore vide
    _bitrateKbps = (0.45 / _posInterval) * 8;
};

// Stabilité 0–100
private _stability = 100;
_stability = _stability - ((_loss min 100) * 0.85);
if (_ms >= 0) then {
    if (_ms > 120) then { _stability = _stability - ((_ms - 120) / 18); };
    if (_ms > 400) then { _stability = _stability - 10; };
} else {
    if (_state isNotEqualTo "linked") then { _stability = _stability - 35; };
};
if (!_extOk) then { _stability = _stability - 25; };
if (!_athenaReady) then { _stability = _stability - 15; };
if (!_canSend) then { _stability = _stability - 20; };
private _zone = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!isNil "_zone" && {_zone isEqualType createHashMap}) then {
    private _plm = _zone getOrDefault ["packet_loss_multiplier", 1];
    if (_plm > 1.05) then { _stability = _stability - ((_plm - 1) * 18); };
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
private _latencyColor = switch (true) do {
    case (_ms < 0): { "#8aa0b4" };
    case (_ms <= 100): { "#7dffb0" };
    case (_ms <= 250): { "#ffd27a" };
    default { "#ff8a7a" };
};

private _lossColor = switch (true) do {
    case (_loss <= 2): { "#7dffb0" };
    case (_loss <= 8): { "#ffd27a" };
    default { "#ff8a7a" };
};

private _syncAge = "jamais";
if (_lastSync >= 0) then {
    private _age = round (diag_tickTime - _lastSync);
    if (_age < 0) then { _age = 0; };
    _syncAge = if (_age < 60) then {
        format ["il y a %1 s", _age]
    } else {
        format ["il y a %1 min", round (_age / 60)]
    };
};

private _radioFreqTxt = if (_radioFreq isEqualTo "N/A" || {_radioType isEqualTo "N/A"}) then {
    "Aucune radio détectée"
} else {
    private _modLabel = switch (_radioType) do {
        case "TFAR": { "TFAR" };
        case "ACRE": { "ACRE" };
        default { _radioType };
    };
    format ["%1 MHz · canal %2 (%3)", _radioFreq, _radioCh, _modLabel]
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

private _extLabel = switch (_extCode) do {
    case "ok": { "Opérationnel" };
    case "bad_response": { "Réponse incorrecte" };
    default { "Non chargé" };
};
private _extColor = if (_extOk) then { "#7dffb0" } else { "#ff8a7a" };

private _deviceBits = [];
if (!_powered) then { _deviceBits pushBack "éteint" };
if (!_screenOk) then { _deviceBits pushBack "écran endommagé" };
if (!_canSend) then { _deviceBits pushBack "émission impossible" };
private _deviceTxt = if ((count _deviceBits) == 0) then { "Fonctionnel" } else { _deviceBits joinString " · " };
private _deviceColor = if ((count _deviceBits) == 0) then { "#7dffb0" } else { "#ff8a7a" };

private _zoneTxt = "Aucune";
if (!isNil "_zone" && {_zone isEqualType createHashMap}) then {
    private _plm = _zone getOrDefault ["packet_loss_multiplier", 1];
    private _latAdd = _zone getOrDefault ["latency_add", 0];
    if (_plm > 1.02 || {_latAdd > 1}) then {
        _zoneTxt = format ["Perturbée (×%1 pertes · +%2 ms)", (_plm toFixed 1), round _latAdd];
    };
};

private _summary = format [
    "<t color='%1' size='1.05'>%2</t><br/><t color='#9aa4aa'>%3</t> · <t color='%4'>latence %5</t> · <t color='%6'>stabilité %7%%</t>",
    _stateColor,
    _stateLabel,
    _cs,
    _latencyColor,
    _latencyTxt,
    _stabColor,
    round _stability
];

private _row = {
    params ["_label", "_value", ["_color", "#e8f4f0"]];
    format [
        "<t color='#8aa0b4'>%1</t><br/><t color='%2'>%3</t><br/>",
        _label,
        _color,
        _value
    ]
};

private _body = "";
_body = _body + ([
    "Liaison Athena",
    if (_detail isEqualTo "") then { _stateLabel } else { format ["%1 — %2", _stateLabel, _detail] },
    _stateColor
] call _row);
_body = _body + (["Stabilité du lien", format ["%1 (%2 %%)", _stabLabel, round _stability], _stabColor] call _row);
_body = _body + (["Latence (aller-retour)", _latencyTxt, _latencyColor] call _row);
_body = _body + (["Débit estimé", _bitrateTxt, "#c8e8ff"] call _row);
_body = _body + ([
    "Paquets perdus",
    format ["%1 %%  ·  fenêtre %2 envoyés / %3 reçus", (_loss toFixed 1), _sentWin, _recvWin],
    _lossColor
] call _row);
_body = _body + ([
    "Fréquence radio (voix)",
    _radioFreqTxt,
    "#e8f4f0"
] call _row);
_body = _body + ([
    "Fréquence données ATAK",
    format ["%1 MHz · canal %2", _dataFreqMhz, _dataChannel],
    "#c8e8ff"
] call _row);
_body = _body + ([
    "Cadence de position",
    format ["toutes les %1 s", (_posInterval toFixed 1)],
    "#e8f4f0"
] call _row);
_body = _body + (["Dernière sync. position", _syncAge, "#e8f4f0"] call _row);
_body = _body + ([
    "Paquets (session)",
    format ["%1 envoyés · %2 reçus", _sentTot, _recvTot],
    "#e8f4f0"
] call _row);
_body = _body + (["Module de liaison", _extLabel, _extColor] call _row);
_body = _body + ([
    "Athena prêt",
    if (_athenaReady) then { "Oui" } else { "Non" },
    if (_athenaReady) then { "#7dffb0" } else { "#ffd27a" }
] call _row);
_body = _body + (["Carte / contexte", format ["n° %1", _mapId], "#e8f4f0"] call _row);
_body = _body + (["Terminal", _deviceTxt, _deviceColor] call _row);

private _certStatus = missionNamespace getVariable ["COMSPEC_CertStatus", ""];
private _certExpires = missionNamespace getVariable ["COMSPEC_CertExpires", ""];
private _certLabel = [_certStatus, _certExpires] call comspec_overwatch_connect_fnc_certStatusLabel;
private _certColor = switch (toLower _certStatus) do {
    case "active";
    case "issued": { "#7dffb0" };
    case "expired";
    case "revoked": { "#ff8a7a" };
    case "missing": { "#ffd27a" };
    default { "#8aa0b4" };
};
_body = _body + (["Certificat terminal", _certLabel, _certColor] call _row);

private _terminalUid = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
if (_terminalUid isNotEqualTo "") then {
    _body = _body + (["Identité terminal", _terminalUid, "#c8e8ff"] call _row);
};

_body = _body + (["Zone radio", _zoneTxt, if (_zoneTxt isEqualTo "Aucune") then { "#7dffb0" } else { "#ffd27a" }] call _row);

private _sumCtrl = _group controlsGroupCtrl 9801;
if (!isNull _sumCtrl) then {
    _sumCtrl ctrlSetStructuredText parseText _summary;
};
private _bodyCtrl = _group controlsGroupCtrl 9802;
if (!isNull _bodyCtrl) then {
    _bodyCtrl ctrlSetStructuredText parseText _body;
};
