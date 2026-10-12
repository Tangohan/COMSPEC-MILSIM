#include "script_component.hpp"
/*
    Analyse : relais serveur, notes aux joueurs, menus ACE et Zeus.
*/
if (isServer) then {
    ["comspec_sse_orgOp", { params ["_op", "_args"]; [_op, _args] call comspec_sse_fnc_orgOp; }] call CBA_fnc_addEventHandler;
    ["comspec_sse_orgGenerate", { _this call comspec_sse_fnc_orgGenerate; }] call CBA_fnc_addEventHandler;
    ["comspec_sse_custodyOp", { params ["_op", "_args"]; [_op, _args] call comspec_sse_fnc_custody; }] call CBA_fnc_addEventHandler;
    {
        if (isNil _x) then { missionNamespace setVariable [_x, createHashMap]; publicVariable _x; };
    } forEach ["comspec_sse_orgs", "comspec_sse_orgMembers", "comspec_sse_anaSeen", "comspec_sse_custody"];
    // Après l'attribution des clés biométriques (une image après l'apparition).
    [{ [] call comspec_sse_fnc_orgApplyEden; }, [], 1.5] call CBA_fnc_waitAndExecute;
};

if (!hasInterface) exitWith {};

["comspec_sse_anaNote", {
    params [["_msg", "", [""]]];
    if (_msg isEqualTo "") exitWith {};
    hint parseText format ["<t color='#7fd08a' size='1.1'>SSE · Analyse</t><br/><br/>%1", _msg];
}] call CBA_fnc_addEventHandler;

// Tableau des liens et registre ouverts : suivent l'état diffusé par le serveur
// (l'événement part après les publicVariable, qui arrivent dans l'ordre).
// Regroupé : une génération de réseau publie une fois par membre.
["comspec_sse_anaChanged", {
    if (missionNamespace getVariable ["comspec_sse_anaRefreshQueued", false]) exitWith {};
    missionNamespace setVariable ["comspec_sse_anaRefreshQueued", true];
    [{
        missionNamespace setVariable ["comspec_sse_anaRefreshQueued", false];
        if (!isNull (uiNamespace getVariable ["comspec_sse_chartDisplay", displayNull])) then { [false] call comspec_sse_fnc_chartRefresh; };
        if ((missionNamespace getVariable ["comspec_sse_uiScreen", ""]) isEqualTo "evidence" && {!isNil "comspec_sse_fnc_uiRefresh"}) then { ["evidence"] call comspec_sse_fnc_uiRefresh; };
    }, [], 0.5] call CBA_fnc_waitAndExecute;
}] call CBA_fnc_addEventHandler;

// ---------------------------------------------------------------- ACE
[{ !isNil "ace_interact_menu_fnc_createAction" && {!isNull player} }, {
    private _p = [false, false, false, false, true];
    private _none = { [] };
    private _self = [
        (["COMSPEC_SSE_Chart", "Tableau des liens", "\A3\ui_f\data\map\markers\nato\o_hq.paa", { [false] call comspec_sse_fnc_chartOpen }, { true }, _none, [], {[0, 0, 0]}, 1, _p] call ace_interact_menu_fnc_createAction),
        (["COMSPEC_SSE_Register", "Registre des scellés", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa", { ["evidence"] call comspec_sse_fnc_uiOpenScreen }, { true }, _none, [], {[0, 0, 0]}, 1, _p] call ace_interact_menu_fnc_createAction),
        (["COMSPEC_SSE_MySeals", "Pièces que je détiens", "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa", {
            private _ids = ["held", [player]] call comspec_sse_fnc_custody;
            private _all = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
            hint parseText format ["<t color='#7fd08a'>Pièces détenues : %1</t><br/>%2", count _ids,
                (_ids apply { private _p = _all get _x; format ["%1 · %2 · %3", _x, _p getOrDefault ["state", "?"], _p getOrDefault ["desc", ""]] }) joinString "<br/>"];
        }, { true }, _none, [], {[0, 0, 0]}, 1, _p] call ace_interact_menu_fnc_createAction)
    ];
    missionNamespace setVariable ["comspec_sse_anaSelfActions", _self];
    private _install = {
        params ["_unit"];
        if (_unit getVariable ["comspec_sse_anaSelf", false]) exitWith {};
        _unit setVariable ["comspec_sse_anaSelf", true];
        { [_unit, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], _x] call ace_interact_menu_fnc_addActionToObject; } forEach (missionNamespace getVariable ["comspec_sse_anaSelfActions", []]);
    };
    [player] call _install;
    missionNamespace setVariable ["comspec_sse_anaSelfInstall", _install];
    player addEventHandler ["Respawn", { params ["_unit"]; [_unit] call (missionNamespace getVariable "comspec_sse_anaSelfInstall"); }];

    // Remise des pièces à un équipier : inscrite au journal de chacune.
    private _give = [
        "COMSPEC_SSE_GiveSeals", "Remettre mes pièces scellées", "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        {
            params ["_target", "_player"];
            [5, "Remise des scellés, émargement...", {
                params ["_target", "_player"];
                ["transfer", [_player, _target]] call comspec_sse_fnc_custody;
            }, [_target, _player]] call comspec_sse_fnc_progressAction;
        },
        {
            params ["_target", "_player"];
            isPlayer _target && {alive _target} && {_target != _player} && {(["held", [_player]] call comspec_sse_fnc_custody) isNotEqualTo []}
        },
        _none, [], {[0, 0, 0]}, 3, _p
    ] call ace_interact_menu_fnc_createAction;
    ["CAManBase", 0, ["ACE_MainActions"], _give, true] call ace_interact_menu_fnc_addActionToClass;
}, [], 60] call CBA_fnc_waitUntilAndExecute;

// ---------------------------------------------------------------- Zeus (ZEN)
[{ !isNil "zen_custom_modules_fnc_register" }, {
    private _sel = {
        params ["_obj"];
        private _s = [] call comspec_sse_fnc_curatorSelectedObjects;
        if (!isNull _obj) then { _s pushBackUnique _obj; };
        _s
    };
    missionNamespace setVariable ["comspec_sse_anaZenSel", _sel];
    {
        _x params ["_cat", "_name", "_code", "_icon"];
        [_cat, _name, _code, _icon] call zen_custom_modules_fnc_register;
    } forEach [
        ["COMSPEC SSE · Personnes", "Organisation : affecter", { params ["_pos", "_obj"]; ["assign", [_obj] call (missionNamespace getVariable "comspec_sse_anaZenSel")] call comspec_sse_fnc_orgZeusForm; }, "\A3\ui_f\data\map\markers\nato\o_hq.paa"],
        ["COMSPEC SSE · Personnes", "Organisation : générer un réseau", { params ["_pos", "_obj"]; private _s = [_obj] call (missionNamespace getVariable "comspec_sse_anaZenSel"); if (count _s == 1 && {(_s select 0) isKindOf "CAManBase"}) then { _s = units group (_s select 0); }; ["generate", _s] call comspec_sse_fnc_orgZeusForm; }, "\A3\ui_f\data\map\markers\nato\o_hq.paa"],
        ["COMSPEC SSE · Personnes", "Organisation : rattacher des objets", { params ["_pos", "_obj"]; ["link", [_obj] call (missionNamespace getVariable "comspec_sse_anaZenSel")] call comspec_sse_fnc_orgZeusForm; }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa"],
        ["COMSPEC SSE · Personnes", "Organisation : révéler aux joueurs", { params ["_pos", "_obj"]; ["reveal", [_obj] call (missionNamespace getVariable "comspec_sse_anaZenSel")] call comspec_sse_fnc_orgZeusForm; }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa"],
        ["COMSPEC SSE · Personnes", "Organisation : retirer", { params ["_pos", "_obj"]; ["remove", [_obj] call (missionNamespace getVariable "comspec_sse_anaZenSel")] call comspec_sse_fnc_orgZeusForm; }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\exit_ca.paa"],
        ["COMSPEC SSE · Personnes", "Tableau des liens (vue Zeus)", { [true] call comspec_sse_fnc_chartOpen; }, "\A3\ui_f\data\map\markers\nato\o_hq.paa"],
        ["COMSPEC SSE · Mission", "Scellé : altérer le journal (exercice)", {
            params ["_pos", "_obj"];
            private _done = 0;
            { if ((_x getVariable ["COMSPEC_SSE_PieceId", ""]) isNotEqualTo "") then { ["tamper", [_x]] call comspec_sse_fnc_custody; _done = _done + 1; }; } forEach ([_obj] call (missionNamespace getVariable "comspec_sse_anaZenSel"));
            [[format ["%1 journal(aux) altéré(s) : la vérification du registre le signalera.", _done], "Aucune pièce saisie dans la sélection."] select (_done isEqualTo 0)] call comspec_sse_fnc_zeusNotify;
        }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\danger_ca.paa"]
    ];
}, [], 45] call CBA_fnc_waitUntilAndExecute;
