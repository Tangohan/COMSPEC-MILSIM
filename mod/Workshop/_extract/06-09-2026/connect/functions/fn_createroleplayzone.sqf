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

if (!isServer && {isMultiplayer}) exitWith {
    _this remoteExecCall ["comspec_overwatch_connect_fnc_createRoleplayZone", 2];
    createHashMap
};

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

// Créer le marqueur visuel (toujours — Zeus / Eden / script)
private _markerName = format ["comspec_roleplay_zone_%1", _zone get "id"];
private _marker = createMarker [_markerName, _position];
_marker setMarkerShape "ELLIPSE";
_marker setMarkerSize [_radius, _radius];
_marker setMarkerColor _color;
_marker setMarkerBrush "Border";
_marker setMarkerAlpha 0.55;
_marker setMarkerText format ["%1 (%2m)", _typeName, round _radius];
_zone set ["marker", _markerName];

// Ajouter à la liste globale
if (isNil "COMSPEC_RoleplayZones") then {
    COMSPEC_RoleplayZones = [];
};
COMSPEC_RoleplayZones pushBack _zone;
publicVariable "COMSPEC_RoleplayZones";

// Activer le moteur roleplay sur toutes les machines (sinon zones inertes).
//
// Auparavant fait par remoteExecCall ["call", 0, true]. Deux défauts :
//   - « call » n'est pas dans CfgRemoteExec >> Commands, qui est en mode 1
//     (liste blanche). L'appel était donc rejeté et le moteur restait éteint
//     partout sauf sur le serveur — les zones paraissaient inertes.
//   - l'y whitelister ouvrirait l'exécution de code arbitraire à distance à
//     n'importe quel client, ce qu'on ne veut pas dans une partie publique.
//
// Des variables publiques font le même travail sans passer par remoteExec, et
// couvrent en plus les joueurs qui rejoignent en cours de partie — ce que le
// drapeau JIP de l'ancien appel ne pouvait pas faire, « jip = 0 » étant posé
// dans la configuration.
// On ne force que les deux réglages désactivés par défaut, sans lesquels la zone
// resterait inerte. « effets visuels » est déjà activé par défaut : le forcer
// n'aurait d'effet que sur les joueurs qui l'ont volontairement coupé — glitchs
// et parasites se désactivent souvent pour un motif de confort visuel, et une
// pose de zone par un tiers n'a pas à revenir sur ce choix.
missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, true];
missionNamespace setVariable ["comspec_overwatch_roleplay_network_failures", true, true];

// Log
diag_log format ["[COMSPEC Roleplay] Zone créée: %1 à %2 (rayon %3m, intensité %4%%)", 
    _typeName, _position, _radius, _intensity];

// Notification
if (hasInterface) then {
    private _msg = format ["Zone créée : %1 (%2m)", _typeName, _radius];
    [_msg, "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
};

_zone
