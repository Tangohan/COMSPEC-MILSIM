using System;
using System.Collections.Concurrent;
using System.Net.Http;

namespace COMSPECExtension;

/// <summary>
/// Refus d'Athena qui ne changeront pas tout seuls (401/403 avec un code « règle ») :
/// on arrête d'envoyer au lieu de redemander toutes les 4 à 8 s.
///  - Refus du compte ou de la clé (clé refusée après client-init, Steam non lié, compte désactivé,
///    Steam / réseau bloqué, compte bloqué, communauté inconnue) : plus aucune requête authentifiée
///    jusqu'à une nouvelle liaison, une session rétablie (READY) ou un redémarrage d'Arma.
///  - Fonction absente de l'offre de la communauté (feature_unavailable) : cette adresse seule se tait
///    30 min, puis un seul essai (l'offre a pu changer sur le site).
/// </summary>
public static partial class Extension
{
    private static readonly string[] PermanentRefusalCodes =
    {
        "unauthorized", "tenant_context_required", "tenant_required", "steam_not_linked",
        "account_disabled", "steam_required", "atak_blocked", "mod_steam_blocked", "mod_ip_blocked",
    };

    private const long FeatureLatchMs = 30L * 60 * 1000;

    private static volatile string _authLatchCode = "";

    private static readonly ConcurrentDictionary<string, long> FeatureLatchUntilMs = new();

    /// <summary>Compte ou clé refusés pour de bon : plus de sondage ni d'envoi authentifié.</summary>
    private static bool IsAuthLatched()
        => _authLatchCode.Length > 0
            || string.Equals(_gameAuthState, "C2_UNAUTHORIZED", StringComparison.Ordinal);

    private static string RefusalEndpointKey(string url)
    {
        try { return new Uri(url).AbsolutePath.ToLowerInvariant(); }
        catch { return (url ?? "").ToLowerInvariant(); }
    }

    private static bool IsFeatureLatched(string url)
    {
        if (FeatureLatchUntilMs.IsEmpty || string.IsNullOrEmpty(url)) return false;
        var key = RefusalEndpointKey(url);
        if (!FeatureLatchUntilMs.TryGetValue(key, out var until)) return false;
        if (Environment.TickCount64 < until) return true;
        FeatureLatchUntilMs.TryRemove(key, out _);
        return false;
    }

    private static bool IsRefusalLatched(string url) => IsAuthLatched() || IsFeatureLatched(url);

    /// <summary>Champ « error » du JSON de refus ({"error":"code",…}), en minuscules ; vide sinon.</summary>
    private static string RefusalErrorCode(string body)
    {
        if (string.IsNullOrEmpty(body)) return "";
        var i = body.IndexOf("\"error\"", StringComparison.Ordinal);
        if (i < 0) return "";
        i = body.IndexOf(':', i);
        if (i < 0) return "";
        i = body.IndexOf('"', i);
        if (i < 0) return "";
        var j = body.IndexOf('"', i + 1);
        if (j < 0 || j - i > 64) return "";
        return body.Substring(i + 1, j - i - 1).Trim().ToLowerInvariant();
    }

    private static string RefusalBody(HttpResponseMessage? response)
    {
        // Contenu déjà mis en mémoire par SendAsync (ResponseContentRead) : relecture possible.
        try { return response?.Content.ReadAsStringAsync().GetAwaiter().GetResult() ?? ""; }
        catch { return ""; }
    }

    /// <summary>
    /// À appeler sur chaque 401/403 d'Athena. 403 « règle » : verrou immédiat ; 401 : client-init une fois
    /// (une session expirée se rétablit), verrou seulement si Athena le refuse aussi.
    /// </summary>
    private static void NoteRefusal(string url, int status, string body)
    {
        if (status != 401 && status != 403) return;
        var code = RefusalErrorCode(body);
        if (status == 403 && code == "feature_unavailable")
        {
            FeatureLatchUntilMs[RefusalEndpointKey(url)] = Environment.TickCount64 + FeatureLatchMs;
            return;
        }
        if (status == 403 && Array.IndexOf(PermanentRefusalCodes, code) >= 0)
        {
            LatchAuthRefusal(code, "ERR|" + code);
            return;
        }
        if (status == 401)
            MaybeReauthAfter401(url, force: true);
    }

    private static void LatchAuthRefusal(string code, string detail)
    {
        if (string.Equals(_authLatchCode, code, StringComparison.Ordinal)) return;
        _authLatchCode = code;
        SetGameAuth("C2_UNAUTHORIZED", _gameAuthProgress, "C2_UNAUTHORIZED");
        // SQF : canal fermé sans réouverture automatique pour un refus définitif (fn_extensionCallback).
        InvokeCallback("AuthInvalidated", detail);
    }

    /// <summary>Session rétablie (READY après liaison, nouvelle clé ou client-init accepté) : on repart de zéro.</summary>
    private static void ClearRefusalLatches()
    {
        _authLatchCode = "";
        FeatureLatchUntilMs.Clear();
    }
}
