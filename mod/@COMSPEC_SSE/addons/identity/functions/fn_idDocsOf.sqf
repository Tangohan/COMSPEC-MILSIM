/*
    Documents réellement présents : inventaire de la personne (uniforme, gilet, sac) et, autour, conteneurs au sol
    (affaires tombées d'un corps, caisse). Aucun coût par image : appelé seulement par une action.
    [_unit, _radius] call comspec_sse_fnc_idDocsOf -> [[classe, porteur (unité ou conteneur)], …]
      _radius : rayon des conteneurs proches (0 : inventaire seul ; défaut 3 m)
*/
params [["_u", objNull, [objNull]], ["_radius", 3, [0]]];
if (isNull _u) exitWith { [] };
// Exemplaires délivrés (COMSPEC_ID_Card_001…) et documents vierges du catalogue (Arsenal, Zeus).
private _isDoc = { (_this select [0, 11]) isEqualTo "COMSPEC_ID_" };
private _out = [];
private _seen = [];
{
    if (!(_x in _seen) && {_x call _isDoc}) then { _seen pushBack _x; _out pushBack [_x, _u]; };
} forEach ((uniformItems _u) + (vestItems _u) + (backpackItems _u) + (items _u));
if (_radius > 0) then {
    {
        private _h = _x;
        {
            if (!(_x in _seen) && {_x call _isDoc}) then { _seen pushBack _x; _out pushBack [_x, _h]; };
        } forEach (itemCargo _h);
    } forEach (nearestObjects [_u, ["WeaponHolder", "GroundWeaponHolder", "WeaponHolderSimulated", "ReammoBox_F"], _radius]);
};
_out
