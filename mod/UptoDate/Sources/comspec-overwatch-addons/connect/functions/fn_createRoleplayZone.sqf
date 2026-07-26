/*
    Crée une zone roleplay avec effets réseau.
    Utilisée par Zeus/Eden ou script.
    
    Params:
        _position - Position [x, y, z] ou objet
        _radius - Rayon en mètres
        _type - Type de zone ("no_coverage", "interference", "degraded", "jammer")
        _intensity - Intensité 0-100 (optionnel, défaut selon type)
    
    Returns:
        _zone - HashMap avec données de la zone
*/

params [
    ["_position", [0,0,0], [[], objNull]],
    ["_radius", 100, [0]],
    ["_type", "degraded", [""]],
    ["_intensity", -1, [0]]
];

// Convertir objet en position
if (_position isEqualType objNull) then {
    _position = getPos _position;
};

// Valeurs par défaut selon le type
private _defaultIntensity = switch (_type) do {
    case "no_coverage": { 100 }; // Aucune liaison
    case "interference": { 50 }; // Interférence moyenne
    case "degraded": { 30 }; // Qualité dégradée
    case "jammer": { 80 }; // Brouillage fort
    default { 30 };
};

if (_intensity < 0) then {
    _intensity = _defaultIntensity;
};

// Créer la zone
private _zone = createHashMap;
_zone set ["position", _position];
_zone set ["radius", _radius];
_zone set ["type", _type];
_zone set ["intensity", _intensity min 100 max 0];
_zone set ["created_at", time];
_zone set ["id", format ["zone_%1_%2", time, floor (random 99999)]];

// Noms lisibles
private _typeName = switch (_type) do {
    case "no_coverage": { "Absence de couverture" };
    case "interference": { "Zone d'interférence" };
    case "degraded": { "Couverture dégradée" };
    case "jammer": { "Brouilleur actif" };
    default { "Zone inconnue" };
};
_zone set ["name", _typeName];

// Couleur selon le type
private _color = switch (_type) do {
    case "no_coverage": { "ColorRed" }; // Rouge (aucune liaison)
    case "interference": { "ColorOrange" }; // Orange (interférence)
    case "degraded": { "ColorYellow" }; // Jaune (dégradé)
    case "jammer": { "ColorPink" }; // Rose (brouilleur)
    default { "ColorGrey" };
};
_zone set ["color", _color];

// Créer le marqueur visuel (si Zeus/Eden)
if (!isNil "bis_fnc_moduleCurator" || {!isMultiplayer}) then {
    private _markerName = format ["comspec_roleplay_zone_%1", _zone get "id"];
    private _marker = createMarker [_markerName, _position];
    _marker setMarkerShape "ELLIPSE";
    _marker setMarkerSize [_radius, _radius];
    _marker setMarkerColor _color;
    _marker setMarkerBrush "Border";
    _marker setMarkerAlpha 0.5;
    _marker setMarkerText format ["%1 (%2m - %3%%)", _typeName, _radius, _intensity];
    
    _zone set ["marker", _markerName];
};

// Ajouter à la liste globale
if (isNil "COMSPEC_RoleplayZones") then {
    COMSPEC_RoleplayZones = [];
};
COMSPEC_RoleplayZones pushBack _zone;
publicVariable "COMSPEC_RoleplayZones";

// Log
diag_log format ["[COMSPEC Roleplay] Zone créée: %1 à %2 (rayon %3m, intensité %4%%)", 
    _typeName, _position, _radius, _intensity];

// Notification
if (hasInterface) then {
    private _msg = format ["Zone roleplay créée : %1 (%2m)", _typeName, _radius];
    hintSilent _msg;
};

_zone
