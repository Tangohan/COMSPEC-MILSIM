/*
    Répare l'ATAK endommagé.
    Nécessite un Toolkit ACE.
    
    Params:
        _repairType - "power" (rallumer) | "screen" (réparer écran) | "full" (réparation complète)
    
    Returns:
        Boolean - Succès de la réparation
*/

params [["_repairType", "power", [""]]];

if (!hasInterface) exitWith { false };

private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_atakState isEqualTo createHashMap) exitWith { false };

// Vérifier si a un toolkit
private _hasToolkit = "ToolKit" in (items player);
if (!_hasToolkit && {_repairType in ["screen", "full"]}) exitWith {
    hintSilent "Un Toolkit ACE est nécessaire pour réparer l'ATAK";
    false
};

private _success = false;

switch (_repairType) do {
    case "power": {
        // Rallumer l'ATAK (gratuit)
        if (!(_atakState get "powered_on")) then {
            _atakState set ["powered_on", true];
            hintSilent "ATAK rallumé";
            playSound "FD_CP_Clear_F";
            _success = true;
        };
    };
    
    case "screen": {
        // Réparer l'écran (nécessite toolkit)
        if (_atakState get "screen_destroyed") then {
            // Animation de réparation
            player playActionNow "Medic";
            
            [{
                params ["_state"];
                _state set ["screen_destroyed", false];
                _state set ["powered_on", true];
                hintSilent "Écran ATAK réparé";
                playSound "FD_CP_Clear_F";
            }, [_atakState], 5] call CBA_fnc_waitAndExecute;
            
            _success = true;
        };
    };
    
    case "full": {
        // Réparation complète (impossible si device_destroyed)
        if (_atakState get "device_destroyed") exitWith {
            hintSilent "ATAK trop endommagé, remplacement nécessaire";
            false
        };
        
        player playActionNow "Medic";
        
        [{
            params ["_state"];
            _state set ["screen_destroyed", false];
            _state set ["powered_on", true];
            hintSilent "ATAK complètement réparé";
            playSound "FD_CP_Clear_F";
        }, [_atakState], 8] call CBA_fnc_waitAndExecute;
        
        _success = true;
    };
};

// Sauvegarder
if (_success) then {
    missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
};

_success
