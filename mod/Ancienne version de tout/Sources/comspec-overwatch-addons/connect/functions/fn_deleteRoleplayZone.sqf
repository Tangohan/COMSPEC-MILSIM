/*
    Supprime une zone roleplay.
    
    Params:
        _zoneId - ID de la zone OU index dans le tableau
*/

params [["_zoneId", "", [0, ""]]];

if (isNil "COMSPEC_RoleplayZones") exitWith { false };

private _zones = COMSPEC_RoleplayZones;
private _found = false;

// Chercher par ID ou index
private _index = -1;
if (_zoneId isEqualType "") then {
    // Recherche par ID
    {
        if ((_x get "id") isEqualTo _zoneId) exitWith {
            _index = _forEachIndex;
        };
    } forEach _zones;
} else {
    // Index direct
    _index = _zoneId;
};

if (_index >= 0 && _index < count _zones) then {
    private _zone = _zones select _index;
    
    // Supprimer le marqueur
    private _marker = _zone getOrDefault ["marker", ""];
    if (_marker != "") then {
        deleteMarker _marker;
    };
    
    // Retirer du tableau
    _zones deleteAt _index;
    COMSPEC_RoleplayZones = _zones;
    publicVariable "COMSPEC_RoleplayZones";
    
    _found = true;
    
    diag_log format ["[COMSPEC Roleplay] Zone supprimée: %1", _zone get "name"];
};

_found
