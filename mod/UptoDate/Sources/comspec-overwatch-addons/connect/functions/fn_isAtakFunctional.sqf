/*
    Vérifie si l'ATAK est fonctionnel.
    
    Returns:
        HashMap avec :
            powered_on - Boolean, ATAK allumé
            screen_ok - Boolean, écran fonctionnel
            connection_ok - Boolean, connexion active
            can_display - Boolean, peut afficher l'interface
            can_send - Boolean, peut envoyer des données
            device_crashed - Boolean, gel temporaire appareil
*/

private _result = createHashMap;

_result set ["powered_on", true];
_result set ["screen_ok", true];
_result set ["connection_ok", true];
_result set ["can_display", true];
_result set ["can_send", true];
_result set ["device_crashed", false];

private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_atakState isEqualTo createHashMap) exitWith { _result };

private _powered = _atakState getOrDefault ["powered_on", true];
private _screenDestroyed = _atakState getOrDefault ["screen_destroyed", false];
private _deviceDestroyed = _atakState getOrDefault ["device_destroyed", false];
private _crashed = _atakState getOrDefault ["device_crashed", false];
private _crashUntil = _atakState getOrDefault ["crash_until", -1];

if (_crashed && {_crashUntil > 0} && {time >= _crashUntil}) then {
    _crashed = false;
    _atakState set ["device_crashed", false];
    _atakState set ["crash_until", -1];
    _atakState set ["powered_on", true];
    missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
};

_result set ["powered_on", _powered && !_crashed];
_result set ["screen_ok", !_screenDestroyed];
_result set ["connection_ok", !_deviceDestroyed && !_crashed];
_result set ["device_crashed", _crashed];
_result set ["can_display", _powered && !_screenDestroyed && !_crashed];
_result set ["can_send", !_deviceDestroyed && !_crashed];

_result
