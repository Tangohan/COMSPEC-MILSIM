/*
    Installe le nœud SSE sur les personnes (CAManBase).

    - Avec @COMSPEC_SSE : on greffe seulement « Fiche Athena » sous le menu SSE
      déjà fourni (Inspecter / Fouiller / biométrie…). Pas de second parent qui
      pourrait masquer le menu terrain.
    - Sans @COMSPEC_SSE : parent « Renseignement SSE » + ouverture fiche Athena,
      visible sur toute personne (hors soi), le terminal refuse clairement si SEEK
      / ATAK manquent.
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

if (uiNamespace getVariable ["COMSPEC_SseAceMenuReady", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_SseAceMenuReady", true];

["INFO", "SSE", "Installation du menu SSE (interaction ACE)"] call comspec_overwatch_connect_fnc_log;

private _noChildren = { [] };
private _cond = {
    params ["_target"];
    [_target] call comspec_overwatch_sse_ace_fnc_sseCanExploit
};
// Corps / blessés au sol : ne pas exiger une LOS stricte.
private _aceParams = [false, false, false, false, true];

private _hasSseTerrain = isClass (configFile >> "CfgPatches" >> "comspec_sse_interaction");

private _open = [
    "COMSPEC_SSE_OpenAthena",
    "Ouvrir la fiche Athena",
    "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa",
    {
        params ["_target"];
        [_target] call comspec_overwatch_connect_fnc_sseOpenTerminal;
    },
    _cond,
    _noChildren,
    [],
    {[0, 0, 0]},
    4,
    _aceParams,
    {
        params ["_target", "", "", "_actionData"];
        if (isNil "_actionData" || {!(_actionData isEqualType [])} || {(count _actionData) < 2}) exitWith {};
        _actionData set [1, ([_target] call comspec_overwatch_sse_ace_fnc_sseExploitTargetLabel)];
    }
] call ace_interact_menu_fnc_createAction;

if (_hasSseTerrain) then {
    // Attendre que @COMSPEC_SSE ait créé le parent COMSPEC_SSE (postInit client).
    [{
        params ["_open"];
        if (!(uiNamespace getVariable ["comspec_sse_aceReady", false])) exitWith {
            // Repli : parent Athena si le terrain n’a pas initialisé (ordre de chargement).
            ["WARN", "SSE", "Menu terrain SSE non prêt — parent Athena autonome"] call comspec_overwatch_connect_fnc_log;
            private _root = [
                "COMSPEC_SSE_ATHENA",
                "Renseignement SSE",
                "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
                {},
                {
                    params ["_target"];
                    [_target] call comspec_overwatch_sse_ace_fnc_sseCanExploit
                },
                { [] },
                [],
                {[0, 0, 0]},
                4,
                [false, false, false, false, true]
            ] call ace_interact_menu_fnc_createAction;
            ["CAManBase", 0, ["ACE_MainActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;
            ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE_ATHENA"], _open, true] call ace_interact_menu_fnc_addActionToClass;
        };
        ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE"], _open, true] call ace_interact_menu_fnc_addActionToClass;
        ["INFO", "SSE", "Fiche Athena greffée sous le menu SSE terrain"] call comspec_overwatch_connect_fnc_log;
    }, [_open], 3] call CBA_fnc_waitAndExecute;
} else {
    private _root = [
        "COMSPEC_SSE_ATHENA",
        "Renseignement SSE",
        "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        {},
        _cond,
        _noChildren,
        [],
        {[0, 0, 0]},
        4,
        _aceParams
    ] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;
    ["CAManBase", 0, ["ACE_MainActions", "COMSPEC_SSE_ATHENA"], _open, true] call ace_interact_menu_fnc_addActionToClass;
    ["INFO", "SSE", "Menu Athena autonome (mod @COMSPEC_SSE absent)"] call comspec_overwatch_connect_fnc_log;
};

["INFO", "SSE", "Menu SSE installé"] call comspec_overwatch_connect_fnc_log;
