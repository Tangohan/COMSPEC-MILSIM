/*
    Lieu crédible et déterministe sur la carte jouée (ville, village, lieu-dit),
    pour les grids citées par les documents, SMS et intel générés.
    [_seed, _salt, _near, _radius] call comspec_sse_fnc_seededMapGrid -> [grid, posATL, nomDuLieu]
      _near   : position de référence ([] = toute la carte)
      _radius : rayon de recherche autour de _near (m)
*/
params [
    ["_seed", 0, [0]],
    ["_salt", "", [""]],
    ["_near", [], [[]]],
    ["_radius", 0, [0]]
];

private _types = ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"];
private _center = [worldSize / 2, worldSize / 2, 0];
private _pool = if (_near isEqualTo [] || {_radius <= 0}) then {
    // Liste de la carte mise en cache (nearestLocations sur toute la carte coûte cher).
    if (isNil "comspec_sse_mapLocations") then {
        comspec_sse_mapLocations = nearestLocations [_center, _types, worldSize];
    };
    comspec_sse_mapLocations
} else {
    nearestLocations [_near, _types, _radius]
};

private _h = [_seed, _salt] call comspec_sse_fnc_hash;
private _pos = [];
private _name = "";
if (_pool isNotEqualTo []) then {
    private _loc = _pool select (_h mod (count _pool));
    _pos = locationPosition _loc;
    _name = text _loc;
} else {
    _pos = if (_near isNotEqualTo []) then { _near } else { _center };
};

// Décalage déterministe (jusqu'à ~400 m) pour ne pas tomber pile sur le centre du lieu.
private _ang = ([_seed, _salt + "|ang"] call comspec_sse_fnc_hash) mod 360;
private _dist = 60 + (([_seed, _salt + "|dst"] call comspec_sse_fnc_hash) mod 340);
_pos = [
    (((_pos select 0) + (_dist * sin _ang)) max 0) min worldSize,
    (((_pos select 1) + (_dist * cos _ang)) max 0) min worldSize,
    0
];

[mapGridPosition _pos, _pos, _name]
