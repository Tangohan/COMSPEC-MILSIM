/*
    Avancement SSE du site autour d'une position : une étape par geste de la doctrine
    (photos en place, fouille, biométrie, numérique, scellés, transmission).
    [_center, _radius] call comspec_sse_fnc_fieldSite -> HashMap
      total, steps [[clé, libellé, faits, attendus]...], ratio (0-1), go (bool)
*/
params [["_center", objNull, [objNull, []]], ["_radius", 50, [0]]];
private _pos = if (_center isEqualType []) then { _center } else { getPosATL _center };

private _ents = (nearestObjects [_pos, [], _radius]) select {
    _x getVariable ["comspec_sse_enabled", false] && {!isPlayer _x}
};
private _custody = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
private _n = createHashMapFromArray [
    ["photo", 0], ["search", 0], ["bio", 0], ["persons", 0], ["digital", 0], ["digitalDone", 0],
    ["seal", 0], ["objects", 0], ["tx", 0]
];
private _inc = { params ["_k"]; _n set [_k, (_n get _k) + 1]; };

{
    private _e = _x;
    private _data = [_e] call comspec_sse_fnc_getData;
    if (!isNil "_data") then {
        private _type = [_data, "type", "OBJECT"] call comspec_sse_fnc_getPair;
        private _photos = [_e, "photos"] call comspec_sse_fnc_getSection;
        if (!isNil "_photos" && {_photos isEqualType []} && {_photos isNotEqualTo []}) then { "photo" call _inc; };
        if ([_data, "searched", false] call comspec_sse_fnc_getPair) then { "search" call _inc; };
        if (([_data, "state", ""] call comspec_sse_fnc_getPair) isEqualTo "TRANSMITTED") then { "tx" call _inc; };
        if (_type isEqualTo "PERSON") then {
            "persons" call _inc;
            private _bio = [_e, "biometrics"] call comspec_sse_fnc_getSection;
            if (!isNil "_bio" && {_bio isEqualType createHashMap} && {(_bio getOrDefault ["fingerprintId", ""]) isNotEqualTo "" || {(_bio getOrDefault ["irisId", ""]) isNotEqualTo ""}}) then { "bio" call _inc; };
        } else {
            "objects" call _inc;
            private _pid = _e getVariable ["COMSPEC_SSE_PieceId", ""];
            if (_pid isNotEqualTo "" && {((_custody getOrDefault [_pid, createHashMap]) getOrDefault ["seal", ""]) isNotEqualTo ""}) then { "seal" call _inc; };
        };
        if (!isNil { _e getVariable "COMSPEC_ATAK_Data" }) then {
            "digital" call _inc;
            private _dx = [_e, "digitalExploitation"] call comspec_sse_fnc_getSection;
            if (!isNil "_dx" && {_dx isEqualType createHashMap} && {count _dx > 0}) then { "digitalDone" call _inc; };
        };
    };
} forEach _ents;

private _total = count _ents;
private _steps = [
    ["photo", "Photos en place", _n get "photo", _total],
    ["search", "Fouille", _n get "search", _total],
    ["bio", "Biométrie", _n get "bio", _n get "persons"],
    ["digital", "Extraction numérique", _n get "digitalDone", _n get "digital"],
    ["seal", "Scellés", _n get "seal", _n get "objects"],
    ["tx", "Transmission", _n get "tx", _total]
];
private _parts = _steps select { (_x select 3) > 0 };
private _ratio = if (_parts isEqualTo []) then { 0 } else {
    private _sum = 0;
    { _sum = _sum + ((_x select 2) / (_x select 3)); } forEach _parts;
    _sum / (count _parts)
};
// Exfiltration : chaque étape attendue faite à 80 % au moins, scellés compris.
private _go = _parts isNotEqualTo [] && {(_parts findIf { ((_x select 2) / (_x select 3)) < 0.8 }) < 0};

createHashMapFromArray [["total", _total], ["steps", _steps], ["ratio", _ratio], ["go", _go]]
