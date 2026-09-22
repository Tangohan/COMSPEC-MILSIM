/**
 * COMSPEC Extension — Intégration configuration réalisme centralisée
 * 
 * Fichier : Extension_RealismConfigMethods.cs
 * 
 * À intégrer dans Extension.cs (classe Extension partielle ou méthodes directes)
 * 
 * Nouvelles méthodes publiques exposées au mod Arma 3 via callExtension :
 * - GetRealismConfig() : Récupère config complète avec cache
 * - GetRealismParam(domain, key) : Récupère un paramètre spécifique
 * - ApplyRealismProfile(profileKey) : Applique un profil (beginner/event/expert)
 * - CalculateWeatherEffects(baseRange, rain, fog, overcast, windKmh) : Calcul effets météo
 * - SyncRelay(relayData) : Synchronise relais avec API ATHENA
 */

using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace COMSPECExtension
{
    public partial class Extension
    {
        // Cache de la configuration réalisme
        private static string _realismConfigCache = null;
        private static DateTime _realismConfigCacheTime = DateTime.MinValue;
        private static readonly TimeSpan _realismConfigCacheTTL = TimeSpan.FromMinutes(3);
        
        /// <summary>
        /// Récupère la configuration réalisme complète depuis API ATHENA.
        /// Utilise un cache de 3 minutes pour éviter appels répétés.
        /// 
        /// Appelé depuis SQF :
        /// private _response = "COMSPECExtension" callExtension ["GetRealismConfig", []];
        /// 
        /// Retour : JSON string avec structure complète config
        /// </summary>
        public async Task<string> GetRealismConfig()
        {
            try
            {
                // Vérifier cache
                if (_realismConfigCache != null && 
                    (DateTime.UtcNow - _realismConfigCacheTime) < _realismConfigCacheTTL)
                {
                    LogInfo($"[GetRealismConfig] Cache hit (age: {(DateTime.UtcNow - _realismConfigCacheTime).TotalSeconds:F1}s)");
                    return _realismConfigCache;
                }
                
                LogInfo("[GetRealismConfig] Cache miss, fetching from API...");
                
                // Construire URL API
                string apiUrl = GetApiUrl(); // Méthode existante
                string endpoint = $"{apiUrl}/api/atak/realism/config";
                
                // Appel HTTP
                using (var client = new HttpClient())
                {
                    client.Timeout = TimeSpan.FromSeconds(10);
                    
                    // Headers auth si nécessaire
                    string apiKey = GetApiKey(); // Méthode existante
                    if (!string.IsNullOrEmpty(apiKey))
                    {
                        client.DefaultRequestHeaders.Add("X-API-Key", apiKey);
                    }
                    
                    var response = await client.GetAsync(endpoint);
                    
                    if (!response.IsSuccessStatusCode)
                    {
                        LogError($"[GetRealismConfig] API error: {response.StatusCode}");
                        return JsonSerializer.Serialize(new { error = $"API error: {response.StatusCode}" });
                    }
                    
                    string json = await response.Content.ReadAsStringAsync();
                    
                    // Parser pour vérifier validité
                    var doc = JsonDocument.Parse(json);
                    var root = doc.RootElement;
                    
                    if (!root.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                    {
                        LogError("[GetRealismConfig] API returned ok=false");
                        return JsonSerializer.Serialize(new { error = "API returned ok=false" });
                    }
                    
                    if (!root.TryGetProperty("config", out var configProp))
                    {
                        LogError("[GetRealismConfig] API response missing 'config' property");
                        return JsonSerializer.Serialize(new { error = "Missing config property" });
                    }
                    
                    // Extraire config
                    string configJson = configProp.GetRawText();
                    
                    // Mettre en cache
                    _realismConfigCache = configJson;
                    _realismConfigCacheTime = DateTime.UtcNow;
                    
                    LogInfo($"[GetRealismConfig] Success, cached {configJson.Length} bytes");
                    
                    return configJson;
                }
            }
            catch (Exception ex)
            {
                LogError($"[GetRealismConfig] Exception: {ex.Message}");
                return JsonSerializer.Serialize(new { error = ex.Message });
            }
        }
        
        /// <summary>
        /// Récupère un paramètre spécifique de la config réalisme.
        /// 
        /// Appelé depuis SQF :
        /// private _response = "COMSPECExtension" callExtension ["GetRealismParam", ["radio_relays", "relay_range_m"]];
        /// 
        /// Arguments : [domain, key]
        /// Retour : valeur du paramètre (JSON)
        /// </summary>
        public async Task<string> GetRealismParam(string domain, string key)
        {
            try
            {
                // Récupérer config complète (depuis cache si dispo)
                string configJson = await GetRealismConfig();
                
                var doc = JsonDocument.Parse(configJson);
                var root = doc.RootElement;
                
                // Vérifier erreur
                if (root.TryGetProperty("error", out _))
                {
                    return configJson; // Retourner erreur directement
                }
                
                // Naviguer vers domain.key
                if (!root.TryGetProperty(domain, out var domainProp))
                {
                    LogWarning($"[GetRealismParam] Domain not found: {domain}");
                    return JsonSerializer.Serialize(new { error = $"Domain not found: {domain}" });
                }
                
                if (!domainProp.TryGetProperty(key, out var valueProp))
                {
                    LogWarning($"[GetRealismParam] Key not found: {domain}.{key}");
                    return JsonSerializer.Serialize(new { error = $"Key not found: {domain}.{key}" });
                }
                
                // Retourner valeur
                string valueJson = valueProp.GetRawText();
                
                LogInfo($"[GetRealismParam] {domain}.{key} = {valueJson}");
                
                return valueJson;
            }
            catch (Exception ex)
            {
                LogError($"[GetRealismParam] Exception: {ex.Message}");
                return JsonSerializer.Serialize(new { error = ex.Message });
            }
        }
        
        /// <summary>
        /// Applique un profil réalisme (beginner/event/expert).
        /// 
        /// Appelé depuis SQF (serveur uniquement) :
        /// private _response = "COMSPECExtension" callExtension ["ApplyRealismProfile", ["expert"]];
        /// 
        /// Argument : profileKey (string)
        /// Retour : { ok: true/false, message: string }
        /// </summary>
        public async Task<string> ApplyRealismProfile(string profileKey)
        {
            try
            {
                LogInfo($"[ApplyRealismProfile] Applying profile: {profileKey}");
                
                // Construire URL API
                string apiUrl = GetApiUrl();
                string endpoint = $"{apiUrl}/api/atak/realism/apply-profile";
                
                // Payload
                var payload = new
                {
                    profile = profileKey
                };
                
                string payloadJson = JsonSerializer.Serialize(payload);
                
                // Appel HTTP POST
                using (var client = new HttpClient())
                {
                    client.Timeout = TimeSpan.FromSeconds(10);
                    
                    string apiKey = GetApiKey();
                    if (!string.IsNullOrEmpty(apiKey))
                    {
                        client.DefaultRequestHeaders.Add("X-API-Key", apiKey);
                    }
                    
                    var content = new StringContent(payloadJson, Encoding.UTF8, "application/json");
                    var response = await client.PostAsync(endpoint, content);
                    
                    string responseJson = await response.Content.ReadAsStringAsync();
                    
                    if (!response.IsSuccessStatusCode)
                    {
                        LogError($"[ApplyRealismProfile] API error: {response.StatusCode}");
                        return JsonSerializer.Serialize(new { ok = false, error = $"API error: {response.StatusCode}" });
                    }
                    
                    // Invalider cache pour forcer rechargement
                    _realismConfigCache = null;
                    _realismConfigCacheTime = DateTime.MinValue;
                    
                    LogInfo($"[ApplyRealismProfile] Success, cache invalidated");
                    
                    return responseJson;
                }
            }
            catch (Exception ex)
            {
                LogError($"[ApplyRealismProfile] Exception: {ex.Message}");
                return JsonSerializer.Serialize(new { ok = false, error = ex.Message });
            }
        }
        
        /// <summary>
        /// Calcule les effets météo sur portée relais.
        /// 
        /// Appelé depuis SQF :
        /// private _response = "COMSPECExtension" callExtension ["CalculateWeatherEffects", [2000, 0.3, 0.1, 0.4, 60]];
        /// 
        /// Arguments : [baseRange, rain, fog, overcast, windKmh]
        /// Retour : { effectiveRange, weatherMultiplier, windMultiplier, description }
        /// </summary>
        public async Task<string> CalculateWeatherEffects(
            float baseRange, 
            float rain, 
            float fog, 
            float overcast, 
            float windKmh)
        {
            try
            {
                // Récupérer config radio depuis cache
                string configJson = await GetRealismConfig();
                var doc = JsonDocument.Parse(configJson);
                var root = doc.RootElement;
                
                // Vérifier erreur
                if (root.TryGetProperty("error", out _))
                {
                    // Pas de config, utiliser valeurs par défaut
                    LogWarning("[CalculateWeatherEffects] No config available, using defaults");
                    return CalculateWeatherEffectsDefault(baseRange, rain, fog, overcast, windKmh);
                }
                
                // Extraire domaine radio_relays
                if (!root.TryGetProperty("radio_relays", out var radioConfig))
                {
                    LogWarning("[CalculateWeatherEffects] radio_relays config missing");
                    return CalculateWeatherEffectsDefault(baseRange, rain, fog, overcast, windKmh);
                }
                
                // Vérifier si météo activée
                bool weatherEnabled = true;
                if (radioConfig.TryGetProperty("weather_effects_enabled", out var enabledProp))
                {
                    weatherEnabled = enabledProp.GetBoolean();
                }
                
                if (!weatherEnabled)
                {
                    // Météo désactivée, retourner range nominale
                    return JsonSerializer.Serialize(new
                    {
                        effectiveRange = baseRange,
                        weatherMultiplier = 1.0f,
                        windMultiplier = 1.0f,
                        description = "Météo désactivée"
                    });
                }
                
                // Calculer multiplicateur météo
                float weatherMult = CalculateWeatherMultiplier(radioConfig, rain, fog, overcast);
                
                // Calculer multiplicateur vent
                float windMult = CalculateWindMultiplier(radioConfig, windKmh);
                
                // Portée effective
                float effectiveRange = baseRange * weatherMult * windMult;
                
                // Description
                string description = GetWeatherDescription(rain, fog, overcast, windKmh, weatherMult, windMult);
                
                LogInfo($"[CalculateWeatherEffects] {baseRange}m → {effectiveRange:F0}m (weather:{weatherMult:F2}, wind:{windMult:F2})");
                
                return JsonSerializer.Serialize(new
                {
                    effectiveRange,
                    weatherMultiplier = weatherMult,
                    windMultiplier = windMult,
                    description
                });
            }
            catch (Exception ex)
            {
                LogError($"[CalculateWeatherEffects] Exception: {ex.Message}");
                return CalculateWeatherEffectsDefault(baseRange, rain, fog, overcast, windKmh);
            }
        }
        
        private float CalculateWeatherMultiplier(JsonElement radioConfig, float rain, float fog, float overcast)
        {
            float mult = 1.0f;
            
            // Pluie
            if (rain > 0.7f) // Orage
            {
                float stormMult = GetFloatFromConfig(radioConfig, "storm_range_multiplier", 0.6f);
                mult *= stormMult;
            }
            else if (rain > 0.3f) // Pluie
            {
                float rainMult = GetFloatFromConfig(radioConfig, "rain_range_multiplier", 0.85f);
                mult *= rainMult;
            }
            
            // Brouillard
            if (fog > 0.3f)
            {
                float fogMult = GetFloatFromConfig(radioConfig, "fog_range_multiplier", 0.70f);
                mult *= fogMult;
            }
            
            return Math.Max(0.3f, mult); // Minimum 30%
        }
        
        private float CalculateWindMultiplier(JsonElement radioConfig, float windKmh)
        {
            float threshold = GetFloatFromConfig(radioConfig, "wind_threshold_kmh", 50f);
            float penaltyPer10 = GetFloatFromConfig(radioConfig, "wind_range_penalty_per_10kmh", 0.05f);
            
            if (windKmh <= threshold)
            {
                return 1.0f;
            }
            
            float excess = windKmh - threshold;
            float penalty = (excess / 10f) * penaltyPer10;
            
            return Math.Max(0.5f, 1.0f - penalty); // Minimum 50%
        }
        
        private float GetFloatFromConfig(JsonElement config, string key, float defaultValue)
        {
            if (config.TryGetProperty(key, out var prop))
            {
                if (prop.ValueKind == JsonValueKind.Number)
                {
                    return (float)prop.GetDouble();
                }
            }
            return defaultValue;
        }
        
        private string GetWeatherDescription(float rain, float fog, float overcast, float windKmh, float weatherMult, float windMult)
        {
            var parts = new List<string>();
            
            if (rain > 0.7f) parts.Add("Orage");
            else if (rain > 0.3f) parts.Add("Pluie");
            
            if (fog > 0.3f) parts.Add("Brouillard");
            
            if (windKmh > 80f) parts.Add($"Vent fort ({windKmh:F0} km/h)");
            else if (windKmh > 50f) parts.Add($"Vent modéré ({windKmh:F0} km/h)");
            
            if (parts.Count == 0) return "Conditions idéales";
            
            float totalMult = weatherMult * windMult;
            int reduction = (int)((1f - totalMult) * 100f);
            
            return $"{string.Join(", ", parts)} (-{reduction}%)";
        }
        
        private string CalculateWeatherEffectsDefault(float baseRange, float rain, float fog, float overcast, float windKmh)
        {
            // Valeurs par défaut hardcodées si config indisponible
            float weatherMult = 1.0f;
            
            if (rain > 0.7f) weatherMult *= 0.6f;
            else if (rain > 0.3f) weatherMult *= 0.85f;
            
            if (fog > 0.3f) weatherMult *= 0.7f;
            
            float windMult = 1.0f;
            if (windKmh > 50f)
            {
                windMult = Math.Max(0.5f, 1.0f - ((windKmh - 50f) / 10f) * 0.05f);
            }
            
            float effectiveRange = baseRange * weatherMult * windMult;
            
            return JsonSerializer.Serialize(new
            {
                effectiveRange,
                weatherMultiplier = weatherMult,
                windMultiplier = windMult,
                description = "Config indisponible (défauts)"
            });
        }
        
        /// <summary>
        /// Synchronise un relais avec l'API ATHENA.
        /// 
        /// Appelé depuis SQF lors de pose relais :
        /// private _response = "COMSPECExtension" callExtension ["SyncRelay", [_relayData]];
        /// 
        /// Argument : JSON relay data
        /// Retour : { ok: true/false, relayId: int }
        /// </summary>
        public async Task<string> SyncRelay(string relayDataJson)
        {
            try
            {
                LogInfo($"[SyncRelay] Syncing relay...");
                
                // Parser relay data
                var doc = JsonDocument.Parse(relayDataJson);
                var root = doc.RootElement;
                
                // Construire URL API
                string apiUrl = GetApiUrl();
                string endpoint = $"{apiUrl}/api/atak/relays/sync";
                
                // Appel HTTP POST
                using (var client = new HttpClient())
                {
                    client.Timeout = TimeSpan.FromSeconds(10);
                    
                    string apiKey = GetApiKey();
                    if (!string.IsNullOrEmpty(apiKey))
                    {
                        client.DefaultRequestHeaders.Add("X-API-Key", apiKey);
                    }
                    
                    var content = new StringContent(relayDataJson, Encoding.UTF8, "application/json");
                    var response = await client.PostAsync(endpoint, content);
                    
                    string responseJson = await response.Content.ReadAsStringAsync();
                    
                    if (!response.IsSuccessStatusCode)
                    {
                        LogError($"[SyncRelay] API error: {response.StatusCode}");
                        return JsonSerializer.Serialize(new { ok = false, error = $"API error: {response.StatusCode}" });
                    }
                    
                    LogInfo($"[SyncRelay] Success");
                    
                    return responseJson;
                }
            }
            catch (Exception ex)
            {
                LogError($"[SyncRelay] Exception: {ex.Message}");
                return JsonSerializer.Serialize(new { ok = false, error = ex.Message });
            }
        }
        
        // Méthodes helper (à adapter selon structure Extension existante)
        private void LogInfo(string message)
        {
            // Adapter selon système de log existant
            Console.WriteLine($"[INFO] {message}");
        }
        
        private void LogWarning(string message)
        {
            Console.WriteLine($"[WARN] {message}");
        }
        
        private void LogError(string message)
        {
            Console.Error.WriteLine($"[ERROR] {message}");
        }
        
        private string GetApiUrl()
        {
            // Retourner URL API depuis config Extension
            // À adapter selon structure existante
            return "https://athena.example.com"; // Placeholder
        }
        
        private string GetApiKey()
        {
            // Retourner clé API depuis config Extension
            // À adapter selon structure existante
            return ""; // Placeholder
        }
    }
}
