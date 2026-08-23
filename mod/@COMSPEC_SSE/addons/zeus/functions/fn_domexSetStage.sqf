/*
    Fixe le palier d’accès d’un support (Zeus live).
    [_entity, _stage, _submit] call comspec_sse_fnc_domexSetStage
*/
params [
    ["_entity", objNull, [objNull]],
    ["_stage", "non_identifie", [""]],
    ["_submit", true, [true]]
];

if (isNull _entity) exitWith { false };
if !([_entity] call comspec_sse_fnc_domexEnsureNode) exitWith { false };

private _allowed = ["non_identifie", "decouvert", "acces_en_cours", "acces_etabli", "exploite"];
if !(_stage in _allowed) then { _stage = "non_identifie"; };

private _prev = _entity getVariable ["comspec_sse_domex_stage", "non_identifie"];
_entity setVariable ["comspec_sse_domex_stage", _stage, true];

private _env = createHashMapFromArray [
    ["event_type", "DOMEX_STAGE"],
    ["summary", format ["Palier %1 → %2", _prev, _stage]],
    ["node_id", _entity getVariable ["comspec_sse_domex_nodeId", ""]],
    ["stage", _stage],
    ["previous_stage", _prev],
    ["net_id", netId _entity]
];
["COMSPEC_SSE_DOMEX_STAGE", _env, true] call comspec_sse_fnc_raiseSseEvent;

if (_submit && {!isNil "comspec_sse_fnc_submitDigitalAcquisition"}) then {
    private _fog = createHashMapFromArray [
        ["origin", "terrain"],
        ["type", _entity getVariable ["comspec_sse_domex_deviceType", "ordinateur"]],
        ["mode", "zeus_stage"]
    ];
    [_entity, _fog, false] call comspec_sse_fnc_submitDigitalAcquisition;
};

true
