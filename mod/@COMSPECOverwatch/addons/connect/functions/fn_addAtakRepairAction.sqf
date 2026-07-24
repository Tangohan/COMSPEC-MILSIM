/*
    Ajoute l'action ACE pour réparer l'ATAK.
    Appelé au postInit.
*/

if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};

// Action pour rallumer l'ATAK
private _actionPower = [
    "COMSPEC_RepairAtakPower",
    "Rallumer l'ATAK",
    "",
    {
        ["power"] call comspec_overwatch_connect_fnc_repairAtak;
    },
    {
        private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
        !(_status get "powered_on") && {!(_status get "device_destroyed")}
    }
] call ace_interact_menu_fnc_createAction;

// Action pour réparer l'écran
private _actionScreen = [
    "COMSPEC_RepairAtakScreen",
    "Réparer écran ATAK",
    "",
    {
        ["screen"] call comspec_overwatch_connect_fnc_repairAtak;
    },
    {
        private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
        !(_status get "screen_ok") && {!(_status get "device_destroyed")} && {"ToolKit" in (items player)}
    }
] call ace_interact_menu_fnc_createAction;

// Action pour diagnostic
private _actionDiag = [
    "COMSPEC_DiagnosticAtak",
    "Diagnostic ATAK",
    "",
    {
        private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
        
        private _msg = "État de l'ATAK :\n\n";
        _msg = _msg + format ["Alimentation : %1\n", if (_status get "powered_on") then {"✓ OK"} else {"✗ Éteint"}];
        _msg = _msg + format ["Écran : %1\n", if (_status get "screen_ok") then {"✓ OK"} else {"✗ Détruit"}];
        _msg = _msg + format ["Connexion : %1\n", if (_status get "connection_ok") then {"✓ OK"} else {"✗ Coupée"}];
        
        if (!(_status get "device_destroyed")) then {
            if (!(_status get "powered_on")) then {
                _msg = _msg + "\nRallumage possible (gratuit)";
            };
            if (!(_status get "screen_ok") && {"ToolKit" in (items player)}) then {
                _msg = _msg + "\nRéparation écran possible (Toolkit)";
            };
        } else {
            _msg = _msg + "\nATAK irréparable, remplacement nécessaire";
        };
        
        hint _msg;
    },
    {true}
] call ace_interact_menu_fnc_createAction;

// Ajouter au self-interact
[player, 1, ["ACE_SelfActions", "ACE_Equipment"], _actionPower] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "ACE_Equipment"], _actionScreen] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "ACE_Equipment"], _actionDiag] call ace_interact_menu_fnc_addActionToObject;
