/*
    Vérifie si l'ATAK est fonctionnel.
    
    Returns:
        HashMap avec :
            powered_on - Boolean, ATAK allumé
            screen_ok - Boolean, écran fonctionnel
            connection_ok - Boolean, connexion active
            can_display - Boolean, peut afficher l'interface
            can_send - Boolean, peut envoyer des données
*/

private _result = createHashMap;

// État par défaut (tout fonctionne)
_result set ["powered_on", true];
_result set ["screen_ok", true];
_result set ["connection_ok", true];
_result set ["can_display", true];
_result set ["can_send", true];

// Vérifier l'état endommagé
private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (!(_atakState isEqualTo createHashMap)) then {
    private _powered = _atakState getOrDefault ["powered_on", true];
    private _screenDestroyed = _atakState getOrDefault ["screen_destroyed", false];
    private _deviceDestroyed = _atakState getOrDefault ["device_destroyed", false];
    
    _result set ["powered_on", _powered];
    _result set ["screen_ok", !_screenDestroyed];
    _result set ["connection_ok", !_deviceDestroyed];
    
    // Peut afficher si allumé ET écran OK
    _result set ["can_display", _powered && !_screenDestroyed];
    
    // Peut envoyer si connexion OK
    _result set ["can_send", !_deviceDestroyed];
};

_result
