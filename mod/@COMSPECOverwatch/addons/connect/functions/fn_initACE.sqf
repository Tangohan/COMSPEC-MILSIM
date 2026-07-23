if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {};

// Condition CBA : toujours un booléen (évite nil dans le menu ACE).
private _condEnabled = { missionNamespace getVariable ["comspec_overwatch_enabled", true] };
private _condUi = { [false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi };
private _condSync = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
};
// insertChildren ACE : DOIT être du code retournant un tableau (jamais nil / array passé à la place).
private _noChildren = { [] };

private _mainAction = [
    "COMSPEC_Main", "COMSPEC Overwatch", "", {}, _condEnabled, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions"], _mainAction] call ace_interact_menu_fnc_addActionToObject;

private _tabletAction = [
    "COMSPEC_Tablet", "Ouvrir tablette Athena", "", {
        ["bft"] call comspec_overwatch_connect_fnc_openTabletView;
    }, _condUi, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _tabletAction] call ace_interact_menu_fnc_addActionToObject;

private _pingAction = [
    "COMSPEC_Ping", "Envoyer Ping", "", {
        [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
        ["Point d'intérêt transmis.", "ping", "info"] call comspec_overwatch_connect_fnc_announce;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _pingAction] call ace_interact_menu_fnc_addActionToObject;

private _medAction = [
    "COMSPEC_Med", "Transmettre Bilan Santé", "", {
        private _state = [player] call comspec_overwatch_connect_fnc_getMedicalState;
        private _parts = _state splitString "|";
        private _health = if (count _parts >= 1) then { _parts select 0 } else { "stable" };
        private _blood = if (count _parts >= 2) then { _parts select 1 } else { "?" };
        private _hr = if (count _parts >= 4) then { _parts select 3 } else { "?" };
        private _status = switch (_health) do {
            case "cardiac_arrest": { "Arrêt cardiaque" };
            case "unconscious": { "Inconscient" };
            case "wounded": { "Blessé" };
            default { "Stable" };
        };
        [player, "CHAT", format ["WIA|%1|sang≈%2%%|FC=%3", _status, _blood, _hr], "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;
        ["Bilan de santé transmis.", "medical", "info"] call comspec_overwatch_connect_fnc_announce;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _medAction] call ace_interact_menu_fnc_addActionToObject;

private _medInboxAction = [
    "COMSPEC_MedInbox", "Alertes médicales (triage)", "", {
        ["medical"] call comspec_overwatch_connect_fnc_openTabletView;
    }, {
        ([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi)
        && {[] call comspec_overwatch_connect_fnc_canTriageMedical}
    }, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _medInboxAction] call ace_interact_menu_fnc_addActionToObject;

private _orderMenu = [
    "COMSPEC_OrderMenu", "Ordres C2", "", {}, _condSync, _noChildren
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
            [format ["Ordre %1 transmis vers %2.", _orderType, _targetName], "order", "info"] call comspec_overwatch_connect_fnc_announce;
        },
        _condSync,
        _noChildren,
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
    "COMSPEC_CAS", "Appui aérien (tablette)", "", {
        ["cas"] call comspec_overwatch_connect_fnc_openTabletView;
    }, _condUi, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _casAction] call ace_interact_menu_fnc_addActionToObject;

private _manifestAction = [
    "COMSPEC_Manifest", "Flight Manifest (tablette)", "", {
        ["manifest"] call comspec_overwatch_connect_fnc_openTabletView;
    }, _condUi, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _manifestAction] call ace_interact_menu_fnc_addActionToObject;

private _reconAction = [
    "COMSPEC_Recon", "Envoyer photo Recon", "", {
        [] call comspec_overwatch_connect_fnc_captureReconImage;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _reconAction] call ace_interact_menu_fnc_addActionToObject;

private _helmetSnapAction = [
    "COMSPEC_HelmetSnap", "Envoyer aperçu casque", "", {
        private _uid = getPlayerUID player;
        private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_cs isEqualTo "") then { _cs = name player; };
        private _cap = format ["Aperçu casque — %1 · grille %2", _cs, mapGridPosition player];
        ["", _cap, "HELMET", format ["helmet:%1", _uid]] call comspec_overwatch_connect_fnc_captureReconImage;
    }, {
        alive player
        && {missionNamespace getVariable ["comspec_overwatch_enabled", true]}
        && {
            ("ItemcTabHCam" in (items player + assignedItems player))
            || {((headgear player) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))}
        }
    }, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _helmetSnapAction] call ace_interact_menu_fnc_addActionToObject;

private _droneSnapAction = [
    "COMSPEC_DroneSnap", "Envoyer aperçu drone", "", {
        private _drone = objNull;
        private _droneState = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
        if (_droneState isEqualType createHashMap) then {
            _drone = _droneState getOrDefault ["drone", objNull];
        };
        if (isNull _drone) then { _drone = getConnectedUAV player; };
        if (isNull _drone || {!alive _drone}) exitWith {
            ["COMSPEC_Warning", ["Aucun drone connecté."]] call comspec_overwatch_connect_fnc_showNotification;
        };
        private _netId = netId _drone;
        if (_netId isEqualTo "") then { _netId = str _drone; };
        private _disp = getText (configOf _drone >> "displayName");
        if (_disp isEqualTo "") then { _disp = typeOf _drone; };
        private _cap = format ["Aperçu drone — %1 · grille %2", _disp, mapGridPosition _drone];
        ["", _cap, "DRONE", format ["drone:%1", _netId]] call comspec_overwatch_connect_fnc_captureReconImage;
    }, {
        alive player
        && {missionNamespace getVariable ["comspec_overwatch_enabled", true]}
        && {
            !isNull (getConnectedUAV player)
            || {
                private _st = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
                (_st isEqualType createHashMap) && {!isNull (_st getOrDefault ["drone", objNull])}
            }
        }
    }, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _droneSnapAction] call ace_interact_menu_fnc_addActionToObject;

private _laserAction = [
    "COMSPEC_LaserSync", "Synchroniser code laser", "", {
        [] call comspec_overwatch_connect_fnc_syncLaserCode;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _laserAction] call ace_interact_menu_fnc_addActionToObject;

private _callsignAction = [
    "COMSPEC_Callsign", "Mon indicatif (tablette)", "", {
        ["callsign"] call comspec_overwatch_connect_fnc_openTabletView;
    }, _condUi, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _callsignAction] call ace_interact_menu_fnc_addActionToObject;

private _ordersAction = [
    "COMSPEC_OrderInbox", "Ordres reçus (tablette)", "", {
        ["orders"] call comspec_overwatch_connect_fnc_openTabletView;
    }, _condUi, _noChildren
] call ace_interact_menu_fnc_createAction;
[player, 1, ["ACE_SelfActions", "COMSPEC_Main"], _ordersAction] call ace_interact_menu_fnc_addActionToObject;
