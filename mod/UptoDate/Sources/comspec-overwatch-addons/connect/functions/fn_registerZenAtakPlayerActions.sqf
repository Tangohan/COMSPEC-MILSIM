/*
    Menus Zeus : panneau ATAK joueur (ZEN + ACE Zeus + Ctrl+double-clic).
*/
if (!hasInterface) exitWith {};

private _pickPlayer = {
    params [["_pos", []], ["_obj", objNull]];
    private _pool = [] call comspec_overwatch_connect_fnc_curatorSelectedObjects;
    private _flatten = {
        params ["_item"];
        if (_item isEqualType []) exitWith {
            { [_x] call _flatten } forEach _item;
        };
        if (_item isEqualType objNull && {!isNull _item}) then {
            _pool pushBackUnique _item;
        };
    };
    [_obj] call _flatten;
    private _unit = objNull;
    {
        if (!(_x isEqualType objNull) || {isNull _x}) then { continue };
        if (isPlayer _x && {_x isKindOf "CAManBase"}) exitWith { _unit = _x; };
        if (!(_x isKindOf "CAManBase")) then {
            private _veh = _x;
            {
                if (isPlayer _x && {alive _x}) exitWith { _unit = _x; };
            } forEach (crew _veh);
        };
        if (!isNull _unit) exitWith {};
    } forEach _pool;
    _unit
};
missionNamespace setVariable ["COMSPEC_ZeusPickPlayer", _pickPlayer];

private _open = {
    params [["_unit", objNull]];
    if (isNull _unit) then {
        _unit = [[], objNull] call (missionNamespace getVariable ["COMSPEC_ZeusPickPlayer", { objNull }]);
    };
    if (isNull _unit) exitWith {
        ["Sélectionnez un joueur.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    // Le menu clic droit ZEN tient encore l’affichage : ouvrir le panneau tout de suite échoue sans message.
    [{
        [_this] call comspec_overwatch_connect_fnc_zeusShowPlayerAtak;
    }, _unit, 0.12] call CBA_fnc_waitAndExecute;
};

missionNamespace setVariable ["COMSPEC_ZeusOpenPlayerAtak", _open];

private _applyFx = {
    params [["_pos", []], ["_obj", objNull], "_act", "_dur"];
    private _unit = [_pos, _obj] call (missionNamespace getVariable ["COMSPEC_ZeusPickPlayer", { objNull }]);
    if (isNull _unit) exitWith {
        ["Sélectionnez un joueur.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    [_unit, _act, _dur] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
    [format ["Zeus → %1 : %2", name _unit, _act], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
};
missionNamespace setVariable ["COMSPEC_ZeusApplyAtakFx", _applyFx];

// --- ZEN : clic droit sur joueur (dossier + actions) ---
if (
    !(missionNamespace getVariable ["COMSPEC_ZenAtakPlayerContextRegistered", false])
    && {!isNil "zen_context_menu_fnc_createAction"}
    && {!isNil "zen_context_menu_fnc_addAction"}
) then {
    private _hasPlayer = {
        params [["_pos", []], ["_obj", objNull]];
        !isNull ([_pos, _obj] call (missionNamespace getVariable ["COMSPEC_ZeusPickPlayer", { objNull }]))
    };
    private _icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa";
    private _root = [
        "comspec_atak_player",
        "ATAK — Infos / dégâts / brouillage",
        _icon,
        {},
        _hasPlayer
    ] call zen_context_menu_fnc_createAction;
    [_root, [], 6] call zen_context_menu_fnc_addAction;

    private _panel = [
        "comspec_atak_player_panel",
        "Ouvrir le panneau",
        _icon,
        {
            params ["_pos", "_obj"];
            private _unit = [_pos, _obj] call (missionNamespace getVariable ["COMSPEC_ZeusPickPlayer", { objNull }]);
            [_unit] call (missionNamespace getVariable ["COMSPEC_ZeusOpenPlayerAtak", {}]);
        },
        { true }
    ] call zen_context_menu_fnc_createAction;
    [_panel, ["comspec_atak_player"], 0] call zen_context_menu_fnc_addAction;

    {
        _x params ["_id", "_label", "_act", "_dur"];
        private _child = [
            format ["comspec_atak_player_%1", _id],
            _label,
            "",
            compile format [
                "params ['_pos', '_obj']; [_pos, _obj, '%1', %2] call (missionNamespace getVariable ['COMSPEC_ZeusApplyAtakFx', {}]);",
                _act,
                _dur
            ],
            { true }
        ] call zen_context_menu_fnc_createAction;
        [_child, ["comspec_atak_player"], _forEachIndex + 1] call zen_context_menu_fnc_addAction;
    } forEach [
        ["jam", "Brouiller (45 s)", "jam", 45],
        ["screen", "Casser l’écran", "screen_break", 30],
        ["power", "Éteindre", "power_off", 30],
        ["crash", "Crash (30 s)", "crash", 30],
        ["destroy", "Détruire l’appareil", "device_destroy", 30],
        ["capture", "Capturer (illisible)", "capture", 30],
        ["repair", "Réparer / rétablir", "repair", 30]
    ];
    missionNamespace setVariable ["COMSPEC_ZenAtakPlayerContextRegistered", true];
};

// --- ZEN : module « poser sur joueur » ---
if (
    !(missionNamespace getVariable ["COMSPEC_ZenAtakPlayerModulesRegistered", false])
    && {!isNil "zen_custom_modules_fnc_register"}
) then {
    [
        "COMSPEC Roleplay",
        "ATAK — Éditer joueur",
        {
            params ["_pos", "_obj"];
            private _unit = [_pos, _obj] call (missionNamespace getVariable ["COMSPEC_ZeusPickPlayer", { objNull }]);
            if (isNull _unit) then {
                { if (isPlayer _x) exitWith { _unit = _x; }; } forEach (nearestObjects [_pos, ["CAManBase"], 5]);
            };
            [_unit] call (missionNamespace getVariable ["COMSPEC_ZeusOpenPlayerAtak", {}]);
        },
        "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa"
    ] call zen_custom_modules_fnc_register;
};

// --- Dotation du terminal SEEK ---
// Le terminal est un objet obligatoire pour ouvrir une fiche SSE : sans moyen de
// le distribuer en jeu, le chef de mission devrait repasser par l’arsenal.
private _giveSeek = {
    params ["_unit"];
    if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {
        ["Sélectionnez un fantassin.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    // La dotation s’exécute là où l’unité est locale ; l’échec (inventaire plein) est
    // annoncé au porteur, pas ici — d’où le libellé « transmise » et non « remise ».
    [_unit, "COMSPEC_Item_SeekTerminal"] remoteExecCall ["comspec_overwatch_connect_fnc_giveSeekTerminal", _unit];
    [format ["Dotation terminal SEEK transmise à %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
};
uiNamespace setVariable ["COMSPEC_ZeusGiveSeek", _giveSeek];

if (
    !(missionNamespace getVariable ["COMSPEC_ZenAtakPlayerModulesRegistered", false])
    && {!isNil "zen_custom_modules_fnc_register"}
) then {
    [
        "COMSPEC Roleplay",
        "Doter du terminal SEEK",
        {
            params ["_pos", "_obj"];
            private _unit = _obj;
            if (isNull _unit) then {
                { if (_x isKindOf "CAManBase") exitWith { _unit = _x; }; } forEach (nearestObjects [_pos, ["CAManBase"], 5]);
            };
            [_unit] call (uiNamespace getVariable ["COMSPEC_ZeusGiveSeek", {}]);
        },
        "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa"
    ] call zen_custom_modules_fnc_register;
};

if (!isNil "zen_custom_modules_fnc_register") then {
    missionNamespace setVariable ["COMSPEC_ZenAtakPlayerModulesRegistered", true];
};

// --- ACE Zeus (modules + menu molette Zeus) ---
if (
    !(missionNamespace getVariable ["COMSPEC_AceAtakPlayerModulesRegistered", false])
    && {!isNil "ace_zeus_fnc_addModule"}
) then {
    ["COMSPEC ATAK", "Doter du terminal SEEK", {
        params ["", ["_unit", objNull]];
        if (isNull _unit) then {
            { if (_x isKindOf "CAManBase") exitWith { _unit = _x; }; } forEach ([] call comspec_overwatch_connect_fnc_curatorSelectedObjects);
        };
        [_unit] call (uiNamespace getVariable ["COMSPEC_ZeusGiveSeek", {}]);
    }, "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa"] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Infos / dégâts / brouillage", {
        params ["", ["_unit", objNull]];
        if (isNull _unit || {!isPlayer _unit}) then {
            { if (isPlayer _x) exitWith { _unit = _x; }; } forEach ([] call comspec_overwatch_connect_fnc_curatorSelectedObjects);
        };
        [_unit] call (missionNamespace getVariable ["COMSPEC_ZeusOpenPlayerAtak", {}]);
    }, "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa"] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Brouiller joueur (45s)", {
        params ["", ["_unit", objNull]];
        if (isNull _unit || {!isPlayer _unit}) exitWith {};
        [_unit, "jam", 45] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
        [format ["Zeus → %1 : brouillage", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    }] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Casser écran ATAK", {
        params ["", ["_unit", objNull]];
        if (isNull _unit || {!isPlayer _unit}) exitWith {};
        [_unit, "screen_break", 30] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
        [format ["Zeus → %1 : écran endommagé", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    }] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Réparer ATAK", {
        params ["", ["_unit", objNull]];
        if (isNull _unit || {!isPlayer _unit}) exitWith {};
        [_unit, "repair", 30] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
        [format ["Zeus → %1 : ATAK rétabli", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    }] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Capturer appareil (illisible)", {
        params ["", ["_unit", objNull]];
        if (isNull _unit || {!isPlayer _unit}) exitWith {};
        [_unit, "capture", 30] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
        [format ["Zeus → %1 : appareil capturé", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    }] call ace_zeus_fnc_addModule;

    missionNamespace setVariable ["COMSPEC_AceAtakPlayerModulesRegistered", true];
};

if (
    !(missionNamespace getVariable ["COMSPEC_AceAtakZeusInteractRegistered", false])
    && {!isNil "ace_interact_menu_fnc_createAction"}
    && {!isNil "ace_interact_menu_fnc_addActionToZeus"}
) then {
    private _hasPlayer = {
        ({ isPlayer _x && {_x isKindOf "CAManBase"} } count ([] call comspec_overwatch_connect_fnc_curatorSelectedObjects)) > 0
    };
    private _root = [
        "comspec_atak_zeus_root",
        "COMSPEC ATAK",
        "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa",
        {},
        _hasPlayer
    ] call ace_interact_menu_fnc_createAction;
    _root = [_root] call comspec_overwatch_connect_fnc_acePadAction;
    [["ACE_ZeusActions"], _root] call ace_interact_menu_fnc_addActionToZeus;

    private _panel = [
        "comspec_atak_zeus_panel",
        "Infos / dégâts / brouillage",
        "",
        { [] call (missionNamespace getVariable "COMSPEC_ZeusOpenPlayerAtak"); },
        { true }
    ] call ace_interact_menu_fnc_createAction;
    _panel = [_panel] call comspec_overwatch_connect_fnc_acePadAction;
    [["ACE_ZeusActions", "comspec_atak_zeus_root"], _panel] call ace_interact_menu_fnc_addActionToZeus;

    {
        _x params ["_id", "_label", "_act", "_dur"];
        private _a = [
            format ["comspec_atak_zeus_%1", _id],
            _label,
            "",
            {
                private _p = _this select 2;
                _p params ["_act", "_dur"];
                private _unit = objNull;
                { if (isPlayer _x) exitWith { _unit = _x; }; } forEach ([] call comspec_overwatch_connect_fnc_curatorSelectedObjects);
                if (isNull _unit) exitWith {};
                [_unit, _act, _dur] remoteExecCall ["comspec_overwatch_connect_fnc_relayZeusAtakEffect", 2];
                [format ["Zeus → %1 : %2", name _unit, _act], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            },
            { true },
            {},
            [_act, _dur]
        ] call ace_interact_menu_fnc_createAction;
        _a = [_a] call comspec_overwatch_connect_fnc_acePadAction;
        [["ACE_ZeusActions", "comspec_atak_zeus_root"], _a] call ace_interact_menu_fnc_addActionToZeus;
    } forEach [
        ["jam", "Brouiller (45s)", "jam", 45],
        ["screen", "Casser l’écran", "screen_break", 30],
        ["power", "Éteindre", "power_off", 30],
        ["crash", "Crash (30s)", "crash", 30],
        ["destroy", "Détruire l’appareil", "device_destroy", 30],
        ["capture", "Capturer (illisible)", "capture", 30],
        ["compromise", "Compromettre", "compromise", 30],
        ["clear_compromise", "Lever capture", "clear_compromise", 30],
        ["repair", "Réparer / rétablir", "repair", 30]
    ];
    missionNamespace setVariable ["COMSPEC_AceAtakZeusInteractRegistered", true];
};

if (missionNamespace getVariable ["COMSPEC_ZeusAtakPlayerActionsRegistered", false]) exitWith {};

// --- Vanilla : Ctrl + double-clic joueur ---
["curatorObjectDoubleClicked", {
    params ["", "_entity"];
    if (isNull _entity || {!isPlayer _entity} || {!(_entity isKindOf "CAManBase")}) exitWith {};
    if !(uiNamespace getVariable ["COMSPEC_ZeusCtrlDown", false]) exitWith {};
    [_entity] call comspec_overwatch_connect_fnc_zeusShowPlayerAtak;
}] call CBA_fnc_addEventHandler;

[{
    private _disp = findDisplay 46;
    if (isNull _disp) exitWith {};
    if !(isNil "COMSPEC_ZeusCtrlKeyEH") exitWith {};
    COMSPEC_ZeusCtrlKeyEH = _disp displayAddEventHandler ["KeyDown", {
        params ["", "_key"];
        if (_key in [29, 157]) then {
            uiNamespace setVariable ["COMSPEC_ZeusCtrlDown", true];
        };
        false
    }];
    _disp displayAddEventHandler ["KeyUp", {
        params ["", "_key"];
        if (_key in [29, 157]) then {
            uiNamespace setVariable ["COMSPEC_ZeusCtrlDown", false];
        };
        false
    }];
}, 1, []] call CBA_fnc_addPerFrameHandler;

missionNamespace setVariable ["COMSPEC_ZeusAtakPlayerActionsRegistered", true];
