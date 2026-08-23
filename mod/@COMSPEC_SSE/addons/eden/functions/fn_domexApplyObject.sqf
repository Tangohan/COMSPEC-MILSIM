/*
    Applique le contrat DOMEX sur un objet (mêmes variables qu’Eden).
    [_entity, _overrides] call comspec_sse_fnc_domexApplyObject
*/
params [
    ["_entity", objNull, [objNull]],
    ["_overrides", createHashMap, [createHashMap]]
];

if (isNull _entity) exitWith { false };
if (_entity isKindOf "CAManBase") exitWith { false };

private _set = {
    params ["_e", "_key", "_val"];
    if (!isNil "_val") then {
        _e setVariable [_key, _val, true];
    };
};

if (_overrides getOrDefault ["enabled", true]) then {
    [_entity, "comspec_sse_domex_enabled", true] call _set;
    [_entity, "comspec_sse_enabled", true] call _set;
};

{
    _x params ["_src", "_dst"];
    if (_overrides getOrDefault [_src, ""] isNotEqualTo "") then {
        [_entity, _dst, _overrides get _src] call _set;
    };
} forEach [
    ["node_id", "comspec_sse_domex_nodeId"],
    ["device_type", "comspec_sse_domex_deviceType"],
    ["owner", "comspec_sse_domex_owner"],
    ["organization", "comspec_sse_domex_org"],
    ["network", "comspec_sse_domex_network"],
    ["security", "comspec_sse_domex_security"],
    ["profile", "comspec_sse_domex_profile"],
    ["duration", "comspec_sse_domex_duration"]
];

if ("exploitable" in _overrides) then {
    [_entity, "comspec_sse_domex_exploitable", _overrides get "exploitable"] call _set;
};
if ("access_physical" in _overrides) then {
    [_entity, "comspec_sse_domex_accessPhysical", _overrides get "access_physical"] call _set;
};
if ("access_remote" in _overrides) then {
    [_entity, "comspec_sse_domex_accessRemote", _overrides get "access_remote"] call _set;
};
if ("stage" in _overrides || {"terrain_stage" in _overrides}) then {
    [_entity, "comspec_sse_domex_stage", _overrides getOrDefault ["stage", _overrides getOrDefault ["terrain_stage", "non_identifie"]]] call _set;
};

private _pktText = _overrides getOrDefault ["packet_text", ""];
private _pktType = _overrides getOrDefault ["packet_type", ""];
if (_pktType isNotEqualTo "" || {_pktText isNotEqualTo ""}) then {
    [_entity, "comspec_sse_domex_p1_type", [_pktType, "document"] select (_pktType isEqualTo "")] call _set;
    [_entity, "comspec_sse_domex_p1_text", _pktText] call _set;
    [_entity, "comspec_sse_domex_p1_quality", _overrides getOrDefault ["packet_quality", "complet"]] call _set;
    [_entity, "comspec_sse_domex_p1_channel", _overrides getOrDefault ["packet_channel", "physique"]] call _set;
    [_entity, "comspec_sse_domex_p1_reveal", "immediat"] call _set;
    [_entity, "comspec_sse_domex_p1_entities", _overrides getOrDefault ["packet_entities", ""]] call _set;
};

if (!isNil "comspec_sse_fnc_makeSearchable") then {
    [_entity, "OBJECT"] call comspec_sse_fnc_makeSearchable;
};

[format ["domexApply %1 node=%2", _entity, _entity getVariable ["comspec_sse_domex_nodeId", ""]]] call comspec_sse_fnc_log;
true
