/*
 * ATHENA C2 - COMSPEC Extension
 * Fonction helper : getRealismConfig
 * 
 * Récupère la configuration réalisme centralisée depuis l'API ATHENA.
 * Cache local côté client pour éviter les appels répétés.
 * 
 * Usage:
 *   _config = call ATHENA_fnc_getRealismConfig;
 *   _relayRange = _config getVariable ["radio_relays.relay_range_m", 2000];
 *   _weatherEnabled = _config getVariable ["radio_relays.weather_effects_enabled", true];
 * 
 * Returns:
 *   locationNull - Config object (namespace) contenant tous les paramètres
 */

// Cache global (persiste pendant toute la session)
if (isNil "ATHENA_realismConfigCache") then {
    ATHENA_realismConfigCache = locationNull;
    ATHENA_realismConfigCacheTime = -999999;
};

// TTL cache : 3 minutes
private _cacheTTL = 180;
private _now = time;

// Retourner depuis cache si valide
if (!isNull ATHENA_realismConfigCache && (_now - ATHENA_realismConfigCacheTime < _cacheTTL)) then {
    ATHENA_realismConfigCache
} else {
    // Fetch depuis API via extension COMSPEC
    private _response = "COMSPECExtension" callExtension ["GetRealismConfig", []];
    private _statusCode = _response select 0;
    private _jsonStr = _response select 1;

    if (_statusCode == 200) then {
        // Parser JSON et créer namespace
        private _configJson = parseSimpleArray _jsonStr;
        
        if (!isNil "_configJson") then {
            private _configNamespace = locationNull;
            _configNamespace = createLocation ["VegetationBroadleaf", [0,0,0], 0, 0];
            
            // Flatten la structure JSON en variables namespace
            // Domaine : radio_relays
            {
                private _key = format["radio_relays.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "radio_relays") get _x];
            } forEach (keys (_configJson get "radio_relays"));
            
            // Domaine : zones_roleplay
            {
                private _key = format["zones_roleplay.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "zones_roleplay") get _x];
            } forEach (keys (_configJson get "zones_roleplay"));
            
            // Domaine : network_simulation
            {
                private _key = format["network_simulation.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "network_simulation") get _x];
            } forEach (keys (_configJson get "network_simulation"));
            
            // Domaine : certificates
            {
                private _key = format["certificates.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "certificates") get _x];
            } forEach (keys (_configJson get "certificates"));
            
            // Domaine : terminal_damage
            {
                private _key = format["terminal_damage.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "terminal_damage") get _x];
            } forEach (keys (_configJson get "terminal_damage"));
            
            // Domaine : waypoints
            {
                private _key = format["waypoints.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "waypoints") get _x];
            } forEach (keys (_configJson get "waypoints"));
            
            // Domaine : symbology_map
            {
                private _key = format["symbology_map.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "symbology_map") get _x];
            } forEach (keys (_configJson get "symbology_map"));
            
            // Domaine : control_measures
            {
                private _key = format["control_measures.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "control_measures") get _x];
            } forEach (keys (_configJson get "control_measures"));
            
            // Domaine : coverage_viewshed
            {
                private _key = format["coverage_viewshed.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "coverage_viewshed") get _x];
            } forEach (keys (_configJson get "coverage_viewshed"));
            
            // Domaine : experience_ambiance
            {
                private _key = format["experience_ambiance.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "experience_ambiance") get _x];
            } forEach (keys (_configJson get "experience_ambiance"));
            
            // Domaine : other_settings
            {
                private _key = format["other_settings.%1", _x];
                _configNamespace setVariable [_key, (_configJson get "other_settings") get _x];
            } forEach (keys (_configJson get "other_settings"));
            
            // Mettre à jour cache
            ATHENA_realismConfigCache = _configNamespace;
            ATHENA_realismConfigCacheTime = _now;
            
            diag_log format["[ATHENA] Realism config loaded: %1 domains", count (keys _configJson)];
            
            _configNamespace
        } else {
            diag_log "[ATHENA] ERROR: Failed to parse realism config JSON";
            ATHENA_realismConfigCache
        };
    } else {
        diag_log format["[ATHENA] ERROR: GetRealismConfig returned status %1", _statusCode];
        ATHENA_realismConfigCache
    };
};
