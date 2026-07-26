/*
    Cree un point d'interet et l'envoie vers Athena.
*/
params [
    ["_poiName", "", [""]],
    ["_category", "OTHER", [""]],
    ["_affiliation", "UNKNOWN", [""]],
    ["_certainty", "POSSIBLE", [""]],
    ["_description", "", [""]],
    ["_position", [], [[]]],
    ["_threatLevel", "LOW", [""]]
];

if (!hasInterface) exitWith { false };

if (_poiName isEqualTo "") exitWith {
    ["Indiquez un nom pour le point d'intérêt.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (_position isEqualTo []) then {
    private _cursorTarget = cursorTarget;
    if (!isNull _cursorTarget) then {
        _position = getPosWorld _cursorTarget;
    } else {
        _position = screenToWorld [0.5, 0.5];
        if (_position isEqualTo [0, 0, 0]) then {
            _position = getPosWorld player;
        };
    };
};

private _poiData = createHashMap;
_poiData set ["poi_name", _poiName];
_poiData set ["category", _category];
_poiData set ["affiliation", _affiliation];
_poiData set ["certainty", _certainty];
_poiData set ["pos_x", _position select 0];
_poiData set ["pos_y", _position select 1];
_poiData set ["grid_reference", mapGridPosition _position];
_poiData set ["description", _description];
_poiData set ["threat_level", _threatLevel];
_poiData set ["source_type", "VISUAL"];
_poiData set ["source_reliability", "USUALLY_RELIABLE"];
_poiData set ["reported_by_callsign", name player];
_poiData set ["reported_by_unit", groupId (group player)];

private _jsonString = [_poiData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _parsed = [
    "COMSPECExtension" callExtension ["CreatePOI", [_jsonString]]
] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
_parsed params ["_ok", "", "_detail"];

if (_ok) then {
    [format ["Point d'intérêt partagé : %1", _poiName], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    private _markerName = format ["poi_local_%1_%2", floor time, floor random 10000];
    private _marker = createMarkerLocal [_markerName, _position];
    private _mType = switch (toUpper _category) do {
        case "CACHE": { "mil_destroy" };
        case "ENEMY_POSITION": { "o_inf" };
        case "OBJECTIVE": { "mil_objective" };
        default { "mil_warning" };
    };
    private _color = switch (_affiliation) do {
        case "FRIENDLY": { "ColorBlue" };
        case "ENEMY": { "ColorRed" };
        case "NEUTRAL": { "ColorGreen" };
        default { "ColorYellow" };
    };
    if (_affiliation isEqualTo "ENEMY" && {_mType isEqualTo "mil_warning"}) then { _mType = "o_unknown"; };
    _marker setMarkerTypeLocal _mType;
    _marker setMarkerColorLocal _color;
    _marker setMarkerTextLocal _poiName;
    _marker setMarkerAlphaLocal 0.8;
    [_markerName, _position, _mType, _color, _poiName, "ace_poi"] call comspec_overwatch_connect_fnc_sendLocalTacticalMarker;
    [{
        params ["_n"];
        "COMSPECExtension" callExtension ["SendMarker", [_n, "{}", "1", "1"]];
        deleteMarkerLocal _n;
    }, [_markerName], 300] call CBA_fnc_waitAndExecute;
    true
} else {
    [([
        _detail,
        "Impossible de créer le point d'intérêt — vérifiez la liaison Athena."
    ] call comspec_overwatch_connect_fnc_atakExtFailMessage), "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
