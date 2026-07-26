/*
    Applique les effets de la zone roleplay actuelle au joueur.
    Appelé périodiquement par PFH.
*/

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith {};

private _zone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
private _lastZone = missionNamespace getVariable ["COMSPEC_LastRoleplayZone", nil];

// Détection entrée/sortie de zone
if (!isNil "_zone" && {isNil "_lastZone" || {(_zone get "id") != (_lastZone get "id")}}) then {
    // Entrée dans une zone
    private _zoneName = _zone get "name";
    private _intensity = _zone get "intensity";
    
    private _msg = format ["Entrée en %1 (intensité %2%%)", _zoneName, _intensity];
    [_msg, "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    
    // Son d'avertissement
    ["warning"] call comspec_overwatch_connect_fnc_playRoleplaySound;
    
    missionNamespace setVariable ["COMSPEC_LastRoleplayZone", _zone, false];
    missionNamespace setVariable ["COMSPEC_InRoleplayZone", true, false];
    
    diag_log format ["[COMSPEC Roleplay] Joueur entré dans zone: %1", _zoneName];
};

if (isNil "_zone" && {!isNil "_lastZone"}) then {
    // Sortie d'une zone
    ["Sortie de zone dégradée", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    
    missionNamespace setVariable ["COMSPEC_LastRoleplayZone", nil, false];
    missionNamespace setVariable ["COMSPEC_InRoleplayZone", false, false];
    
    diag_log "[COMSPEC Roleplay] Joueur sorti de zone";
};

// Appliquer les effets si dans une zone
if (!isNil "_zone") then {
    private _type = _zone get "type";
    private _intensity = _zone get "intensity";
    
    // Stocker les modificateurs de zone
    private _zoneEffects = createHashMap;
    
    switch (_type) do {
        case "no_coverage": {
            // Aucune couverture = déconnexion forcée
            _zoneEffects set ["force_disconnect", true];
            _zoneEffects set ["packet_loss_multiplier", 1.0];
        };
        
        case "interference": {
            // Interférence = packet loss élevé
            _zoneEffects set ["force_disconnect", false];
            _zoneEffects set ["packet_loss_multiplier", (_intensity / 100) * 3]; // x3 max
        };
        
        case "degraded": {
            // Dégradé = latence + packet loss modéré
            _zoneEffects set ["force_disconnect", false];
            _zoneEffects set ["packet_loss_multiplier", (_intensity / 100) * 1.5]; // x1.5 max
            _zoneEffects set ["latency_add", (_intensity / 100) * 500]; // +500ms max
        };
        
        case "jammer": {
            // Brouilleur = déconnexions intermittentes
            _zoneEffects set ["force_disconnect", random 100 < (_intensity / 2)]; // 50% max chance
            _zoneEffects set ["packet_loss_multiplier", (_intensity / 100) * 2]; // x2 max
        };
    };
    
    missionNamespace setVariable ["COMSPEC_ZoneEffects", _zoneEffects, false];
} else {
    missionNamespace setVariable ["COMSPEC_ZoneEffects", nil, false];
};
