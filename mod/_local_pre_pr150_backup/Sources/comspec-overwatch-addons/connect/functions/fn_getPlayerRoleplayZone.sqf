/*
    Retourne la zone roleplay dans laquelle se trouve le joueur.
    Si plusieurs zones, retourne celle avec l'intensité la plus forte.
    
    Returns:
        _zone - HashMap de la zone, ou nil si aucune
*/

if (isNil "COMSPEC_RoleplayZones") exitWith { nil };
if (!hasInterface) exitWith { nil };

private _playerPos = getPos player;
private _zones = COMSPEC_RoleplayZones;
private _currentZone = nil;
private _maxIntensity = -1;

{
    private _zone = _x;
    private _zonePos = _zone get "position";
    private _radius = _zone get "radius";
    private _intensity = _zone get "intensity";
    
    // Vérifier si le joueur est dans la zone
    private _distance = _playerPos distance2D _zonePos;
    
    if (_distance <= _radius) then {
        // Prendre la zone avec l'intensité la plus forte
        if (_intensity > _maxIntensity) then {
            _maxIntensity = _intensity;
            _currentZone = _zone;
        };
    };
} forEach _zones;

_currentZone
