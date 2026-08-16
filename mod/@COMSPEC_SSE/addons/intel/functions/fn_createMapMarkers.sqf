/*
    Crée des marqueurs LOCAUX (non synchronisés Athena) pour l’opérateur in-game.
    Préfixe _comspec_sse_ → exclus du miroir ATAK (évite sse_poi_* techniques sur la carte web).
    [_entity, _intelItems] call comspec_sse_fnc_createMapMarkers
*/
params [
    ["_entity", objNull, [objNull]],
    ["_intelItems", [], [[]]]
];

if (!hasInterface) exitWith { [] };

private _created = [];
private _points = [_entity] call comspec_sse_fnc_extractGeopoints;
{
    private _grid = _x getOrDefault ["grid", ""];
    private _world = _x getOrDefault ["pos", []];
    if (_grid != "" || {(_world isEqualType []) && {(count _world) >= 2}}) then {
        private _name = format ["_comspec_sse_poi_%1_%2", netId _entity, _forEachIndex];
        if (markerType _name isEqualTo "") then {
            private _pos = getPosATL _entity;
            if ((_world isEqualType []) && {(count _world) >= 2} && {(_world select 0) isEqualType 0}) then {
                _pos = [_world select 0, _world select 1, 0];
            };
            createMarkerLocal [_name, _pos];
            _name setMarkerTypeLocal "mil_unknown";
            _name setMarkerColorLocal "ColorOrange";
            _name setMarkerTextLocal format ["SSE %1 (%2)", _x getOrDefault ["label", "POI"], _grid];
            _created pushBack _name;
        };
    };
} forEach _points;

{
    if (_x isEqualType createHashMap && {_x getOrDefault ["actionable", false]}) then {
        private _txt = _x getOrDefault ["text", ""];
        if ((_txt find "Grid") >= 0 || {(_txt find "grid") >= 0}) then {
            private _name = format ["_comspec_sse_act_%1", _x getOrDefault ["id", _forEachIndex]];
            if (markerType _name isEqualTo "") then {
                createMarkerLocal [_name, getPosATL _entity];
                _name setMarkerTypeLocal "mil_warning";
                _name setMarkerColorLocal "ColorRed";
                _name setMarkerTextLocal (_txt select [0, (count _txt) min 40]);
                _created pushBack _name;
            };
        };
    };
} forEach _intelItems;

_created
