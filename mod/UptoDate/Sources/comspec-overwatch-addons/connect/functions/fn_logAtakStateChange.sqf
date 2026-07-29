/*
    Journalise l’état ATAK (liaison, latence, pertes, terminal) dans AppData
    uniquement quand les métriques clés changent — ou si _force.

    Params: [_force]
*/
params [["_force", false, [true]]];

if (!hasInterface) exitWith {};

private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
private _sentWin = _pkt getOrDefault ["packets_sent_window", 0];
private _recvWin = _pkt getOrDefault ["packets_received_window", 0];
private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
if (!(_ms isEqualType 0)) then { _ms = -1; };

private _atak = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
private _compromise = toLower (missionNamespace getVariable ["COMSPEC_CompromiseState", "none"]);

private _sig = format [
    "%1|%2|%3|%4|%5|%6|%7|%8|%9",
    _linkState,
    round _loss,
    round _ms,
    _atak getOrDefault ["powered_on", true],
    _atak getOrDefault ["screen_ok", true],
    _atak getOrDefault ["device_crashed", false],
    _atak getOrDefault ["connection_ok", true],
    _compromise,
    format ["%1/%2", _sentWin, _recvWin]
];

private _prev = missionNamespace getVariable ["COMSPEC_LastAtakStateLogSig", ""];
if (!_force && {_sig isEqualTo _prev}) exitWith {};
missionNamespace setVariable ["COMSPEC_LastAtakStateLogSig", _sig, false];

private _stateLabel = switch (_linkState) do {
    case "linked": { "En liaison" };
    case "connecting": { "Connexion…" };
    case "degraded": { "Dégradée" };
    case "disabled": { "Désactivée" };
    default { "Hors liaison" };
};

private _deviceBits = [];
if !(_atak getOrDefault ["powered_on", true]) then { _deviceBits pushBack "éteint" };
if !(_atak getOrDefault ["screen_ok", true]) then { _deviceBits pushBack "écran endommagé" };
if (_atak getOrDefault ["device_crashed", false]) then { _deviceBits pushBack "terminal gelé" };
if !(_atak getOrDefault ["connection_ok", true]) then { _deviceBits pushBack "appareil HS" };
if (_compromise in ["captured", "compromised"]) then {
    _deviceBits pushBack if (_compromise isEqualTo "compromised") then { "compromis" } else { "capturé" };
};

private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isEqualTo "") then { _cs = name player; };

private _msg = format [
    "%1 · %2 · latence %3 · pertes %4%% · paquets %5/%6",
    _cs,
    _stateLabel,
    if (_ms < 0) then { "—" } else { format ["%1 ms", round _ms] },
    (_loss toFixed 1),
    _sentWin,
    _recvWin
];

if (count _deviceBits > 0) then {
    _msg = _msg + format [" · terminal : %1", _deviceBits joinString ", "];
};

private _level = if (count _deviceBits > 0 || {_linkState isNotEqualTo "linked"}) then { "WARN" } else { "INFO" };
[_level, "Etat", _msg, "liaison"] call comspec_overwatch_connect_fnc_logAtakEvent;
