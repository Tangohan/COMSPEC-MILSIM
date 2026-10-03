/* Types et couleurs de marqueurs proposés par l'éditeur (lus dans la config, mis en cache). */
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_MarkerCatalog", []];
if ((count _cache) > 0) exitWith { _cache };
private _classes = ["Military", "NATO_BLUFOR", "NATO_OPFOR", "NATO_Independent", "NATO_Civilian", "NATO_Unknown", "Flags", "Civilian", "System"];
private _types = [];
{
    if ((getNumber (_x >> "scope")) >= 2 && {(getText (_x >> "markerClass")) in _classes} && {(getText (_x >> "icon")) isNotEqualTo ""}) then {
        _types pushBack [getText (_x >> "name"), configName _x, getText (_x >> "icon"), getText (_x >> "markerClass")];
    };
} forEach ("true" configClasses (configFile >> "CfgMarkers"));
// Militaire d'abord, puis OTAN, triés par nom.
private _order = { params ["_c"]; (_classes find _c) max 0 };
_types = _types apply { [[_x select 3] call _order, _x select 0, _x] };
_types sort true;
_types = _types apply { _x select 2 };
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
