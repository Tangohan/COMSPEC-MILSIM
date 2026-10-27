/*
    Générateur narratif Zeus : brief textuel → réseau cohérent.
    [_brief, _center, _radius, _overrides, _notify] call comspec_sse_fnc_generateFromBrief
    _notify (défaut true) : false quand le module appelant pousse sa propre notification Zeus.
*/
params [
    ["_brief", "cellule logistique de 5 personnes", [""]],
    ["_center", objNull, [objNull, []]],
    ["_radius", 40, [0]],
    ["_ov", createHashMap, [createHashMap]],
    ["_notify", true, [true]]
];

private _pos = if (_center isEqualType []) then { _center } else {
    if (isNull _center) then { getPosATL player } else { getPosATL _center }
};

private _count = _ov getOrDefault ["count", 5];
private _profile = _ov getOrDefault ["profile", "INSURGENT"];
private _complexity = _ov getOrDefault ["complexity", "DETAILED"];
private _theme = _ov getOrDefault ["theme", "fuel_delivery"];

private _b = toLower _brief;
// Effectif : nombre suivi d'un nom d'effectif (« cellule de 15 personnes » → 15, borné 1..10),
// sinon premier nombre isolé d'au plus deux chiffres (pas une grille ni une heure),
// sinon nombre en toutes lettres (FR / EN).
private _num = _b regexFind ["([0-9]{1,2})\s*(personnes?|hommes?|membres?|men|people|members?)", 0];
if (_num isNotEqualTo []) then {
    _num = [[(_num select 0) select 1]];
} else {
    _num = _b regexFind ["(^|[^0-9])[0-9]{1,2}([^0-9]|$)", 0];
    if (_num isNotEqualTo []) then {
        private _m = (((_num select 0) select 0) select 0) regexReplace ["[^0-9]", ""];
        _num = [[[_m, 0]]];
    };
};
if (_num isNotEqualTo []) then {
    _count = ((parseNumber (((_num select 0) select 0) select 0)) max 1) min 10;
} else {
    if ((_b find "cinq") >= 0 || {(_b find "five") >= 0}) then { _count = 5; } else {
        if ((_b find "trois") >= 0 || {(_b find "three") >= 0}) then { _count = 3; };
    };
};
if ((_b find "financ") >= 0) then { _profile = "FINANCIER"; _theme = "finance_drop"; };
if ((_b find "ied") >= 0 || {(_b find "engin") >= 0} || {(_b find "explosi") >= 0}) then { _profile = "TECHNICIAN"; _theme = "ied_cell"; };
if ((_b find "courrier") >= 0 || {(_b find "courier") >= 0}) then { _profile = "COURIER"; _theme = "courier_run"; };
if ((_b find "command") >= 0 || {(_b find "chef") >= 0}) then { _profile = "COMMANDER"; _theme = "meeting_alpha"; };

private _seed = floor random 999999;
private _cluster = [_seed, _profile, _complexity, "IRAQ"] call comspec_sse_fnc_generateCluster;
_cluster set ["theme", _theme];
_cluster set ["brief", _brief];

// Jamais de cellule sur un joueur (ni le Zeus lui-même).
private _units = nearestObjects [_pos, ["CAManBase"], _radius] select { alive _x && {!isPlayer _x} && {!(_x in playableUnits)} };
private _n = (_count min count _units) max 0;
private _jobs = [];
for "_i" from 0 to (_n - 1) do {
    private _u = _units select _i;
    _u setVariable ["comspec_sse_region", "IRAQ", true];
    _u setVariable ["comspec_sse_theme", _theme, true];
    // generateData appelle déjà attachIntelLayers — ne pas doubler.
    _jobs pushBack [_u, _profile, _complexity, "ZEUS_BRIEF", _cluster];
};

{
    _jobs pushBack [_x, _profile, _complexity, "ZEUS_BRIEF", _cluster];
} forEach ((nearestObjects [_pos, ["LandVehicle"], _radius]) select [0, 2]);

[
    _jobs,
    {
        params ["_ent", "_profile", "_complexity", "_by", "_cluster"];
        if (isNull _ent) exitWith {};
        if (_ent getVariable ["comspec_sse_generating", false]) exitWith {};
        [_ent, _profile, _complexity, _by, _cluster] call comspec_sse_fnc_generateData;
    },
    0.28
] call comspec_sse_fnc_queueEntityJobs;

[_brief, "ORGANIZATION", createHashMapFromArray [
    ["label", _brief],
    ["tags", ["PERSON", "LOGISTICS"]],
    ["members", count _jobs]
]] call comspec_sse_fnc_addLogicalEntity;

if (_notify) then {
    [format ["Réseau en file depuis brief\n%1\n%2 entité(s)", _brief, count _jobs], "INFO"] call comspec_sse_fnc_notify;
};
_jobs apply { _x select 0 }
