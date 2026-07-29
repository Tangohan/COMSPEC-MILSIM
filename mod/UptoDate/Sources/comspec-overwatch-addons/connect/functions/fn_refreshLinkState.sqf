/*
    Recalcule COMSPEC_LinkState selon l'état réel (coupure simulée, zone, terminal, Athena).
    Corrige le blocage « Hors liaison » après fin de brouillage / coupure réseau.

    Returns: "linked" | "degraded" | "offline" | "connecting"
*/
if (!hasInterface) exitWith { "offline" };

[] call comspec_overwatch_connect_fnc_isNetworkDisconnected;

private _atak = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
private _blocked = false;
private _degraded = false;

if (_atak getOrDefault ["device_crashed", false]) then { _blocked = true; };
if !(_atak getOrDefault ["connection_ok", true]) then { _blocked = true; };
if !(_atak getOrDefault ["powered_on", true]) then { _blocked = true; };
if ([] call comspec_overwatch_connect_fnc_isNetworkDisconnected) then { _blocked = true; };

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
if (_prev isNotEqualTo _state) then {
    missionNamespace setVariable ["COMSPEC_LinkState", _state, false];
    if (_prev isEqualTo "offline" && {_state in ["linked", "degraded"]}) then {
        ["reconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
    };
    ["COMSPEC_AthenaLinkChanged", [_state]] call CBA_fnc_localEvent;
};

player setVariable ["COMSPEC_LinkState", _state, true];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;
[] call comspec_overwatch_connect_fnc_updateDeviceOverlay;

_state
