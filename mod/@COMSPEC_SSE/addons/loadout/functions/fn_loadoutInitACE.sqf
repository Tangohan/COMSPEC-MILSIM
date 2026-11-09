/*
    Menus ACE de l'équipement (XEH_postInitClient), posés une seule fois par classe (aucune action par objet) :
      soi (ACE_SelfActions) › Équipement › Copier ma tenue pour Athena (fn_loadoutExport)
      objet caisse de kits (variable publique comspec_sse_loadoutCrate ; caisses, objets, véhicules) :
        Prendre mon kit (kit attribué sur Athena, sinon tenue de la caisse) · Kits proposés › un kit par ligne
    Le serveur décide (fn_loadoutTake « srvTake ») : les conditions ici ne servent qu'à l'affichage.
*/
if (!hasInterface || {isNil "ace_interact_menu_fnc_addActionToClass"}) exitWith { false };
if (missionNamespace getVariable ["comspec_sse_loAceReady", false]) exitWith { false };
missionNamespace setVariable ["comspec_sse_loAceReady", true];

private _new = { if (isNil "comspec_sse_fnc_aceAction") then { _this call ace_interact_menu_fnc_createAction } else { _this call comspec_sse_fnc_aceAction } };
private _icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\rifle_ca.paa";

// --- Soi : export de la tenue -------------------------------------------------------------------------------------
private _self = ["COMSPEC_LO_Self", "Équipement", _icon, {}, { alive player }] call _new;
["CAManBase", 1, ["ACE_SelfActions"], _self, true] call ace_interact_menu_fnc_addActionToClass;
["CAManBase", 1, ["ACE_SelfActions", "COMSPEC_LO_Self"], ([
    "COMSPEC_LO_Export", "Copier ma tenue pour Athena", "", { [player] call comspec_sse_fnc_loadoutExport; }, { true }
] call _new), true] call ace_interact_menu_fnc_addActionToClass;

// --- Caisse de kits -----------------------------------------------------------------------------------------------
private _isCrate = {
    params ["_t", "_p"];
    (missionNamespace getVariable ["comspec_sse_lo_take", true]) && {_t getVariable ["comspec_sse_loadoutCrate", false]}
        && {alive _p} && {(_p distance _t) <= ((missionNamespace getVariable ["comspec_sse_lo_distance", 5]) + 1)}
};
missionNamespace setVariable ["comspec_sse_loIsCrate", _isCrate];
private _take = ["COMSPEC_LO_Take", "Prendre mon kit", _icon, {
    params ["_t"];
    ["take", [_t, 0]] call comspec_sse_fnc_loadoutTake;
}, { _this call (missionNamespace getVariable "comspec_sse_loIsCrate") }] call _new;
private _list = ["COMSPEC_LO_List", "Kits proposés", "", {}, {
    (_this call (missionNamespace getVariable "comspec_sse_loIsCrate"))
        && {((missionNamespace getVariable ["comspec_sse_loadoutMine", []]) isNotEqualTo []) || {((_this select 0) getVariable ["comspec_sse_loadoutCrateKit", []]) isNotEqualTo []}}
}, {
    params ["_t"];
    private _rows = +(missionNamespace getVariable ["comspec_sse_loadoutMine", []]);
    private _ck = _t getVariable ["comspec_sse_loadoutCrateKit", []];
    if (_ck isNotEqualTo []) then { _rows pushBack ["crate", format ["Tenue de la caisse : %1", _ck param [0, ""]]]; };
    private _i = -1;
    _rows apply {
        _x params ["_id", "_name"];
        _i = _i + 1;
        // Nom saisi sur Athena : affiché tel quel (pas de traduction).
        [[format ["COMSPEC_LO_Kit_%1", _i], _name, "", {
            params ["_t", "", "_id"];
            ["take", [_t, _id]] call comspec_sse_fnc_loadoutTake;
        }, { true }, {}, _id] call ace_interact_menu_fnc_createAction, [], _t]
    }
}] call _new;
// Classes racines disjointes (aucune action en double). Caisses, objets physiques et véhicules : sous les actions
// principales d'ACE ; objets statiques (tables, bâtiments posés), souvent sans actions principales : nœud propre.
{
    [_x, 0, ["ACE_MainActions"], _take, true] call ace_interact_menu_fnc_addActionToClass;
    [_x, 0, ["ACE_MainActions"], _list, true] call ace_interact_menu_fnc_addActionToClass;
} forEach ["ThingX", "LandVehicle", "Air", "Ship"];
private _root = ["COMSPEC_LO_Root", "Caisse de kits", _icon, {}, { _this call (missionNamespace getVariable "comspec_sse_loIsCrate") }, {}, [], [0, 0, 0], 3] call _new;
["Static", 0, [], _root, true] call ace_interact_menu_fnc_addActionToClass;
["Static", 0, ["COMSPEC_LO_Root"], _take, true] call ace_interact_menu_fnc_addActionToClass;
["Static", 0, ["COMSPEC_LO_Root"], _list, true] call ace_interact_menu_fnc_addActionToClass;
true
