/*
    Génère un site SSE cohérent autour d'une position / bâtiment.
    [_center, _radius, _profile, _complexity, _options] call comspec_sse_fnc_generateSite

    File CBA étalée : une entité / ~0,12 s pour éviter pic generateData + ACE.
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

private _objCount = 0;
{
    if (_objCount >= _maxObjects) exitWith {};
    if !(_x in _targets) then {
        _targets pushBack _x;
        _objCount = _objCount + 1;
    };
} forEach _nearStuff;

if (_center isEqualType objNull && {!isNull _center}) then {
    _targets pushBackUnique _center;
};

// État partagé entre les jobs CBA (référence HashMap).
private _state = createHashMapFromArray [
    ["processed", []],
    ["phoneDone", false]
];

private _processOne = {
    params ["_ent", "_profile", "_complexity", "_cluster", "_wantDigital", "_wantDocs", "_wantNetwork", "_state"];
    if (isNull _ent) exitWith {};
    if (!isNil "comspec_sse_fnc_isHatchetVehicle" && {[_ent] call comspec_sse_fnc_isHatchetVehicle}) exitWith {};
    if (_ent getVariable ["comspec_sse_generating", false]) exitWith {};

    private _processed = _state getOrDefault ["processed", []];

    if (_ent isKindOf "CAManBase") then {
        [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
        if (_wantNetwork && {count _processed > 0}) then {
            private _other = _processed select 0;
            if (!isNull _other && {_other isKindOf "CAManBase"}) then {
                [_ent, _other, "CONTACT", 0.75, "SITE"] call comspec_sse_fnc_linkEntities;
            };
        };
    } else {
        if (_ent isKindOf "LandVehicle") then {
            _ent setVariable ["comspec_sse_forcedType", "VEHICLE", true];
            [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
            private _men = _processed select { !isNull _x && {_x isKindOf "CAManBase"} };
            if (count _men > 0) then {
                [_ent, _men select 0, "REFERENCES", 0.7, "SITE"] call comspec_sse_fnc_linkEntities;
            };
        } else {
            if (_wantDigital && {!(_state getOrDefault ["phoneDone", false])}) then {
                _state set ["phoneDone", true];
                _ent setVariable ["comspec_sse_forcedType", "PHONE", true];
                [_ent, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
                private _men = _processed select { !isNull _x && {_x isKindOf "CAManBase"} };
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
    _state set ["processed", _processed];
};

if (isNil "CBA_fnc_waitAndExecute") then {
    { [_x, _profile, _complexity, _cluster, _wantDigital, _wantDocs, _wantNetwork, _state] call _processOne; } forEach _targets;
} else {
    {
        private _delay = _forEachIndex * 0.28;
        [{
            params ["_ent", "_profile", "_complexity", "_cluster", "_wantDigital", "_wantDocs", "_wantNetwork", "_state", "_fn"];
            [_ent, _profile, _complexity, _cluster, _wantDigital, _wantDocs, _wantNetwork, _state] call _fn;
        }, [_x, _profile, _complexity, _cluster, _wantDigital, _wantDocs, _wantNetwork, _state, _processOne], _delay] call CBA_fnc_waitAndExecute;
    } forEach _targets;
};

[format ["generateSite pos=%1 radius=%2 entities=%3 cluster=%4 (queued)", _pos, _radius, count _targets, _cluster getOrDefault ["clusterId", "?"]]] call comspec_sse_fnc_log;

_targets
