/*
    Simule une déconnexion réseau temporaire côté mod.
    Active si Simulation de liaison dégradée OU roleplay + simulations réseau.
*/

if (!hasInterface) exitWith {};
if !([] call comspec_overwatch_connect_fnc_isLinkDegradeSimActive) exitWith {
    private _st = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
    if (_st isEqualType createHashMap && {_st getOrDefault ["sim_local", false]}) then {
        _st set ["is_disconnected", false];
        _st set ["disconnect_until", -1];
        _st set ["sim_local", false];
        missionNamespace setVariable ["COMSPEC_LinkDegradeSimState", createHashMap, false];
    };
};

private _simState = missionNamespace getVariable ["COMSPEC_LinkDegradeSimState", createHashMap];
if (!(_simState isEqualType createHashMap) || {(count _simState) isEqualTo 0}) then {
    _simState = createHashMap;
    _simState set ["loss_floor", 12 + random 10];
    _simState set ["tx_drop_chance", 5 + random 8];
    _simState set ["next_pulse", time + 20 + random 40];
    missionNamespace setVariable ["COMSPEC_LinkDegradeSimState", _simState, false];
};
if (time >= (_simState getOrDefault ["next_pulse", 0])) then {
    _simState set ["loss_floor", 10 + random 28];
    _simState set ["tx_drop_chance", 4 + random 14];
    _simState set ["next_pulse", time + 25 + random 55];
};

if (isNil {missionNamespace getVariable "COMSPEC_NetworkDisconnectState"}) then {
    missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", createHashMap, false];
    private _state0 = missionNamespace getVariable "COMSPEC_NetworkDisconnectState";
    _state0 set ["is_disconnected", false];
    _state0 set ["disconnect_until", -1];
    _state0 set ["next_disconnect_at", time + 180 + random 240];
    _state0 set ["disconnect_count", 0];
    _state0 set ["sim_local", false];
};

private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
private _now = time;

if (_state getOrDefault ["is_disconnected", false]) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    if (_now >= _until) then {
        _state set ["is_disconnected", false];
        _state set ["disconnect_until", -1];
        _state set ["sim_local", false];
        _state set ["next_disconnect_at", _now + 240 + random 360];
        ["Liaison ATAK rétablie", "link", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        [] call comspec_overwatch_connect_fnc_refreshLinkState;
        missionNamespace setVariable ["COMSPEC_DisconnectHintShown", false, false];
        ["reconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
        if (!isNil "comspec_overwatch_connect_fnc_extensionCallback") then {
            ["NetworkReconnected", ""] call comspec_overwatch_connect_fnc_extensionCallback;
        };
    };
} else {
    private _nextDisconnectAt = _state getOrDefault ["next_disconnect_at", _now + 300];
    if (_now >= _nextDisconnectAt) then {
        private _duration = floor (4 + (random 18));
        _state set ["is_disconnected", true];
        _state set ["disconnect_until", _now + _duration];
        _state set ["disconnect_count", (_state getOrDefault ["disconnect_count", 0]) + 1];
        _state set ["sim_local", true];
        private _msg = format ["Perte de liaison ATAK (%1s)", _duration];
        [_msg, "link", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        ["disconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
        if (!isNil "comspec_overwatch_connect_fnc_extensionCallback") then {
            ["NetworkDisconnected", str _duration] call comspec_overwatch_connect_fnc_extensionCallback;
        };
    };
};
