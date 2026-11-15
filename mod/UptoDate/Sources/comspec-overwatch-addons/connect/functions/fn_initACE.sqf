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
// v5 : un seul bouton Cartographie (fenêtre de relevé) à la place des trois envois séparés.
private _menuVer = 9;
if ((missionNamespace getVariable ["COMSPEC_ACEClassTreeVer", 0]) isEqualTo _menuVer) exitWith {
    missionNamespace setVariable ["COMSPEC_ACEMenuReady", true, false];
    missionNamespace setVariable ["COMSPEC_ACEMenuStructureVer", _menuVer, false];
    missionNamespace setVariable ["COMSPEC_ACEMenuUnit", player, false];
    missionNamespace setVariable ["COMSPEC_ACEAthenaReady", true, false];
};

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
        ["CAManBase", 1, _path + [_actionId], true] call ace_interact_menu_fnc_removeActionFromClass;
        if (!isNull player) then {
            [typeOf player, 1, _path + [_actionId], true] call ace_interact_menu_fnc_removeActionFromClass;
        };
    };
    if (!isNull player && {!isNil "ace_interact_menu_fnc_removeActionFromObject"}) then {
        [player, 1, _path + [_actionId]] call ace_interact_menu_fnc_removeActionFromObject;
    };
} forEach _installed;

// Anciennes feuilles (45 identifiants × 3 retraits, dont 2 avec héritage = 135 appels dans l'image) : retirées
// seulement si un arbre d'une autre version a déjà été posé pendant cette mission. À l'arrivée en mission il n'y a rien à
// retirer : ACE vide ses actions de classe à chaque mission, et tout ce qui est posé sous COMSPEC_Main passe par
// fn_aceAddSelfAction, donc figure dans COMSPEC_ACESelfActions (retiré ci-dessus). Avant, ces 135 retraits sans effet
// étaient faits à chaque arrivée.
if ((missionNamespace getVariable ["COMSPEC_ACEMenuStructureVer", 0]) isNotEqualTo 0) then {
    // Purge des anciennes feuilles à plat sous COMSPEC Athena (session Arma précédente).
    private _mainPath = ["ACE_SelfActions", "COMSPEC_Main"];
    private _legacyFlat = [
        "COMSPEC_OrderInbox", "COMSPEC_Messages", "COMSPEC_Photos", "COMSPEC_Briefing",
        "COMSPEC_EcotiMarkBuilding", "COMSPEC_EcotiCycleFloor", "COMSPEC_EcotiIlluminate",
        "COMSPEC_EcotiRouteAdd", "COMSPEC_EcotiRouteClear",
        "COMSPEC_Ping", "COMSPEC_Med", "COMSPEC_MedInbox", "COMSPEC_IntelNote",
        "COMSPEC_Resynch", "COMSPEC_HelmetSnap", "COMSPEC_DroneSnap", "COMSPEC_LaserSync",
        "COMSPEC_Terrain", "COMSPEC_Scene", "COMSPEC_GeoNetwork", "COMSPEC_Recon", "COMSPEC_ReconNote",
        "COMSPEC_CAS", "COMSPEC_Manifest", "COMSPEC_OrderMenu", "COMSPEC_OrderCompose",
        "COMSPEC_OrderFrago", "COMSPEC_OrderMove", "COMSPEC_OrderHold", "COMSPEC_OrderRecon",
        "COMSPEC_OrderQRF", "COMSPEC_Loadout", "COMSPEC_WardrobePush", "COMSPEC_WardrobePull", "COMSPEC_WardrobeSyncAll",
        "COMSPEC_Callsign", "COMSPEC_BugReport", "COMSPEC_DisableOwnPhoneGps",
        "COMSPEC_ChargesAtak", "COMSPEC_RepairAtakPower", "COMSPEC_RepairAtakScreen",
        "COMSPEC_DiagnosticAtak"
    ];
    {
        if (!isNil "ace_interact_menu_fnc_removeActionFromClass") then {
            ["CAManBase", 1, _mainPath + [_x], true] call ace_interact_menu_fnc_removeActionFromClass;
            if (!isNull player) then {
                [typeOf player, 1, _mainPath + [_x], true] call ace_interact_menu_fnc_removeActionFromClass;
            };
        };
        if (!isNull player && {!isNil "ace_interact_menu_fnc_removeActionFromObject"}) then {
            [player, 1, _mainPath + [_x]] call ace_interact_menu_fnc_removeActionFromObject;
        };
    } forEach _legacyFlat;

    private _mapLegacyPath = ["ACE_SelfActions", "COMSPEC_Main", "COMSPEC_MapSurvey"];
    {
        if (!isNil "ace_interact_menu_fnc_removeActionFromClass") then {
            ["CAManBase", 1, _mapLegacyPath + [_x], true] call ace_interact_menu_fnc_removeActionFromClass;
            if (!isNull player) then {
                [typeOf player, 1, _mapLegacyPath + [_x], true] call ace_interact_menu_fnc_removeActionFromClass;
            };
        };
        if (!isNull player && {!isNil "ace_interact_menu_fnc_removeActionFromObject"}) then {
            [player, 1, _mapLegacyPath + [_x]] call ace_interact_menu_fnc_removeActionFromObject;
        };
    } forEach ["COMSPEC_Terrain", "COMSPEC_Scene", "COMSPEC_GeoNetwork"];
};

missionNamespace setVariable ["COMSPEC_ACESelfActions", [], false];
missionNamespace setVariable ["COMSPEC_ACEMenuReady", true, false];
missionNamespace setVariable ["COMSPEC_ACEMenuStructureVer", _menuVer, false];
// Les sous-menus ATAK / Athena bootstrap doivent se réinstaller après le purge.
missionNamespace setVariable ["COMSPEC_ATAKMenuReady", false, false];
missionNamespace setVariable ["COMSPEC_ACEAthenaReady", false, false];

["INFO", "ACE", format ["Installation menus ACE SelfActions (arbre v%1)", _menuVer]] call comspec_overwatch_connect_fnc_log;

// Installation étalée : les actions sont préparées ici (léger) et mises en file dans l'ordre (un dossier avant ses
// feuilles) ; elles sont posées plus bas, quelques-unes par image (2 ms au plus par image), au lieu de 26 à 43
// addActionToClass avec héritage dans la même image à l'arrivée en mission.
// File : [0, action, chemin] = self-action (fn_aceAddSelfAction) ; [1, arguments] = addActionToClass direct.
private _queue = [];

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
    private _a = [_id, _label, "", {}, _cond, { [] }] call comspec_overwatch_connect_fnc_aceAction;
    _queue pushBack [0, _a, _path];
};

// ATAK natif : plus les entrées qui ouvraient une ancienne fenêtre plein écran par-dessus le jeu, ni celles qui
// cherchaient l'ancien ATAK (toast « ATAK Enhanced non disponible ») ; tout cela est dans les apps du téléphone.
// Restent les actions sans fenêtre et sans équivalent au téléphone (ECOTI, ping, bilan santé, photos Recon/casque/drone,
// code laser, tenues, resynchro, ordres rapides). Un dossier vidé (Appui & mission) disparaît de lui-même dans ACE.
private _nativeDrop = if (isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main")) then { [
    "COMSPEC_Account", "COMSPEC_Tablet", "COMSPEC_OrderInbox", "COMSPEC_Messages", "COMSPEC_Photos",
    "COMSPEC_Briefing", "COMSPEC_IntelNote", "COMSPEC_MedInbox", "COMSPEC_ReconNote", "COMSPEC_TheaterSurvey",
    "COMSPEC_CAS", "COMSPEC_Manifest", "COMSPEC_OrderCompose", "COMSPEC_OrderFrago", "COMSPEC_Callsign",
    "COMSPEC_BugReport", "COMSPEC_DiagIsolate"
] } else { [] };

private _fnc_leaf = {
    params ["_id", "_label", "_stmt", "_path", ["_cond", {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { [player] call comspec_overwatch_connect_fnc_hasTerminal }
    }]];
    if (_id in _nativeDrop) exitWith {};
    private _a = [_id, _label, "", _stmt, _cond, { [] }] call comspec_overwatch_connect_fnc_aceAction;
    _queue pushBack [0, _a, _path];
};

private _mainAction = ["COMSPEC_Main", "COMSPEC Athena", "", {}, _condEnabled, _noChildren] call comspec_overwatch_connect_fnc_aceAction;
_queue pushBack [0, _mainAction, ["ACE_SelfActions"]];

["COMSPEC_Account", "Connexion Athena", {
    [] call comspec_overwatch_connect_fnc_openLogin;
}, _root, _condEnabled] call _fnc_leaf;

["COMSPEC_Tablet", "Ouvrir téléphone ATAK", {
    ["all"] call comspec_overwatch_connect_fnc_openAthenaFeature;
}, _root, _condSync] call _fnc_leaf;

["COMSPEC_Apps", "Applications", _root] call _fnc_folder;
["COMSPEC_Situation", "Infos tactiques JVN", _root, _condEcoti] call _fnc_folder;
["COMSPEC_Transmit", "Transmission", _root] call _fnc_folder;
["COMSPEC_MapSurvey", "Cartographie", _root] call _fnc_folder;
["COMSPEC_Support", "Appui & mission", _root] call _fnc_folder;
["COMSPEC_Link", "Compte & liaison", _root] call _fnc_folder;

private _apps = _root + ["COMSPEC_Apps"];
private _sit = _root + ["COMSPEC_Situation"];
private _tx = _root + ["COMSPEC_Transmit"];
private _map = _root + ["COMSPEC_MapSurvey"];
private _sup = _root + ["COMSPEC_Support"];
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

["COMSPEC_EcotiClearBuilding", "Annuler le pointage de bâtiment", {
    [] call comspec_overwatch_connect_fnc_ecotiClearBuilding;
}, _sit, {
    ([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)
    && {!isNull (missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull])}
}] call _fnc_leaf;

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
    && {(count (missionNamespace getVariable ["COMSPEC_EcotiRoutePoints", []])) > 0 || {!isNil "comspec_atak_native_fnc_routeAction" && {(["jvnCount"] call comspec_atak_native_fnc_routeAction) > 0}}}
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

["COMSPEC_ReconNote", "Reco : Noter", {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openRecon") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_openRecon;
    } else {
        [] call comspec_overwatch_connect_fnc_reconNoteShow;
    };
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

["COMSPEC_TheaterSurvey", "Relevé de la carte", {
    [false] call comspec_overwatch_connect_fnc_theaterSurveyShow;
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
    ] call comspec_overwatch_connect_fnc_aceAction;
    _queue pushBack [0, _a, _ord];
} forEach [
    ["COMSPEC_OrderMove", "Déplacement (rapide)", "MOVE", "déplacement"],
    ["COMSPEC_OrderHold", "Maintien (rapide)", "HOLD", "maintien"],
    ["COMSPEC_OrderRecon", "Reconnaissance (rapide)", "RECON", "reconnaissance"],
    ["COMSPEC_OrderQRF", "Renfort (rapide)", "QRF", "renfort"]
];

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

["COMSPEC_DiagIsolate", "Dépannage liaison…", {
    [] call comspec_overwatch_connect_fnc_diagIsolateLaunch;
}, _link, { true }] call _fnc_leaf;

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
] call comspec_overwatch_connect_fnc_aceAction;
_captureAtakAction = [_captureAtakAction] call comspec_overwatch_connect_fnc_acePadAction;
if (
    _captureAtakAction isNotEqualTo []
    && {!(missionNamespace getVariable ["COMSPEC_ACEClassActionsReady", false])}
) then {
    _queue pushBack [1, ["CAManBase", 0, ["ACE_MainActions"], _captureAtakAction, true]];
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
] call comspec_overwatch_connect_fnc_aceAction;
_disablePhoneAction = [_disablePhoneAction] call comspec_overwatch_connect_fnc_acePadAction;
if (
    _disablePhoneAction isNotEqualTo []
    && {!(missionNamespace getVariable ["COMSPEC_ACEClassActionsReady", false])}
) then {
    _queue pushBack [1, ["CAManBase", 0, ["ACE_MainActions"], _disablePhoneAction, true]];
};
if (
    (_captureAtakAction isNotEqualTo [])
    || {_disablePhoneAction isNotEqualTo []}
) then {
    missionNamespace setVariable ["COMSPEC_ACEClassActionsReady", true, false];
};

// Pose de la file. Premier passage tout de suite (la racine COMSPEC_Main existe dès cette image), la suite une
// image après l'autre. Pendant ce temps COMSPEC_ACEMenuBuilding retient fn_initATAKMenu et le dossier « Charges ATAK »
// (fn_initChargeAceActions), qui se posent après, comme avant : l'ordre des entrées du menu ne change pas.
missionNamespace setVariable ["COMSPEC_ACEMenuBuilding", true, false];
private _fnc_drain = {
    params ["_job", "_handle"];
    _job params ["_queue", "_stat", "_menuVer"];
    private _t0 = diag_tickTime;
    while { _queue isNotEqualTo [] } do {
        (_queue deleteAt 0) params ["_kind", "_a", ["_path", []]];
        if (_kind isEqualTo 0) then {
            [_a, _path] call comspec_overwatch_connect_fnc_aceAddSelfAction;
        } else {
            _a call ace_interact_menu_fnc_addActionToClass;
        };
        _stat set [0, (_stat select 0) + 1];
        // Budget par image : au moins une action, puis arrêt dès 2 ms.
        if ((diag_tickTime - _t0) > 0.002) exitWith {};
    };
    private _dt = diag_tickTime - _t0;
    _stat set [1, (_stat select 1) + 1];
    _stat set [2, (_stat select 2) + _dt];
    _stat set [3, (_stat select 3) max _dt];
    // Part de l'image comptée par l'ATAK natif (ligne « Image figée » et bilan des boucles).
    if (!isNil "comspec_atak_native_fnc_perfAcc") then { ["menus ACE (installation)", _t0] call comspec_atak_native_fnc_perfAcc; };
    if (_queue isNotEqualTo []) exitWith {};

    if (_handle >= 0) then { [_handle] call CBA_fnc_removePerFrameHandler; };
    missionNamespace setVariable ["COMSPEC_ACEMenuBuilding", false, false];
    [] call comspec_overwatch_connect_fnc_initChargeAceActions;

    missionNamespace setVariable ["COMSPEC_ACEMenuUnit", player, false];
    missionNamespace setVariable ["COMSPEC_ACEClassTreeVer", _menuVer, false]; // par mission : ACE vide ses actions de classe à chaque mission
    diag_log format ["[COMSPEC Overwatch][PERF] Menus ACE (installation) : %1 actions, %2 ms sur %3 image(s), pire image %4 ms",
        _stat select 0, ((_stat select 2) * 1000) toFixed 1, _stat select 1, ((_stat select 3) * 1000) toFixed 1];

    if (!isNil "comspec_overwatch_connect_fnc_getBloodType") then {
        private _bt = [] call comspec_overwatch_connect_fnc_getBloodType;
        if (_bt isNotEqualTo "") then {
            [(["COMSPECExtension", "SetBloodType", [_bt]] call comspec_overwatch_connect_fnc_extDirect)] call comspec_overwatch_connect_fnc_extResult;
            if (!isNil "comspec_overwatch_connect_fnc_operatorProfileTick") then {
                [{
                    ["blood_type_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick;
                }, [], 2] call CBA_fnc_waitAndExecute;
            };
        };
    };
};
// [actions posées, images, temps total, pire image]
private _job = [_queue, [0, 0, 0, 0], _menuVer];
[_job, -1] call _fnc_drain;
if (_queue isNotEqualTo []) then {
    [_fnc_drain, 0, _job] call CBA_fnc_addPerFrameHandler;
};
