if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};

private _mainAction = [
    "COMSPEC_Main", "COMSPEC Overwatch", "", {}, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions"], _mainAction] call ace_interact_menu_fnc_addActionToObject;

private _pingAction = [
    "COMSPEC_Ping", "Envoyer Ping", "", {
        [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Ping transmis.";
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _pingAction] call ace_interact_menu_fnc_addActionToObject;

private _medAction = [
    "COMSPEC_Med", "Transmettre Bilan Santé", "", {
        private _blood = player getVariable ["ace_medical_bloodVolume", 100];
        private _incap = player getVariable ["ace_medical_incapacitated", false];
        private _status = if (_incap) then { "Inconscient" } else { if (_blood < 60) then { "Blessé" } else { "Stable" } };
        [player, "CHAT", format ["WIA|%1|blood=%2", _status, round _blood], "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;
        systemChat "Bilan santé transmis.";
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _medAction] call ace_interact_menu_fnc_addActionToObject;

private _orderMenu = [
    "COMSPEC_OrderMenu", "Ordres C2", "", {}, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _orderMenu] call ace_interact_menu_fnc_addActionToObject;

{
    _x params ["_id", "_label", "_type"];
    private _a = [
        _id,
        _label,
        "",
        {
            params ["_target", "_player", "_params"];
            _params params ["_orderType"];
            private _g = group _player;
            private _targetName = if (!isNull leader _g) then { groupId _g } else { name _player };
            [_orderType, _targetName, "", "IMPORTANT", ""] call comspec_overwatch_connect_fnc_issueOrder;
            systemChat format ["Ordre %1 transmis vers %2.", _orderType, _targetName];
        },
        { comspec_overwatch_enabled },
        [_type]
    ] call ace_interact_menu_fnc_createAction;
    [player, 1, ["ACE_SelfActions", "COMSPEC_Main", "COMSPEC_OrderMenu"], _a] call ace_interact_menu_fnc_addActionToObject;
} forEach [
    ["COMSPEC_OrderMove", "Ordonner MOVE", "MOVE"],
    ["COMSPEC_OrderHold", "Ordonner HOLD", "HOLD"],
    ["COMSPEC_OrderRecon", "Ordonner RECON", "RECON"],
    ["COMSPEC_OrderQRF", "Ordonner QRF", "QRF"]
];

private _casAction = [
    "COMSPEC_CAS", "Ouvrir CAS 9-Line", "", {
        [] call comspec_overwatch_connect_fnc_openCASDialog;
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _casAction] call ace_interact_menu_fnc_addActionToObject;

private _manifestAction = [
    "COMSPEC_Manifest", "Flight Manifest", "", {
        createDialog "COMSPEC_FlightManifest_Dialog";
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _manifestAction] call ace_interact_menu_fnc_addActionToObject;

private _reconAction = [
    "COMSPEC_Recon", "Envoyer photo Recon", "", {
        [] call comspec_overwatch_connect_fnc_captureReconImage;
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _reconAction] call ace_interact_menu_fnc_addActionToObject;

private _laserAction = [
    "COMSPEC_LaserSync", "Synchroniser code laser", "", {
        [] call comspec_overwatch_connect_fnc_syncLaserCode;
    }, { comspec_overwatch_enabled }
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _laserAction] call ace_interact_menu_fnc_addActionToObject;
