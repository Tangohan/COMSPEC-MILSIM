/*
    Crée des marqueurs locaux pour les points / intel géospatiaux.
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
    if (_grid != "") then {
        private _name = format ["sse_poi_%1_%2", netId _entity, _forEachIndex];
        if (markerType _name isEqualTo "") then {
            // grid → approx world pos via player map (fallback entity pos)
            private _pos = getPosATL _entity;
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
            private _name = format ["sse_act_%1", _x getOrDefault ["id", _forEachIndex]];
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
