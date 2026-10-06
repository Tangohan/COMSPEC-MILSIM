/*
    Photo d'une escouade (groupe Arma) pour Athena : nom, type, camp, chef, équipes de feu (couleur, icône,
    description, membres, rôles et rôle mémorisé) et membres sans équipe, plus les rôles créés en jeu (custom_roles).
    Params : [groupe]. Renvoie un HashMap (fn_json).
*/
params [["_g", grpNull]];
if (isNull _g) exitWith { createHashMap };
private _types = [] call comspec_atak_native_fnc_groupTypes;
private _gt = _g getVariable ["COMSPEC_GroupType", "INF"];
private _member = {
    params ["_u"];
    private _i = [_u] call comspec_atak_native_fnc_ftInfo;
    createHashMapFromArray [
        ["callsign", [_u] call comspec_atak_native_fnc_unitCallsign], ["name", name _u], ["uid", [getPlayerUID _u, ""] select !isPlayer _u],
        ["player", isPlayer _u], ["role", _i get "role"], ["role_label", _i get "roleLabel"], ["role_pref", _u getVariable ["COMSPEC_FTRolePref", ""]], ["leader", _i get "lead"],
        ["alive", alive _u], ["online", _u getVariable ["COMSPEC_ATAK_Beacon", false]], ["grid", mapGridPosition _u]
    ]
};
private _all = [_g] call comspec_atak_native_fnc_ftTeams;
private _free = (_all select ((count _all) - 1)) select 1;
_all deleteAt ((count _all) - 1);
createHashMapFromArray [
    ["mission_key", missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]]],
    ["squad", createHashMapFromArray [
        ["key", groupId _g + "#" + str side _g], ["name", groupId _g], ["side", str side _g], ["type", _gt],
        ["type_label", ((_types select { (_x select 0) isEqualTo _gt }) param [0, ["", "Infanterie"]]) select 1],
        ["leader", [leader _g] call comspec_atak_native_fnc_unitCallsign], ["members", count units _g], ["locked", _g getVariable ["COMSPEC_GroupLocked", false]]
    ]],
    ["teams", _all apply {
        _x params ["_t", "_m"];
        createHashMapFromArray [
            ["key", _t get "id"], ["name", _t get "name"], ["color", _t get "hex"], ["color_key", _t get "color"], ["icon", _t get "iconKey"],
            ["description", _t get "desc"], ["locked", _t get "locked"], ["members", _m apply { [_x] call _member }]
        ]
    }],
    ["unassigned", _free apply { [_x] call _member }],
    // Rôles créés en jeu (fn_ftServer « roleNew ») : retenus par Athena pour toute la communauté.
    ["custom_roles", ((missionNamespace getVariable ["COMSPEC_ATAK_FtExtraRoles", []]) select { (_x param [4, ""]) isEqualTo "CUSTOM" }) apply {
        _x params ["_k", "_l", "_ab", "_ik", "", ["_by", ""]];
        createHashMapFromArray [["key", _k], ["label", _l], ["short", _ab], ["icon", _ik], ["by", _by]]
    }]
]
