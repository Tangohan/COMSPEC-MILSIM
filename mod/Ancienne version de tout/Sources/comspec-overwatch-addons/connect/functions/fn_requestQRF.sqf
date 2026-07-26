/*
 * Auteur: COMSPEC
 * Demande QRF (Quick Reaction Force) pour appui immédiat
 *
 * Arguments:
 * 0: Type menace <STRING> - "AMBUSH", "ATTACK", "TROOPS_IN_CONTACT", "IED_STRIKE"
 * 1: Priorité <STRING> - "ROUTINE", "PRIORITY", "IMMEDIATE", "FLASH"
 * 2: Description menace <STRING>
 * 3: Taille ennemi estimée <STRING> - "FIRE_TEAM", "SQUAD", "PLATOON", "COMPANY", "UNKNOWN"
 * 4: (Optional) Nombre victimes amies <NUMBER>
 * 5: (Optional) Statut unité <STRING> - "SECURE", "ENGAGED", "PINNED", "OVERRUN"
 * 6: (Optional) Position contact <ARRAY> - [x, y] (défaut: joueur)
 *
 * Valeur de retour:
 * <BOOL> - true si succès
 *
 * Exemple:
 * ["AMBUSH", "IMMEDIATE", "Embuscade sur convoi, tirs nourris depuis bâtiments", "SQUAD", 2, "PINNED"] call comspec_overwatch_connect_fnc_requestQRF;
 */

params [
    ["_threatType", "ATTACK", [""]],
    ["_priority", "IMMEDIATE", [""]],
    ["_threatDescription", "", [""]],
    ["_enemyStrength", "UNKNOWN", [""]],
    ["_friendlyCasualties", 0, [0]],
    ["_friendlyStatus", "ENGAGED", [""]],
    ["_contactPos", [], [[]]]
];

// Validation
if (_threatDescription isEqualTo "") exitWith {
    systemChat "❌ Description menace requise";
    false
};

// Position par défaut
if (_contactPos isEqualTo []) then {
    _contactPos = getPosWorld player;
};

// Compter effectif unité
private _friendlyStrength = {alive _x} count units (group player);

// Préparer données
private _qrfData = createHashMap;
_qrfData set ["priority", _priority];
_qrfData set ["contact_pos_x", _contactPos select 0];
_qrfData set ["contact_pos_y", _contactPos select 1];
_qrfData set ["grid_reference", mapGridPosition _contactPos];
_qrfData set ["threat_type", _threatType];
_qrfData set ["threat_description", _threatDescription];
_qrfData set ["enemy_strength", _enemyStrength];
_qrfData set ["requesting_unit", groupId (group player)];
_qrfData set ["requesting_callsign", name player];
_qrfData set ["friendly_strength", _friendlyStrength];
_qrfData set ["friendly_casualties", _friendlyCasualties];
_qrfData set ["friendly_status", _friendlyStatus];

// Support requis par défaut
_qrfData set ["support_requested", ["infantry"]];
if (_enemyStrength in ["PLATOON", "COMPANY"]) then {
    _qrfData set ["support_requested", ["infantry", "armor", "cas"]];
};
if (_friendlyCasualties > 0) then {
    private _support = _qrfData get "support_requested";
    _support pushBackUnique "medevac";
    _qrfData set ["support_requested", _support];
};

// Envoyer via extension
private _jsonString = [_qrfData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["RequestQRF", [_jsonString]];

// Feedback
if ((_result select 0) isEqualTo "OK") then {
    systemChat "✅ QRF demandée, renfort en route";
    
    // Hint visuel critique
    private _urgencyColor = if (_priority isEqualTo "FLASH") then {"#ff0000"} else {"#ff9900"};
    hint parseText format [
        "<t color='%1' size='1.5' align='center'>QRF ACTIVÉE</t><br/>" +
        "<t size='1.2'>Menace: %2</t><br/>" +
        "<t size='1.1'>Tenez position, renfort arrive</t><br/>" +
        "<t size='1'>Effectif ennemi: %3</t><br/>" +
        "<t size='1'>Statut: %4</t>",
        _urgencyColor,
        _threatType,
        _enemyStrength,
        _friendlyStatus
    ];
    
    // Son alerte selon urgence
    if (_priority isEqualTo "FLASH") then {
        playSound "RadioAmbient5";
    } else {
        playSound "RadioAmbient3";
    };
    
    // Marker contact local
    private _markerName = format ["qrf_contact_%1", time];
    private _marker = createMarkerLocal [_markerName, _contactPos];
    _marker setMarkerTypeLocal "hd_destroy";
    _marker setMarkerColorLocal "ColorRed";
    _marker setMarkerTextLocal format ["QRF %1 - %2", _priority, _threatType];
    _marker setMarkerAlphaLocal 1.0;
    
    // Flare rouge si nuit
    if (sunOrMoon < 0.5) then {
        private _flare = "F_40mm_Red" createVehicle _contactPos;
        _flare setPos [_contactPos select 0, _contactPos select 1, (_contactPos select 2) + 150];
        _flare setVelocity [0, 0, -5];
    };
    
    // Log activité
    ["QRF_REQUESTED", createHashMapFromArray [
        ["threat_type", _threatType],
        ["priority", _priority],
        ["enemy_strength", _enemyStrength],
        ["casualties", _friendlyCasualties]
    ]] call comspec_overwatch_connect_fnc_publishEvent;
    
    true
} else {
    systemChat format ["❌ Erreur demande QRF: %1", _result select 1];
    false
};
