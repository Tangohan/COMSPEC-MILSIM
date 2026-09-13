/*
    Vérifie si le mod est actuellement en état de déconnexion simulée.
*/

private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
private _isDisconnected = _state getOrDefault ["is_disconnected", false];

if (_isDisconnected) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    if (_until < 0 || {time < _until}) exitWith { true };
    _state set ["is_disconnected", false];
    _state set ["disconnect_until", -1];
    _state set ["sim_local", false];
    missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", _state, false];
    [] call comspec_overwatch_connect_fnc_refreshLinkState;
};

if !([] call comspec_overwatch_connect_fnc_isLinkDegradeSimActive) exitWith { false };

_isDisconnected = _state getOrDefault ["is_disconnected", false];
if (_isDisconnected) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    if (time >= _until) then {
        _state set ["is_disconnected", false];
        _state set ["sim_local", false];
        _isDisconnected = false;
    };
};

_isDisconnected
