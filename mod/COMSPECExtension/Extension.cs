using System.Collections.Concurrent;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

public static class Extension
{
    // Timeout HttpClient = plafond global ; les appels sync utilisent aussi un CTS dédié.
    // 3 s était trop juste pour TLS+DNS sur le premier appel (redeem / whoami).
    private const int SyncTimeoutSeconds = 8;
    private static string _baseUrl = "";
    private static string _apiKey = "";
    private static readonly HttpClient HttpClient = new() { Timeout = TimeSpan.FromSeconds(SyncTimeoutSeconds) };
    private static readonly ConcurrentQueue<(string Url, string Body)> PendingPosts = new();
    private static readonly int MaxQueueSize = 500;
    private static readonly object QueueDrainLock = new();
    private static System.Threading.Timer? _drainTimer;

    [UnmanagedFunctionPointer(CallingConvention.StdCall)]
    private delegate int ExtensionCallback([MarshalAs(UnmanagedType.LPStr)] string name, [MarshalAs(UnmanagedType.LPStr)] string function, [MarshalAs(UnmanagedType.LPStr)] string data);

    private static ExtensionCallback? _callback;

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionRegisterCallback")]
    public static void RvExtensionRegisterCallback(nint func)
    {
        if (func == 0) { _callback = null; return; }
        _callback = Marshal.GetDelegateForFunctionPointer<ExtensionCallback>(func);
    }

    private static void InvokeCallback(string function, string data)
    {
        var cb = _callback;
        if (cb == null) return;
        if (function.Length > 64) function = function.Substring(0, 64);
        if (data.Length > 20000) data = data.Substring(0, 20000);
        try
        {
            // Arma callback peut être appelé hors thread jeu ; démarrer une tâche dédiée.
            _ = Task.Run(() =>
            {
                try { cb("comspec", function, data); }
                catch { /* ne jamais faire planter le process hôte */ }
            });
        }
        catch { }
    }

    private static void EnsureDrainTimer()
    {
        if (_drainTimer != null) return;
        _drainTimer = new System.Threading.Timer(_ => DrainQueue(), null, 2000, 2000);
    }

    private static void DrainQueue()
    {
        if (string.IsNullOrEmpty(_baseUrl)) return;
        lock (QueueDrainLock)
        {
            for (var i = 0; i < 5 && PendingPosts.TryDequeue(out var item); i++)
            {
                try
                {
                    var content = new StringContent(item.Body, Encoding.UTF8, "application/json");
                    var response = HttpClient.PostAsync(item.Url, content).GetAwaiter().GetResult();
                    if (!response.IsSuccessStatusCode && PendingPosts.Count < MaxQueueSize)
                        PendingPosts.Enqueue(item);
                }
                catch
                {
                    if (PendingPosts.Count < MaxQueueSize) PendingPosts.Enqueue(item);
                }
            }
        }
    }

    private static void EnqueueOrSend(string url, string jsonBody)
    {
        try
        {
            var content = new StringContent(jsonBody, Encoding.UTF8, "application/json");
            _ = HttpClient.PostAsync(url, content).ContinueWith(t =>
            {
                if (t.IsFaulted || !t.Result.IsSuccessStatusCode)
                {
                    if (PendingPosts.Count < MaxQueueSize)
                    {
                        PendingPosts.Enqueue((url, jsonBody));
                        EnsureDrainTimer();
                    }
                }
            });
        }
        catch
        {
            if (PendingPosts.Count < MaxQueueSize)
            {
                PendingPosts.Enqueue((url, jsonBody));
                EnsureDrainTimer();
            }
        }
    }

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionVersion")]
    public static void RvExtensionVersion(nint output, int outputSize)
    {
        Output(output, outputSize, "COMSPECExtension 1.6");
    }

    private static void Output(nint output, int outputSize, string data)
    {
        if (outputSize <= 0) return;
        var bytes = Encoding.UTF8.GetBytes(data ?? "");
        var n = Math.Min(bytes.Length, outputSize - 1);
        if (n > 0) Marshal.Copy(bytes, 0, output, n);
        Marshal.WriteByte(output, n, 0); // C-string NUL — requis par callExtension Arma
    }

    [UnmanagedCallersOnly(EntryPoint = "RVExtension")]
    public static void RvExtension(nint output, int outputSize, nint function)
    {
        var functionString = Marshal.PtrToStringUTF8(function);
        RvExtensionArgsImpl(functionString, []);
        Output(output, outputSize, "");
    }

    private const int MaxOutputBytes = 8000;

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionArgs")]
    public static int RvExtensionArgs(nint output, int outputSize, nint function, nint args, int argCount)
    {
        var functionString = Marshal.PtrToStringUTF8(function);
        var argsString = new string?[argCount];
        for (var i = 0; i < argCount; i++)
            argsString[i] = Marshal.PtrToStringUTF8(Marshal.ReadIntPtr(args + (i * Marshal.SizeOf<nint>())));

        // Filet de sécurité au niveau du point d'entrée natif : une méthode [UnmanagedCallersOnly]
        // ne doit jamais laisser fuiter d'exception (le runtime .NET fait un fail-fast qui tue le
        // process hôte, c-à-d Arma). Toute exception imprévue devient un ERR| exploitable en SQF
        // plutôt qu'un plantage silencieux ou un retour vide sans diagnostic.
        string? syncResult;
        try
        {
            syncResult = TryGetSyncResponse(functionString, argsString);
        }
        catch (Exception ex)
        {
            // Même mapping que les handlers internes (évite ERR|exception:InvalidOperationException opaque).
            syncResult = FormatCaughtError(ex);
        }

        if (syncResult != null)
        {
            var maxLen = Math.Min(syncResult.Length, Math.Min(outputSize - 1, MaxOutputBytes));
            if (maxLen < syncResult.Length)
                syncResult = syncResult.Substring(0, maxLen);
            Output(output, outputSize, syncResult);
            return 0;
        }

        try
        {
            RvExtensionArgsImpl(functionString, argsString);
        }
        catch
        {
            // best effort : ne pas laisser fuiter d'exception hors du point d'entrée natif.
        }
        Output(output, outputSize, "");
        return 0;
    }

    private static readonly object HeaderLock = new();

    /// <summary>
    /// Mémorise la clé API et tente de l’appliquer sur le HttpClient partagé.
    /// Ne doit JAMAIS lever : DefaultRequestHeaders lève InvalidOperationException
    /// si une requête est déjà en vol (file d’attente / Connect async).
    /// </summary>
    private static void ApplyApiKeyHeaders(string? apiKey)
    {
        var key = (apiKey ?? "").Trim();
        lock (HeaderLock)
        {
            _apiKey = key;
            try
            {
                HttpClient.DefaultRequestHeaders.Remove("X-COMSPEC-KEY");
                HttpClient.DefaultRequestHeaders.Remove("X-ATAK-TOKEN");
                if (_apiKey.Length == 0) return;
                HttpClient.DefaultRequestHeaders.TryAddWithoutValidation("X-COMSPEC-KEY", _apiKey);
            }
            catch (InvalidOperationException)
            {
                // Requête en cours : la clé reste dans _apiKey ; AttachApiKeyHeader la posera
                // sur les prochains HttpRequestMessage dédiés.
            }
        }
    }

    /// <summary>Attache X-COMSPEC-KEY sur une requête (thread-safe, pas de DefaultRequestHeaders).</summary>
    private static void AttachApiKeyHeader(HttpRequestMessage req)
    {
        var key = _apiKey;
        if (key.Length == 0) return;
        req.Headers.Remove("X-COMSPEC-KEY");
        req.Headers.TryAddWithoutValidation("X-COMSPEC-KEY", key);
    }

    /// <summary>
    /// Nettoie une URL issue de SQF/CBA (guillemets, espaces) et impose un schéma absolu.
    /// Sans schéma absolu, HttpClient lève InvalidOperationException (« request URI must be absolute »).
    /// </summary>
    private static string NormalizeBaseUrl(string? raw)
    {
        var s = (raw ?? "").Trim().TrimEnd('/');
        if (s.Length >= 2 && ((s[0] == '"' && s[^1] == '"') || (s[0] == '\'' && s[^1] == '\'')))
            s = s.Substring(1, s.Length - 2).Trim().TrimEnd('/');
        // Caractères invisibles / BOM parfois collés par copier-coller dans le dialog.
        s = s.Trim('\uFEFF', '\u200B', '\u200E', '\u200F');
        if (s.Length == 0) return "";
        if (s.StartsWith("https://", StringComparison.OrdinalIgnoreCase)
            || s.StartsWith("http://", StringComparison.OrdinalIgnoreCase))
            return s.TrimEnd('/');
        // Hôte nu (ex. athena.ttrd.fr/public) → https par défaut.
        if (s.Contains('.', StringComparison.Ordinal) && !s.Contains("://", StringComparison.Ordinal))
            return "https://" + s.TrimStart('/');
        return s.TrimEnd('/');
    }

    private static bool TryBuildRequestUri(string baseUrl, string relativePath, out Uri? uri, out string errorCode)
    {
        uri = null;
        errorCode = "invalid_url";
        var root = NormalizeBaseUrl(baseUrl);
        if (root.Length == 0)
        {
            errorCode = "invalid";
            return false;
        }
        if (!Uri.TryCreate(root, UriKind.Absolute, out var baseUri)
            || (baseUri.Scheme != Uri.UriSchemeHttps && baseUri.Scheme != Uri.UriSchemeHttp))
        {
            errorCode = "invalid_url";
            return false;
        }
        // Important : ne PAS combiner avec un chemin absolu "/api/..." via Uri(base, path) —
        // ça remplace tout le path et perd le préfixe /public
        // (https://host/public + /api/x → https://host/api/x → 404).
        var path = relativePath.StartsWith('/') ? relativePath : "/" + relativePath;
        var combined = root.TrimEnd('/') + path;
        if (!Uri.TryCreate(combined, UriKind.Absolute, out uri) || uri is null || !uri.IsAbsoluteUri)
        {
            errorCode = "invalid_url";
            return false;
        }
        return true;
    }

    /// <summary>
    /// Lit le corps en octets puis UTF-8 : évite InvalidOperationException de ReadAsStringAsync
    /// quand le Content-Type annonce un charset non supporté (fréquent en Native AOT).
    /// </summary>
    private static string ReadContentUtf8(HttpResponseMessage resp, CancellationToken token)
    {
        var bytes = resp.Content.ReadAsByteArrayAsync(token).GetAwaiter().GetResult();
        if (bytes.Length == 0) return "";
        return Encoding.UTF8.GetString(bytes);
    }

    private static string FormatCaughtError(Exception ex)
    {
        // Messages stables pour SQF (pas de '|' qui casse le split).
        if (ex is InvalidOperationException ioe)
        {
            var msg = ioe.Message ?? "";
            if (msg.Contains("request URI", StringComparison.OrdinalIgnoreCase)
                || msg.Contains("BaseAddress", StringComparison.OrdinalIgnoreCase)
                || msg.Contains("absolute", StringComparison.OrdinalIgnoreCase))
                return "ERR|invalid_url";
            if (msg.Contains("charset", StringComparison.OrdinalIgnoreCase))
                return "ERR|invalid_response";
            // Souvent : modification DefaultRequestHeaders pendant un envoi HTTP.
            if (msg.Contains("header", StringComparison.OrdinalIgnoreCase)
                || msg.Contains("headers", StringComparison.OrdinalIgnoreCase))
                return "ERR|busy_retry";
            return "ERR|invalid_op";
        }
        if (ex is UriFormatException) return "ERR|invalid_url";
        if (ex is JsonException) return "ERR|invalid_response";
        if (ex is HttpRequestException) return "ERR|network";
        if (ex is OperationCanceledException) return "ERR|timeout";
        // Une ligne de détail (sans '|') pour le journal SQF.
        var detail = (ex.Message ?? "").Replace('|', ' ').Replace('\n', ' ').Replace('\r', ' ').Trim();
        if (detail.Length > 80) detail = detail.Substring(0, 80);
        return detail.Length > 0
            ? "ERR|exception:" + ex.GetType().Name + ":" + detail
            : "ERR|exception:" + ex.GetType().Name;
    }

    private static string? TryGetSyncResponse(string? function, string?[] args)
    {
        // Sonde légère : confirme que la DLL répond (chargée et non bloquée, ex. par BattlEye).
        if (function is "Ping" or "Warmup" or "GetExtensionVersion")
        {
            return "OK|COMSPECExtension 1.6";
        }

        // Connect doit pouvoir s’exécuter même si aucune URL n’est encore mémorisée
        // (sinon le premier appel retourne ERR|invalid et la liaison ne s’établit jamais).
        if (function == "Connect" && args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
        {
            var normalized = NormalizeBaseUrl(args[0]);
            if (normalized.Length == 0
                || !Uri.TryCreate(normalized, UriKind.Absolute, out var connectUri)
                || (connectUri.Scheme != Uri.UriSchemeHttps && connectUri.Scheme != Uri.UriSchemeHttp))
                return "ERR|invalid_url";
            _baseUrl = normalized;
            var key = args.Length > 1 ? (args[1] ?? "") : "";
            ApplyApiKeyHeaders(key);
            return "OK|connected";
        }

        // Code de liaison compte Athena — URL fournie en argument (pas besoin d’un Connect préalable).
        if (function == "RedeemGameLink" && args.Length >= 2)
        {
            using var redeemCts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
            var redeemToken = redeemCts.Token;
            var baseUrl = NormalizeBaseUrl(args[0]);
            var linkCode = (args[1] ?? "").Trim().ToUpperInvariant();
            var steamUid = args.Length > 2 ? (args[2] ?? "").Trim() : "";
            if (baseUrl.Length == 0 || linkCode.Length < 4) return "ERR|invalid";
            if (!TryBuildRequestUri(baseUrl, "/api/atak/game-link/redeem", out var redeemUri, out var uriErr) || redeemUri is null)
                return "ERR|" + uriErr;

            try
            {
                var payload = $"{{\"code\":\"{EscapeJson(linkCode)}\",\"steam_uid\":\"{EscapeJson(steamUid)}\"}}";
                // Contenu JSON sans ctor media-type (évite surprises Native AOT / headers).
                using var content = new StringContent(payload, Encoding.UTF8);
                content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
                // HttpRequestMessage dédié : redeem sans clé API (le code court est le secret).
                using var req = new HttpRequestMessage(HttpMethod.Post, redeemUri) { Content = content };
                var resp = HttpClient.SendAsync(req, redeemToken).GetAwaiter().GetResult();
                var respBody = ReadContentUtf8(resp, redeemToken);
                if (!resp.IsSuccessStatusCode)
                {
                    var httpCode = (int)resp.StatusCode;
                    if (httpCode == 404)
                    {
                        if (respBody.Contains("code_already_used", StringComparison.Ordinal))
                            return "ERR|code_already_used";
                        if (respBody.Contains("code_expired", StringComparison.Ordinal))
                            return "ERR|code_expired";
                        if (respBody.Contains("code_invalid_or_expired", StringComparison.Ordinal))
                            return "ERR|code_invalid_or_expired";
                        // 404 HTML (route hors /public) vs JSON métier.
                        if (respBody.Contains("<html", StringComparison.OrdinalIgnoreCase)
                            || respBody.Contains("<!DOCTYPE", StringComparison.OrdinalIgnoreCase))
                            return "ERR|not_found";
                        if (respBody.Length == 0)
                            return "ERR|not_found";
                        return "ERR|code_invalid_or_expired";
                    }
                    if (httpCode == 400 && respBody.Contains("invalid_code", StringComparison.Ordinal))
                        return "ERR|invalid_code";
                    if (httpCode == 503)
                        return "ERR|http_503";
                    return "ERR|http_" + httpCode;
                }
                using var doc = JsonDocument.Parse(respBody);
                var root = doc.RootElement;
                var apiUrl = root.TryGetProperty("api_url", out var au) ? (au.GetString() ?? "") : "";
                var apiKey = root.TryGetProperty("api_key", out var ak) ? (ak.GetString() ?? "") : "";
                var tenantId = root.TryGetProperty("tenant_id", out var ti)
                    ? (ti.ValueKind == JsonValueKind.Number ? ti.GetRawText() : (ti.GetString() ?? ""))
                    : "";
                apiUrl = NormalizeBaseUrl(apiUrl.Length == 0 ? baseUrl : apiUrl);
                if (apiUrl.Length == 0) apiUrl = baseUrl;
                _baseUrl = apiUrl;
                // Mémorise la clé sans exiger DefaultRequestHeaders (race → busy_retry).
                ApplyApiKeyHeaders(apiKey);
                var simplified = apiUrl + "\t" + apiKey + "\t" + tenantId;
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            catch (Exception ex)
            {
                return FormatCaughtError(ex);
            }
        }

        if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
        // Re-normalise au cas où une ancienne session a stocké une URL sans schéma.
        _baseUrl = NormalizeBaseUrl(_baseUrl);
        if (!Uri.TryCreate(_baseUrl, UriKind.Absolute, out _))
            return "ERR|invalid_url";

        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
        var token = cts.Token;
        try
        {
            if (function == "GetMarkers")
            {
                var since = args.Length > 0 ? (args[0] ?? "") : "";
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/markers?mapId=1", out var markersUri, out var markersErr) || markersUri is null)
                    return "ERR|" + markersErr;
                var url = markersUri.AbsoluteUri;
                if (!string.IsNullOrEmpty(since)) url += "&since=" + Uri.EscapeDataString(since);
                var response = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                response.EnsureSuccessStatusCode();
                var body = ReadContentUtf8(response, token);
                return "OK|" + SimplifyMarkersJson(body);
            }
            if (function == "GetUnits")
            {
                if (!TryBuildRequestUri(_baseUrl, "/api/units?mapId=1", out var unitsUri, out var unitsErr) || unitsUri is null)
                    return "ERR|" + unitsErr;
                var response = HttpClient.GetAsync(unitsUri, token).GetAwaiter().GetResult();
                response.EnsureSuccessStatusCode();
                var body = ReadContentUtf8(response, token);
                return "OK|" + SimplifyUnitsJson(body);
            }
            if (function == "GetClientIp")
            {
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/whoami", out var whoamiUri, out var whoamiErr) || whoamiUri is null)
                    return "ERR|" + whoamiErr;
                try
                {
                    var response = HttpClient.GetAsync(whoamiUri, token).GetAwaiter().GetResult();
                    if (!response.IsSuccessStatusCode)
                    {
                        var code = (int)response.StatusCode;
                        if (code == 401 || code == 403) return "ERR|unauthorized";
                        if (code == 404) return "ERR|not_found";
                        return "ERR|http_" + code;
                    }
                    var body = ReadContentUtf8(response, token);
                    using var doc = JsonDocument.Parse(body);
                    var ip = doc.RootElement.TryGetProperty("ip", out var p) ? (p.GetString() ?? "—") : "—";
                    return "OK|" + ip;
                }
                catch (Exception ex)
                {
                    return FormatCaughtError(ex);
                }
            }
            if (function == "FireSupport.Request" && args.Length >= 6)
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var gunX = args.Length > 1 && double.TryParse(args[1], out var gx) ? gx : 0.0;
                var gunY = args.Length > 2 && double.TryParse(args[2], out var gy) ? gy : 0.0;
                var gunZ = args.Length > 3 && double.TryParse(args[3], out var gz) ? gz : 0.0;
                var targetX = double.TryParse(args[4], out var tx) ? tx : 0.0;
                var targetY = double.TryParse(args[5], out var ty) ? ty : 0.0;
                var targetZ = args.Length > 6 && double.TryParse(args[6], out var tz) ? tz : 0.0;
                var ammoType = args.Length > 7 ? (args[7] ?? "HE") : "HE";
                var fireUnitId = args.Length > 8 && int.TryParse(args[8], out var fid) ? fid : (int?)null;
                var payload = fireUnitId.HasValue && fireUnitId > 0
                    ? $"{{\"missionId\":\"{EscapeJson(missionId)}\",\"fireUnitId\":{fireUnitId.Value},\"target_x\":{targetX.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"target_y\":{targetY.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"target_z\":{targetZ.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"ammoType\":\"{EscapeJson(ammoType)}\"}}"
                    : $"{{\"missionId\":\"{EscapeJson(missionId)}\",\"gun_x\":{gunX.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"gun_y\":{gunY.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"gun_z\":{gunZ.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"target_x\":{targetX.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"target_y\":{targetY.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"target_z\":{targetZ.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"ammoType\":\"{EscapeJson(ammoType)}\"}}";
                var content = new StringContent(payload, Encoding.UTF8, "application/json");
                var resp = HttpClient.PostAsync(_baseUrl + "/api/fire-support/calculate", content, token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "GetFireSupportUnits")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = HttpClient.GetAsync(_baseUrl + "/api/fire-support/units?missionId=" + Uri.EscapeDataString(missionId), token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "DangerZones.Sync")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = HttpClient.GetAsync(_baseUrl + "/api/danger-zones?missionId=" + Uri.EscapeDataString(missionId), token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "IFF.Current")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = HttpClient.GetAsync(_baseUrl + "/api/iff/current?missionId=" + Uri.EscapeDataString(missionId), token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "IFF.Status")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = HttpClient.GetAsync(_baseUrl + "/api/iff/assets?missionId=" + Uri.EscapeDataString(missionId), token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "Intel.Report" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return "{\"status\":\"error\",\"message\":\"payload vide\"}";
                var content = new StringContent(json, Encoding.UTF8, "application/json");
                var response = HttpClient.PostAsync(_baseUrl + "/api/intel/report", content, token).GetAwaiter().GetResult();
                var body = response.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                var safeBody = body.Replace("\n", " ").Replace("\r", "");
                if (!response.IsSuccessStatusCode)
                {
                    var msg = safeBody.Length > 180 ? safeBody.Substring(0, 180) + "..." : safeBody;
                    return "{\"status\":\"error\",\"message\":\"" + EscapeJson(msg) + "\"}";
                }
                return safeBody.Length <= MaxOutputBytes - 1 ? safeBody : safeBody.Substring(0, MaxOutputBytes - 1);
            }
            if (function == "GetCASForCallsign" && args.Length >= 1)
            {
                var callsign = Uri.EscapeDataString(args[0] ?? "");
                var mapId = args.Length > 1 ? (args[1] ?? "1") : "1";
                var url = _baseUrl + "/api/cas?mapId=" + mapId + "&assignedTo=" + callsign;
                var resp = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                var safe = respBody.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                return "OK|" + (safe.Length > MaxOutputBytes - 4 ? safe.Substring(0, MaxOutputBytes - 4) : safe);
            }
            if (function == "GetMapShapes")
            {
                var mapId = args.Length > 0 ? (args[0] ?? "1") : "1";
                var since = args.Length > 1 ? Uri.EscapeDataString(args[1] ?? "") : "";
                var url = _baseUrl + "/api/map-shapes?mapId=" + mapId;
                if (!string.IsNullOrEmpty(since)) url += "&since=" + since;
                var resp = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                var safe = respBody.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                return "OK|" + (safe.Length > MaxOutputBytes - 4 ? safe.Substring(0, MaxOutputBytes - 4) : safe);
            }
            if (function == "GetLaserCodes")
            {
                var mapId = args.Length > 0 ? (args[0] ?? "1") : "1";
                var resp = HttpClient.GetAsync(_baseUrl + "/api/atak/laser-codes?mapId=" + mapId, token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                var safe = respBody.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                return "OK|" + (safe.Length > MaxOutputBytes - 4 ? safe.Substring(0, MaxOutputBytes - 4) : safe);
            }
            // Diapositives de briefing tactique (tableau/écran Eden Editor, ou dialog de briefing).
            // Format simplifié tabulation/retour-ligne (même convention que GetMarkers/GetUnits) :
            // pas de parseur JSON natif en SQF, donc une ligne par diapositive : id\ttitle\tsortOrder\timageUrl
            if (function == "GetBriefingSlides")
            {
                var tenantId = args.Length > 0 ? (args[0] ?? "") : "";
                var url = _baseUrl + "/api/atak/briefing-slides";
                if (!string.IsNullOrEmpty(tenantId)) url += "?tenant_id=" + Uri.EscapeDataString(tenantId);
                var resp = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                resp.EnsureSuccessStatusCode();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                var simplified = SimplifyBriefingSlidesJson(respBody);
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Connexion téléphone (inspiré de cTab) : génère un token/QR + un code court côté serveur.
            // Format : token\tcode\tconnectUrl\tqrImageUrl\texpiresAt — le QR se télécharge ensuite
            // via DownloadBriefingSlideImage(qrImageUrl, "phoneqr") comme n'importe quelle diapositive.
            if (function == "GetPhoneConnectInfo")
            {
                var tenantId = args.Length > 0 ? (args[0] ?? "") : "";
                var url = _baseUrl + "/api/atak/phone-pairing";
                if (!string.IsNullOrEmpty(tenantId)) url += "?tenant_id=" + Uri.EscapeDataString(tenantId);
                var resp = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    // 404 : URL de base incorrecte (souvent sans /public) ou route absente du déploiement.
                    if (code == 404) return "ERR|not_found";
                    if (code == 401) return "ERR|unauthorized";
                    if (code == 403)
                    {
                        // Communauté manquante côté serveur vs clé refusée.
                        if (respBody.Contains("tenant_context_required", StringComparison.Ordinal))
                            return "ERR|no_tenant";
                        return "ERR|unauthorized";
                    }
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyPhonePairingJson(respBody);
                if (simplified.Length == 0) return "ERR|invalid_response";
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Profil site (nom, callsign, photo) d'un joueur identifié par son SteamUID, résolu via
            // le compte Athena lié (voir RedeemGameLink). Format : displayName\tcallsign\tavatarUrl —
            // l'avatar se télécharge ensuite via DownloadBriefingSlideImage(avatarUrl, "avatar_<uid>")
            // comme n'importe quelle image (même mécanisme que les diapositives / le QR téléphone).
            if (function == "GetPlayerAvatarInfo" && args.Length >= 1)
            {
                var steamUid = (args[0] ?? "").Trim();
                if (steamUid.Length == 0) return "ERR|invalid";
                var url = _baseUrl + "/api/atak/player-profile?steam_uid=" + Uri.EscapeDataString(steamUid);
                var resp = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                var respBody = resp.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401) return "ERR|unauthorized";
                    if (code == 403)
                    {
                        if (respBody.Contains("tenant_context_required", StringComparison.Ordinal))
                            return "ERR|no_tenant";
                        return "ERR|unauthorized";
                    }
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyPlayerProfileJson(respBody);
                if (simplified.Length == 0) return "ERR|invalid_response";
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Télécharge une diapositive (image_url renvoyée par GetBriefingSlides) et la met en cache local ;
            // retourne le chemin de fichier local à passer à setObjectTexture / ctrlSetText côté SQF.
            if (function == "DownloadBriefingSlideImage" && args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
            {
                var imageUrl = args[0]!;
                var cacheKeyRaw = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]! : "slide";
                var safeKey = new string(cacheKeyRaw.Where(c => char.IsLetterOrDigit(c) || c == '_' || c == '-').ToArray());
                if (safeKey.Length == 0) safeKey = "slide";
                var ext = imageUrl.ToLowerInvariant().Contains(".png") ? ".png" : ".jpg";

                // Accepte les URL relatives renvoyées par l’API (ex. /api/atak/.../qr.png).
                if (!Uri.TryCreate(imageUrl, UriKind.Absolute, out _)
                    && !string.IsNullOrEmpty(_baseUrl)
                    && imageUrl.StartsWith('/'))
                {
                    imageUrl = _baseUrl.TrimEnd('/') + imageUrl;
                }

                HttpResponseMessage imgResp;
                try
                {
                    imgResp = HttpClient.GetAsync(imageUrl, token).GetAwaiter().GetResult();
                }
                catch (OperationCanceledException)
                {
                    return "ERR|timeout";
                }
                catch (HttpRequestException)
                {
                    return "ERR|network";
                }

                if (!imgResp.IsSuccessStatusCode)
                {
                    var code = (int)imgResp.StatusCode;
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 404) return "ERR|not_found";
                    return "ERR|http_" + code;
                }

                var bytes = imgResp.Content.ReadAsByteArrayAsync(token).GetAwaiter().GetResult();
                if (bytes.Length == 0) return "ERR|empty_image";

                var cacheDir = GetWritableCacheDir();
                if (cacheDir == null) return "ERR|no_writable_cache_dir";

                var destPath = Path.Combine(cacheDir, "briefing_" + safeKey + ext);
                try
                {
                    File.WriteAllBytes(destPath, bytes);
                }
                catch (Exception ex)
                {
                    return "ERR|write_failed_" + ex.GetType().Name;
                }

                return "OK|" + destPath;
            }
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException)
        {
            return "ERR|network";
        }
        catch (Exception)
        {
            return "ERR|invalid";
        }
        return null;
    }

    /// <summary>
    /// Dossier de cache local pour les diapositives téléchargées : à côté de la DLL en priorité
    /// (dossier du mod, généralement accessible en écriture), sinon dossier temporaire système.
    /// </summary>
    private static string? GetWritableCacheDir()
    {
        var candidates = new[]
        {
            Path.Combine(AppContext.BaseDirectory, "comspec_cache", "briefing"),
            Path.Combine(Path.GetTempPath(), "comspec_cache", "briefing"),
        };
        foreach (var dir in candidates)
        {
            try
            {
                Directory.CreateDirectory(dir);
                var probe = Path.Combine(dir, ".write_test");
                File.WriteAllText(probe, "ok");
                File.Delete(probe);
                return dir;
            }
            catch
            {
                // Essaie le prochain dossier candidat.
            }
        }

        return null;
    }

    private static string SimplifyMarkersJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            foreach (var el in doc.RootElement.EnumerateArray())
            {
                var id = el.GetProperty("id").GetInt32();
                var layerId = el.TryGetProperty("layerId", out var l) ? l.GetInt32() : 1;
                var dataStr = el.TryGetProperty("markerData", out var md)
                    ? (md.ValueKind == JsonValueKind.String ? md.GetString() ?? "{}" : md.GetRawText())
                    : "{}";
                double x = 0, y = 0;
                var type = "mil_dot";
                var text = "";
                try
                {
                    using var data = JsonDocument.Parse(dataStr);
                    var root = data.RootElement;
                    if (root.TryGetProperty("pos", out var pos) && pos.GetArrayLength() >= 2)
                    {
                        x = pos[0].GetDouble();
                        y = pos[1].GetDouble();
                    }
                    if (root.TryGetProperty("type", out var t)) type = t.GetString() ?? type;
                    if (root.TryGetProperty("text", out var tx)) text = tx.GetString() ?? "";
                }
                catch { }
                text = text.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
                sb.Append("M\t").Append(id).Append("\t").Append(layerId).Append("\t")
                    .Append(x.ToString("R", System.Globalization.CultureInfo.InvariantCulture)).Append("\t")
                    .Append(y.ToString("R", System.Globalization.CultureInfo.InvariantCulture)).Append("\t")
                    .Append(type).Append("\t").Append(text).Append("\n");
            }
            return sb.ToString();
        }
        catch { return "[]"; }
    }

    private static string SimplifyUnitsJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            foreach (var el in doc.RootElement.EnumerateArray())
            {
                var callSign = el.TryGetProperty("call_sign", out var cs) ? cs.GetString() ?? "" : "";
                var gridRef = el.TryGetProperty("grid_ref", out var gr) ? gr.GetString() ?? "" : "";
                var parts = gridRef.Split(' ', StringSplitOptions.RemoveEmptyEntries);
                var gx = parts.Length >= 1 && double.TryParse(parts[0], out var px) ? px : 0;
                var gy = parts.Length >= 2 && double.TryParse(parts[1], out var py) ? py : 0;
                sb.Append("U\t").Append(callSign.Replace("\t", " ")).Append("\t")
                    .Append(gx.ToString("R", System.Globalization.CultureInfo.InvariantCulture)).Append("\t")
                    .Append(gy.ToString("R", System.Globalization.CultureInfo.InvariantCulture)).Append("\n");
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    private static string SimplifyBriefingSlidesJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("slides", out var slides)) return "";
            foreach (var el in slides.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i) ? i.GetInt32() : 0;
                var title = el.TryGetProperty("title", out var t) ? (t.GetString() ?? "") : "";
                var sortOrder = el.TryGetProperty("sort_order", out var s) ? s.GetInt32() : 0;
                var imageUrl = el.TryGetProperty("image_url", out var u) ? (u.GetString() ?? "") : "";
                if (imageUrl.Length == 0) continue;
                title = title.Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
                sb.Append(id).Append('\t').Append(title).Append('\t').Append(sortOrder).Append('\t').Append(imageUrl).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    private static string SimplifyPhonePairingJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            var token = root.TryGetProperty("token", out var tk) ? (tk.GetString() ?? "") : "";
            var code = root.TryGetProperty("code", out var cd) ? (cd.GetString() ?? "") : "";
            var connectUrl = root.TryGetProperty("connect_url", out var cu) ? (cu.GetString() ?? "") : "";
            var qrImageUrl = root.TryGetProperty("qr_image_url", out var qu) ? (qu.GetString() ?? "") : "";
            var expiresAt = root.TryGetProperty("expires_at", out var ea) ? (ea.GetString() ?? "") : "";
            if (token.Length == 0 || qrImageUrl.Length == 0) return "";
            return token + "\t" + code + "\t" + connectUrl + "\t" + qrImageUrl + "\t" + expiresAt;
        }
        catch { return ""; }
    }

    private static string SimplifyPlayerProfileJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            var displayName = root.TryGetProperty("display_name", out var dn) ? (dn.GetString() ?? "") : "";
            var callsign = root.TryGetProperty("callsign", out var cs) ? (cs.GetString() ?? "") : "";
            var avatarUrl = root.TryGetProperty("avatar_url", out var au) ? (au.GetString() ?? "") : "";
            var unitName = root.TryGetProperty("unit_name", out var un) ? (un.GetString() ?? "") : "";
            var atakId = root.TryGetProperty("atak_id", out var ai) ? (ai.GetString() ?? "") : "";
            // playtime_hours/last_seen_at sont explicitement null en JSON quand la donnée n'est pas
            // trackée côté serveur (table absente, joueur jamais rapporté) — jamais une valeur
            // inventée : la chaîne reste vide et le SQF affiche un placeholder explicite.
            var playtimeHours = root.TryGetProperty("playtime_hours", out var ph) && ph.ValueKind == JsonValueKind.Number
                ? ph.GetDouble().ToString("0.0", System.Globalization.CultureInfo.InvariantCulture)
                : "";
            var lastSeenAt = root.TryGetProperty("last_seen_at", out var ls) && ls.ValueKind == JsonValueKind.String
                ? (ls.GetString() ?? "")
                : "";
            displayName = displayName.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
            callsign = callsign.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
            unitName = unitName.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
            atakId = atakId.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
            if (displayName.Length == 0 && callsign.Length == 0) return "";
            return displayName + "\t" + callsign + "\t" + avatarUrl + "\t" + unitName + "\t" + atakId + "\t" + playtimeHours + "\t" + lastSeenAt;
        }
        catch { return ""; }
    }

    private static void RvExtensionArgsImpl(string? function, string?[] args)
    {
        try
        {
            if (function == "Connect")
            {
                if (args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
                {
                    _baseUrl = args[0]!.TrimEnd('/');
                    var key = args.Length > 1 ? (args[1] ?? "") : "";
                    ApplyApiKeyHeaders(key);
                    SendChatMessage("COMSPEC Overwatch: mod actif.");
                    SendChatMessage("Liaison établie avec le nœud: " + _baseUrl);
                    var initUrl = _baseUrl + "/api/atak/client-init";
                    var initBody = "{\"mapId\":1}";
                    _ = HttpClient.PostAsync(initUrl, new StringContent(initBody, Encoding.UTF8, "application/json"))
                        .ContinueWith(t =>
                        {
                            try
                            {
                                if (t.IsFaulted || t.IsCanceled)
                                {
                                    InvokeCallback("Error", "Portail injoignable");
                                    return;
                                }
                                var resp = t.Result;
                                if (resp.IsSuccessStatusCode)
                                {
                                    InvokeCallback("Connected", _baseUrl);
                                }
                                else
                                {
                                    InvokeCallback("Error", "HTTP " + (int)resp.StatusCode);
                                }
                            }
                            catch
                            {
                                InvokeCallback("Error", "Portail injoignable");
                            }
                        });
                }
                return;
            }

            if (function == "UpdatePosition" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 4)
            {
                var posX = double.TryParse(args[0], out var x) ? x : 0;
                var posY = double.TryParse(args[1], out var y) ? y : 0;
                var heading = double.TryParse(args[2], out var h) ? h : (double?)null;
                var callSign = args[3] ?? "Unknown";
                var role = args.Length > 4 ? (args[4] ?? "") : "";
                var health = args.Length > 5 ? (args[5] ?? "ok") : "ok";
                var fuel = args.Length > 6 ? (args[6] ?? "") : "";
                var ammo = args.Length > 7 ? (args[7] ?? "n/a") : "n/a";
                var radioFreq = args.Length > 8 ? (args[8] ?? "") : "";
                // args[9] = JSON véhicule optionnel (speed, in_vehicle, vector_dir, vector_up, velocity…)
                var vehicleJson = args.Length > 9 ? (args[9] ?? "") : "";
                var headingStr = heading.HasValue ? heading.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture) : "null";
                var extra = new System.Text.StringBuilder();
                extra.Append("\"role\":\"").Append(EscapeJson(role)).Append("\"");
                extra.Append(",\"health\":\"").Append(EscapeJson(health)).Append("\"");
                if (!string.IsNullOrEmpty(fuel)) extra.Append(",\"fuel\":\"").Append(EscapeJson(fuel)).Append("\"");
                extra.Append(",\"ammo\":\"").Append(EscapeJson(ammo)).Append("\"");
                if (!string.IsNullOrEmpty(radioFreq)) extra.Append(",\"radio_freq\":\"").Append(EscapeJson(radioFreq)).Append("\"");
                if (!string.IsNullOrWhiteSpace(vehicleJson) && vehicleJson.StartsWith('{') && vehicleJson.EndsWith('}'))
                {
                    // Fusionne le JSON véhicule dans extra (sans enveloppe {}).
                    var inner = vehicleJson.Substring(1, vehicleJson.Length - 2).Trim();
                    if (inner.Length > 0) extra.Append(',').Append(inner);
                }
                var payload = $"{{\"mapId\":1,\"call_sign\":\"{EscapeJson(callSign)}\",\"pos_x\":{posX.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"pos_y\":{posY.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"heading\":{headingStr},\"role\":\"{EscapeJson(role)}\",\"extra\":{{{extra}}}}}";
                EnqueueOrSend(_baseUrl + "/api/atak/position", payload);
                return;
            }

            if (function == "ReportPlaytime" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var uid = args[0] ?? "";
                var secStr = args[1] ?? "0";
                var secs = long.TryParse(secStr, out var s) ? s : 0L;
                if (secs < 1) return;
                if (secs > 7200) secs = 7200;
                var call = args.Length > 2 ? (args[2] ?? "") : "";
                var payload = $"{{\"player_uid\":\"{EscapeJson(uid)}\",\"session_seconds\":{secs.ToString(System.Globalization.CultureInfo.InvariantCulture)},\"call_sign\":\"{EscapeJson(call)}\"}}";
                EnqueueOrSend(_baseUrl + "/api/atak/playtime", payload);
                return;
            }

            if (function == "SendChat" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var author = args[0] ?? "Unknown";
                var body = args[1] ?? "";
                var payload = $"{{\"mapId\":1,\"author\":\"{EscapeJson(author)}\",\"body\":\"{EscapeJson(body)}\"}}";
                EnqueueOrSend(_baseUrl + "/api/chat", payload);
                return;
            }

            if (function == "SendPing" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 4)
            {
                var author = args[0] ?? "Unknown";
                var x = args[1] ?? "0";
                var y = args[2] ?? "0";
                var msg = args[3] ?? "Ping";
                var payload = $"{{\"mapId\":1,\"author\":\"{EscapeJson(author)}\",\"pos_x\":{x},\"pos_y\":{y},\"message\":\"{EscapeJson(msg)}\"}}";
                EnqueueOrSend(_baseUrl + "/api/pings", payload);
                return;
            }

            if (function == "SendIntel" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 3)
            {
                var type = args[0] ?? "MARKER";
                var body = args[1] ?? "";
                var extra = args[2] ?? "";
                var payload = $"{{\"mapId\":1,\"type\":\"{EscapeJson(type)}\",\"body\":\"{EscapeJson(body)}\",\"data\":\"{EscapeJson(extra)}\"}}";
                EnqueueOrSend(_baseUrl + "/api/atak/intel", payload);
                return;
            }

            if (function == "SendDesignator" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 3)
            {
                var callSign = args[0] ?? "Unknown";
                var x = args[1] ?? "0";
                var y = args[2] ?? "0";
                var payload = $"{{\"mapId\":1,\"call_sign\":\"{EscapeJson(callSign)}\",\"pos_x\":{x},\"pos_y\":{y}}}";
                EnqueueOrSend(_baseUrl + "/api/atak/designator", payload);
                return;
            }

            if (function == "SendSigint" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 3)
            {
                var callSign = args[0] ?? "Unknown";
                var x = args[1] ?? "0";
                var y = args[2] ?? "0";
                var bearing = args.Length > 3 ? (args[3] ?? "") : "";
                var payload = !string.IsNullOrEmpty(bearing) && double.TryParse(bearing, out var b)
                    ? $"{{\"mapId\":1,\"call_sign\":\"{EscapeJson(callSign)}\",\"pos_x\":{x},\"pos_y\":{y},\"bearing\":{b.ToString("R", System.Globalization.CultureInfo.InvariantCulture)}}}"
                    : $"{{\"mapId\":1,\"call_sign\":\"{EscapeJson(callSign)}\",\"pos_x\":{x},\"pos_y\":{y}}}";
                EnqueueOrSend(_baseUrl + "/api/atak/sigint", payload);
                return;
            }

            if (function == "Logistics.Update" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return;
                EnqueueOrSend(_baseUrl + "/api/logistics/update", json);
                return;
            }

            if (function == "Intel.Report" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return;
                EnqueueOrSend(_baseUrl + "/api/intel/report", json);
                return;
            }

            if (function == "IFF.Response" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return;
                EnqueueOrSend(_baseUrl + "/api/iff/respond", json);
                return;
            }

            if (function == "SendMarker" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var armaName = args[0] ?? "";
                var markerDataRaw = args[1] ?? "{}";
                var layerId = args.Length > 2 ? (args[2] ?? "1") : "1";
                var deleted = args.Length > 3 && (args[3] ?? "") == "1";
                var payload = deleted
                    ? "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\",\"deleted\":true}"
                    : "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\",\"markerData\":" + markerDataRaw + "}";
                EnqueueOrSend(_baseUrl + "/api/atak/marker", payload);
                return;
            }

            if (function == "UploadImage" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var path = args[0] ?? "";
                var author = args.Length > 1 ? (args[1] ?? "Unknown") : "Unknown";
                if (string.IsNullOrWhiteSpace(path)) return;
                try
                {
                    if (!File.Exists(path)) return;
                    var fi = new FileInfo(path);
                    if (fi.Length > 5 * 1024 * 1024) return;
                    var bytes = File.ReadAllBytes(path);
                    var multipart = new MultipartFormDataContent();
                    multipart.Add(new StringContent("1"), "mapId");
                    multipart.Add(new StringContent(author), "author");
                    var fileContent = new ByteArrayContent(bytes);
                    fileContent.Headers.ContentType = new MediaTypeHeaderValue("image/jpeg");
                    multipart.Add(fileContent, "photo", Path.GetFileName(path) ?? "photo.jpg");
                    _ = HttpClient.PostAsync(_baseUrl + "/api/intel/photos", multipart);
                }
                catch { }
                return;
            }

            if (function == "SendFlightManifest" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                EnqueueOrSend(_baseUrl + "/api/atak/flight-manifest", json);
                return;
            }

            if (function == "SendCASState" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var id = args[0] ?? "";
                var status = args[1] ?? "";
                var payload = "{\"status\":\"" + EscapeJson(status) + "\"}";
                EnqueueOrSend(_baseUrl + "/api/cas/" + Uri.EscapeDataString(id) + "/status", payload);
                return;
            }
            if (function == "SendCASAck" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var id = args[0] ?? "";
                EnqueueOrSend(_baseUrl + "/api/cas/" + Uri.EscapeDataString(id) + "/ack", "{}");
                return;
            }
            if (function == "SendCASCheckLine" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 4)
            {
                var id = args[0] ?? "";
                var line = args[1] ?? "";
                var isChecked = (args[2] ?? "true").ToLowerInvariant() == "true";
                var checkedBy = args[3] ?? "Pilot";
                var payload = "{\"line\":\"" + EscapeJson(line) + "\",\"checked\":" + (isChecked ? "true" : "false") + ",\"checkedBy\":\"" + EscapeJson(checkedBy) + "\"}";
                EnqueueOrSend(_baseUrl + "/api/cas/" + Uri.EscapeDataString(id) + "/check-line", payload);
                return;
            }

            if (function == "SyncLaserCode" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 3)
            {
                var callSign = args[0] ?? "Unknown";
                var laserCode = args[1] ?? "1688";
                var posX = args.Length > 2 ? (args[2] ?? "0") : "0";
                var posY = args.Length > 3 ? (args[3] ?? "0") : "0";
                var status = args.Length > 4 ? (args[4] ?? "ACTIVE") : "ACTIVE";
                var payload = $"{{\"mapId\":1,\"call_sign\":\"{EscapeJson(callSign)}\",\"laser_code\":\"{EscapeJson(laserCode)}\",\"pos_x\":{posX},\"pos_y\":{posY},\"status\":\"{EscapeJson(status)}\"}}";
                EnqueueOrSend(_baseUrl + "/api/atak/laser-codes", payload);
                return;
            }

            if (function == "UploadReconImage" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var path = args[0] ?? "";
                var author = args[1] ?? "Unknown";
                if (string.IsNullOrWhiteSpace(path)) return;
                try
                {
                    if (!File.Exists(path)) return;
                    var fi = new FileInfo(path);
                    if (fi.Length > 5 * 1024 * 1024) return;
                    var bytes = File.ReadAllBytes(path);
                    var multipart = new MultipartFormDataContent();
                    multipart.Add(new StringContent("1"), "mapId");
                    multipart.Add(new StringContent(author), "author");
                    var posX = args.Length > 2 ? (args[2] ?? "") : "";
                    var posY = args.Length > 3 ? (args[3] ?? "") : "";
                    var posZ = args.Length > 4 ? (args[4] ?? "") : "";
                    var grid = args.Length > 5 ? (args[5] ?? "") : "";
                    var heading = args.Length > 6 ? (args[6] ?? "") : "";
                    var altitude = args.Length > 7 ? (args[7] ?? "") : "";
                    var caption = args.Length > 8 ? (args[8] ?? "") : "";
                    var unitName = args.Length > 9 ? (args[9] ?? "") : "";
                    var side = args.Length > 10 ? (args[10] ?? "WEST") : "WEST";
                    var missionId = args.Length > 11 ? (args[11] ?? "") : "";
                    var device = args.Length > 12 ? (args[12] ?? "CTAB") : "CTAB";
                    var capturedAt = args.Length > 13 ? (args[13] ?? "") : "";
                    if (!string.IsNullOrEmpty(posX)) multipart.Add(new StringContent(posX), "pos_x");
                    if (!string.IsNullOrEmpty(posY)) multipart.Add(new StringContent(posY), "pos_y");
                    if (!string.IsNullOrEmpty(posZ)) multipart.Add(new StringContent(posZ), "pos_z");
                    if (!string.IsNullOrEmpty(grid)) multipart.Add(new StringContent(grid), "grid_ref");
                    if (!string.IsNullOrEmpty(heading)) multipart.Add(new StringContent(heading), "heading");
                    if (!string.IsNullOrEmpty(altitude)) multipart.Add(new StringContent(altitude), "altitude");
                    if (!string.IsNullOrEmpty(caption)) multipart.Add(new StringContent(caption), "caption");
                    if (!string.IsNullOrEmpty(unitName)) multipart.Add(new StringContent(unitName), "unit_name");
                    multipart.Add(new StringContent(side), "side");
                    if (!string.IsNullOrEmpty(missionId)) multipart.Add(new StringContent(missionId), "mission_id");
                    multipart.Add(new StringContent(device), "device_type");
                    if (!string.IsNullOrEmpty(capturedAt)) multipart.Add(new StringContent(capturedAt), "capturedAt");
                    var fileContent = new ByteArrayContent(bytes);
                    fileContent.Headers.ContentType = new MediaTypeHeaderValue("image/jpeg");
                    multipart.Add(fileContent, "image", Path.GetFileName(path) ?? "recon.jpg");
                    _ = HttpClient.PostAsync(_baseUrl + "/api/recon/images", multipart);
                }
                catch { }
                return;
            }

            if (function == "PilotResponse" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var callsign = args[0] ?? "";
                var status = args[1] ?? "";
                if (string.IsNullOrWhiteSpace(callsign) || string.IsNullOrWhiteSpace(status)) return;
                var payload = "{\"pilot_status\":\"" + EscapeJson(status) + "\"}";
                try
                {
                    var url = _baseUrl + "/api/atak/air-assets/" + Uri.EscapeDataString(callsign) + "/pilot-status";
                    var content = new StringContent(payload, Encoding.UTF8, "application/json");
                    var request = new HttpRequestMessage(HttpMethod.Patch, url) { Content = content };
                    _ = HttpClient.SendAsync(request);
                }
                catch
                {
                    if (PendingPosts.Count < MaxQueueSize)
                    {
                        var url = _baseUrl + "/api/atak/air-assets/" + Uri.EscapeDataString(callsign) + "/pilot-status";
                        PendingPosts.Enqueue((url, payload));
                        EnsureDrainTimer();
                    }
                }
                return;
            }
        }
        catch
        {
            // ignore for now
        }
    }

    private static void SendChatMessage(string body)
    {
        if (string.IsNullOrEmpty(_baseUrl)) return;
        try
        {
            var payload = $"{{\"mapId\":1,\"author\":\"COMSPEC Overwatch\",\"body\":\"{EscapeJson(body)}\"}}";
            EnqueueOrSend(_baseUrl + "/api/chat", payload);
        }
        catch
        {
            // ignore
        }
    }

    private static string EscapeJson(string s)
    {
        if (string.IsNullOrEmpty(s)) return s;
        return s.Replace("\\", "\\\\").Replace("\"", "\\\"").Replace("\n", "\\n").Replace("\r", "\\r");
    }
}
