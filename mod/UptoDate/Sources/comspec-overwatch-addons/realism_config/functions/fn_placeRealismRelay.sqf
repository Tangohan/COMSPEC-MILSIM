/**
 * ATHENA_fnc_placeRealismRelay
 * 
 * Pose un relais radio avec paramètres réalisme depuis config centralisée.
 * Le relais est synchronisé avec l'API ATHENA et visible sur Tacmap.
 * 
 * Arguments:
 *   0: OBJECT - Joueur qui pose le relais
 *   1: ARRAY (Optionnel) - Position [x, y, z] (défaut: position joueur)
 * 
 * Retour:
 *   OBJECT - Objet relais créé (objNull si erreur)
 * 
 * Exemples:
 *   [player] call ATHENA_fnc_placeRealismRelay;
 *   [player, getPos player] call ATHENA_fnc_placeRealismRelay;
 * 
 * Note:
 *   - Consomme un item "ATHENA_RelayKit" dans l'inventaire du joueur
 *   - Synchronise automatiquement avec API ATHENA (via COMSPEC DLL)
 *   - Applique paramètres de portée, débit, certificats depuis config réalisme
 */

params [
    ["_player", objNull, [objNull]],
    ["_position", [], [[]]]
];

// Vérifier joueur valide
if (isNull _player) exitWith {
    diag_log "[ATHENA] placeRealismRelay: joueur null";
    objNull
};

// Position par défaut = position joueur
if (count _position == 0) then {
    _position = getPos _player;
};

// Vérifier que joueur a un kit relais
if (!("ATHENA_RelayKit" in items _player)) exitWith {
    hint "❌ Vous n'avez pas de kit relais";
    diag_log "[ATHENA] placeRealismRelay: joueur n'a pas de kit relais";
    objNull
};

// Récupérer paramètres config réalisme
private _relayRangeM = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
private _relayThroughputKbps = ["radio_relays", "relay_throughput_kbps", 256] call ATHENA_fnc_getRealismParam;
private _maxConnections = ["radio_relays", "max_relay_connections", 10] call ATHENA_fnc_getRealismParam;
private _certificateRequired = ["certificates", "certificate_required", true] call ATHENA_fnc_getRealismParam;
private _weatherEnabled = ["radio_relays", "weather_effects_enabled", true] call ATHENA_fnc_getRealismParam;

diag_log format ["[ATHENA] placeRealismRelay: config lue - range:%1m, throughput:%2kbps, maxConn:%3", _relayRangeM, _relayThroughputKbps, _maxConnections];

// Créer objet relais physique
private _relayClass = "Land_PortableServer_01_connected_F"; // Classe Arma 3
private _relay = _relayClass createVehicle _position;
_relay setPos _position;
_relay setVectorUp [0, 0, 1];

// Générer UID unique
private _relayUID = format ["RELAY_%1_%2", floor (time * 1000), floor (random 9999)];

// Stocker métadonnées sur l'objet
_relay setVariable ["ATHENA_relayUID", _relayUID, true];
_relay setVariable ["ATHENA_relayRange", _relayRangeM, true];
_relay setVariable ["ATHENA_relayThroughput", _relayThroughputKbps, true];
_relay setVariable ["ATHENA_relayMaxConnections", _maxConnections, true];
_relay setVariable ["ATHENA_relayOwner", getPlayerUID _player, true];
_relay setVariable ["ATHENA_relayOwnerName", name _player, true];
_relay setVariable ["ATHENA_relayConnectedClients", [], true];
_relay setVariable ["ATHENA_relayStatus", "active", true];
_relay setVariable ["ATHENA_weatherAffected", _weatherEnabled, true];

// Ajouter action ACE pour voir statut
if (isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) then {
    [
        _relay,
        0,
        ["ACE_MainActions"],
        [
            "ATHENA_ViewRelayStatus",
            "📡 Voir statut relais",
            "",
            {
                params ["_target", "_player"];
                private _range = _target getVariable ["ATHENA_relayRange", 0];
                private _throughput = _target getVariable ["ATHENA_relayThroughput", 0];
                private _connections = count (_target getVariable ["ATHENA_relayConnectedClients", []]);
                private _maxConn = _target getVariable ["ATHENA_relayMaxConnections", 0];
                private _status = _target getVariable ["ATHENA_relayStatus", "unknown"];
                
                hint format [
                    "📡 RELAIS RADIO\n\nPortée : %1m\nDébit : %2 kbps\nConnexions : %3/%4\nStatut : %5",
                    _range,
                    _throughput,
                    _connections,
                    _maxConn,
                    toUpper _status
                ];
            },
            {true}
        ] call ace_interact_menu_fnc_createAction,
        []
    ] call ace_interact_menu_fnc_addActionToObject;
};

// Ajouter action destruction (pour propriétaire ou admin)
if (isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) then {
    [
        _relay,
        0,
        ["ACE_MainActions"],
        [
            "ATHENA_DestroyRelay",
            "💥 Détruire relais",
            "",
            {
                params ["_target", "_player"];
                private _ownerUID = _target getVariable ["ATHENA_relayOwner", ""];
                
                if (getPlayerUID _player == _ownerUID || {_player call ATHENA_fnc_isAdmin}) then {
                    ["ATHENA_relayDestroyed", [_target]] call CBA_fnc_serverEvent;
                    deleteVehicle _target;
                    hint "✅ Relais détruit";
                } else {
                    hint "❌ Vous n'êtes pas propriétaire de ce relais";
                };
            },
            {true}
        ] call ace_interact_menu_fnc_createAction,
        []
    ] call ace_interact_menu_fnc_addActionToObject;
};

// Synchroniser avec API ATHENA via DLL
private _syncData = [
    ["uid", _relayUID],
    ["position", _position],
    ["range_m", _relayRangeM],
    ["throughput_kbps", _relayThroughputKbps],
    ["max_connections", _maxConnections],
    ["owner_uid", getPlayerUID _player],
    ["owner_name", name _player],
    ["certificate_required", _certificateRequired],
    ["weather_affected", _weatherEnabled],
    ["created_at", systemTime]
];

private _response = "COMSPECExtension" callExtension ["SyncRelay", [str _syncData]];

private _code = _response select 1;

if (_code isEqualTo 0) then {
    diag_log format ["[ATHENA] placeRealismRelay: relais %1 synchronisé avec API", _relayUID];
    
    // Notifier tous les clients
    ["ATHENA_relayPlaced", [_relay, _relayUID, _player]] call CBA_fnc_globalEvent;
    
    // Retirer kit de l'inventaire
    _player removeItem "ATHENA_RelayKit";
    
    hint format ["✅ Relais posé\nPortée : %1m\nUID : %2", _relayRangeM, _relayUID];
} else {
    diag_log format ["[ATHENA] placeRealismRelay: ERREUR sync API (code %1)", _code];
    hint "⚠️ Relais posé mais non synchronisé avec API";
};

// Activer cercle de portée visible par propriétaire
if (hasInterface && {_player == player}) then {
    private _marker = createMarkerLocal [_relayUID, _position];
    _marker setMarkerShapeLocal "ELLIPSE";
    _marker setMarkerSizeLocal [_relayRangeM, _relayRangeM];
    _marker setMarkerColorLocal "ColorBlue";
    _marker setMarkerAlphaLocal 0.3;
    _marker setMarkerTextLocal format ["Relais %1", _relayUID];
};

// Appliquer effets météo si activés
if (_weatherEnabled) then {
    [_relay] spawn {
        params ["_relay"];
        
        while {alive _relay && {!isNull _relay}} do {
            private _weather = [] call ATHENA_fnc_getCurrentWeather;
            private _effects = [_relay getVariable ["ATHENA_relayRange", 2000], _weather] call ATHENA_fnc_calculateWeatherEffects;
            
            // Mettre à jour portée effective
            _relay setVariable ["ATHENA_relayEffectiveRange", _effects select 0, true];
            
            sleep 60; // Maj toutes les 60s
        };
    };
};

_relay
