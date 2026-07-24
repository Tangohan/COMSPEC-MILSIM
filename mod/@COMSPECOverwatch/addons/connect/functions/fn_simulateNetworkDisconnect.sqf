/*
    Simule une déconnexion réseau temporaire côté mod.
    Bloque tous les envois pendant une durée aléatoire.
    
    Cette fonction est appelée périodiquement par un PFH si le roleplay est activé.
*/

// Vérifier si le mode roleplay est activé
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_network_failures", false])) exitWith {};

// Initialiser l'état si nécessaire
if (isNil {missionNamespace getVariable "COMSPEC_NetworkDisconnectState"}) then {
    missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", createHashMap, false];
    private _state = missionNamespace getVariable "COMSPEC_NetworkDisconnectState";
    _state set ["is_disconnected", false];
    _state set ["disconnect_until", -1];
    _state set ["next_disconnect_at", time + 600]; // Première déconnexion dans 10 min
    _state set ["disconnect_count", 0];
};

private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
private _now = time;

// Si on est en période de déconnexion
if (_state getOrDefault ["is_disconnected", false]) then {
    private _until = _state getOrDefault ["disconnect_until", -1];
    
    if (_now >= _until) then {
        // Fin de la déconnexion
        _state set ["is_disconnected", false];
        _state set ["disconnect_until", -1];
        
        // Planifier la prochaine déconnexion (intervalle configurable, défaut 10 min)
        private _interval = 600; // Par défaut 10 minutes
        _state set ["next_disconnect_at", _now + _interval];
        
        // Notification
        hintSilent "Liaison ATAK rétablie";
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        
        // Callback pour l'extension
        if (!isNil "comspec_overwatch_connect_fnc_extensionCallback") then {
            ["NetworkReconnected", ""] call comspec_overwatch_connect_fnc_extensionCallback;
        };
        
        diag_log format ["[COMSPEC Roleplay] Déconnexion terminée après %1 secondes", _until - (_now - (_until - _now))];
    };
    
    exitWith {};
};

// Vérifier s'il est temps de déclencher une nouvelle déconnexion
private _nextDisconnectAt = _state getOrDefault ["next_disconnect_at", _now + 600];

if (_now >= _nextDisconnectAt) then {
    // Déclencher une déconnexion
    private _minDuration = 5; // Minimum 5 secondes
    private _maxDuration = 30; // Maximum 30 secondes
    
    // Duration aléatoire
    private _duration = _minDuration + (random (_maxDuration - _minDuration));
    _duration = floor _duration;
    
    _state set ["is_disconnected", true];
    _state set ["disconnect_until", _now + _duration];
    _state set ["disconnect_count", (_state getOrDefault ["disconnect_count", 0]) + 1];
    
    // Notification
    private _msg = format ["Perte de liaison ATAK (%1s)", _duration];
    hintSilent _msg;
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    
    // Callback pour l'extension
    if (!isNil "comspec_overwatch_connect_fnc_extensionCallback") then {
        ["NetworkDisconnected", str _duration] call comspec_overwatch_connect_fnc_extensionCallback;
    };
    
    diag_log format ["[COMSPEC Roleplay] Déconnexion simulée déclenchée: %1 secondes (occurrence #%2)", _duration, _state get "disconnect_count"];
};
