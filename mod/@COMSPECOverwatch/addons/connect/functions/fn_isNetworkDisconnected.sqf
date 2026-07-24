/*
    Vérifie si le mod est actuellement en état de déconnexion simulée.
    
    Returns:
        true si déconnecté, false sinon
*/

// Si le roleplay n'est pas activé, jamais déconnecté
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_network_failures", false])) exitWith { false };

private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
private _isDisconnected = _state getOrDefault ["is_disconnected", false];

// Vérifier aussi le timing au cas où
if (_isDisconnected) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    if (time >= _until) then {
        // La déconnexion devrait être terminée mais n'a pas été mise à jour
        _state set ["is_disconnected", false];
        _isDisconnected = false;
    };
};

_isDisconnected
