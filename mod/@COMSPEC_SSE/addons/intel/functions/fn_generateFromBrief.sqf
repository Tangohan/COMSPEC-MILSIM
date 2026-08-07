/*
    Générateur narratif Zeus : brief textuel → réseau cohérent.
    [_brief, _center, _radius, _overrides] call comspec_sse_fnc_generateFromBrief
*/
params [
    ["_brief", "cellule logistique de 5 personnes", [""]],
    ["_center", objNull, [objNull, []]],
    ["_radius", 40, [0]],
    ["_ov", createHashMap, [createHashMap]]
];

private _pos = if (_center isEqualType []) then { _center } else {
    if (isNull _center) then { getPosATL player } else { getPosATL _center }
};

private _count = _ov getOrDefault ["count", 5];
private _profile = _ov getOrDefault ["profile", "INSURGENT"];
private _complexity = _ov getOrDefault ["complexity", "DETAILED"];
private _theme = _ov getOrDefault ["theme", "fuel_delivery"];

// Parse basique du brief
private _b = toLower _brief;
if ((_b find "5") >= 0 || {(_b find "cinq") >= 0}) then { _count = 5; };
if ((_b find "3") >= 0 || {(_b find "trois") >= 0}) then { _count = 3; };
if ((_b find "financ") >= 0) then { _profile = "FINANCIER"; _theme = "finance_drop"; };
if ((_b find "ied") >= 0 || {(_b find "engin") >= 0}) then { _profile = "TECHNICIAN"; _theme = "ied_cell"; };
if ((_b find "courrier") >= 0) then { _profile = "COURIER"; _theme = "courier_run"; };
if ((_b find "command") >= 0 || {(_b find "chef") >= 0}) then { _profile = "COMMANDER"; _theme = "meeting_alpha"; };

private _seed = floor random 999999;
private _cluster = [_seed, _profile, _complexity, "IRAQ"] call comspec_sse_fnc_generateCluster;
_cluster set ["theme", _theme];
_cluster set ["brief", _brief];

private _created = [];
private _units = nearestObjects [_pos, ["CAManBase"], _radius];
private _n = (_count min count _units) max 0;
for "_i" from 0 to (_n - 1) do {
    private _u = _units select _i;
    _u setVariable ["comspec_sse_region", "IRAQ", true];
    _u setVariable ["comspec_sse_theme", _theme, true];
    [_u, _profile, _complexity, "ZEUS_BRIEF", _cluster] call comspec_sse_fnc_generateData;
    [_u] call comspec_sse_fnc_attachIntelLayers;
    _created pushBack _u;
};

// Véhicules proches
{
    [_x, _profile, _complexity, "ZEUS_BRIEF", _cluster] call comspec_sse_fnc_generateData;
    [_x] call comspec_sse_fnc_attachIntelLayers;
    _created pushBack _x;
} forEach ((nearestObjects [_pos, ["LandVehicle"], _radius]) select [0, 2]);

[_brief, "ORGANIZATION", createHashMapFromArray [
    ["label", _brief],
    ["tags", ["PERSON", "LOGISTICS"]],
    ["members", count _created]
]] call comspec_sse_fnc_addLogicalEntity;

hint format ["Réseau généré depuis brief\n%1\n%2 entité(s)", _brief, count _created];
_created
