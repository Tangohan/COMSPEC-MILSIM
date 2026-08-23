/*
    Compile le nœud DOMEX + paquets depuis les variables d’objet (Eden / Zeus).
    [_entity] call comspec_sse_fnc_domexGetNode
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { createHashMap };

private _splitEntities = {
    params ["_raw"];
    private _out = [];
    if (_raw isEqualType []) exitWith {
        {
            if (_x isEqualType createHashMap) then {
                _out pushBack _x;
            };
            if (_x isEqualType "" && {_x != ""}) then {
                private _parts = _x splitString "|";
                private _label = trim (_parts param [0, ""]);
                private _kind = toLower (trim (_parts param [1, "lieu"]));
                if (_label != "") then {
                    _out pushBack createHashMapFromArray [["label", _label], ["kind", _kind]];
                };
            };
        } forEach _raw;
        _out
    };
    private _txt = _raw;
    if !(_txt isEqualType "") then { _txt = str _txt; };
    {
        private _line = trim _x;
        if (_line != "") then {
            private _parts = _line splitString "|";
            private _label = trim (_parts param [0, ""]);
            private _kind = toLower (trim (_parts param [1, "lieu"]));
            if (_label != "") then {
                _out pushBack createHashMapFromArray [["label", _label], ["kind", _kind]];
            };
        };
    } forEach (_txt splitString endl);
    _out
};

private _packetAt = {
    params ["_e", "_idx"];
    private _pfx = format ["comspec_sse_domex_p%1_", _idx];
    private _type = _e getVariable [_pfx + "type", ""];
    private _text = _e getVariable [_pfx + "text", ""];
    if (_type isEqualTo "" && {_text isEqualTo ""}) exitWith { createHashMap };
    private _nodeId = _e getVariable ["comspec_sse_domex_nodeId", ""];
    createHashMapFromArray [
        ["packet_uid", format ["%1-P%2", [_nodeId, format ["OBJ-%1", netId _e]] select (_nodeId isEqualTo ""), _idx]],
        ["type", _type],
        ["packet_type", _type],
        ["text", _text],
        ["body_text", _text],
        ["quality", _e getVariable [_pfx + "quality", "complet"]],
        ["channel", _e getVariable [_pfx + "channel", "physique"]],
        ["reveal", _e getVariable [_pfx + "reveal", "immediat"]],
        ["reveal_after", _e getVariable [_pfx + "reveal", "immediat"]],
        ["entities", [_e getVariable [_pfx + "entities", ""]] call _splitEntities],
        ["origin", "scenario"],
        ["position", getPosATL _e],
        ["pos_x", (getPosATL _e) select 0],
        ["pos_y", (getPosATL _e) select 1],
        ["grid_reference", mapGridPosition _e],
        ["show_on_map", false]
    ]
};

private _packets = [];
{
    private _p = [_entity, _x] call _packetAt;
    if (count _p > 0) then { _packets pushBack _p; };
} forEach [1, 2];

{
    if (_x isEqualType createHashMap && {count _x > 0}) then {
        _packets pushBack _x;
    };
} forEach (_entity getVariable ["comspec_sse_domex_livePackets", []]);

private _nodeId = _entity getVariable ["comspec_sse_domex_nodeId", ""];
if (_nodeId isEqualTo "" && {count _packets > 0 || {_entity getVariable ["comspec_sse_domex_enabled", false]}}) then {
    _nodeId = format ["OBJ-%1", netId _entity];
};

private _duration = _entity getVariable ["comspec_sse_domex_duration", "180"];
if (_duration isEqualType 0) then { _duration = str _duration; };

createHashMapFromArray [
    ["enabled", _entity getVariable ["comspec_sse_domex_enabled", false]],
    ["node_id", _nodeId],
    ["device_type", _entity getVariable ["comspec_sse_domex_deviceType", "ordinateur"]],
    ["owner", _entity getVariable ["comspec_sse_domex_owner", ""]],
    ["owner_label", _entity getVariable ["comspec_sse_domex_owner", ""]],
    ["organization", _entity getVariable ["comspec_sse_domex_org", ""]],
    ["organization_label", _entity getVariable ["comspec_sse_domex_org", ""]],
    ["network", _entity getVariable ["comspec_sse_domex_network", ""]],
    ["fictional_network", _entity getVariable ["comspec_sse_domex_network", ""]],
    ["exploitable", _entity getVariable ["comspec_sse_domex_exploitable", true]],
    ["access_physical", _entity getVariable ["comspec_sse_domex_accessPhysical", true]],
    ["access_remote", _entity getVariable ["comspec_sse_domex_accessRemote", false]],
    ["security", _entity getVariable ["comspec_sse_domex_security", "moyenne"]],
    ["security_tier", _entity getVariable ["comspec_sse_domex_security", "moyenne"]],
    ["profile", _entity getVariable ["comspec_sse_domex_profile", "generique"]],
    ["content_profile", _entity getVariable ["comspec_sse_domex_profile", "generique"]],
    ["duration", _duration],
    ["duration_s", parseNumber _duration],
    ["stage", _entity getVariable ["comspec_sse_domex_stage", "non_identifie"]],
    ["terrain_stage", _entity getVariable ["comspec_sse_domex_stage", "non_identifie"]],
    ["packets", _packets]
]
