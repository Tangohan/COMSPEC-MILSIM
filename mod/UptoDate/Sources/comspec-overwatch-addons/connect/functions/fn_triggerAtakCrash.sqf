/*
    Déclenche un gel temporaire de l'ATAK (crash appareil, distinct déconnexion réseau).
    Params: [_durationSec]
*/
if (!hasInterface) exitWith {};

params [["_durationSec", 20, [0]]];

private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_atakState isEqualTo createHashMap) then {
    _atakState = createHashMap;
    _atakState set ["powered_on", true];
    _atakState set ["screen_destroyed", false];
    _atakState set ["device_destroyed", false];
};

if (_atakState getOrDefault ["device_destroyed", false]) exitWith {};

_atakState set ["device_crashed", true];
_atakState set ["crash_until", time + (_durationSec max 5)];
missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];

["Terminal ATAK bloqué — redémarrage en cours", "system", "critical"] call comspec_overwatch_connect_fnc_ambientHint;
["crash"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
[0.85, 25, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;

[{
    private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
    if (_state isEqualTo createHashMap) exitWith {};
    if (_state getOrDefault ["device_destroyed", false]) exitWith {};

    _state set ["device_crashed", false];
    _state set ["crash_until", -1];
    _state set ["powered_on", true];
    missionNamespace setVariable ["COMSPEC_AtakState", _state, false];
    missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];

    [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    ["Liaison ATAK rétablie", "link", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    ["reconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
}, [], _durationSec] call CBA_fnc_waitAndExecute;
