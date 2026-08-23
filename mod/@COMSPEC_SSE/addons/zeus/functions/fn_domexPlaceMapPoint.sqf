/*
    Pose un point carte (Zeus live) : marqueur chef de mission + paquet coordonnée.
    [_pos, _text, _entity, _quality] call comspec_sse_fnc_domexPlaceMapPoint
*/
params [
    ["_pos", [], [[]]],
    ["_text", "", [""]],
    ["_entity", objNull, [objNull]],
    ["_quality", "complet", [""]]
];

if (!(_pos isEqualType []) || {count _pos < 2}) exitWith { false };
_text = trim _text;
if (_text isEqualTo "") then { _text = "Point de renseignement"; };

private _grid = mapGridPosition _pos;
private _seq = missionNamespace getVariable ["comspec_sse_domex_mapSeq", 0];
_seq = _seq + 1;
missionNamespace setVariable ["comspec_sse_domex_mapSeq", _seq, true];
private _uid = format ["ZEUS-MAP-P%1", _seq];

private _packet = createHashMapFromArray [
    ["packet_uid", _uid],
    ["type", "coordinate"],
    ["packet_type", "coordinate"],
    ["text", _text],
    ["body_text", _text],
    ["quality", _quality],
    ["channel", "zeus_live"],
    ["origin", "zeus_live"],
    ["reveal", "immediat"],
    ["reveal_after", "immediat"],
    ["show_on_map", true],
    ["position", _pos],
    ["pos_x", _pos select 0],
    ["pos_y", _pos select 1],
    ["pos_z", if (count _pos > 2) then { _pos select 2 } else { 0 }],
    ["grid_reference", _grid],
    ["entities", format ["%1 | lieu", _grid]]
];

if (!isNull _entity && {!(_entity isKindOf "CAManBase")}) then {
    [_entity] call comspec_sse_fnc_domexEnsureNode;
    _packet set ["packet_uid", format ["%1-M%2", _entity getVariable ["comspec_sse_domex_nodeId", "OBJ"], _seq]];
    [_entity, _packet, true] call comspec_sse_fnc_domexAddLivePacket;
} else {
    if (!isNil "comspec_sse_fnc_submitDigitalAcquisition") then {
        private _fog = createHashMapFromArray [
            ["uid", "ZEUS-MAP"],
            ["origin", "zeus_live"],
            ["type", "zeus_live"],
            ["mode", "zeus_map"],
            ["position", _pos],
            ["node_id", "ZEUS-MAP"],
            ["device_type", "gps"],
            ["packets", [_packet]]
        ];
        [objNull, _fog, false] call comspec_sse_fnc_submitDigitalAcquisition;
    };
    private _env = createHashMapFromArray [
        ["event_type", "DOMEX_MAP_POINT"],
        ["summary", format ["Point carte %1", _grid]],
        ["packet_uid", _uid],
        ["label", _text],
        ["position", _pos],
        ["grid_reference", _grid]
    ];
    ["COMSPEC_SSE_DOMEX_MAP_POINT", _env, true] call comspec_sse_fnc_raiseSseEvent;
};

true
