/*
    Joue un son roleplay dans l'ATAK Enhanced.
    
    Params:
        0: STRING - Type de son ("disconnect", "reconnect", "interference", "zone_alert")
*/

if (!hasInterface) exitWith {};

params [
    ["_soundType", "", [""]]
];

if (_soundType == "") exitWith {};

// Sons du jeu Arma 3 (pas de fichiers externes requis)
private _sound = switch (_soundType) do {
    case "disconnect": {
        ["A3\Sounds_F\sfx\radio\ambient_radio18.wss", 0.8]
    };
    case "reconnect": {
        ["A3\Sounds_F\sfx\beep_target.wss", 0.5]
    };
    case "interference": {
        ["A3\Sounds_F\sfx\radio\ambient_radio17.wss", 0.4]
    };
    case "zone_alert": {
        ["A3\Sounds_F\sfx\alarm_independent.wss", 0.6]
    };
    case "screen_broken": {
        ["A3\Sounds_F\sfx\ui\vehicles\vehicle_collision.wss", 0.7]
    };
    default { nil };
};

if (isNil "_sound") exitWith {};

_sound params ["_file", "_volume"];
playSound3D [_file, player, false, getPosASL player, _volume, 1, 10];
