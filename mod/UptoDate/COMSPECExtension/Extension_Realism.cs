using System;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

public partial class Extension
{
    // Cache config réalisme (3 minutes TTL)
    private static string _realismConfigCache = "";
    private static long _realismConfigCacheTicks = 0;
    private static readonly long _realismConfigCacheTTL = TimeSpan.FromMinutes(3).Ticks;

    /// <summary>
    /// GetRealismConfig : Récupère config complète avec cache 3 min
    /// </summary>
    private static string GetRealismConfigSync(CancellationToken token)
    {
        try
        {
            var now = DateTime.UtcNow.Ticks;
            
            // Cache hit ?
            if (_realismConfigCache.Length > 0 && 
                (now - _realismConfigCacheTicks) < _realismConfigCacheTTL)
            {
                return "OK|" + _realismConfigCache;
            }
            
            // Cache miss : appel API
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/realism/config", out var uri, out var err) || uri is null)
                return "ERR|" + err;
            
            using var resp = SendGet(uri.ToString(), token);
            var body = ReadContentUtf8(resp, token);
            
            if (!resp.IsSuccessStatusCode)
            {
                var code = (int)resp.StatusCode;
                if (code == 401) return "ERR|unauthorized";
                if (code == 403) return "ERR|forbidden";
                if (code == 404) return "ERR|not_found";
                return "ERR|http_" + code;
            }
            
            // Parser JSON
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                return "ERR|api_error";
            
            if (!doc.RootElement.TryGetProperty("config", out var configProp))
                return "ERR|missing_config";
            
            var configJson = configProp.GetRawText();
            
            // Mettre en cache
            _realismConfigCache = configJson;
            _realismConfigCacheTicks = now;
            
            return "OK|" + configJson;
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException ex)
        {
            return "ERR|network_" + (ex.Message.Length > 50 ? ex.Message.Substring(0, 50) : ex.Message);
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// GetRealismParam : Lecture paramètre spécifique (domain, key)
    /// </summary>
    private static string GetRealismParamSync(string domain, string key, CancellationToken token)
    {
        try
        {
            // Récupérer config complète (avec cache)
            var configResult = GetRealismConfigSync(token);
            if (!configResult.StartsWith("OK|"))
                return configResult; // Propager l'erreur
            
            var configJson = configResult.Substring(3); // Skip "OK|"
            
            // Parser et extraire paramètre
            using var doc = JsonDocument.Parse(configJson);
            var root = doc.RootElement;
            
            if (!root.TryGetProperty(domain, out var domainProp))
                return "ERR|domain_not_found";
            
            if (!domainProp.TryGetProperty(key, out var keyProp))
                return "ERR|key_not_found";
            
            // Retourner valeur brute (nombre, bool, string, array, object)
            var valueJson = keyProp.GetRawText();
            return "OK|" + valueJson;
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// ApplyRealismProfile : Applique profil (beginner/event/expert)
    /// </summary>
    private static string ApplyRealismProfileSync(string profileKey, CancellationToken token)
    {
        try
        {
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/realism/apply-profile", out var uri, out var err) || uri is null)
                return "ERR|" + err;
            
            var payload = $"{{\"profile_key\":\"{EscapeJson(profileKey)}\"}}";
            using var resp = SendJsonPost(uri.ToString(), payload, token);
            var body = ReadContentUtf8(resp, token);
            
            if (!resp.IsSuccessStatusCode)
            {
                var code = (int)resp.StatusCode;
                if (code == 400) return "ERR|invalid_profile";
                if (code == 401) return "ERR|unauthorized";
                if (code == 403) return "ERR|forbidden";
                if (code == 404) return "ERR|not_found";
                return "ERR|http_" + code;
            }
            
            // Invalider cache
            _realismConfigCache = "";
            _realismConfigCacheTicks = 0;
            
            // Parser réponse
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                return "ERR|api_error";
            
            var profileLabel = doc.RootElement.TryGetProperty("profile_label", out var labelProp) 
                ? labelProp.GetString() ?? profileKey 
                : profileKey;
            
            return "OK|" + profileLabel;
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException ex)
        {
            return "ERR|network_" + (ex.Message.Length > 50 ? ex.Message.Substring(0, 50) : ex.Message);
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// CalculateWeatherEffects : Calcul effets météo sur portée relais
    /// </summary>
    private static string CalculateWeatherEffectsSync(double baseRange, double rain, double fog, double overcast, double windKmh)
    {
        try
        {
            // Facteurs d'atténuation (0.0-1.0, 1.0 = pas d'effet)
            double rainFactor = 1.0 - (rain * 0.15);        // Pluie max -15%
            double fogFactor = 1.0 - (fog * 0.25);          // Brouillard max -25%
            double overcastFactor = 1.0 - (overcast * 0.10); // Couvert max -10%
            double windFactor = Math.Max(0.85, 1.0 - (windKmh / 150.0 * 0.15)); // Vent fort max -15%
            
            // Facteur combiné
            double combinedFactor = rainFactor * fogFactor * overcastFactor * windFactor;
            combinedFactor = Math.Max(0.5, Math.Min(1.0, combinedFactor)); // Clamp 0.5-1.0
            
            // Portée effective
            double effectiveRange = baseRange * combinedFactor;
            effectiveRange = Math.Max(50, effectiveRange); // Minimum 50m
            
            // Facteur réduction débit (1.0 = plein débit, 0.5 = moitié)
            double throughputFactor = Math.Max(0.7, combinedFactor);
            
            // Retour : effectiveRange|throughputFactor|combinedFactor
            return $"OK|{effectiveRange:F0}|{throughputFactor:F2}|{combinedFactor:F2}";
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// SyncRelay : Synchronise relais avec API ATHENA
    /// </summary>
    private static string SyncRelaySync(string relayDataJson, CancellationToken token)
    {
        try
        {
            // Valider JSON
            using var testDoc = JsonDocument.Parse(relayDataJson);
            
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/relays/sync", out var uri, out var err) || uri is null)
                return "ERR|" + err;
            
            using var resp = SendJsonPost(uri.ToString(), relayDataJson, token);
            var body = ReadContentUtf8(resp, token);
            
            if (!resp.IsSuccessStatusCode)
            {
                var code = (int)resp.StatusCode;
                if (code == 400) return "ERR|invalid_data";
                if (code == 401) return "ERR|unauthorized";
                if (code == 403) return "ERR|forbidden";
                if (code == 404) return "ERR|not_found";
                return "ERR|http_" + code;
            }
            
            // Parser réponse
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                return "ERR|api_error";
            
            var relayId = doc.RootElement.TryGetProperty("relay_id", out var idProp) && idProp.ValueKind == JsonValueKind.Number
                ? idProp.GetInt32()
                : 0;
            
            var status = doc.RootElement.TryGetProperty("status", out var statusProp)
                ? statusProp.GetString() ?? "synced"
                : "synced";
            
            return $"OK|{relayId}|{status}";
        }
        catch (JsonException)
        {
            return "ERR|invalid_json";
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException ex)
        {
            return "ERR|network_" + (ex.Message.Length > 50 ? ex.Message.Substring(0, 50) : ex.Message);
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }
}
