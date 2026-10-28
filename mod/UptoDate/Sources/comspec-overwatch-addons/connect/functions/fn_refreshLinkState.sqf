/*
    Recalcule COMSPEC_LinkState selon l'état réel (coupure simulée, zone, terminal, Athena).
    Corrige le blocage « Hors liaison » après fin de brouillage / coupure réseau.

    Anti-battement : l'état calculé doit tenir un moment avant d'être appliqué (perte 3 s,
    retour depuis « offline » 6 s, autres changements 4 s). Sans cela, deux sources en
    désaccord (position : relais / perte de paquet tirée au sort, ici : rien) faisaient
    alterner offline / linked toutes les 2 s, avec bip de reconnexion et notifications.
    Relais exigés (COMSPEC_LinkViaRelays) : même règle que fn_canTransmit (passerelle de
    véhicule du téléphone natif acceptée comme relais).

    Returns: "linked" | "degraded" | "offline" | "connecting"
*/
if (!hasInterface) exitWith { "offline" };

if (missionNamespace getVariable ["COMSPEC_LinkStateRefreshing", false]) exitWith {
    missionNamespace getVariable ["COMSPEC_LinkState", "offline"]
};
private _lastAt = missionNamespace getVariable ["COMSPEC_LinkStateRefreshedAt", -99];
if ((diag_tickTime - _lastAt) < 0.8) exitWith {
    missionNamespace getVariable ["COMSPEC_LinkState", "offline"]
};
missionNamespace setVariable ["COMSPEC_LinkStateRefreshing", true, false];

[] call comspec_overwatch_connect_fnc_isNetworkDisconnected;

private _atak = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
private _blocked = false;
private _degraded = false;

if (_atak getOrDefault ["device_crashed", false]) then { _blocked = true; };
if !(_atak getOrDefault ["connection_ok", true]) then { _blocked = true; };
if !(_atak getOrDefault ["powered_on", true]) then { _blocked = true; };
if ([] call comspec_overwatch_connect_fnc_isNetworkDisconnected) then { _blocked = true; };
// Relais exigés : même verdict que fn_canTransmit (sinon la position remet « offline » et ce calcul « linked »).
if (
    !_blocked
    && {missionNamespace getVariable ["COMSPEC_LinkViaRelays", false]}
    && {!([] call comspec_overwatch_connect_fnc_isNearLiveRelay)}
    && {isNil "comspec_atak_native_fnc_vehicleGateway" || {(["nearest"] call comspec_atak_native_fnc_vehicleGateway) isEqualTo []}}
) then { _blocked = true; };

private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!_blocked && {!isNil "_zoneFx"} && {_zoneFx isEqualType createHashMap}) then {
    if (_zoneFx getOrDefault ["force_disconnect", false]) then {
        _blocked = true;
    } else {
        private _type = toLower (_zoneFx getOrDefault ["type", ""]);
        private _lossFloor = _zoneFx getOrDefault ["packet_loss_floor", 0];
        private _latAdd = _zoneFx getOrDefault ["latency_add", 0];
        if (
            _type in ["jammer", "interference", "no_coverage"]
            || {_lossFloor >= 35}
            || {_latAdd >= 300}
        ) then {
            _degraded = true;
        };
    };
};

if !(_atak getOrDefault ["screen_ok", true]) then { _degraded = true; };

private _state = "linked";
if (_blocked) then {
    _state = "offline";
} else {
    if (_degraded) then {
        _state = "degraded";
    } else {
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
            private _cur = missionNamespace getVariable ["COMSPEC_LinkState", "connecting"];
            _state = if (_cur in ["connecting", "disabled"]) then { _cur } else { "connecting" };
        };
    };
};

private _prev = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
// Anti-battement : [état candidat, depuis, état d'alors]. Le changement n'est appliqué qu'après la durée de maintien.
private _now = diag_tickTime;
// Début de la coupure, vu d'ici (l'état « offline » peut aussi venir d'une autre fonction).
if (_prev isEqualTo "offline") then {
    if ((missionNamespace getVariable ["COMSPEC_LinkOfflineSince", -1]) < 0) then { missionNamespace setVariable ["COMSPEC_LinkOfflineSince", _now, false]; };
} else {
    missionNamespace setVariable ["COMSPEC_LinkOfflineSince", -1, false];
};
if (_prev isEqualTo _state) then {
    missionNamespace setVariable ["COMSPEC_LinkStatePending", [], false];
} else {
    private _pend = missionNamespace getVariable ["COMSPEC_LinkStatePending", []];
    // Candidat périmé (autre état visé, ou l'état a changé ailleurs entre-temps) : le maintien repart de zéro.
    if (!(_pend isEqualType []) || {(_pend param [0, ""]) isNotEqualTo _state} || {(_pend param [2, ""]) isNotEqualTo _prev}) then {
        _pend = [_state, _now, _prev];
        missionNamespace setVariable ["COMSPEC_LinkStatePending", _pend, false];
    };
    private _hold = switch (true) do {
        case (_state isEqualTo "offline"): { 3 };
        case (_prev isEqualTo "offline"): { 6 };
        default { 4 };
    };
    if ((_now - (_pend param [1, _now])) < _hold) then { _state = _prev; } else {
        missionNamespace setVariable ["COMSPEC_LinkStatePending", [], false];
    };
};
if (_prev isNotEqualTo _state) then {
    missionNamespace setVariable ["COMSPEC_LinkState", _state, false];
    private _offSince = missionNamespace getVariable ["COMSPEC_LinkOfflineSince", -1];
    missionNamespace setVariable ["COMSPEC_LinkOfflineSince", [-1, _now] select (_state isEqualTo "offline"), false];
    if (_prev isEqualTo "offline" && {_state in ["linked", "degraded"]}) then {
        // Pas de bip à chaque micro-coupure brouilleur (1–7 s), ni après une coupure de moins de 10 s.
        private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
        private _jammerFlicker = !isNil "_zoneFx" && {_zoneFx isEqualType createHashMap} && {
            (toLower (_zoneFx getOrDefault ["type", ""])) isEqualTo "jammer"
        };
        private _outage = if (_offSince < 0) then { 0 } else { _now - _offSince };
        if (!_jammerFlicker && {_outage >= 10}) then {
            ["reconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
        };
    };
    ["COMSPEC_AthenaLinkChanged", [_state]] call CBA_fnc_localEvent;
};

// Diffusion seulement si la valeur change : appelé toutes les 2 s (effets de zone), le setVariable public
// partait à chaque fois vers tous les clients (+ JIP) même identique. Lu par Zeus sur l'unité.
if ((player getVariable ["COMSPEC_LinkState", ""]) isNotEqualTo _state) then {
    player setVariable ["COMSPEC_LinkState", _state, true];
};
if (_prev isNotEqualTo _state) then {
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    [] call comspec_overwatch_connect_fnc_updateDeviceOverlay;
};
missionNamespace setVariable ["COMSPEC_LinkStateRefreshedAt", diag_tickTime, false];
missionNamespace setVariable ["COMSPEC_LinkStateRefreshing", false, false];

_state
