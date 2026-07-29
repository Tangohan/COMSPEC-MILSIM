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
    ["Toolkit requis pour réparer l'ATAK.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

private _success = false;

switch (_repairType) do {
    case "power": {
        // Rallumer l'ATAK (gratuit)
        if (!(_atakState getOrDefault ["powered_on", true]) || {_atakState getOrDefault ["device_crashed", false]}) then {
            _atakState set ["powered_on", true];
            _atakState set ["device_crashed", false];
            _atakState set ["crash_until", -1];
            ["ATAK rallumé", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
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
                ["Écran ATAK réparé", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
                playSound "FD_CP_Clear_F";
            }, [_atakState], 5] call CBA_fnc_waitAndExecute;
            
            _success = true;
        };
    };
    
    case "full": {
        // Réparation complète (impossible si device_destroyed)
        if (_atakState get "device_destroyed") exitWith {
            ["ATAK trop endommagé — remplacement requis", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
            false
        };
        
        player playActionNow "Medic";
        
        [{
            params ["_state"];
            _state set ["screen_destroyed", false];
            _state set ["powered_on", true];
            ["ATAK réparé", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            playSound "FD_CP_Clear_F";
        }, [_atakState], 8] call CBA_fnc_waitAndExecute;
        
        _success = true;
    };
};

// Sauvegarder
if (_success) then {
    missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
    [
        "INFO",
        "Terminal",
        format ["Réparation %1 effectuée", _repairType],
        "system"
    ] call comspec_overwatch_connect_fnc_logAtakEvent;
    [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
};

_success
