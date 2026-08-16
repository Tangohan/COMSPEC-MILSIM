/*
    Marque une section d’exploitation véhicule et recalcule le %.
    [_entity, _section] call comspec_sse_fnc_markVehicleSection
    _section : exterior | cabin | cargo | documents | digital
*/
params [
    ["_entity", objNull, [objNull]],
    ["_section", "", [""]]
];

if (isNull _entity || {_section isEqualTo ""}) exitWith { 0 };
if !(_entity isKindOf "LandVehicle" || {_entity isKindOf "Air"} || {_entity isKindOf "Ship"}) exitWith { 0 };

[_entity] call comspec_sse_fnc_ensureGenerated;

private _expl = [_entity, "exploitation"] call comspec_sse_fnc_getSection;
if (isNil "_expl" || {!(_expl isEqualType createHashMap)}) then {
    _expl = createHashMapFromArray [
        ["pct", 0],
        ["sections", createHashMapFromArray [
            ["exterior", false],
            ["cabin", false],
            ["cargo", false],
            ["documents", false],
            ["digital", false]
        ]]
    ];
};

private _sections = _expl getOrDefault ["sections", createHashMap];
if !(_sections isEqualType createHashMap) then { _sections = createHashMap; };
_sections set [toLower _section, true];
_expl set ["sections", _sections];

private _keys = ["exterior", "cabin", "cargo", "documents", "digital"];
private _done = 0;
{
    if (_sections getOrDefault [_x, false]) then { _done = _done + 1; };
} forEach _keys;
private _pct = round ((_done / (count _keys)) * 100);
_expl set ["pct", _pct];
[_entity, "exploitation", _expl, true] call comspec_sse_fnc_setSection;

if (_pct >= 100) then {
    [_entity, "EXPLOITED"] call comspec_sse_fnc_setState;
} else {
    if (_pct >= 40) then {
        [_entity, "PARTIALLY_EXPLOITED"] call comspec_sse_fnc_setState;
    } else {
        if (_pct > 0) then {
            [_entity, "SEARCHED"] call comspec_sse_fnc_setState;
        };
    };
};

private _env = createHashMapFromArray [
    ["event_type", "VEHICLE_EXPLOIT"],
    ["source_system", "ARMA_SSE"],
    ["entity_type", "VEHICLE"],
    ["summary", format ["Exploitation véhicule — %1%% (%2)", _pct, _section]],
    ["identity_tier", "DECLARED"],
    ["source_reliability", "C"],
    ["info_credibility", 3],
    ["payload", createHashMapFromArray [
        ["section", toLower _section],
        ["pct", _pct]
    ]]
];
["COMSPEC_SSE_VEHICLE_EXPLOIT", _env, false] call comspec_sse_fnc_raiseSseEvent;

_pct
