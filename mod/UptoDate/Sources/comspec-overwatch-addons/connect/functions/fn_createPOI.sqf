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
// Appelé depuis une action ACE / un raccourci CBA (non planifié) : CreatePOI est un appel HTTP
// synchrone, il bloquerait le jeu. On relance en planifié, comme submitTacticalReport.
if (!canSuspend) exitWith { _this spawn comspec_overwatch_connect_fnc_createPOI; true };

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
    "CreatePOI",
    [_jsonString],
    format ["POI %1", _poiName],
    true,
    true,
    "liaison",
    true
] call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params ["_ok", "_status", "_detail"];
// Liaison coupée : le POI est en file hors ligne, pas perdu. L'annoncer comme un échec
// pousserait à le recréer ; on garde le marqueur local et on prévient le joueur.
private _queued = (!_ok) && {_status isEqualTo "QUEUED"};
// Délai d'attente dépassé (extAwait rend ERR|timeout) : l'extension peut encore poster le POI.
// Issue incertaine, pas un échec : on garde le marqueur local pour éviter un doublon recréé.
private _timedOut = (!_ok) && {!_queued} && {(toLower _detail) isEqualTo "timeout" || {(toLower _status) isEqualTo "timeout"}};

if (_ok || _queued || _timedOut) then {
    if (_queued) then {
        [format ["Liaison coupée — point d'intérêt %1 conservé, il partira au rétablissement.", _poiName], "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    } else {
        if (_timedOut) exitWith {
            [format ["Athena tarde à répondre — le point d'intérêt %1 a peut-être déjà été transmis, ne le recréez pas.", _poiName], "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        };
        [format ["Point d'intérêt partagé : %1", _poiName], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    };
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
