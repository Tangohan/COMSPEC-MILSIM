/*
    Action ACE : Ouvrir le tableau de bord des relais
    Ajoutée aux objets spéciaux (ordinateurs, tablettes, etc.)
*/

if (!hasInterface) exitWith {};

// Ajouter l'action aux ordinateurs/tablettes en mission
["Land_Laptop_unfolded_F", 0, ["ACE_MainActions"], [
    "COMSPEC_OpenRelayDashboard",
    "📡 Tableau de bord relais ATAK",
    "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa",
    {call comspec_overwatch_connect_fnc_openRelayDashboard},
    {true}
] call ace_interact_menu_fnc_createAction, [], [0, 0, 0], 100] call ace_interact_menu_fnc_addActionToClass;

["Land_Tablet_02_F", 0, ["ACE_MainActions"], [
    "COMSPEC_OpenRelayDashboard",
    "📡 Tableau de bord relais ATAK",
    "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa",
    {call comspec_overwatch_connect_fnc_openRelayDashboard},
    {true}
] call ace_interact_menu_fnc_createAction, [], [0, 0, 0], 100] call ace_interact_menu_fnc_addActionToClass;

// Ajouter aux objets marqués spécifiquement
["All", 0, ["ACE_MainActions"], [
    "COMSPEC_OpenRelayDashboard",
    "📡 Tableau de bord relais ATAK",
    "\a3\ui_f\data\IGUI\Cfg\simpleTasks\types\signal_ca.paa",
    {call comspec_overwatch_connect_fnc_openRelayDashboard},
    {_target getVariable ["COMSPEC_HasRelayDashboard", false]}
] call ace_interact_menu_fnc_createAction, [], [0, 0, 0], 100] call ace_interact_menu_fnc_addActionToClass;

// Action scroll menu fallback (si pas ACE)
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) then {
    player addAction [
        "📡 Tableau de bord relais ATAK",
        {call comspec_overwatch_connect_fnc_openRelayDashboard},
        nil,
        1.5,
        true,
        true,
        "",
        "true",
        5
    ];
};

diag_log "[COMSPEC ATAK][Actions] Actions tableau de bord relais ajoutées";
