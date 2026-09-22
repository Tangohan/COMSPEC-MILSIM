/*
 * ATHENA C2 - COMSPEC Extension
 * Ajouts pour config réalisme centralisée - Phase 3
 * 
 * Ce fichier documente les nouvelles méthodes à ajouter à Extension.cs
 * pour supporter la configuration réalisme centralisée.
 */

using System;
using System.Net.Http;
using System.Text.Json;
using System.Threading.Tasks;

namespace COMSPEC
{
    public partial class Extension
    {
        // Cache de la config réalisme
        private static string _realismConfigCache = null;
        private static DateTime _realismConfigCacheTime = DateTime.MinValue;
        private static readonly TimeSpan _realismConfigCacheTTL = TimeSpan.FromMinutes(3);

        /// <summary>
        /// Récupère la configuration réalisme centralisée depuis l'API ATHENA.
        /// Utilise un cache côté client pour éviter les appels répétés.
        /// 
        /// Appelée depuis SQF : "COMSPECExtension" callExtension ["GetRealismConfig", []];
        /// </summary>
        /// <returns>JSON de la config complète</returns>
        public async Task<string> GetRealismConfig()
        {
            try
            {
                // Vérifier cache
                if (_realismConfigCache != null && 
                    (DateTime.UtcNow - _realismConfigCacheTime) < _realismConfigCacheTTL)
                {
                    LogInfo("[GetRealismConfig] Returning from cache");
                    return _realismConfigCache;
                }

                // Construire URL API
                string baseUrl = GetApiBaseUrl(); // Méthode existante
                string endpoint = $"{baseUrl}/api/atak/realism/config";

                LogInfo($"[GetRealismConfig] Fetching from {endpoint}");

                // Appel HTTP
                using (HttpClient client = GetAuthenticatedHttpClient())
                {
                    HttpResponseMessage response = await client.GetAsync(endpoint);
                    
                    if (!response.IsSuccessStatusCode)
                    {
                        LogError($"[GetRealismConfig] HTTP {response.StatusCode}");
                        return _realismConfigCache ?? "{}"; // Fallback sur cache périmé
                    }

                    string jsonResponse = await response.Content.ReadAsStringAsync();
                    
                    // Parser la réponse
                    using (JsonDocument doc = JsonDocument.Parse(jsonResponse))
                    {
                        if (doc.RootElement.TryGetProperty("ok", out JsonElement okProp) && 
                            okProp.GetBoolean() &&
                            doc.RootElement.TryGetProperty("config", out JsonElement configProp))
                        {
                            string configJson = configProp.GetRawText();
                            
                            // Mettre à jour cache
                            _realismConfigCache = configJson;
                            _realismConfigCacheTime = DateTime.UtcNow;
                            
                            LogInfo("[GetRealismConfig] Config fetched and cached");
                            return configJson;
                        }
                    }

                    LogError("[GetRealismConfig] Invalid response format");
                    return _realismConfigCache ?? "{}";
                }
            }
            catch (Exception ex)
            {
                LogError($"[GetRealismConfig] Exception: {ex.Message}");
                return _realismConfigCache ?? "{}"; // Fallback sur cache périmé
            }
        }

        /// <summary>
        /// Calcule la portée effective d'un relais selon les conditions météo.
        /// Utilise la config centralisée + paramètres météo du jeu.
        /// 
        /// Appelée depuis SQF : "COMSPECExtension" callExtension ["CalculateWeatherEffects", [_baseRange, _rain, _fog, _overcast, _windKmh]];
        /// </summary>
        /// <param name="baseRange">Portée nominale (m)</param>
        /// <param name="rain">Intensité pluie (0-1)</param>
        /// <param name="fog">Intensité brouillard (0-1)</param>
        /// <param name="overcast">Couverture nuageuse (0-1)</param>
        /// <param name="windKmh">Vitesse vent (km/h)</param>
        /// <returns>JSON: {effectiveRange, weatherMult, windMult, description}</returns>
        public async Task<string> CalculateWeatherEffects(
            float baseRange, 
            float rain, 
            float fog, 
            float overcast, 
            float windKmh)
        {
            try
            {
                // Récupérer config
                string configJson = await GetRealismConfig();
                using (JsonDocument doc = JsonDocument.Parse(configJson))
                {
                    if (!doc.RootElement.TryGetProperty("radio_relays", out JsonElement radioConfig))
                    {
                        LogError("[CalculateWeatherEffects] radio_relays not found in config");
                        return JsonSerializer.Serialize(new {
                            effectiveRange = baseRange,
                            weatherMult = 1.0f,
                            windMult = 1.0f,
                            description = "Config error"
                        });
                    }

                    // Vérifier si météo activée
                    bool weatherEnabled = radioConfig.TryGetProperty("weather_effects_enabled", out JsonElement enabledProp) 
                        && enabledProp.GetBoolean();

                    if (!weatherEnabled)
                    {
                        return JsonSerializer.Serialize(new {
                            effectiveRange = baseRange,
                            weatherMult = 1.0f,
                            windMult = 1.0f,
                            description = "Effets météo désactivés"
                        });
                    }

                    // Calculer multiplicateur météo
                    float weatherMult = CalculateWeatherMultiplier(radioConfig, rain, fog, overcast);

                    // Calculer multiplicateur vent
                    float windThreshold = radioConfig.TryGetProperty("wind_threshold_kmh", out JsonElement threshProp) 
                        ? threshProp.GetSingle() : 50f;
                    float windPenalty = radioConfig.TryGetProperty("wind_range_penalty_per_10kmh", out JsonElement penaltyProp) 
                        ? penaltyProp.GetSingle() : 0.05f;

                    float windMult = 1.0f;
                    if (windKmh > windThreshold)
                    {
                        float windOver = windKmh - windThreshold;
                        int penalties = (int)Math.Floor(windOver / 10f);
                        windMult = 1.0f - (penalties * windPenalty);
                        windMult = Math.Max(0.5f, windMult);
                    }

                    // Portée effective
                    float totalMult = weatherMult * windMult;
                    float effectiveRange = baseRange * totalMult;

                    // Description
                    string description = GetWeatherDescription(rain, fog, overcast, windKmh, weatherMult, windMult);

                    return JsonSerializer.Serialize(new {
                        effectiveRange = Math.Round(effectiveRange),
                        weatherMult = weatherMult,
                        windMult = windMult,
                        description = description
                    });
                }
            }
            catch (Exception ex)
            {
                LogError($"[CalculateWeatherEffects] Exception: {ex.Message}");
                return JsonSerializer.Serialize(new {
                    effectiveRange = baseRange,
                    weatherMult = 1.0f,
                    windMult = 1.0f,
                    description = "Error"
                });
            }
        }

        private float CalculateWeatherMultiplier(JsonElement radioConfig, float rain, float fog, float overcast)
        {
            bool isStorm = (rain > 0.5f) && (overcast > 0.7f);

            if (isStorm)
            {
                return radioConfig.TryGetProperty("storm_range_multiplier", out JsonElement stormProp) 
                    ? stormProp.GetSingle() : 0.60f;
            }

            float mult = 1.0f;

            if (rain > 0.3f)
            {
                mult = radioConfig.TryGetProperty("rain_range_multiplier", out JsonElement rainProp) 
                    ? rainProp.GetSingle() : 0.85f;
            }

            if (fog > 0.5f)
            {
                float fogMult = radioConfig.TryGetProperty("fog_range_multiplier", out JsonElement fogProp) 
                    ? fogProp.GetSingle() : 0.70f;
                mult = Math.Min(mult, fogMult);
            }

            return mult;
        }

        private string GetWeatherDescription(float rain, float fog, float overcast, float windKmh, float weatherMult, float windMult)
        {
            bool isStorm = (rain > 0.5f) && (overcast > 0.7f);

            string description;
            if (isStorm)
            {
                description = $"Orage ({Math.Round(weatherMult * 100)}%)";
            }
            else if (rain > 0.3f)
            {
                description = $"Pluie ({Math.Round(weatherMult * 100)}%)";
            }
            else if (fog > 0.5f)
            {
                description = $"Brouillard ({Math.Round(weatherMult * 100)}%)";
            }
            else
            {
                description = "Temps clair";
            }

            if (windMult < 1.0f)
            {
                description += $" × Vent {Math.Round(windKmh)} km/h ({Math.Round(windMult * 100)}%)";
            }

            return description;
        }
    }
}
