/*
    Ajoute un renseignement live (Zeus) sur un objet, lève l’événement, envoie au laboratoire.
    [_entity, _packet, _submit] call comspec_sse_fnc_domexAddLivePacket
*/
params [
    ["_entity", objNull, [objNull]],
    ["_packet", createHashMap, [createHashMap]],
    ["_submit", true, [true]]
];

if (isNull _entity) exitWith { false };
if !([_entity] call comspec_sse_fnc_domexEnsureNode) exitWith { false };
if (count _packet == 0) exitWith { false };

private _nodeId = _entity getVariable ["comspec_sse_domex_nodeId", format ["OBJ-%1", netId _entity]];
private _seq = _entity getVariable ["comspec_sse_domex_liveSeq", 0];
_seq = _seq + 1;
_entity setVariable ["comspec_sse_domex_liveSeq", _seq, true];

if ((_packet getOrDefault ["packet_uid", ""]) isEqualTo "") then {
    _packet set ["packet_uid", format ["%1-L%2", _nodeId, _seq]];
};
if ((_packet getOrDefault ["origin", ""]) isEqualTo "") then {
    _packet set ["origin", "zeus_live"];
};
if ((_packet getOrDefault ["channel", ""]) isEqualTo "") then {
    _packet set ["channel", "zeus_live"];
};
if ((_packet getOrDefault ["reveal", ""]) isEqualTo "" && {(_packet getOrDefault ["reveal_after", ""]) isEqualTo ""}) then {
    _packet set ["reveal", "immediat"];
    _packet set ["reveal_after", "immediat"];
};

private _live = _entity getVariable ["comspec_sse_domex_livePackets", []];
if !(_live isEqualType []) then { _live = []; };
_live pushBack _packet;
_entity setVariable ["comspec_sse_domex_livePackets", _live, true];

private _env = createHashMapFromArray [
    ["event_type", "DOMEX_PACKET"],
    ["summary", format ["Renseignement live %1", _packet getOrDefault ["packet_uid", ""]]],
    ["packet_uid", _packet getOrDefault ["packet_uid", ""]],
    ["node_id", _nodeId],
    ["packet_type", _packet getOrDefault ["type", _packet getOrDefault ["packet_type", ""]]],
    ["net_id", netId _entity]
];
["COMSPEC_SSE_DOMEX_PACKET", _env, true] call comspec_sse_fnc_raiseSseEvent;

if (_packet getOrDefault ["show_on_map", false]) then {
    private _pinPos = _packet getOrDefault ["position", []];
    if (_pinPos isEqualType [] && {count _pinPos >= 2}) then {
        private _pinEnv = createHashMapFromArray [
            ["event_type", "DOMEX_MAP_POINT"],
            ["summary", "Point carte renseignement"],
            ["packet_uid", _packet getOrDefault ["packet_uid", ""]],
            ["label", _packet getOrDefault ["text", _packet getOrDefault ["body_text", "Renseignement"]]],
            ["position", _pinPos],
            ["grid_reference", _packet getOrDefault ["grid_reference", mapGridPosition _pinPos]]
        ];
        ["COMSPEC_SSE_DOMEX_MAP_POINT", _pinEnv, true] call comspec_sse_fnc_raiseSseEvent;
    };
};

if (_submit && {!isNil "comspec_sse_fnc_submitDigitalAcquisition"}) then {
    private _fog = createHashMapFromArray [
        ["origin", "zeus_live"],
        ["type", "zeus_live"],
        ["mode", "zeus_live"]
    ];
    [_entity, _fog, false] call comspec_sse_fnc_submitDigitalAcquisition;
};

true
