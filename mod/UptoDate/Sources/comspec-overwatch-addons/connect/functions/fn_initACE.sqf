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

if (isNull player) exitWith {
    ["DEBUG", "ACE", "player null — report init +2s"] call comspec_overwatch_connect_fnc_log;
    [{ [] call comspec_overwatch_connect_fnc_initACE }, [], 2] call CBA_fnc_waitAndExecute;
};

[] call comspec_overwatch_connect_fnc_aceSweepPlayerSelfActions;

// Version de structure : forcer le rebuild si l’arbre change (évite l’ancien menu plat).
private _menuVer = 3;
if (
    (missionNamespace getVariable ["COMSPEC_ACEMenuStructureVer", 0]) isEqualTo _menuVer
    && {missionNamespace getVariable ["COMSPEC_ACEMenuReady", false]}
) exitWith {
    missionNamespace setVariable ["COMSPEC_ACEMenuUnit", player, false];
};

// Retirer toutes les actions COMSPEC déjà posées (classe + objet) avant réinstallation.
private _installed = missionNamespace getVariable ["COMSPEC_ACESelfActions", []];
if (!(_installed isEqualType [])) then { _installed = []; };
{
    if (!(_x isEqualType []) || {(count _x) < 2}) then { continue };
    _x params ["_path", "_actionId"];
    if (!(_path isEqualType []) || {!(_actionId isEqualType "")}) then { continue };
    if (!isNil "ace_interact_menu_fnc_removeActionFromClass") then {
        ["CAManBase", 1, _path, _actionId] call ace_interact_menu_fnc_removeActionFromClass;
    };
    if (!isNull player && {!isNil "ace_interact_menu_fnc_removeActionFromObject"}) then {
        [player, 1, _path, _actionId] call ace_interact_menu_fnc_removeActionFromObject;
    };
} forEach _installed;
missionNamespace setVariable ["COMSPEC_ACESelfActions", [], false];
missionNamespace setVariable ["COMSPEC_ACEMenuReady", true, false];
missionNamespace setVariable ["COMSPEC_ACEMenuStructureVer", _menuVer, false];
// Les sous-menus ATAK / Athena bootstrap doivent se réinstaller après le purge.
missionNamespace setVariable ["COMSPEC_ATAKMenuReady", false, false];
missionNamespace setVariable ["COMSPEC_ACEAthenaReady", false, false];

["INFO", "ACE", "Installation menus ACE SelfActions (arbre v3)"] call comspec_overwatch_connect_fnc_log;

private _condEnabled = { missionNamespace getVariable ["comspec_overwatch_enabled", true] };
private _condSync = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
};
private _condEcoti = { [] call comspec_overwatch_connect_fnc_ecotiIsAvailable };
private _noChildren = { [] };
private _root = ["ACE_SelfActions", "COMSPEC_Main"];

private _fnc_folder = {
    params ["_id", "_label", "_path", ["_cond", { missionNamespace getVariable ["comspec_overwatch_enabled", true] }]];
    private _a = [_id, _label, "", {}, _cond, { [] }] call ace_interact_menu_fnc_createAction;
    [_a, _path] call comspec_overwatch_connect_fnc_aceAddSelfAction;
};

private _fnc_leaf = {
    params ["_id", "_label", "_stmt", "_path", ["_cond", {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    }]];
    private _a = [_id, _label, "", _stmt, _cond, { [] }] call ace_interact_menu_fnc_createAction;
    [_a, _path] call comspec_overwatch_connect_fnc_aceAddSelfAction;
};

private _mainAction = ["COMSPEC_Main", "COMSPEC Athena", "", {}, _condEnabled, _noChildren] call ace_interact_menu_fnc_createAction;
[_mainAction, ["ACE_SelfActions"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

["COMSPEC_Account", "Connexion Athena", {
    [] call comspec_overwatch_connect_fnc_openLogin;
}, _root, _condEnabled] call _fnc_leaf;

["COMSPEC_Tablet", "Ouvrir téléphone ATAK", {
    ["all"] call comspec_overwatch_connect_fnc_openAthenaFeature;
}, _root, _condSync] call _fnc_leaf;

["COMSPEC_Apps", "Applications", _root] call _fnc_folder;
["COMSPEC_Situation", "Affichage situation", _root, _condEcoti] call _fnc_folder;
["COMSPEC_Transmit", "Transmission", _root] call _fnc_folder;
["COMSPEC_MapSurvey", "Cartographie", _root] call _fnc_folder;
["COMSPEC_Support", "Appui & mission", _root] call _fnc_folder;
["COMSPEC_Loadout", "Tenues", _root] call _fnc_folder;
["COMSPEC_Link", "Compte & liaison", _root] call _fnc_folder;

private _apps = _root + ["COMSPEC_Apps"];
private _sit = _root + ["COMSPEC_Situation"];
private _tx = _root + ["COMSPEC_Transmit"];
private _map = _root + ["COMSPEC_MapSurvey"];
private _sup = _root + ["COMSPEC_Support"];
private _load = _root + ["COMSPEC_Loadout"];
private _link = _root + ["COMSPEC_Link"];

["COMSPEC_OrderInbox", "Ordres reçus", {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openTask") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_openTask;
    } else {
        [] call comspec_overwatch_connect_fnc_orderInboxShow;
    };
}, _apps] call _fnc_leaf;

["COMSPEC_Messages", "Messagerie", {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openComms") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_openComms;
    } else {
        ["messages"] call comspec_overwatch_connect_fnc_openAthenaFeature;
    };
}, _apps] call _fnc_leaf;

["COMSPEC_Photos", "Photos", {
    ["photo"] call comspec_overwatch_connect_fnc_openAthenaFeature;
}, _apps] call _fnc_leaf;

["COMSPEC_Briefing", "Briefing / diaporama", {
    [] call comspec_overwatch_connect_fnc_openBriefingBoard;
}, _apps] call _fnc_leaf;

["COMSPEC_IntelNote", "Fiche de renseignement…", {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openNote") then {
        [""] call comspec_overwatch_atak_athena_fnc_athena_openNote;
    } else {
        [""] call comspec_overwatch_connect_fnc_intelNoteShow;
    };
}, _apps] call _fnc_leaf;

["COMSPEC_EcotiMarkBuilding", "Désigner ce bâtiment", {
    [] call comspec_overwatch_connect_fnc_ecotiMarkBuilding;
}, _sit, _condEcoti] call _fnc_leaf;

["COMSPEC_EcotiCycleFloor", "Changer d’étage", {
    [] call comspec_overwatch_connect_fnc_ecotiCycleFloor;
}, _sit, {
    private _cut = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
    ([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)
    && {_cut isEqualType true}
    && {_cut}
    && {!isNull (missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull])}
}] call _fnc_leaf;

["COMSPEC_EcotiCutAtLook", "Découper à la hauteur regardée", {
    [] call comspec_overwatch_connect_fnc_ecotiCutAtLook;
}, _sit, _condEcoti] call _fnc_leaf;

["COMSPEC_EcotiIlluminate", "Éclairer / éteindre la zone", {
    ["toggle"] call comspec_overwatch_connect_fnc_ecotiIlluminateZone;
}, _sit, _condEcoti] call _fnc_leaf;

["COMSPEC_EcotiRouteAdd", "Ajouter un point d’itinéraire", {
    ["add"] call comspec_overwatch_connect_fnc_ecotiRouteEdit;
}, _sit, _condEcoti] call _fnc_leaf;

["COMSPEC_EcotiRouteClear", "Effacer l’itinéraire", {
    ["clear"] call comspec_overwatch_connect_fnc_ecotiRouteEdit;
}, _sit, {
    ([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)
    && {(count (missionNamespace getVariable ["COMSPEC_EcotiRoutePoints", []])) > 0}
}] call _fnc_leaf;

["COMSPEC_Ping", "Envoyer Ping", {
    [player, "PING", getPos player, "Point d'interet", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
    ["Point d'intérêt transmis.", "ping", "info"] call comspec_overwatch_connect_fnc_announce;
}, _tx] call _fnc_leaf;

["COMSPEC_Med", "Transmettre bilan santé", {
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
}, _tx] call _fnc_leaf;

["COMSPEC_MedInbox", "Alertes médicales (triage)", {
    ["urgences"] call comspec_overwatch_connect_fnc_openAthenaFeature;
}, _tx, {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    && { [] call comspec_overwatch_connect_fnc_canTriageMedical }
}] call _fnc_leaf;

["COMSPEC_Recon", "Envoyer photo Recon", {
    [] call comspec_overwatch_connect_fnc_captureReconImage;
}, _tx] call _fnc_leaf;

["COMSPEC_HelmetSnap", "Envoyer aperçu casque", {
    private _uid = getPlayerUID player;
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    private _cap = format ["Aperçu casque — %1 · grille %2", _cs, mapGridPosition player];
    ["", _cap, "HELMET", format ["helmet:%1", _uid]] call comspec_overwatch_connect_fnc_captureReconImage;
}, _tx, {
    alive player
    && {missionNamespace getVariable ["comspec_overwatch_enabled", true]}
    && {
        ("ItemcTabHCam" in (items player + assignedItems player))
        || {((headgear player) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))}
    }
}] call _fnc_leaf;

["COMSPEC_DroneSnap", "Envoyer aperçu drone", {
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
}, _tx, {
    alive player
    && {missionNamespace getVariable ["comspec_overwatch_enabled", true]}
    && {
        !isNull (getConnectedUAV player)
        || {
            private _st = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
            (_st isEqualType createHashMap) && {!isNull (_st getOrDefault ["drone", objNull])}
        }
    }
}] call _fnc_leaf;

["COMSPEC_LaserSync", "Synchroniser code laser", {
    [] call comspec_overwatch_connect_fnc_syncLaserCode;
    ["Code laser synchronisé.", "laser", "info"] call comspec_overwatch_connect_fnc_announce;
}, _tx] call _fnc_leaf;

["COMSPEC_Terrain", "Relever le relief", {
    [] call comspec_overwatch_connect_fnc_sampleTerrain;
}, _map] call _fnc_leaf;

["COMSPEC_Scene", "Relever bâtiments et forêts", {
    [true] call comspec_overwatch_connect_fnc_sampleScene;
}, _map] call _fnc_leaf;

["COMSPEC_GeoNetwork", "Relever villes et routes", {
    [] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
}, _map] call _fnc_leaf;

["COMSPEC_CAS", "Appui aérien", {
    [] call comspec_overwatch_connect_fnc_casRequestShow;
}, _sup] call _fnc_leaf;

["COMSPEC_Manifest", "Manifeste de vol", {
    [] call comspec_overwatch_connect_fnc_flightManifestShow;
}, _sup] call _fnc_leaf;

private _condCommander = {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    && { [] call comspec_overwatch_connect_fnc_canIssueOrder }
};

["COMSPEC_OrderMenu", "Ordres C2", _sup, _condCommander] call _fnc_folder;
private _ord = _sup + ["COMSPEC_OrderMenu"];

["COMSPEC_OrderCompose", "Rédiger un ordre / FRAGO…", {
    [""] call comspec_overwatch_connect_fnc_orderComposeShow;
}, _ord, {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true] }
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    && { [] call comspec_overwatch_connect_fnc_canIssueOrder }
}] call _fnc_leaf;

["COMSPEC_OrderFrago", "Rédiger un FRAGO…", {
    ["FRAGO"] call comspec_overwatch_connect_fnc_orderComposeShow;
}, _ord, {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true] }
    && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    && { [] call comspec_overwatch_connect_fnc_canIssueOrder }
}] call _fnc_leaf;

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
    [_a, _ord] call comspec_overwatch_connect_fnc_aceAddSelfAction;
} forEach [
    ["COMSPEC_OrderMove", "Déplacement (rapide)", "MOVE", "déplacement"],
    ["COMSPEC_OrderHold", "Maintien (rapide)", "HOLD", "maintien"],
    ["COMSPEC_OrderRecon", "Reconnaissance (rapide)", "RECON", "reconnaissance"],
    ["COMSPEC_OrderQRF", "Renfort (rapide)", "QRF", "renfort"]
];

["COMSPEC_WardrobePush", "Envoyer tenues vers Athena", {
    [] spawn { [] call comspec_overwatch_connect_fnc_arsenalPushAll; };
}, _load] call _fnc_leaf;

["COMSPEC_WardrobePull", "Charger tenues depuis Athena", {
    [] spawn { [] call comspec_overwatch_connect_fnc_arsenalPullAll; };
}, _load] call _fnc_leaf;

["COMSPEC_Callsign", "Mon indicatif / liaison", {
    ["liaison"] call comspec_overwatch_connect_fnc_openAthenaFeature;
}, _link] call _fnc_leaf;

["COMSPEC_Resynch", "Resynchroniser", {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openResynch") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_openResynch;
    } else {
        [] spawn { [] call comspec_overwatch_connect_fnc_forceSyncData; };
    };
}, _link] call _fnc_leaf;

["COMSPEC_DisableOwnPhoneGps", "Couper mon téléphone GPS", {
    [player] call comspec_overwatch_connect_fnc_aceDisablePhoneTrack;
}, _link, {
    (missionNamespace getVariable ["comspec_overwatch_enabled", true])
    && { [player, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag }
}] call _fnc_leaf;

["COMSPEC_BugReport", "Signaler un problème…", {
    [] call comspec_overwatch_connect_fnc_bugReportShow;
}, _link, _condEnabled] call _fnc_leaf;

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
            isNull (objectParent player)
            || { (vehicle player) isEqualTo player }
            || { !([vehicle player] call comspec_overwatch_connect_fnc_isHatchetVehicle) }
        }
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
        && {
            isNull (objectParent player)
            || { (vehicle player) isEqualTo player }
            || { !([vehicle player] call comspec_overwatch_connect_fnc_isHatchetVehicle) }
        }
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
        if (!isNil "comspec_overwatch_connect_fnc_operatorProfileTick") then {
            [{
                ["blood_type_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick;
            }, [], 2] call CBA_fnc_waitAndExecute;
        };
    };
};
