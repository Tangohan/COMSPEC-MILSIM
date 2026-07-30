/*
    Installe le nœud « Renseignement SSE » sur les personnes (CAManBase).
    Garde runtime ACE : sans ace_interact_menu, la couche se retire en silence plutôt
    que d’imposer une dépendance dure qui ferait échouer le démarrage sans ACE.
*/
if (!hasInterface) exitWith {};

if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith {
    ["initSseAce", "ace_interact_menu absent — couche SSE non installée", nil, "SSE", "INFO"] call comspec_overwatch_connect_fnc_logFnError;
};
if (isNil "ace_interact_menu_fnc_createAction" || {isNil "ace_interact_menu_fnc_addActionToClass"}) exitWith {
    ["initSseAce", "API ace_interact_menu indisponible — couche SSE non installée", nil, "SSE", "WARN"] call comspec_overwatch_connect_fnc_logFnError;
};
if (isNil "comspec_overwatch_connect_fnc_ssePersonDialogShow") exitWith {
    ["initSseAce", "connect absent — terminal SSE introuvable", nil, "SSE", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
};

if (missionNamespace getVariable ["COMSPEC_SseAceMenuReady", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_SseAceMenuReady", true, false];

["INFO", "SSE", "Installation du menu SSE (interaction ACE)"] call comspec_overwatch_connect_fnc_log;

private _noChildren = { [] };
private _cond = {
    params ["_target"];
    [_target] call comspec_overwatch_sse_ace_fnc_sseCanExploit
};

// Nœud parent — porte la condition ; les entrées filles héritent de sa visibilité.
private _root = [
    "COMSPEC_SSE",
    "Renseignement SSE",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
    {},
    _cond,
    _noChildren
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;

// Ouverture de la fiche : le terminal accepte déjà une cible, aucune UI à dupliquer.
private _open = [
    "COMSPEC_SSE_Open",
    "Ouvrir la fiche SSE",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa",
    {
        params ["_target"];
        [_target] call comspec_overwatch_connect_fnc_sseOpenTerminal;
    },
    _cond,
    _noChildren,
    nil,
    nil,
    4,
    [false, false, false, false, false],
    {
        // modifierFunction ACE : [_target, _player, _actionData].
        // Libellé contextuel (décédé / détenu / inconscient) — lecture locale seulement.
        params ["_target", "", "_actionData"];
        if (isNil "_actionData" || {!(_actionData isEqualType [])} || {(count _actionData) < 2}) exitWith {};
        _actionData set [1, ([_target] call comspec_overwatch_sse_ace_fnc_sseExploitTargetLabel)];
    }
] call ace_interact_menu_fnc_createAction;
["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _open, true] call ace_interact_menu_fnc_addActionToClass;

// Pas de greffe dans l’écran médical ACE / KAT : ses identifiants de contrôle changent
// d’une version à l’autre. Le nœud ci-dessus couvre le même geste sans rien y toucher.

["INFO", "SSE", "Menu SSE installé"] call comspec_overwatch_connect_fnc_log;
