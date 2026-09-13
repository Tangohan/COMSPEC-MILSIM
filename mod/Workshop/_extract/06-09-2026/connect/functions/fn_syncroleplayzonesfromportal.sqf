/*
    Crée/met à jour les zones roleplay depuis la config portail.
    Format lignes (extension GetRoleplayConfig) : name\ttype\tx\ty\tradius\tintensity
*/
if (!isServer && {!hasInterface}) exitWith {};

private _cfg = missionNamespace getVariable ["COMSPEC_PortalRoleplayConfig", createHashMap];
private _zonesLines = _cfg getOrDefault ["zones_lines", ""];

if (_zonesLines isEqualTo "") then {
    private _zonesJson = _cfg getOrDefault ["zones_json", ""];
    if (_zonesJson isEqualTo "") exitWith { false };
    _zonesLines = _zonesJson;
};

private _signature = str _zonesLines;
if (_signature isEqualTo (missionNamespace getVariable ["COMSPEC_PortalZonesSignature", ""])) exitWith { false };
missionNamespace setVariable ["COMSPEC_PortalZonesSignature", _signature, false];

if (!isNil "COMSPEC_RoleplayZones") then {
    private _filtered = [];
    {
        if ((_x getOrDefault ["source", ""]) isNotEqualTo "portal") then {
            _filtered pushBack _x;
        };
    } forEach COMSPEC_RoleplayZones;
    COMSPEC_RoleplayZones = _filtered;
} else {
    COMSPEC_RoleplayZones = [];
};

private _mapType = {
    params ["_raw"];
    private _t = toLower (trim _raw);
    switch (_t) do {
        case "high_loss": { "interference" };
        case "jamming": { "jammer" };
        case "jammer": { "jammer" };
        case "no_signal": { "no_coverage" };
        case "no_coverage": { "no_coverage" };
        case "degraded": { "degraded" };
        case "interference": { "interference" };
        default { "degraded" };
    };
};

{
    private _line = trim _x;
    if (_line isEqualTo "") then { continue };
    private _parts = _line splitString toString [9];
    if ((count _parts) < 5) then { continue };

    private _name = _parts select 0;
    private _typeRaw = _parts select 1;
    private _pos = [parseNumber (_parts select 2), parseNumber (_parts select 3), 0];
    private _radius = parseNumber (_parts select 4);
    private _intensity = if ((count _parts) > 5) then { parseNumber (_parts select 5) } else { 50 };

    if ((count _pos) < 2) then { continue };
    private _type = [_typeRaw] call _mapType;

    private _zone = createHashMap;
    _zone set ["id", format ["portal_%1_%2", round (_pos select 0), round (_pos select 1)]];
    _zone set ["name", _name];
    _zone set ["type", _type];
    _zone set ["intensity", (_intensity max 0) min 100];
    _zone set ["position", _pos];
    _zone set ["radius", _radius max 25];
    _zone set ["source", "portal"];

    COMSPEC_RoleplayZones pushBack _zone;
} forEach (_zonesLines splitString toString [10]);

publicVariable "COMSPEC_RoleplayZones";
true
