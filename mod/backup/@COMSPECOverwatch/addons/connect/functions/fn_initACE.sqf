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
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _medAction] call ace_interact_menu_fnc_addActionToObject;
