/*
    Menus Zeus : panneau ATAK joueur (ZEN + ACE Zeus + Ctrl+double-clic).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZeusAtakPlayerActionsRegistered", false]) exitWith {};

private _open = {
    private _unit = objNull;
    private _pool = [];
    if (!isNil "zen_context_menu_selectedObjects") then {
        _pool = zen_context_menu_selectedObjects;
    };
    if (!(_pool isEqualType []) || {_pool isEqualTo []}) then {
        _pool = missionNamespace getVariable ["zen_context_menu_selected", []];
    };
    if (!(_pool isEqualType []) || {_pool isEqualTo []}) then {
        _pool = curatorSelected select 0;
    };
    {
        if (!isNull _x && {isPlayer _x} && {_x isKindOf "CAManBase"}) exitWith { _unit = _x; };
    } forEach _pool;
    if (isNull _unit) exitWith {
        ["Sélectionnez un joueur.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
    [_unit] call comspec_overwatch_connect_fnc_zeusShowPlayerAtak;
};

missionNamespace setVariable ["COMSPEC_ZeusOpenPlayerAtak", _open];

// --- ZEN : clic droit sur joueur ---
if (!isNil "zen_context_menu_fnc_createAction" && {!isNil "zen_context_menu_fnc_addAction"}) then {
    private _action = [
        "comspec_atak_player",
        "ATAK — Infos / dégâts / brouillage",
        "\A3\ui_f\data\igui\cfg\simpletasks\types\Radio_ca.paa",
        { [] call (missionNamespace getVariable "COMSPEC_ZeusOpenPlayerAtak"); },
        {
            private _pool = if (!isNil "zen_context_menu_selectedObjects") then {
                zen_context_menu_selectedObjects
            } else {
                curatorSelected select 0
            };
            ({ !isNull _x && {isPlayer _x} && {_x isKindOf "CAManBase"} } count _pool) > 0
        }
    ] call zen_context_menu_fnc_createAction;
    [_action, [], 6] call zen_context_menu_fnc_addAction;
};

// --- ZEN : module « poser sur joueur » ---
if (!isNil "zen_custom_modules_fnc_register") then {
    [
        "COMSPEC Roleplay",
        "ATAK — Éditer joueur",
        {
            params ["_pos", "_obj"];
            private _unit = _obj;
            if (isNull _unit || {!isPlayer _unit}) then {
                { if (isPlayer _x) exitWith { _unit = _x; }; } forEach (nearestObjects [_pos, ["CAManBase"], 5]);
            };
            if (isNull _unit || {!isPlayer _unit}) then {
                { if (isPlayer _x) exitWith { _unit = _x; }; } forEach (curatorSelected select 0);
            };
            [_unit] call comspec_overwatch_connect_fnc_zeusShowPlayerAtak;
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

if (!isNil "zen_custom_modules_fnc_register") then {
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

// --- ACE Zeus (modules + menu molette Zeus) ---
if (!isNil "ace_zeus_fnc_addModule") then {
    ["COMSPEC ATAK", "Doter du terminal SEEK", {
        params ["", ["_unit", objNull]];
        if (isNull _unit) then {
            { if (_x isKindOf "CAManBase") exitWith { _unit = _x; }; } forEach (curatorSelected select 0);
        };
        [_unit] call (uiNamespace getVariable ["COMSPEC_ZeusGiveSeek", {}]);
    }, "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa"] call ace_zeus_fnc_addModule;

    ["COMSPEC ATAK", "Infos / dégâts / brouillage", {
        params ["", ["_unit", objNull]];
        if (isNull _unit || {!isPlayer _unit}) then {
            { if (isPlayer _x) exitWith { _unit = _x; }; } forEach (curatorSelected select 0);
        };
        [_unit] call comspec_overwatch_connect_fnc_zeusShowPlayerAtak;
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
};

if (!isNil "ace_interact_menu_fnc_createAction" && {!isNil "ace_interact_menu_fnc_addActionToZeus"}) then {
    private _hasPlayer = {
        ({ !isNull _x && {isPlayer _x} && {_x isKindOf "CAManBase"} } count (curatorSelected select 0)) > 0
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
                { if (isPlayer _x) exitWith { _unit = _x; }; } forEach (curatorSelected select 0);
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
};

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
