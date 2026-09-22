/*
    Thread de mise à jour des effets météo sur les relais.
    
    Appelé depuis fn_placeRealismRelay ou fn_placeAtakRelay pour un relais donné.
    Boucle tant que le relais est actif et met à jour portée effective + débit.
    
    Params: [_relay]
    
    Version initiale : utilise config réalisme centralisée + Extension C# CalculateWeatherEffects
*/

params [["_relay", objNull, [objNull]]];

if (isNull _relay) exitWith {
    diag_log "[ATHENA Weather] ERR: Relais null fourni à fn_updateRelayWeatherEffects";
    false
};

// Vérifier si météo activée dans config
private _weatherEnabled = false;
if (!isNil "ATHENA_fnc_getRealismParam") then {
    _weatherEnabled = ["radio_relays", "weather_effects_enabled", false] call ATHENA_fnc_getRealismParam;
};

if (!_weatherEnabled) exitWith {
    diag_log format ["[ATHENA Weather] Weather effects désactivés pour relais %1", _relay getVariable ["COMSPEC_AtakRelayName", "Unknown"]];
    false
};

// Lancer thread mise à jour
[_relay] spawn {
    params ["_relay"];
    
    private _relayName = _relay getVariable ["COMSPEC_AtakRelayName", "Relais"];
    private _relayUid = _relay getVariable ["COMSPEC_AtakRelayUid", netId _relay];
    
    diag_log format ["[ATHENA Weather] Thread météo démarré pour %1 (UID: %2)", _relayName, _relayUid];
    
    // Boucle tant que relais actif
    while {alive _relay && {!isNull _relay}} do {
        // Récupérer météo actuelle
        private _rain = rain;
        private _fog = fog;
        private _overcast = overcast;
        private _wind = wind;
        private _windSpeed = (_wind select 0) + (_wind select 1); // vectorMagnitude approximé
        private _windKmh = _windSpeed * 3.6; // m/s → km/h
        
        // Récupérer portée de base du relais
        private _baseRange = _relay getVariable ["ATHENA_relayRange", 2000];
        if (!(_baseRange isEqualType 0)) then {
            _baseRange = _relay getVariable ["COMSPEC_AtakRelayRange", 2000];
            if (!(_baseRange isEqualType 0)) then { _baseRange = 2000; };
            // Stocker dans variable dédiée météo
            _relay setVariable ["ATHENA_relayRange", _baseRange, true];
        };
        
        // Appeler Extension C# pour calcul effets
        private _response = "COMSPECExtension" callExtension [
            "CalculateWeatherEffects",
            [str _baseRange, str _rain, str _fog, str _overcast, str _windKmh]
        ];
        
        private _result = _response select 0;
        private _code = _response select 1;
        
        if (_code isEqualTo 0 && {(_result select [0, 3]) isEqualTo "OK|"}) then {
            // Parser résultat : OK|effectiveRange|throughputFactor|combinedFactor
            private _data = (_result select [3]) splitString "|";
            
            if (count _data >= 3) then {
                private _effectiveRange = parseNumber (_data select 0);
                private _throughputFactor = parseNumber (_data select 1);
                private _combinedFactor = parseNumber (_data select 2);
                
                // Stocker portée effective et facteur débit
                _relay setVariable ["ATHENA_relayEffectiveRange", _effectiveRange, true];
                _relay setVariable ["ATHENA_relayThroughputFactor", _throughputFactor, true];
                _relay setVariable ["ATHENA_relayWeatherFactor", _combinedFactor, true];
                
                // Log si changement significatif (> 10%)
                private _reduction = (1 - _combinedFactor) * 100;
                if (_reduction >= 10) then {
                    diag_log format [
                        "[ATHENA Weather] %1: Portée réduite de %2%% par météo (base: %3m → effectif: %4m) | pluie:%5 fog:%6 couvert:%7 vent:%8km/h",
                        _relayName,
                        round _reduction,
                        round _baseRange,
                        round _effectiveRange,
                        (_rain toFixed 2),
                        (_fog toFixed 2),
                        (_overcast toFixed 2),
                        round _windKmh
                    ];
                };
            } else {
                diag_log format ["[ATHENA Weather] WARN: Format réponse invalide pour %1: %2", _relayName, _result];
            };
        } else {
            // Erreur Extension C# - log mais pas de spam
            if ((time mod 300) < 60) then { // Log 1x toutes les 5 minutes
                diag_log format ["[ATHENA Weather] ERR: Extension C# erreur pour %1: %2", _relayName, _result];
            };
            
            // Fallback : portée nominale
            _relay setVariable ["ATHENA_relayEffectiveRange", _baseRange, true];
            _relay setVariable ["ATHENA_relayThroughputFactor", 1.0, true];
            _relay setVariable ["ATHENA_relayWeatherFactor", 1.0, true];
        };
        
        // Attendre 60 secondes avant prochaine mise à jour
        sleep 60;
    };
    
    diag_log format ["[ATHENA Weather] Thread météo arrêté pour %1 (relais détruit/supprimé)", _relayName];
};

true
