/*
    Vérifie si le mod est actuellement en état de déconnexion simulée.
    
    Returns:
        true si déconnecté, false sinon
*/

private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
private _isDisconnected = _state getOrDefault ["is_disconnected", false];

// Brouillage / coupure Zeus (ou simulateNetworkDisconnect) — indépendant du roleplay admin
if (_isDisconnected) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    if (_until < 0 || {time < _until}) exitWith { true };
    _state set ["is_disconnected", false];
    _state set ["disconnect_until", -1];
    missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", _state, false];
    _isDisconnected = false;
    [] call comspec_overwatch_connect_fnc_refreshLinkState;
};

// Déconnexions aléatoires roleplay (config portail)
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_network_failures", false])) exitWith { false };

_isDisconnected = _state getOrDefault ["is_disconnected", false];
if (_isDisconnected) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    if (time >= _until) then {
        _state set ["is_disconnected", false];
        _isDisconnected = false;
    };
};

_isDisconnected
