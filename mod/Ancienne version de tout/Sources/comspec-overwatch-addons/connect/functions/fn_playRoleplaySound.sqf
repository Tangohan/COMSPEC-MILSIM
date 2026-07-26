/*
    Joue des effets sonores pour les événements roleplay.
    
    Params:
        _type - Type de son ("disconnect", "reconnect", "glitch", "warning")
*/

params [["_type", "", [""]]];

if (_type isEqualTo "") exitWith {};

// Vérifier si les effets sonores sont activés
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false])) exitWith {};

switch (_type) do {
    case "disconnect": {
        // Son de déconnexion (static + bip)
        playSound "FD_CP_Not_Clear_F";
        [{
            playSound "AddItemFailed";
        }, [], 0.3] call CBA_fnc_waitAndExecute;
    };
    
    case "reconnect": {
        // Son de reconnexion (bip positif)
        playSound "FD_CP_Clear_F";
    };
    
    case "glitch": {
        // Son de glitch/parasite
        playSound "AddItemFailed";
    };
    
    case "warning": {
        // Son d'avertissement
        playSound "Orange_NotificationDefault_01";
    };
    
    case "degraded": {
        // Son de qualité dégradée
        playSound "Orange_NotificationDefault_02";
    };
};
