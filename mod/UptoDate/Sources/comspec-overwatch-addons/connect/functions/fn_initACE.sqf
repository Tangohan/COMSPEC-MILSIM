if (!hasInterface) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["initACE", "ace_interact_menu absent — menus non installés", nil, "ACE", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
};
if (isNil "ace_interact_menu_fnc_createAction") exitWith {
    ["initACE", "ace_interact_menu_fnc_createAction indéfini", nil, "ACE", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
};
if (isNil "ace_interact_menu_fnc_addActionToObject") exitWith {
    ["initACE", "ace_interact_menu_fnc_addActionToObject indéfini", nil, "ACE", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
};

// Joueur pas encore prêt : reporter (évite addActionToObject sur objNull).
if (isNull player) exitWith {
    ["DEBUG", "ACE", "player null — report init +2s"] call comspec_overwatch_connect_fnc_log;
    [{ [] call comspec_overwatch_connect_fnc_initACE }, [], 2] call CBA_fnc_waitAndExecute;
};

[] call comspec_overwatch_connect_fnc_aceSweepPlayerSelfActions;

if (missionNamespace getVariable ["COMSPEC_ACEMenuReady", false]) exitWith {
    missionNamespace setVariable ["COMSPEC_ACEMenuUnit", player, false];
};
missionNamespace setVariable ["COMSPEC_ACEMenuReady", true, false];
["INFO", "ACE", "Installation menus ACE SelfActions"] call comspec_overwatch_connect_fnc_log;

// Condition CBA : toujours un booléen (évite nil dans le menu ACE).
private _condEnabled = { missionNamespace getVariable ["comspec_overwatch_enabled", true] };
private _condSync = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
};
// insertChildren ACE : DOIT être du code retournant un tableau (jamais nil / array passé à la place).
private _noChildren = { [] };

private _mainAction = [
    "COMSPEC_Main", "COMSPEC Overwatch", "", {}, _condEnabled, _noChildren
] call ace_interact_menu_fnc_createAction;
[_mainAction, ["ACE_SelfActions"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _tabletAction = [
    "COMSPEC_Tablet", "Ouvrir téléphone ATAK", "", {
        ["all"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_tabletAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _resynchAction = [
    "COMSPEC_Resynch", "Resynch Athena (tout renvoyer)", "", {
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openResynch") then {
            [] call comspec_overwatch_atak_athena_fnc_athena_openResynch;
        } else {
            [] spawn { [] call comspec_overwatch_connect_fnc_forceSyncData; };
        };
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_resynchAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _wardrobePushAction = [
    "COMSPEC_WardrobePush", "Wardrobes ACE → Athena", "", {
        [] spawn { [] call comspec_overwatch_connect_fnc_arsenalPushAll; };
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_wardrobePushAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _wardrobePullAction = [
    "COMSPEC_WardrobePull", "Wardrobes Athena → ACE", "", {
        [] spawn { [] call comspec_overwatch_connect_fnc_arsenalPullAll; };
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_wardrobePullAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _noteAction = [
    "COMSPEC_IntelNote", "Rédiger une fiche de renseignement…", "", {
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openNote") then {
            [""] call comspec_overwatch_atak_athena_fnc_athena_openNote;
        } else {
            [""] call comspec_overwatch_connect_fnc_intelNoteShow;
        };
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_noteAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _pingAction = [
    "COMSPEC_Ping", "Envoyer Ping", "", {
        [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
        ["Point d'intérêt transmis.", "ping", "info"] call comspec_overwatch_connect_fnc_announce;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_pingAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

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
[_medAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _medInboxAction = [
    "COMSPEC_MedInbox", "Alertes médicales (triage)", "", {
        ["urgences"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    }, {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
        && { [] call comspec_overwatch_connect_fnc_canTriageMedical }
    }, _noChildren
] call ace_interact_menu_fnc_createAction;
[_medInboxAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _condCommander = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    && { [] call comspec_overwatch_connect_fnc_canIssueOrder }
};

private _orderMenu = [
    "COMSPEC_OrderMenu", "Ordres C2", "", {}, _condCommander, _noChildren
] call ace_interact_menu_fnc_createAction;
[_orderMenu, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _composeAction = [
    "COMSPEC_OrderCompose", "Rédiger un ordre / FRAGO…", "", {
        [""] call comspec_overwatch_connect_fnc_orderComposeShow;
    }, {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true] }
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
        && { [] call comspec_overwatch_connect_fnc_canIssueOrder }
    }, _noChildren
] call ace_interact_menu_fnc_createAction;
[_composeAction, ["ACE_SelfActions", "COMSPEC_Main", "COMSPEC_OrderMenu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _fragoAction = [
    "COMSPEC_OrderFrago", "Rédiger un FRAGO…", "", {
        ["FRAGO"] call comspec_overwatch_connect_fnc_orderComposeShow;
    }, {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true] }
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
        && { [] call comspec_overwatch_connect_fnc_canIssueOrder }
    }, _noChildren
] call ace_interact_menu_fnc_createAction;
[_fragoAction, ["ACE_SelfActions", "COMSPEC_Main", "COMSPEC_OrderMenu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

{
    _x params ["_id", "_label", "_type", "_announce"];
    private _a = [
        _id,
        _label,
        "",
        {
            params ["_target", "_player", "_params"];
            _params params ["_orderType", "_announceLabel"];
            if !([] call comspec_overwatch_connect_fnc_canIssueOrder) exitWith {
                ["Seul le chef d’unité peut émettre cet ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
            };
            private _g = group _player;
            private _hasGroupLeader = !isNull leader _g;
            private _targetName = if (_hasGroupLeader) then { groupId _g } else { name _player };
            private _targetType = if (_hasGroupLeader) then { "group" } else { "solo" };
            [_orderType, _targetName, "", "IMPORTANT", "", _targetType] call comspec_overwatch_connect_fnc_issueOrder;
            [format ["Ordre %1 → %2", _announceLabel, _targetName], "order", "info"] call comspec_overwatch_connect_fnc_announce;
        },
        _condCommander,
        _noChildren,
        [_type, _announce]
    ] call ace_interact_menu_fnc_createAction;
    [_a, ["ACE_SelfActions", "COMSPEC_Main", "COMSPEC_OrderMenu"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;
} forEach [
    ["COMSPEC_OrderMove", "Ordonner déplacement (rapide)", "MOVE", "déplacement"],
    ["COMSPEC_OrderHold", "Ordonner maintien (rapide)", "HOLD", "maintien"],
    ["COMSPEC_OrderRecon", "Ordonner reconnaissance (rapide)", "RECON", "reconnaissance"],
    ["COMSPEC_OrderQRF", "Ordonner renfort (rapide)", "QRF", "renfort"]
];

private _casAction = [
    "COMSPEC_CAS", "Appui aérien (Athena)", "", {
        [] call comspec_overwatch_connect_fnc_casRequestShow;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_casAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _manifestAction = [
    "COMSPEC_Manifest", "Manifeste de vol (Athena)", "", {
        [] call comspec_overwatch_connect_fnc_flightManifestShow;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_manifestAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _briefingAction = [
    "COMSPEC_Briefing", "Briefing / diaporama", "", {
        [] call comspec_overwatch_connect_fnc_openBriefingBoard;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_briefingAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _reconAction = [
    "COMSPEC_Recon", "Envoyer photo Recon", "", {
        [] call comspec_overwatch_connect_fnc_captureReconImage;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_reconAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _terrainAction = [
    "COMSPEC_Terrain", "Relever le relief autour de l'équipe", "", {
        [] call comspec_overwatch_connect_fnc_sampleTerrain;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_terrainAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _sceneAction = [
    "COMSPEC_Scene", "Relever bâtiments et forêts autour de l'équipe", "", {
        [true] call comspec_overwatch_connect_fnc_sampleScene;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_sceneAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _geoAction = [
    "COMSPEC_GeoNetwork", "Relever villes et routes (réseau Athena)", "", {
        [] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_geoAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

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
[_helmetSnapAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

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
[_droneSnapAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _laserAction = [
    "COMSPEC_LaserSync", "Synchroniser code laser", "", {
        [] call comspec_overwatch_connect_fnc_syncLaserCode;
        ["Code laser synchronisé.", "laser", "info"] call comspec_overwatch_connect_fnc_announce;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_laserAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _callsignAction = [
    "COMSPEC_Callsign", "Mon indicatif / liaison", "", {
        ["liaison"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_callsignAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _ordersAction = [
    "COMSPEC_OrderInbox", "Ordres C2 (TASK)", "", {
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openTask") then {
            [] call comspec_overwatch_atak_athena_fnc_athena_openTask;
        } else {
            [] call comspec_overwatch_connect_fnc_orderInboxShow;
        };
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_ordersAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _messagesAction = [
    "COMSPEC_Messages", "Messagerie Athena", "", {
        ["messages"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_messagesAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _photosAction = [
    "COMSPEC_Photos", "Photos Athena", "", {
        ["photo"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    }, _condSync, _noChildren
] call ace_interact_menu_fnc_createAction;
[_photosAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _bugAction = [
    "COMSPEC_BugReport", "Signaler un problème…", "", {
        [] call comspec_overwatch_connect_fnc_bugReportShow;
    }, _condEnabled, _noChildren
] call ace_interact_menu_fnc_createAction;
[_bugAction, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

private _disableOwnPhone = [
    "COMSPEC_DisableOwnPhoneGps",
    "Couper mon téléphone GPS",
    "\A3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa",
    {
        [player] call comspec_overwatch_connect_fnc_aceDisablePhoneTrack;
    },
    {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { [player, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag }
    },
    _noChildren
] call ace_interact_menu_fnc_createAction;
[_disableOwnPhone, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

// Action sur un autre joueur : saisir / marquer ATAK capturé (clé incorrecte)
private _captureAtakAction = [
    "COMSPEC_CaptureAtak",
    "Saisir l’ATAK (capturer)",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa",
    {
        params ["_target"];
        [_target] call comspec_overwatch_connect_fnc_captureEnemyAtak;
    },
    {
        params ["_target"];
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { !isNull _target }
        && { isPlayer _target }
        && { !(_target isEqualTo player) }
        && { (player distance _target) < 4 }
        && {
            (!alive _target)
            || { lifeState _target == "INCAPACITATED" }
            || { captive _target }
            || { _target getVariable ["ACE_isUnconscious", false] }
        }
    },
    _noChildren
] call ace_interact_menu_fnc_createAction;
_captureAtakAction = [_captureAtakAction] call comspec_overwatch_connect_fnc_acePadAction;
if (
    _captureAtakAction isNotEqualTo []
    && {!(missionNamespace getVariable ["COMSPEC_ACEClassActionsReady", false])}
) then {
    ["CAManBase", 0, ["ACE_MainActions"], _captureAtakAction, true] call ace_interact_menu_fnc_addActionToClass;
};

private _disablePhoneAction = [
    "COMSPEC_DisablePhoneGps",
    "Couper le téléphone GPS",
    "\A3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa",
    {
        params ["_target"];
        [_target] call comspec_overwatch_connect_fnc_aceDisablePhoneTrack;
    },
    {
        params ["_target"];
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { !isNull _target }
        && { !(_target isEqualTo player) }
        && { _target isKindOf "CAManBase" }
        && { (player distance _target) < 4 }
        && { [_target, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag }
    },
    _noChildren
] call ace_interact_menu_fnc_createAction;
_disablePhoneAction = [_disablePhoneAction] call comspec_overwatch_connect_fnc_acePadAction;
if (
    _disablePhoneAction isNotEqualTo []
    && {!(missionNamespace getVariable ["COMSPEC_ACEClassActionsReady", false])}
) then {
    ["CAManBase", 0, ["ACE_MainActions"], _disablePhoneAction, true] call ace_interact_menu_fnc_addActionToClass;
};
if (
    (_captureAtakAction isNotEqualTo [])
    || {_disablePhoneAction isNotEqualTo []}
) then {
    missionNamespace setVariable ["COMSPEC_ACEClassActionsReady", true, false];
};

[] call comspec_overwatch_connect_fnc_initChargeAceActions;

missionNamespace setVariable ["COMSPEC_ACEMenuUnit", player, false];

if (!isNil "comspec_overwatch_connect_fnc_getBloodType") then {
    private _bt = [] call comspec_overwatch_connect_fnc_getBloodType;
    if (_bt isNotEqualTo "") then {
        ["COMSPECExtension" callExtension ["SetBloodType", [_bt]]] call comspec_overwatch_connect_fnc_extResult;
    };
};
