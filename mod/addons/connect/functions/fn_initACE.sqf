if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};

private _mainAction = [
    "COMSPEC_Main", "COMSPEC Overwatch", "", {}, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions"], _mainAction] call ace_interact_menu_fnc_addActionToObject;

private _pingAction = [
    "COMSPEC_Ping", "Envoyer Ping", "", {
        [player, "PING", getPos player, "Point d'interet"] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Ping transmis.";
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _pingAction] call ace_interact_menu_fnc_addActionToObject;

private _medAction = [
    "COMSPEC_Med", "Transmettre Bilan Santé", "", {
        private _blood = player getVariable ["ace_medical_bloodVolume", 100];
        private _incap = player getVariable ["ace_medical_incapacitated", false];
        private _status = if (_incap) then { "Inconscient" } else { if (_blood < 60) then { "Blessé" } else { "Stable" } };
        [player, "CHAT", format ["Statut Médical: %1 (sang: %2)", _status, round _blood]] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Bilan santé transmis.";
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_addActionToObject;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _medAction] call ace_interact_menu_fnc_addActionToObject;

private _reportInfantry = [
    "COMSPEC_ReportInfantry",
    "Reporter contact infanterie",
    "",
    {
        private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
        [player, "REPORT", ["INFANTRY", getPosATL player, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Rapport INFANTRY transmis.";
    },
    { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _reportInfantry] call ace_interact_menu_fnc_addActionToObject;

private _reportVehicle = [
    "COMSPEC_ReportVehicle",
    "Reporter véhicule",
    "",
    {
        private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
        [player, "REPORT", ["VEHICLE", getPosATL player, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Rapport VEHICLE transmis.";
    },
    { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _reportVehicle] call ace_interact_menu_fnc_addActionToObject;

private _reportArmor = [
    "COMSPEC_ReportArmor",
    "Reporter blindé",
    "",
    {
        private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
        [player, "REPORT", ["ARMOR", getPosATL player, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Rapport ARMOR transmis.";
    },
    { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _reportArmor] call ace_interact_menu_fnc_addActionToObject;

private _reportAA = [
    "COMSPEC_ReportAA",
    "Reporter défense AA",
    "",
    {
        private _missionId = missionNamespace getVariable ["comspec_overwatch_mission_id", "DEFAULT_MISSION"];
        [player, "REPORT", ["AIR_DEFENSE", getPosATL player, _missionId, name player]] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Rapport AIR_DEFENSE transmis.";
    },
    { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _reportAA] call ace_interact_menu_fnc_addActionToObject;
