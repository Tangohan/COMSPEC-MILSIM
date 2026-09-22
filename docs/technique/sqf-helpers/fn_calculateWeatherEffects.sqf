/*
 * ATHENA C2 - COMSPEC Extension
 * Fonction helper : calculateWeatherEffects
 * 
 * Calcule l'impact de la météo sur la portée des relais radio.
 * Utilise la config réalisme centralisée + météo actuelle du jeu.
 * 
 * Arguments:
 *   0: NUMBER - Portée nominale du relais (en mètres)
 * 
 * Returns:
 *   ARRAY - [portée effective, multiplicateur météo, multiplicateur vent, description]
 * 
 * Usage:
 *   _effectiveRange = [2000] call ATHENA_fnc_calculateWeatherEffects;
 *   // => [1700, 0.85, 1.0, "Pluie (85%)"]
 */

params [
    ["_baseRange", 2000, [0]]
];

// Récupérer config réalisme
private _config = call ATHENA_fnc_getRealismConfig;

// Si météo désactivée, retourner portée nominale
private _weatherEnabled = _config getVariable ["radio_relays.weather_effects_enabled", true];
if (!_weatherEnabled) exitWith {
    [_baseRange, 1.0, 1.0, "Effets météo désactivés"]
};

// Récupérer conditions météo actuelles
private _rain = rain;
private _fog = fog;
private _overcast = overcast;
private _wind = wind;
private _windSpeed = vectorMagnitude _wind;
private _windKmh = _windSpeed * 3.6; // m/s vers km/h

// Détecter orage (pluie + couverture élevée)
private _isStorm = (_rain > 0.5) && (_overcast > 0.7);

// Calculer multiplicateur météo
private _weatherMult = 1.0;

if (_isStorm) then {
    _weatherMult = _config getVariable ["radio_relays.storm_range_multiplier", 0.60];
} else {
    if (_rain > 0.3) then {
        _weatherMult = _config getVariable ["radio_relays.rain_range_multiplier", 0.85];
    };
    
    if (_fog > 0.5) then {
        private _fogMult = _config getVariable ["radio_relays.fog_range_multiplier", 0.70];
        _weatherMult = _weatherMult min _fogMult;
    };
};

// Calculer multiplicateur vent
private _windThreshold = _config getVariable ["radio_relays.wind_threshold_kmh", 50];
private _windPenaltyPer10 = _config getVariable ["radio_relays.wind_range_penalty_per_10kmh", 0.05];

private _windMult = 1.0;
if (_windKmh > _windThreshold) then {
    private _windOver = _windKmh - _windThreshold;
    private _penalties = floor(_windOver / 10);
    _windMult = 1.0 - (_penalties * _windPenaltyPer10);
    _windMult = _windMult max 0.5; // Cap à -50%
};

// Portée effective
private _totalMult = _weatherMult * _windMult;
private _effectiveRange = _baseRange * _totalMult;

// Description textuelle
private _description = "";
if (_isStorm) then {
    _description = format["Orage (%1%%)", round(_weatherMult * 100)];
} else {
    if (_rain > 0.3) then {
        _description = format["Pluie (%1%%)", round(_weatherMult * 100)];
    } else {
        if (_fog > 0.5) then {
            _description = format["Brouillard (%1%%)", round(_weatherMult * 100)];
        } else {
            _description = "Temps clair";
        };
    };
};

if (_windMult < 1.0) then {
    _description = format["%1 × Vent %2 km/h (%3%%)", _description, round(_windKmh), round(_windMult * 100)];
};

// Retour
[
    round(_effectiveRange),
    _weatherMult,
    _windMult,
    _description
]
