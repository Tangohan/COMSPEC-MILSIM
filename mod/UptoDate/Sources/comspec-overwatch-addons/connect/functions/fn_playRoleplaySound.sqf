/*
    Joue des effets sonores pour les événements roleplay.
    
    Params:
        _type - Type de son ("disconnect", "reconnect", "glitch", "warning")
*/

params [["_type", "", [""]]];

if (_type isEqualTo "") exitWith {};

// Vérifier si les effets sonores sont activés
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false])) exitWith {};

private _vol = ["fx"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
if (_vol <= 0.01) exitWith {};

private _play = {
    params ["_snd"];
    if (_snd isEqualTo "") exitWith {};
    playSoundUI [_snd, _vol, 1];
};

switch (_type) do {
    case "disconnect": {
        ["FD_CP_Not_Clear_F"] call _play;
        [{
            params ["_vol"];
            if ((["fx"] call comspec_overwatch_connect_fnc_getAtakSoundVolume) <= 0.01) exitWith {};
            playSoundUI ["AddItemFailed", _vol, 1];
        }, [_vol], 0.3] call CBA_fnc_waitAndExecute;
    };
    case "reconnect": {
        ["FD_CP_Clear_F"] call _play;
    };
    case "glitch": {
        ["AddItemFailed"] call _play;
    };
    case "warning": {
        ["Orange_NotificationDefault_01"] call _play;
    };
    case "degraded": {
        ["Orange_NotificationDefault_02"] call _play;
    };
};
