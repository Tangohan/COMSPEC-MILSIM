/*
    Génère un site SSE cohérent autour d'une position / bâtiment.
    [_center, _radius, _profile, _complexity, _options] call comspec_sse_fnc_generateSite

    _center: Object | Array position
    _options (optionnel HashMap ou pairs):
      maxObjects, digital, documents, caches, network
*/
params [
    ["_center", objNull, [objNull, []]],
    ["_radius", 35, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "DETAILED", [""]],
    ["_options", [], [[], createHashMap]]
];

private _pos = if (_center isEqualType []) then { _center } else { getPosATL _center };
if (_pos isEqualTo [0,0,0] && {_center isEqualType objNull} && {isNull _center}) exitWith {
    ["generateSite: centre invalide", "ERROR"] call comspec_sse_fnc_log;
    []
};

private _opt = if (_options isEqualType createHashMap) then { _options } else {
    private _h = createHashMap;
    { _x params ["_k", "_v"]; _h set [toLower _k, _v]; } forEach _options;
    _h
};

private _maxObjects = _opt getOrDefault ["maxobjects", 8];
private _wantDigital = _opt getOrDefault ["digital", true];
private _wantDocs = _opt getOrDefault ["documents", true];
private _wantNetwork = _opt getOrDefault ["network", true];

private _seed = [round ((_pos select 0) * 10), format ["site_%1_%2", round (_pos select 1), _radius]] call comspec_sse_fnc_hash;
private _cluster = [_seed, _profile, _complexity] call comspec_sse_fnc_generateCluster;

private _nearMen = nearestObjects [_pos, ["CAManBase"], _radius];
private _nearVehicles = nearestObjects [_pos, ["LandVehicle"], _radius];
private _nearStuff = nearestObjects [_pos, ["ThingX", "ReammoBox_F", "WeaponHolder"], _radius];

private _targets = [];
{ _targets pushBackUnique _x; } forEach _nearMen;
{ _targets pushBackUnique _x; } forEach _nearVehicles;

// Limiter les objets
private _objCount = 0;
{
    if (_objCount >= _maxObjects) exitWith {};
    if !(_x in _targets) then {
        _targets pushBack _x;
        _objCount = _objCount + 1;
    };
} forEach _nearStuff;

// Si peu de cibles, marquer le centre
if (_center isEqualType objNull && {!isNull _center}) then {
    _targets pushBackUnique _center;
};

private _processed = [];
private _phoneCreated = false;

{
    private _ent = _x;
    if (!isNull _ent) then {

    if (_ent isKindOf "CAManBase") then {
        [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
        if (_wantNetwork && {count _processed > 0}) then {
            private _other = _processed select 0;
            if (_other isKindOf "CAManBase") then {
                [_ent, _other, "CONTACT", 0.75, "SITE"] call comspec_sse_fnc_linkEntities;
            };
        };
    } else {
        if (_ent isKindOf "LandVehicle") then {
            _ent setVariable ["comspec_sse_forcedType", "VEHICLE", true];
            [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
            // Lier au premier personnage
            private _men = _processed select { _x isKindOf "CAManBase" };
            if (count _men > 0) then {
                [_ent, _men select 0, "REFERENCES", 0.7, "SITE"] call comspec_sse_fnc_linkEntities;
            };
        } else {
            if (_wantDigital && {!_phoneCreated}) then {
                _ent setVariable ["comspec_sse_forcedType", "PHONE", true];
                [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
                _phoneCreated = true;
                private _men = _processed select { _x isKindOf "CAManBase" };
                if (count _men > 0) then {
                    [_ent, _men select 0, "OWNER", 0.9, "SITE"] call comspec_sse_fnc_linkEntities;
                };
            } else {
                if (_wantDocs) then {
                    _ent setVariable ["comspec_sse_forcedType", "DOCUMENT", true];
                    [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
                } else {
                    [_ent, "OBJECT", _profile, _complexity] call comspec_sse_fnc_makeSearchable;
                    [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
                };
            };
        };
    };

    _processed pushBack _ent;
    }; // !isNull
} forEach _targets;

[format ["generateSite pos=%1 radius=%2 entities=%3 cluster=%4", _pos, _radius, count _processed, _cluster getOrDefault ["clusterId", "?"]]] call comspec_sse_fnc_log;

_processed
