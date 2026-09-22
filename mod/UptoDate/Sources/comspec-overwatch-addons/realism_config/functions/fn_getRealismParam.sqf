/**
 * ATHENA_fnc_getRealismParam
 * 
 * Récupère un paramètre de configuration réalisme depuis la config centralisée.
 * Utilise le cache local pour éviter appels répétés à la DLL.
 * 
 * Arguments:
 *   0: STRING - Domaine (ex: "radio_relays", "certificates", "terminal_damage")
 *   1: STRING - Clé du paramètre (ex: "relay_range_m", "certificate_required")
 *   2: ANY - (Optionnel) Valeur par défaut si param introuvable
 * 
 * Retour:
 *   ANY - Valeur du paramètre (type selon config)
 * 
 * Exemples:
 *   _range = ["radio_relays", "relay_range_m"] call ATHENA_fnc_getRealismParam;
 *   _certRequired = ["certificates", "certificate_required", false] call ATHENA_fnc_getRealismParam;
 *   _weatherEnabled = ["radio_relays", "weather_effects_enabled"] call ATHENA_fnc_getRealismParam;
 * 
 * Note:
 *   Cache rafraîchi toutes les 3 minutes. Modifier ATHENA_realismCacheTTL pour ajuster.
 */

params [
    ["_domain", "", [""]],
    ["_paramKey", "", [""]],
    ["_defaultValue", nil]
];

// Vérifier arguments
if (_domain isEqualTo "" || _paramKey isEqualTo "") exitWith {
    diag_log format ["[ATHENA] getRealismParam: domaine ou clé vide, retour default: %1", _defaultValue];
    _defaultValue
};

// Initialiser cache global si inexistant
if (isNil "ATHENA_realismConfigCache") then {
    ATHENA_realismConfigCache = createHashMap;
    ATHENA_realismConfigCacheTime = -999999;
    ATHENA_realismCacheTTL = 180; // 3 minutes
    diag_log "[ATHENA] getRealismParam: cache initialisé";
};

// Rafraîchir cache si expiré
private _now = time;
if ((_now - ATHENA_realismConfigCacheTime) > ATHENA_realismCacheTTL) then {
    diag_log format ["[ATHENA] getRealismParam: cache expiré (age: %1s), rafraîchissement...", (_now - ATHENA_realismConfigCacheTime)];
    
    // Appel DLL COMSPEC pour récupérer config
    private _response = "COMSPECExtension" callExtension ["GetRealismConfig", []];
    
    // _response format: [result, code]
    // result = JSON string si succès, message erreur sinon
    // code = 0 si succès, 1 si erreur
    
    private _result = _response select 0;
    private _code = _response select 1;
    
    if (_code isEqualTo 0) then {
        // Parsing JSON → HashMap
        try {
            private _configJson = parseSimpleArray _result;
            
            // Convertir JSON en HashMap pour accès rapide
            // Structure: { "radio_relays": { "relay_range_m": 2000, ... }, ... }
            ATHENA_realismConfigCache = createHashMapFromArray _configJson;
            ATHENA_realismConfigCacheTime = _now;
            
            diag_log format ["[ATHENA] getRealismParam: cache rafraîchi, %1 domaines chargés", count ATHENA_realismConfigCache];
        } catch {
            diag_log format ["[ATHENA] getRealismParam: ERREUR parsing JSON: %1", _exception];
            // Garder ancien cache en cas d'erreur parsing
        };
    } else {
        diag_log format ["[ATHENA] getRealismParam: ERREUR DLL (code %1): %2", _code, _result];
        // Garder ancien cache si erreur DLL
    };
};

// Lecture dans cache
private _domainCache = ATHENA_realismConfigCache getOrDefault [_domain, createHashMap];

if (_domainCache isEqualTo createHashMap) then {
    diag_log format ["[ATHENA] getRealismParam: domaine '%1' introuvable, retour default: %2", _domain, _defaultValue];
    _defaultValue
} else {
    private _value = _domainCache getOrDefault [_paramKey, _defaultValue];
    
    if (isNil "_value" || {_value isEqualTo _defaultValue}) then {
        diag_log format ["[ATHENA] getRealismParam: paramètre '%1.%2' introuvable, retour default: %3", _domain, _paramKey, _defaultValue];
    };
    
    _value
};
