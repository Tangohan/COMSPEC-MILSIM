/* Types et couleurs de marqueurs proposés par l'éditeur (lus dans la config, mis en cache). */
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_MarkerCatalog", []];
if ((count _cache) > 0) exitWith { _cache };
// Tous les marqueurs chargés (Arma, MarkersPlus, Metis, cTab…), hors classe Système, avec le libellé du catalogue web.
private _labels = [] call comspec_atak_native_fnc_markerLabels;
private _first = ["Military", "NATO_BLUFOR", "NATO_OPFOR", "NATO_Independent", "NATO_Civilian", "NATO_Unknown"];
private _types = [];
{
    private _mc = getText (_x >> "markerClass");
    if ((getNumber (_x >> "scope")) >= 2 && {_mc isNotEqualTo "System"} && {(getText (_x >> "icon")) isNotEqualTo ""}) then {
        private _cls = configName _x;
        _types pushBack [_labels getOrDefault [toLower _cls, getText (_x >> "name")], _cls, getText (_x >> "icon"), _mc];
    };
} forEach ("true" configClasses (configFile >> "CfgMarkers"));
// Militaire et OTAN d'abord, puis les autres classes, triés par nom.
private _order = { params ["_c"]; private _i = _first find _c; [_i, 50] select (_i < 0) };
_types = _types apply { [[_x select 3] call _order, _x select 3, _x select 0, _x] };
_types sort true;
_types = _types apply { _x select 3 };
private _colors = [];
{
    if ((getNumber (_x >> "scope")) >= 2) then {
        private _rgba = (getArray (_x >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
        _colors pushBack [getText (_x >> "name"), configName _x, _rgba];
    };
} forEach ("true" configClasses (configFile >> "CfgMarkerColors"));
_cache = [_types, _colors];
uiNamespace setVariable ["COMSPEC_ATAK_MarkerCatalog", _cache];
_cache
