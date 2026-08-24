using System.Collections.Concurrent;
using System.Globalization;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using System.Threading;

namespace COMSPECExtension;

public static class Extension
{
    // Timeout HttpClient = plafond global ; les appels sync utilisent aussi un CTS dédié.
    // 3 s était trop juste pour TLS+DNS sur le premier appel (redeem / whoami).
    private const int SyncTimeoutSeconds = 8;
    private const int UploadTimeoutSeconds = 60;
    private static string _baseUrl = "";
    private static string _apiKey = "";
    /// <summary>Tenant issu du redeem / Connect (requis par client-init côté portail).</summary>
    private static string _tenantId = "";
    /// <summary>SteamID64 mémorisé (liaison / Connect / UpdatePosition) — identité côté DLL, pas seulement SQF.</summary>
    private static string _steamUid = "";
    /// <summary>Version du mod Overwatch (CfgPatches) — remontée dans les journal Activité.</summary>
    private static string _modVersion = "";
    /// <summary>Jeton de session court renvoyé par client-init (anti-spoof serveur).</summary>
    private static string _sessionToken = "";
    /// <summary>ID BFT (military_id) lié à l’indicatif — renvoyé par client-init / profil.</summary>
    private static string _militaryId = "";
    /// <summary>Indicatif tactique confirmé par Athena (client-init).</summary>
    private static string _callSign = "";
    private static readonly HttpClient HttpClient = new() { Timeout = TimeSpan.FromSeconds(SyncTimeoutSeconds) };
    /// <summary>Client dédié aux photos (PNG volumineux + liaison dégradée).</summary>
    private static readonly HttpClient UploadHttpClient = new() { Timeout = TimeSpan.FromSeconds(UploadTimeoutSeconds) };
    private static readonly ConcurrentQueue<(string Url, string Body)> PendingPosts = new();
    private static readonly int MaxQueueSize = 500;
    private static readonly object QueueDrainLock = new();
    /// <summary>
    /// Dernière position en attente de retry (coupure / 429). Coalescée : une seule entrée,
    /// toujours la plus récente — évite de rejouer des positions périmées à la reconnexion.
    /// </summary>
    private static (string Url, string Body)? _coalescedPosition;
    private static readonly object CoalescedPositionLock = new();
    private static System.Threading.Timer? _drainTimer;
    private static readonly object DrainTimerLock = new();
    /// <summary>Période de flush des positions coalescées (250–2000 ms), pilotée par le profil réseau SQF.</summary>
    private static int _drainPeriodMs = 1000;
    /// <summary>Backoff après HTTP 429 (Ticks UTC). Pendant ce délai : pas d’envoi position / drain réduit.</summary>
    private static long _rateLimitUntilTicks;
    private static int _rateLimitBackoffSec = 2;
    private static long _lastRateLimitCbTicks;
    private static long _lastAuth401ReauthTicks;
    private static long _terrainChunkBlockedUntilTicks;

    /// <summary>
    /// Dernier échec d'envoi fire-and-forget (position, tchat, marqueurs...) : ces requêtes ne
    /// remontent jamais d'erreur à SQF (retry silencieux via PendingPosts), donc sans ce
    /// compteur un échec serveur persistant (403/422/500) est invisible même en debug.
    /// 0 = code réseau (pas de réponse HTTP, ex. DNS/TLS/timeout).
    /// </summary>
    private static int _lastPostErrorCode;
    private static string _lastPostErrorPath = "";
    private static long _lastPostErrorAtTicks;

    /// <summary>Chemin résolu du journal fichier COMSPEC Overwatch (une session Arma = un fichier).</summary>
    private static string? _resolvedLogPath;
    private static bool _sessionLogInitialized;
    private const int DefaultRetainedLogFiles = 12;
    private static readonly object LogFileLock = new();

    private static long _lastPostErrorCbTicks;

    // --- Photo sidecar (queue + watcher) : callExtension ne fait que signaler ---
    private sealed class ReconPhotoJob
    {
        public string RawPath = "";
        public string Author = "Unknown";
        public string?[] Meta = Array.Empty<string?>();
        public string DedupKey = "";
        public bool NewestFallback;
        public DateTime EnqueuedUtc = DateTime.UtcNow;
        /// <summary>recon = ATAK Photos ; sse_face = photo visage SEEK.</summary>
        public string UploadKind = "recon";
        public string SsePersonId = "";
        public string SseAngle = "face";
    }

    private static readonly ConcurrentQueue<ReconPhotoJob> PhotoJobs = new();
    private static readonly ConcurrentDictionary<string, long> PhotoDedupTicks = new(StringComparer.OrdinalIgnoreCase);
    private static readonly object ScreenshotWatcherLock = new();
    private static int _photoWorkerRunning;
    private static bool _screenshotWatchersStarted;
    private static long _lastWatcherAttemptTicks;
    private static readonly List<FileSystemWatcher> ScreenshotWatchers = new();
    private static readonly ConcurrentDictionary<string, byte> WatcherDebounce = new(StringComparer.OrdinalIgnoreCase);
    private const int PhotoDedupTtlSeconds = 300;
    private const int PhotoQueueMax = 64;
    private const int WatcherMinAgeSeconds = 2;   // ignorer fichiers déjà présents au démarrage (sauf très récents)
    private const int WatcherMaxAgeSeconds = 120; // ne pas remonter des captures anciennes

    /// <summary>Dernière pose connue (UpdatePosition) pour uploads déclenchés par le watcher.</summary>
    private static string _lastPhotoAuthor = "";
    private static string _lastPhotoPosX = "";
    private static string _lastPhotoPosY = "";
    private static string _lastPhotoPosZ = "";
    private static string _lastPhotoHeading = "";
    private static string _lastPhotoGrid = "";
    private static long _screenshotWatchersStartedTicks;
    private static bool _screenshotQuotaEnsured;

    private static void NotePostError(int code, string url)
    {
        _lastPostErrorCode = code;
        string path;
        try { path = new Uri(url).AbsolutePath; } catch { path = url; }
        _lastPostErrorPath = path.Replace("|", "/");
        System.Threading.Interlocked.Exchange(ref _lastPostErrorAtTicks, DateTime.UtcNow.Ticks);

        // Remonte vers le journal SQF (anti-spam : 1 callback / 3 s max).
        var now = DateTime.UtcNow.Ticks;
        var prev = System.Threading.Interlocked.Read(ref _lastPostErrorCbTicks);
        if (now - prev < TimeSpan.FromSeconds(3).Ticks) return;
        if (System.Threading.Interlocked.CompareExchange(ref _lastPostErrorCbTicks, now, prev) != prev) return;
        InvokeCallback("PostError", $"{code}|{_lastPostErrorPath}|0");
    }

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

    private static void ApplyModVersion(string? raw)
    {
        var v = (raw ?? "").Trim();
        if (v.Length == 0 || v.Length > 48) return;
        // Évite d’écraser une version déjà connue par une valeur vide / bruit.
        _modVersion = v;
    }

    private static string ModVersionJsonFragment()
    {
        if (_modVersion.Length == 0) return "";
        return $",\"mod_version\":\"{EscapeJson(_modVersion)}\"";
    }

    /// <summary>
    /// Corps JSON client-init (mapId + tenant_id + steam_uid si connus).
    /// Sans tenant le portail peut répondre 403 (tenant_context_required).
    /// </summary>
    private static string BuildClientInitBody()
    {
        var sb = new StringBuilder("{\"mapId\":1");
        if (_tenantId.Length > 0)
        {
            if (long.TryParse(_tenantId, out var tid) && tid > 0)
                sb.Append(",\"tenant_id\":").Append(tid.ToString(System.Globalization.CultureInfo.InvariantCulture));
            else
                sb.Append(",\"tenant_id\":\"").Append(EscapeJson(_tenantId)).Append('"');
        }
        if (_steamUid.Length > 0)
            sb.Append(",\"steam_uid\":\"").Append(EscapeJson(_steamUid)).Append('"');
        if (_modVersion.Length > 0)
            sb.Append(",\"mod_version\":\"").Append(EscapeJson(_modVersion)).Append('"');
        sb.Append('}');
        return sb.ToString();
    }

    /// <summary>Corps JSON disconnect (mapId + tenant + indicatif + steam optionnels).</summary>
    private static string BuildDisconnectBody(string callSign)
    {
        var sb = new StringBuilder("{\"mapId\":1");
        if (_tenantId.Length > 0)
        {
            if (long.TryParse(_tenantId, out var tid) && tid > 0)
                sb.Append(",\"tenant_id\":").Append(tid.ToString(System.Globalization.CultureInfo.InvariantCulture));
            else
                sb.Append(",\"tenant_id\":\"").Append(EscapeJson(_tenantId)).Append('"');
        }
        if (!string.IsNullOrEmpty(callSign))
            sb.Append(",\"call_sign\":\"").Append(EscapeJson(callSign)).Append('"');
        if (_steamUid.Length > 0)
            sb.Append(",\"steam_uid\":\"").Append(EscapeJson(_steamUid)).Append('"');
        if (_sessionToken.Length > 0)
            sb.Append(",\"session_token\":\"").Append(EscapeJson(_sessionToken)).Append('"');
        if (_modVersion.Length > 0)
            sb.Append(",\"mod_version\":\"").Append(EscapeJson(_modVersion)).Append('"');
        sb.Append('}');
        return sb.ToString();
    }

    /// <summary>
    /// POST /api/atak/client-init synchrone : valide la clé + le tenant (+ Steam lié) avant OK|connected.
    /// Mémorise session_token si le portail en renvoie un.
    /// </summary>
    private static string VerifyClientInitSync()
    {
        var baseUrl = _baseUrl;
        if (string.IsNullOrEmpty(baseUrl))
            return "ERR|not_connected";
        if (!TryBuildRequestUri(baseUrl, "/api/atak/client-init", out var initUri, out var initErr) || initUri is null)
            return "ERR|" + initErr;

        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
        try
        {
            var resp = SendJsonPost(initUri.AbsoluteUri, BuildClientInitBody(), cts.Token);
            var code = (int)resp.StatusCode;
            var respBody = "";
            try { respBody = ReadContentUtf8(resp, cts.Token); } catch { /* ignore */ }

            if (resp.IsSuccessStatusCode)
            {
                TryRememberSessionFromInitBody(respBody);
                return "OK|connected";
            }

            if (code is 401)
                return "ERR|unauthorized";
            if (code is 403)
            {
                var modBlock = MapModAccessBlockError(respBody);
                if (modBlock != null)
                    return modBlock;
                if (respBody.Contains("steam_not_linked", StringComparison.OrdinalIgnoreCase))
                    return "ERR|steam_not_linked";
                if (respBody.Contains("account_disabled", StringComparison.OrdinalIgnoreCase))
                    return "ERR|account_disabled";
                if (respBody.Contains("tenant_context_required", StringComparison.OrdinalIgnoreCase)
                    || respBody.Contains("tenant", StringComparison.OrdinalIgnoreCase))
                    return "ERR|tenant_required";
                return "ERR|unauthorized";
            }
            if (code == 400 && respBody.Contains("invalid_steam", StringComparison.OrdinalIgnoreCase))
                return "ERR|invalid_steam";
            if (code == 404) return "ERR|not_found";
            if (code == 503) return "ERR|http_503";
            return "ERR|http_" + code;
        }
        catch (Exception ex)
        {
            return FormatCaughtError(ex);
        }
    }

    private static void TryRememberSessionFromInitBody(string respBody)
    {
        if (string.IsNullOrWhiteSpace(respBody) || respBody[0] != '{') return;
        try
        {
            using var doc = JsonDocument.Parse(respBody);
            var root = doc.RootElement;
            if (root.TryGetProperty("session_token", out var tok))
            {
                var t = (tok.GetString() ?? "").Trim();
                if (t.Length >= 32)
                    _sessionToken = t;
            }
            if (root.TryGetProperty("steam_uid", out var su))
            {
                var s = (su.GetString() ?? "").Trim();
                if (TryNormalizeSteamUid(s, out var sn))
                    _steamUid = sn;
            }
            // Indicatif + ID BFT liés (même identité TOC / carte / terminal)
            var cs = "";
            if (root.TryGetProperty("call_sign", out var csEl))
                cs = (csEl.GetString() ?? "").Trim();
            if (cs.Length == 0 && root.TryGetProperty("callsign", out var cs2))
                cs = (cs2.GetString() ?? "").Trim();
            if (cs.Length > 0)
                _callSign = cs;
            var mid = "";
            if (root.TryGetProperty("military_id", out var midEl))
                mid = (midEl.GetString() ?? "").Trim();
            if (mid.Length == 0 && root.TryGetProperty("bft_id", out var bftEl))
                mid = (bftEl.GetString() ?? "").Trim();
            if (mid.Length > 0)
                _militaryId = mid;
            if (_militaryId.Length > 0 || _callSign.Length > 0)
            {
                // SQF : COMSPEC_MilitaryId / éventuel sync indicatif
                InvokeCallback("BftIdentity", _callSign + "\t" + _militaryId);
            }
        }
        catch { /* ignore */ }
    }

    /// <summary>
    /// POST /api/atak/disconnect synchrone (timeout court : le process Arma peut mourir tout de suite).
    /// </summary>
    private static string VerifyDisconnectSync(string callSign)
    {
        var baseUrl = _baseUrl;
        if (string.IsNullOrEmpty(baseUrl))
            return "ERR|not_connected";
        if (_apiKey.Length == 0)
            return "ERR|unauthorized";
        if (!TryBuildRequestUri(baseUrl, "/api/atak/disconnect", out var discUri, out var discErr) || discUri is null)
            return "ERR|" + discErr;

        // Timeout court : quit / Ended doit aboutir avant la mort du process.
        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(3));
        try
        {
            var resp = SendJsonPost(discUri.AbsoluteUri, BuildDisconnectBody(callSign), cts.Token);
            if (resp.IsSuccessStatusCode)
                return "OK|disconnected";
            var code = (int)resp.StatusCode;
            if (code is 401 or 403) return "ERR|unauthorized";
            if (code == 404) return "ERR|not_found";
            return "ERR|http_" + code;
        }
        catch (Exception ex)
        {
            return FormatCaughtError(ex);
        }
    }

    /// <summary>
    /// Ancien chemin async (RvExtensionArgsImpl) : notifie SQF sans bloquer.
    /// Préférer VerifyClientInitSync sur Connect.
    /// </summary>
    private static void StartClientInitAsync()
    {
        var baseUrl = _baseUrl;
        if (string.IsNullOrEmpty(baseUrl)) return;
        if (!TryBuildRequestUri(baseUrl, "/api/atak/client-init", out var initUri, out _) || initUri is null)
            return;

        try
        {
            var initReq = new HttpRequestMessage(HttpMethod.Post, initUri)
            {
                Content = JsonContent(BuildClientInitBody())
            };
            AttachApiKeyHeader(initReq);
            _ = HttpClient.SendAsync(initReq).ContinueWith(t =>
            {
                try { initReq.Dispose(); } catch { /* ignore */ }
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
                        try { TryRememberSessionFromInitBody(ReadContentUtf8(resp, CancellationToken.None)); } catch { /* ignore */ }
                        InvokeCallback("Connected", baseUrl);
                    }
                    else if ((int)resp.StatusCode == 401)
                        InvokeCallback("Error", "Acces refuse (cle Athena)");
                    else if ((int)resp.StatusCode == 403)
                    {
                        var body403 = "";
                        try { body403 = ReadContentUtf8(resp, CancellationToken.None); } catch { /* ignore */ }
                        var modBlock = MapModAccessBlockError(body403);
                        if (modBlock == "ERR|mod_steam_blocked")
                            InvokeCallback("Error", "Acces mod refuse — identifiant Steam restreint");
                        else if (modBlock == "ERR|mod_ip_blocked")
                            InvokeCallback("Error", "Acces mod refuse — adresse reseau restreinte");
                        else
                            InvokeCallback("Error", "Communaute Athena manquante — refaites la liaison");
                    }
                    else
                        InvokeCallback("Error", "HTTP " + (int)resp.StatusCode);
                }
                catch
                {
                    InvokeCallback("Error", "Portail injoignable");
                }
            });
        }
        catch
        {
            // best effort
        }
    }

    private static void EnsureDrainTimer()
    {
        var period = Math.Clamp(_drainPeriodMs, 250, 2000);
        lock (DrainTimerLock)
        {
            if (_drainTimer == null)
                _drainTimer = new System.Threading.Timer(_ => DrainQueue(), null, period, period);
            else
                _drainTimer.Change(period, period);
        }
    }

    private static string ApplyTelemetryBatch(string? raw)
    {
        var s = (raw ?? "").Trim().Replace(',', '.');
        if (!double.TryParse(s, System.Globalization.NumberStyles.Float,
                System.Globalization.CultureInfo.InvariantCulture, out var parsed))
            return "ERR|invalid_batch";
        // SQF envoie des millisecondes ; un profil « 1 » (seconde) reste accepté.
        var ms = parsed <= 10 ? (int)Math.Round(parsed * 1000.0) : (int)Math.Round(parsed);
        ms = Math.Clamp(ms, 250, 2000);
        _drainPeriodMs = ms;
        EnsureDrainTimer();
        return "OK|" + ms.ToString(System.Globalization.CultureInfo.InvariantCulture);
    }

    private static bool IsPositionEndpoint(string url) =>
        url.Contains("/api/atak/position", StringComparison.OrdinalIgnoreCase);

    /// <summary>
    /// Routes ATAK authentifiées où un 401 transitoire (clé / session) justifie un re-client-init.
    /// </summary>
    private static bool IsAuthSensitiveEndpoint(string url)
    {
        if (string.IsNullOrWhiteSpace(url)) return false;
        return url.Contains("/api/atak/position", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/atak/marker", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/atak/markers", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/atak/client-init", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/atak/explosive-timers", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/recon/", StringComparison.OrdinalIgnoreCase);
    }

    private static void MaybeReauthAfter401(string url)
    {
        if (_apiKey.Length == 0 || string.IsNullOrEmpty(_baseUrl)) return;
        if (!IsAuthSensitiveEndpoint(url)) return;
        var now = DateTime.UtcNow.Ticks;
        if (now - System.Threading.Interlocked.Read(ref _lastAuth401ReauthTicks) <= TimeSpan.FromSeconds(30).Ticks)
            return;
        System.Threading.Interlocked.Exchange(ref _lastAuth401ReauthTicks, now);
        _ = Task.Run(() =>
        {
            try { VerifyClientInitSync(); } catch { /* ignore */ }
        });
    }

    /// <summary>
    /// Retry après échec / backoff. Positions = slot unique (dernière gagne) ;
    /// autres posts = FIFO bornée.
    /// </summary>
    private static void EnqueueForRetry(string url, string jsonBody)
    {
        if (IsPositionEndpoint(url))
        {
            lock (CoalescedPositionLock)
                _coalescedPosition = (url, jsonBody);
            EnsureDrainTimer();
            return;
        }
        if (PendingPosts.Count < MaxQueueSize)
        {
            PendingPosts.Enqueue((url, jsonBody));
            EnsureDrainTimer();
        }
    }

    private static bool TryTakeCoalescedPosition(out (string Url, string Body) item)
    {
        lock (CoalescedPositionLock)
        {
            if (_coalescedPosition is { } pos)
            {
                _coalescedPosition = null;
                item = pos;
                return true;
            }
        }
        item = default;
        return false;
    }

    private static bool IsRateLimitedNow()
    {
        return DateTime.UtcNow.Ticks < System.Threading.Interlocked.Read(ref _rateLimitUntilTicks);
    }

    private static int ParseRetryAfterSeconds(HttpResponseMessage? response, int fallback)
    {
        if (response == null) return fallback;
        try
        {
            var ra = response.Headers.RetryAfter;
            if (ra?.Delta is { } delta)
                return Math.Clamp((int)Math.Ceiling(delta.TotalSeconds), 1, 120);
            if (ra?.Date is { } date)
            {
                var sec = (int)Math.Ceiling((date.UtcDateTime - DateTime.UtcNow).TotalSeconds);
                if (sec > 0) return Math.Clamp(sec, 1, 120);
            }
        }
        catch { /* ignore */ }
        try
        {
            var body = response.Content.ReadAsStringAsync().GetAwaiter().GetResult();
            if (!string.IsNullOrWhiteSpace(body))
            {
                using var doc = JsonDocument.Parse(body);
                if (doc.RootElement.TryGetProperty("retry_after", out var prop)
                    && prop.TryGetInt32(out var n)
                    && n > 0)
                    return Math.Clamp(n, 1, 120);
            }
        }
        catch { /* ignore */ }
        return fallback;
    }

    private static void NoteRateLimited(HttpResponseMessage? response = null)
    {
        var next = Math.Min(_rateLimitBackoffSec * 2, 60);
        if (_rateLimitBackoffSec < 2) _rateLimitBackoffSec = 2;
        var delaySec = ParseRetryAfterSeconds(response, _rateLimitBackoffSec);
        _rateLimitBackoffSec = Math.Max(next, delaySec);
        var until = DateTime.UtcNow.AddSeconds(delaySec).Ticks;
        System.Threading.Interlocked.Exchange(ref _rateLimitUntilTicks, until);
        var now = DateTime.UtcNow.Ticks;
        // Évite de spammer le callback SQF (max ~1 / 3 s).
        if (now - System.Threading.Interlocked.Read(ref _lastRateLimitCbTicks) > TimeSpan.FromSeconds(3).Ticks)
        {
            System.Threading.Interlocked.Exchange(ref _lastRateLimitCbTicks, now);
            InvokeCallback("RateLimited", delaySec.ToString(System.Globalization.CultureInfo.InvariantCulture));
        }
    }

    private static void NoteRateLimitCleared()
    {
        if (_rateLimitBackoffSec <= 2 && System.Threading.Interlocked.Read(ref _rateLimitUntilTicks) == 0)
            return;
        _rateLimitBackoffSec = 2;
        System.Threading.Interlocked.Exchange(ref _rateLimitUntilTicks, 0);
        InvokeCallback("RateLimitClear", "");
    }

    private static void HandlePostResponse(HttpResponseMessage? response, string url, string jsonBody)
    {
        if (response == null)
        {
            NotePostError(0, url);
            EnqueueForRetry(url, jsonBody);
            return;
        }
        if ((int)response.StatusCode == 429)
        {
            NoteRateLimited(response);
            // Position : garder la dernière pour flush après backoff. Autres : ne pas
            // ré-enfiler (évite boucle 429 → lag) — le SQF renverra si besoin.
            if (IsPositionEndpoint(url))
                EnqueueForRetry(url, jsonBody);
            return;
        }
        if (response.IsSuccessStatusCode)
        {
            NoteRateLimitCleared();
            return;
        }
        var code = (int)response.StatusCode;
        NotePostError(code, url);
        // 401 : clé / session temporairement incohérente — retenter client-init
        // (position, marqueur, recon… ; le chat synchrone peut encore passer).
        if (code == 401)
        {
            MaybeReauthAfter401(url);
            if (url.Contains("/terrain/chunk", StringComparison.OrdinalIgnoreCase))
                System.Threading.Interlocked.Exchange(
                    ref _terrainChunkBlockedUntilTicks,
                    DateTime.UtcNow.AddSeconds(90).Ticks);
        }
        if (ShouldRetryStatusCode(code))
            EnqueueForRetry(url, jsonBody);
    }

    private static bool ShouldRetryStatusCode(int code)
    {
        if (code <= 0) return true;
        if (code >= 500) return true;
        return code is 408 or 425;
    }

    /// <summary>Envoi synchrone d’un item de file. false = stop drain (429).</summary>
    private static bool TrySendQueuedPost((string Url, string Body) item)
    {
        try
        {
            using var req = new HttpRequestMessage(HttpMethod.Post, item.Url)
            {
                Content = JsonContent(item.Body)
            };
            AttachApiKeyHeader(req);
            var response = HttpClient.SendAsync(req).GetAwaiter().GetResult();
            if ((int)response.StatusCode == 429)
            {
                NoteRateLimited(response);
                EnqueueForRetry(item.Url, item.Body);
                return false;
            }
            if (!response.IsSuccessStatusCode)
            {
                var code = (int)response.StatusCode;
                NotePostError(code, item.Url);
                if (ShouldRetryStatusCode(code))
                    EnqueueForRetry(item.Url, item.Body);
            }
            else
            {
                NoteRateLimitCleared();
            }
        }
        catch
        {
            NotePostError(-1, item.Url);
            EnqueueForRetry(item.Url, item.Body);
        }
        return true;
    }

    private static void DrainQueue()
    {
        if (string.IsNullOrEmpty(_baseUrl)) return;
        if (IsRateLimitedNow()) return;
        lock (QueueDrainLock)
        {
            const int maxPerTick = 3;
            var sent = 0;
            // Toujours la position coalescée en premier (freshness > ordre FIFO).
            var hadCoalesced = TryTakeCoalescedPosition(out var posItem);
            if (hadCoalesced)
            {
                if (!TrySendQueuedPost(posItem))
                    return;
                sent++;
            }
            while (sent < maxPerTick && PendingPosts.TryDequeue(out var item))
            {
                // Positions résiduelles dans l’ancienne FIFO : si le slot coalescé
                // n’existait pas, on coalesce (dernière gagne) ; sinon ce sont des périmées.
                if (IsPositionEndpoint(item.Url))
                {
                    if (!hadCoalesced)
                        EnqueueForRetry(item.Url, item.Body);
                    continue;
                }
                if (!TrySendQueuedPost(item))
                    break;
                sent++;
            }
            // Flush d’une position uniquement issue du balayage FIFO (si budget restant).
            if (!hadCoalesced && sent < maxPerTick && TryTakeCoalescedPosition(out var fromFifo))
                TrySendQueuedPost(fromFifo);
        }
    }

    private static string HandleTerrainChunk(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl))
            return FormatAtakExtArray("ERROR", "not_connected");
        if (args.Length < 1)
            return FormatAtakExtArray("ERROR", "empty");
        if (DateTime.UtcNow.Ticks < System.Threading.Interlocked.Read(ref _terrainChunkBlockedUntilTicks))
            return FormatAtakExtArray("ERROR", "unauthorized");
        var json = args[0] ?? "";
        if (string.IsNullOrWhiteSpace(json) || json.Length < 8)
            return FormatAtakExtArray("ERROR", "empty");
        EnqueueOrSend(_baseUrl + "/api/atak/terrain/chunk", EnrichAtakPayload(json));
        return FormatAtakExtArray("OK", "queued");
    }

    private static void EnqueueOrSend(string url, string jsonBody)
    {
        // Positions : toujours coalescer (dernière gagne) puis flush périodique.
        // Un POST immédiat par callExtension ferait exploser la charge avec N joueurs.
        if (IsPositionEndpoint(url))
        {
            EnqueueForRetry(url, jsonBody);
            return;
        }
        if (IsRateLimitedNow())
        {
            // Position : coalescer pour flush dès fin de backoff. Autres : file limitée.
            if (IsPositionEndpoint(url))
                EnqueueForRetry(url, jsonBody);
            else if (PendingPosts.Count < MaxQueueSize)
            {
                PendingPosts.Enqueue((url, jsonBody));
                EnsureDrainTimer();
            }
            return;
        }
        try
        {
            var req = new HttpRequestMessage(HttpMethod.Post, url)
            {
                Content = JsonContent(jsonBody)
            };
            AttachApiKeyHeader(req);
            _ = HttpClient.SendAsync(req).ContinueWith(t =>
            {
                try { req.Dispose(); } catch { /* ignore */ }
                HttpResponseMessage? resp = null;
                if (t.Status == TaskStatus.RanToCompletion)
                    resp = t.Result;
                HandlePostResponse(resp, url, jsonBody);
            });
        }
        catch
        {
            NotePostError(-1, url);
            EnqueueForRetry(url, jsonBody);
        }
    }

    private static StringContent JsonContent(string jsonBody)
    {
        var content = new StringContent(jsonBody ?? "", Encoding.UTF8);
        content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
        return content;
    }

    /// <summary>GET avec clé API sur la requête (jamais DefaultRequestHeaders — race Native AOT).</summary>
    private static HttpResponseMessage SendGet(Uri uri, CancellationToken token)
    {
        using var req = new HttpRequestMessage(HttpMethod.Get, uri);
        AttachApiKeyHeader(req);
        return HttpClient.SendAsync(req, token).GetAwaiter().GetResult();
    }

    private static HttpResponseMessage SendGet(string url, CancellationToken token)
    {
        using var req = new HttpRequestMessage(HttpMethod.Get, url);
        AttachApiKeyHeader(req);
        return HttpClient.SendAsync(req, token).GetAwaiter().GetResult();
    }

    /// <summary>
    /// Cache des GET périodiques (marqueurs, ordres, chat, CAS…).
    /// Le thread jeu Arma ne doit jamais attendre le round-trip HTTP : chaque hitch
    /// de 50–500 ms (jusqu’à SyncTimeoutSeconds si Athena est lent) est un micro-freeze.
    /// </summary>
    private sealed class PollGetSlot
    {
        public volatile string Result = "OK|";
        public long FetchedAtMs;
        public int InFlight;
    }

    private static readonly ConcurrentDictionary<string, PollGetSlot> PollGetCache = new();

    /// <summary>
    /// Retourne tout de suite le dernier résultat connu et rafraîchit en arrière-plan.
    /// Premier appel : "OK|" vide (le SQF ignore déjà les corps vides / non-OK).
    /// En cas d’erreur réseau après un succès, on conserve le dernier OK.
    /// </summary>
    private static string ServePollGet(string cacheKey, string url, Func<string, int, string> format)
    {
        if (string.IsNullOrEmpty(url))
            return "ERR|invalid_url";

        var slot = PollGetCache.GetOrAdd(cacheKey, static _ => new PollGetSlot());
        var now = Environment.TickCount64;
        const int minRefreshMs = 1200;
        var stale = slot.FetchedAtMs == 0 || (now - slot.FetchedAtMs) >= minRefreshMs;
        if (stale && Interlocked.CompareExchange(ref slot.InFlight, 1, 0) == 0)
        {
            ThreadPool.QueueUserWorkItem(_ =>
            {
                try
                {
                    using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
                    using var resp = SendGet(url, cts.Token);
                    var body = ReadContentUtf8(resp, cts.Token);
                    var formatted = format(body, (int)resp.StatusCode);
                    if (formatted.StartsWith("OK|", StringComparison.Ordinal) || slot.FetchedAtMs == 0)
                        slot.Result = formatted;
                    slot.FetchedAtMs = Environment.TickCount64;
                }
                catch
                {
                    if (slot.FetchedAtMs == 0)
                        slot.Result = "ERR|network";
                    slot.FetchedAtMs = Environment.TickCount64;
                }
                finally
                {
                    Interlocked.Exchange(ref slot.InFlight, 0);
                }
            });
        }

        return slot.Result;
    }

    private static string PollHttpErr(int code)
    {
        if (code == 404) return "ERR|not_found";
        if (code == 401 || code == 403) return "ERR|unauthorized";
        if (code == 503) return "ERR|unavailable";
        return "ERR|http_" + code;
    }

    private static string PollOkClipped(string payload)
    {
        payload ??= "";
        if (payload.Length > MaxOutputBytes - 4)
            payload = payload.Substring(0, MaxOutputBytes - 4);
        return "OK|" + payload;
    }

    private static HttpResponseMessage SendJsonPost(string url, string jsonBody, CancellationToken token)
    {
        using var req = new HttpRequestMessage(HttpMethod.Post, url)
        {
            Content = JsonContent(jsonBody)
        };
        AttachApiKeyHeader(req);
        return HttpClient.SendAsync(req, token).GetAwaiter().GetResult();
    }

    // --- MessageBox Win32 (alerte liaison Athena / note bêta) ---
    private const uint MbOk = 0x00000000;
    private const uint MbYesNo = 0x00000004;
    private const uint MbIconInformation = 0x00000040;
    private const uint MbSetForeground = 0x00010000;
    private const uint MbTopmost = 0x00040000;
    private const int IdNo = 7;

    [DllImport("user32.dll", CharSet = CharSet.Unicode, ExactSpelling = true)]
    private static extern int MessageBoxW(nint hWnd, string lpText, string lpCaption, uint uType);

    private static readonly object AthenaHelpLock = new();
    private static bool _athenaHelpShowing;

    private static readonly object BetaNoteLock = new();
    private static bool _betaNoteShowing;

    /// <summary>
    /// Affiche une alerte Windows (MessageBox) avec la marche à suivre pour lier Athena.
    /// Retour : OK|dismissed (Oui) | OK|dont_show (Non) | OK|busy (déjà affichée).
    /// </summary>
    private static string ShowAthenaLinkHelpMessageBox()
    {
        lock (AthenaHelpLock)
        {
            if (_athenaHelpShowing)
                return "OK|busy";
            _athenaHelpShowing = true;
        }

        try
        {
            const string title = "COMSPEC Overwatch — Lier mon compte Athena";
            const string body =
                "Pour apparaître sur la carte tactique et utiliser Overwatch, associez votre compte Athena.\n\n" +
                "Marche à suivre :\n" +
                "1. Ouvrez Athena dans un navigateur :\n" +
                "   https://athena.ttrd.fr/public\n" +
                "2. Connectez-vous à votre compte.\n" +
                "3. Générez un code de liaison (profil / liaison jeu).\n" +
                "4. En jeu : appuyez sur K (menu Overwatch) → Compte Athena → collez le code.\n\n" +
                "Si le code ne fonctionne pas : quittez complètement Arma, puis relancez.\n" +
                "Vous pouvez aussi vérifier l’adresse Athena dans les réglages du mod (Escape → Options → Modules complémentaires → COMSPEC Overwatch).\n\n" +
                "Oui = j’ai compris (ce message ne réapparaîtra pas avant la prochaine session)\n" +
                "Non = ne plus afficher ce message";

            var result = MessageBoxW(
                IntPtr.Zero,
                body,
                title,
                MbYesNo | MbIconInformation | MbSetForeground | MbTopmost);

            return result == IdNo ? "OK|dont_show" : "OK|dismissed";
        }
        catch
        {
            return "ERR|messagebox_failed";
        }
        finally
        {
            lock (AthenaHelpLock)
            {
                _athenaHelpShowing = false;
            }
        }
    }

    /// <summary>
    /// Note bêta publique — affichée au 1er lancement (menu principal).
    /// Retour : OK|ack | OK|busy | ERR|…
    /// </summary>
    private static string ShowBetaAccessNoteMessageBox()
    {
        lock (BetaNoteLock)
        {
            if (_betaNoteShowing)
                return "OK|busy";
            _betaNoteShowing = true;
        }

        try
        {
            var custom = BetaNoticeWindow.ShowModal();
            if (custom.StartsWith("OK|", StringComparison.Ordinal))
                return custom;

            const string title = "COMSPEC Overwatch — Bêta publique";
            const string body =
                "Bienvenue dans la bêta publique de COMSPEC Overwatch.\n\n" +
                "Ce pack est encore en phase de test. Certaines fonctions peuvent évoluer, " +
                "être temporairement indisponibles ou se comporter de façon inattendue.\n\n" +
                "Pour signaler un problème en jeu : Échap → gestion du mod → Signaler un problème.\n" +
                "Suivez les nouveautés et le journal des changements sur la page Steam Workshop.\n\n" +
                "En continuant, des informations techniques limitées (identifiant Steam, " +
                "version du pack, détails clients associés) peuvent être enregistrées pour " +
                "faire tourner la bêta et Athena.\n\n" +
                "Utilisez ce pack de façon responsable pendant les sessions organisées.\n\n" +
                "OK = j’ai lu (ce message ne réapparaîtra plus)";

            MessageBoxW(
                IntPtr.Zero,
                body,
                title,
                MbOk | MbIconInformation | MbSetForeground | MbTopmost);

            return "OK|ack";
        }
        catch
        {
            return "ERR|messagebox_failed";
        }
        finally
        {
            lock (BetaNoteLock)
            {
                _betaNoteShowing = false;
            }
        }
    }

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionVersion")]
    public static void RvExtensionVersion(nint output, int outputSize)
    {
            Output(output, outputSize, "COMSPECExtension 2.0.12");
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

        // Comme cTab IRL (Worker.ArmaString) : callExtension passe chaque argument avec guillemets.
        // Sans strip, RedeemGameLink envoie le code "ABCD12" (invalide) et Connect met
        // X-COMSPEC-KEY: "secret" → 401 sur toutes les routes authentifiées.
        for (var i = 0; i < argsString.Length; i++)
            argsString[i] = ArmaString(argsString[i]);

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
    /// Retire les guillemets ajoutés par Arma autour des arguments callExtension (même logique que cTab IRL).
    /// </summary>
    private static string ArmaString(string? str)
    {
        if (str == null) return string.Empty;
        if (str.Length >= 2
            && ((str[0] == '"' && str[^1] == '"') || (str[0] == '\'' && str[^1] == '\'')))
            return str.Substring(1, str.Length - 2);
        return str;
    }

    /// <summary>
    /// SQF peut livrer un JSON avec guillemets doublés non décodés → json_decode PHP vide → callsign « Unknown ».
    /// </summary>
    private static string NormalizeArmaJson(string? raw)
    {
        var s = (raw ?? string.Empty).Trim();
        if (s.Length == 0) return "{}";
        try
        {
            using var _ = JsonDocument.Parse(s);
            return s;
        }
        catch
        {
            // ignore — tentative de normalisation ci-dessous
        }

        // Virgule décimale FR dans les nombres (pos_x, pos_y, etc.)
        var commaFixed = System.Text.RegularExpressions.Regex.Replace(
            s,
            @"(?<=[:\[\s])(-?\d+),(\d{1,6})(?=[,\}\]\s])",
            "$1.$2");
        if (commaFixed != s)
        {
            try
            {
                using var _ = JsonDocument.Parse(commaFixed);
                return commaFixed;
            }
            catch
            {
                // ignore
            }
        }

        // Cas fréquent : {""mapId"":1,""callsign"":""N-10""}
        if (s.Contains("\"\"", StringComparison.Ordinal))
        {
            var doubled = s.Replace("\"\"", "\"", StringComparison.Ordinal);
            try
            {
                using var _ = JsonDocument.Parse(doubled);
                return doubled;
            }
            catch
            {
                // ignore
            }
        }

        return s;
    }

    /// <summary>
    /// Mémorise la clé API. Ne touche plus DefaultRequestHeaders (race → InvalidOperationException
    /// + risque d’envoyer une ancienne clé en double). AttachApiKeyHeader sur chaque requête.
    /// </summary>
    private static void ApplyApiKeyHeaders(string? apiKey)
    {
        var key = SanitizeSecret(apiKey);
        lock (HeaderLock)
        {
            _apiKey = key;
            // Nettoyage best-effort d’éventuelles clés héritées d’anciennes versions de la DLL.
            try
            {
                HttpClient.DefaultRequestHeaders.Remove("X-COMSPEC-KEY");
                HttpClient.DefaultRequestHeaders.Remove("X-ATAK-TOKEN");
            }
            catch (InvalidOperationException)
            {
                // Requête en vol : ignorer ; les envois passent par AttachApiKeyHeader.
            }
        }
    }

    /// <summary>Mémorise le tenant (redeem / 3e arg Connect). Vide = laisser le portail utiliser ATAK_DEFAULT_TENANT_ID.</summary>
    private static void ApplyTenantId(string? tenantId)
    {
        var t = SanitizeSecret(tenantId);
        if (t.Length > 0)
            _tenantId = t;
    }

    /// <summary>Guillemets Arma + espaces / BOM parfois collés via profileNamespace ou CBA.</summary>
    private static string SanitizeSecret(string? raw)
    {
        var s = ArmaString(raw).Trim();
        s = s.Trim('\uFEFF', '\u200B', '\u200E', '\u200F');
        // Double strip si une ancienne version a persisté des guillemets dans la valeur.
        for (var n = 0; n < 2; n++)
        {
            if (s.Length >= 2
                && ((s[0] == '"' && s[^1] == '"') || (s[0] == '\'' && s[^1] == '\'')))
                s = s.Substring(1, s.Length - 2).Trim();
            else
                break;
        }
        return s;
    }

    /// <summary>Attache X-COMSPEC-KEY (+ session / Steam mémorisés) sur une requête.</summary>
    private static void AttachApiKeyHeader(HttpRequestMessage req)
    {
        var key = _apiKey;
        if (key.Length > 0)
        {
            req.Headers.Remove("X-COMSPEC-KEY");
            req.Headers.TryAddWithoutValidation("X-COMSPEC-KEY", key);
        }
        var sess = _sessionToken;
        if (sess.Length > 0)
        {
            req.Headers.Remove("X-COMSPEC-SESSION");
            req.Headers.TryAddWithoutValidation("X-COMSPEC-SESSION", sess);
        }
        var steam = _steamUid;
        if (steam.Length > 0)
        {
            req.Headers.Remove("X-COMSPEC-STEAM");
            req.Headers.TryAddWithoutValidation("X-COMSPEC-STEAM", steam);
        }
    }

    private static void ApplySteamUid(string? steamUid)
    {
        if (TryNormalizeSteamUid(steamUid, out var sn))
            _steamUid = sn;
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

    /// <summary>
    /// Restriction admin (Steam / adresse réseau) → code SQF dédié (pas un faux unauthorized).
    /// </summary>
    private static string? MapModAccessBlockError(string? respBody)
    {
        if (string.IsNullOrEmpty(respBody))
            return null;
        if (respBody.Contains("mod_steam_blocked", StringComparison.OrdinalIgnoreCase))
            return "ERR|mod_steam_blocked";
        if (respBody.Contains("mod_ip_blocked", StringComparison.OrdinalIgnoreCase))
            return "ERR|mod_ip_blocked";
        return null;
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
            return "OK|COMSPECExtension 2.0.12";
        }

        if (function == "SetTelemetryBatch")
        {
            return ApplyTelemetryBatch(args.Length > 0 ? args[0] : "");
        }

        // Phase 1-2 ATAK : initATAK.sqf attend un tableau ["version","label"].
        if (function == "GetVersion")
        {
            return FormatAtakExtArray("2.0.12", "COMSPEC Extension ATAK");
        }

        if (function == "Terrain.Chunk")
        {
            return HandleTerrainChunk(args);
        }

        // Captures locales (dossier Screenshots du profil) — hors ligne, sans liaison Athena.
        // Lignes : nom\tchemin
        if (function == "ListLocalScreenshots")
        {
            var limit = 24;
            if (args.Length > 0 && int.TryParse(args[0], out var n) && n > 0)
                limit = Math.Min(n, 40);
            var body = ListLocalScreenshotsTab(limit);
            var cap = MaxOutputBytes - 4;
            if (body.Length > cap)
            {
                var cut = body.LastIndexOf('\n', cap);
                body = cut > 0 ? body.Substring(0, cut + 1) : body.Substring(0, cap);
            }
            return "OK|" + body;
        }

        // Alerte Windows : marche à suivre pour lier le compte Athena (bloquant, thread OK).
        if (function is "ShowAthenaLinkHelp")
        {
            return ShowAthenaLinkHelpMessageBox();
        }

        // Note d’accès anticipé (bêta) — 1er lancement menu principal.
        if (function is "ShowBetaAccessNote")
        {
            return ShowBetaAccessNoteMessageBox();
        }

        // Dernier échec d'un envoi fire-and-forget (position, tchat, marqueurs...). Ces envois
        // ne remontent jamais d'erreur à SQF (retry silencieux) : sans ce point d'accès, un
        // rejet serveur persistant (403/422/500) est invisible même dans le debug technique.
        // Retour : OK|none, ou OK|<code>|<chemin>|<secondes écoulées>. code=0 = pas de réponse
        // HTTP (DNS/TLS/timeout) ; code=-1 = exception avant même l'envoi.
        if (function == "GetLastPostError")
        {
            var ticks = System.Threading.Interlocked.Read(ref _lastPostErrorAtTicks);
            if (ticks == 0) return "OK|none";
            var ageSec = Math.Max(0, (int)((DateTime.UtcNow.Ticks - ticks) / TimeSpan.TicksPerSecond));
            return $"OK|{_lastPostErrorCode}|{_lastPostErrorPath}|{ageSec}";
        }

        // Nouvelle session journal (1 fichier / lancement Arma). args[0] = nb de fichiers à conserver (défaut 12).
        if (function == "LogSessionStart")
        {
            var keep = DefaultRetainedLogFiles;
            if (args.Length >= 1 && int.TryParse((args[0] ?? "").Trim(), out var parsedKeep) && parsedKeep > 0)
                keep = Math.Min(parsedKeep, 50);
            var started = StartNewLogSession(keep);
            return started != null ? $"OK|{started}" : "ERR|no_writable_path";
        }

        // Journal fichier COMSPEC Overwatch (best-effort — diag_log/RPT côté SQF reste la source
        // de vérité si ce fichier est indisponible). args[0] = ligne déjà formatée par
        // comspec_overwatch_connect_fnc_log ; aucun secret n'y transite (clé Athena/tokens jamais
        // inclus par l'appelant SQF). Retour OK|<chemin absolu> pour que le boot puisse le journaliser.
        if (function == "LogWrite" && args.Length >= 1 && !string.IsNullOrEmpty(args[0]))
        {
            var path = ResolveLogFilePath();
            if (path == null) return "ERR|no_writable_path";
            EnqueueLogLine(path, args[0]!);
            return $"OK|{path}";
        }

        // Dernières lignes du journal de la session en cours. args[0] = octets max (défaut 14000).
        if (function == "GetLogTail")
        {
            var maxBytes = 14000;
            if (args.Length >= 1 && int.TryParse((args[0] ?? "").Trim(), out var parsed) && parsed > 0)
                maxBytes = Math.Min(parsed, 32000);

            var path = ResolveLogFilePath();
            if (path == null || !File.Exists(path)) return "OK|";

            var main = ReadLogFileTail(path, maxBytes);
            if (string.IsNullOrWhiteSpace(main)) return "OK|";

            var combined = SanitizeLogForReport(main);
            if (combined.Length > maxBytes)
                combined = combined[^maxBytes..];
            var payload = combined.Replace("\r", "").Replace('\n', '\t');
            return "OK|" + payload;
        }

        // Google Slides public → PNG locaux (async callback google_deck_ready / google_deck_error).
        // Args : [url, index, requestId]. Retour sync : ["accepted"] | ["rejected","code"].
        if (function == "LoadGoogleDeck" && args.Length >= 1)
        {
            var url = (args[0] ?? "").Trim();
            var indexStr = args.Length > 1 ? (args[1] ?? "0") : "0";
            var requestId = args.Length > 2 ? (args[2] ?? "").Trim() : "";
            if (requestId.Length == 0)
                requestId = "deck_" + Guid.NewGuid().ToString("N")[..12];
            if (!int.TryParse(indexStr.Trim().Replace(',', '.'), System.Globalization.NumberStyles.Integer,
                    System.Globalization.CultureInfo.InvariantCulture, out var index))
                index = 0;
            return GoogleSlidesDeck.StartLoad(url, index, requestId, InvokeCallback);
        }

        // Navigation dans un deck déjà résolu. Args : [presentationId, index, requestId].
        if (function == "LoadGoogleSlide" && args.Length >= 2)
        {
            var presentationId = (args[0] ?? "").Trim();
            var indexStr = args[1] ?? "0";
            var requestId = args.Length > 2 ? (args[2] ?? "").Trim() : "";
            if (requestId.Length == 0)
                requestId = "slide_" + Guid.NewGuid().ToString("N")[..12];
            if (!int.TryParse(indexStr.Trim().Replace(',', '.'), System.Globalization.NumberStyles.Integer,
                    System.Globalization.CultureInfo.InvariantCulture, out var index))
                index = 0;
            return GoogleSlidesDeck.StartSlide(presentationId, index, requestId, InvokeCallback);
        }

        if (function == "CancelGoogleDeck")
        {
            return GoogleSlidesDeck.Cancel();
        }

        // Connect : mémorise URL/clé/tenant puis valide la clé via client-init synchrone.
        // Sans clé (et aucune clé déjà mémorisée après Redeem) → OK|connected (SQF : joignable non lié).
        // Avec clé → ERR|unauthorized / tenant_required si le portail refuse (plus de faux « Connecté »).
        // Clé vide en arg = garder la clé déjà mémorisée (Redeem/Steam) : évite qu’un round-trip
        // SQF/CBA tronqué écrase une clé valide juste après un redeem OK.
        if (function == "Connect" && args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
        {
            var normalized = NormalizeBaseUrl(args[0]);
            if (normalized.Length == 0
                || !Uri.TryCreate(normalized, UriKind.Absolute, out var connectUri)
                || (connectUri.Scheme != Uri.UriSchemeHttps && connectUri.Scheme != Uri.UriSchemeHttp))
                return "ERR|invalid_url";
            _baseUrl = normalized;

            var prevKey = _apiKey;
            var prevTenant = _tenantId;
            var keyArg = SanitizeSecret(args.Length > 1 ? args[1] : "");
            // Ne pas remplacer une clé longue (Redeem) par une valeur SQF plus courte (troncature CBA/EDITBOX).
            if (keyArg.Length > 0)
            {
                if (prevKey.Length == 0 || keyArg.Length >= prevKey.Length || keyArg == prevKey)
                    ApplyApiKeyHeaders(keyArg);
                // sinon : conserver prevKey (clé SQF suspecte / tronquée)
            }
            if (args.Length > 2)
            {
                var tidArg = SanitizeSecret(args[2]);
                if (tidArg.Length > 0)
                    ApplyTenantId(tidArg);
            }
            // args[3] = Steam UID (identité fiable côté extension, pas seulement SQF ultérieur).
            if (args.Length > 3)
                ApplySteamUid(args[3]);
            // args[4] = version mod Overwatch (journal Activité).
            if (args.Length > 4)
                ApplyModVersion(args[4]);
            if (_apiKey.Length == 0)
                return "OK|connected";

            var verify = VerifyClientInitSync();
            if (verify.StartsWith("OK|", StringComparison.Ordinal))
            {
                EnsureScreenshotWatchers();
                return verify;
            }

            // Dernier filet : si la clé SQF vient de faire échouer client-init, restaurer Redeem.
            if (prevKey.Length > 0 && !string.Equals(prevKey, _apiKey, StringComparison.Ordinal))
            {
                ApplyApiKeyHeaders(prevKey);
                if (prevTenant.Length > 0)
                    ApplyTenantId(prevTenant);
                var restored = VerifyClientInitSync();
                if (restored.StartsWith("OK|", StringComparison.Ordinal))
                {
                    EnsureScreenshotWatchers();
                    return restored;
                }
            }
            return verify;
        }

        // Déconnexion explicite (sortie mission / quit) — sync court avant mort du process.
        if (function is "Disconnect" or "ClientDisconnect")
        {
            var callSign = args.Length > 0 ? (args[0] ?? "").Trim() : "";
            if (args.Length > 1)
                ApplyModVersion(args[1]);
            return VerifyDisconnectSync(callSign);
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
            if (!TryNormalizeSteamUid(steamUid, out var steamNorm) && steamUid.Length > 0)
            {
                // UID présent mais non reconnu : on envoie quand même le trim pour laisser le serveur trancher ;
                // s’il est vide, pas de champ utile.
                steamNorm = steamUid;
            }
            if (!TryBuildRequestUri(baseUrl, "/api/atak/game-link/redeem", out var redeemUri, out var uriErr) || redeemUri is null)
                return "ERR|" + uriErr;

            try
            {
                var payload = $"{{\"code\":\"{EscapeJson(linkCode)}\",\"steam_uid\":\"{EscapeJson(steamNorm)}\"}}";
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
                    var modBlockRedeem = MapModAccessBlockError(respBody);
                    if (modBlockRedeem != null)
                        return modBlockRedeem;
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
                apiKey = SanitizeSecret(apiKey);
                if (apiKey.Length == 0) return "ERR|http_503";
                // Toujours l’URL qui a réussi le redeem (évite node_url admin incorrect → 401).
                _baseUrl = baseUrl;
                ApplyApiKeyHeaders(apiKey);
                ApplyTenantId(tenantId);
                ApplySteamUid(steamNorm);
                _sessionToken = "";
                // Valide immédiatement la clé (évite Redeem OK puis Connect 401 à cause d’un round-trip SQF).
                var verify = VerifyClientInitSync();
                if (!verify.StartsWith("OK|", StringComparison.Ordinal))
                    return verify;
                // Séparateur | (pas tab) : Arma/SQF splitString "\t" est fragile → URL/"h" + clé tronquée.
                var simplified = baseUrl + "|" + apiKey + "|" + tenantId;
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            catch (Exception ex)
            {
                return FormatCaughtError(ex);
            }
        }

        // Liaison par Steam ID déjà enregistré sur le compte Athena (sans code court).
        if (function == "LinkBySteam" && args.Length >= 2)
        {
            using var steamCts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
            var steamToken = steamCts.Token;
            var baseUrl = NormalizeBaseUrl(args[0]);
            var steamUid = (args[1] ?? "").Trim();
            // Solo / éditeur : getPlayerUID est souvent vide ou placeholder → message dédié.
            if (baseUrl.Length == 0) return "ERR|invalid_url";
            if (!TryNormalizeSteamUid(steamUid, out var steamNorm)) return "ERR|no_steam_uid";
            if (!TryBuildRequestUri(baseUrl, "/api/atak/game-link/by-steam", out var steamUri, out var steamUriErr) || steamUri is null)
                return "ERR|" + steamUriErr;

            try
            {
                var payload = $"{{\"steam_uid\":\"{EscapeJson(steamNorm)}\"}}";
                using var content = new StringContent(payload, Encoding.UTF8);
                content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
                using var req = new HttpRequestMessage(HttpMethod.Post, steamUri) { Content = content };
                var resp = HttpClient.SendAsync(req, steamToken).GetAwaiter().GetResult();
                var respBody = ReadContentUtf8(resp, steamToken);
                if (!resp.IsSuccessStatusCode)
                {
                    var httpCode = (int)resp.StatusCode;
                    if (httpCode == 404)
                    {
                        if (respBody.Contains("steam_not_linked", StringComparison.Ordinal))
                            return "ERR|steam_not_linked";
                        if (respBody.Contains("<html", StringComparison.OrdinalIgnoreCase)
                            || respBody.Contains("<!DOCTYPE", StringComparison.OrdinalIgnoreCase)
                            || respBody.Length == 0)
                            return "ERR|not_found";
                        return "ERR|steam_not_linked";
                    }
                    if (httpCode == 400) return "ERR|invalid_steam";
                    if (httpCode == 401 || httpCode == 403)
                    {
                        var modBlockSteam = MapModAccessBlockError(respBody);
                        if (modBlockSteam != null)
                            return modBlockSteam;
                        // Route absente sur un portail pas à jour → middleware clé API.
                        if (respBody.Contains("unauthorized", StringComparison.OrdinalIgnoreCase)
                            || respBody.Contains("X_COMSPEC", StringComparison.OrdinalIgnoreCase)
                            || respBody.Contains("X-COMSPEC", StringComparison.OrdinalIgnoreCase))
                            return "ERR|server_outdated";
                        return "ERR|account_disabled";
                    }
                    if (httpCode == 503) return "ERR|http_503";
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
                apiKey = SanitizeSecret(apiKey);
                if (apiKey.Length == 0) return "ERR|http_503";
                // Toujours l’URL qui a réussi by-steam (évite node_url admin incorrect → 401).
                _baseUrl = baseUrl;
                ApplyApiKeyHeaders(apiKey);
                ApplyTenantId(tenantId);
                ApplySteamUid(steamNorm);
                _sessionToken = "";
                var verifySteam = VerifyClientInitSync();
                if (!verifySteam.StartsWith("OK|", StringComparison.Ordinal))
                    return verifySteam;
                // Séparateur | (pas tab) — même format que RedeemGameLink.
                var simplified = baseUrl + "|" + apiKey + "|" + tenantId;
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            catch (Exception ex)
            {
                return FormatCaughtError(ex);
            }
        }

        // Inscription accès anticipé (bêta) — URL en argument, pas de clé API.
        // args: baseUrl, steamUid, playerUid, playerName, modVersion, armaBuild, armaBranch, extensionVersion, acknowledged(0|1)
        if (function == "RegisterBeta" && args.Length >= 1)
        {
            using var betaCts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
            var betaToken = betaCts.Token;
            var baseUrl = NormalizeBaseUrl(args[0]);
            if (baseUrl.Length == 0) return "ERR|invalid_url";
            if (!TryBuildRequestUri(baseUrl, "/api/atak/beta-register", out var betaUri, out var betaUriErr) || betaUri is null)
                return "ERR|" + betaUriErr;

            var steamUid = args.Length > 1 ? (args[1] ?? "").Trim() : "";
            var playerUid = args.Length > 2 ? (args[2] ?? "").Trim() : "";
            var playerName = args.Length > 3 ? (args[3] ?? "").Trim() : "";
            var modVersion = args.Length > 4 ? (args[4] ?? "").Trim() : "";
            var armaBuild = args.Length > 5 ? (args[5] ?? "").Trim() : "";
            var armaBranch = args.Length > 6 ? (args[6] ?? "").Trim() : "";
            var extVersion = args.Length > 7 ? (args[7] ?? "").Trim() : "1.17";
            var ackRaw = args.Length > 8 ? (args[8] ?? "1").Trim() : "1";
            var acknowledged = ackRaw is not ("0" or "false" or "no");

            if (!TryNormalizeSteamUid(steamUid, out var steamNorm) && steamUid.Length > 0)
                steamNorm = steamUid;
            if (steamNorm.Length == 0 && TryNormalizeSteamUid(playerUid, out var fromPlayer))
                steamNorm = fromPlayer;

            try
            {
                var payload =
                    $"{{\"steam_uid\":\"{EscapeJson(steamNorm)}\"," +
                    $"\"player_uid\":\"{EscapeJson(playerUid)}\"," +
                    $"\"player_name\":\"{EscapeJson(playerName)}\"," +
                    $"\"mod_version\":\"{EscapeJson(modVersion)}\"," +
                    $"\"arma_build\":\"{EscapeJson(armaBuild)}\"," +
                    $"\"arma_branch\":\"{EscapeJson(armaBranch)}\"," +
                    $"\"extension_version\":\"{EscapeJson(extVersion)}\"," +
                    $"\"acknowledged\":{(acknowledged ? "true" : "false")}}}";
                using var content = new StringContent(payload, Encoding.UTF8);
                content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
                using var req = new HttpRequestMessage(HttpMethod.Post, betaUri) { Content = content };
                var resp = HttpClient.SendAsync(req, betaToken).GetAwaiter().GetResult();
                var respBody = ReadContentUtf8(resp, betaToken);
                if (!resp.IsSuccessStatusCode)
                {
                    var httpCode = (int)resp.StatusCode;
                    if (httpCode == 429) return "ERR|rate_limited";
                    if (httpCode == 400) return "ERR|invalid";
                    if (httpCode == 503) return "ERR|http_503";
                    return "ERR|http_" + httpCode;
                }
                // Refuser un HTML de redirection (portail démo) pris pour un succès HTTP 200.
                var bodyNorm = respBody.Replace(" ", "", StringComparison.Ordinal);
                if (bodyNorm.IndexOf("\"ok\":true", StringComparison.OrdinalIgnoreCase) < 0
                    && bodyNorm.IndexOf("\"ok\":1", StringComparison.OrdinalIgnoreCase) < 0)
                {
                    return "ERR|invalid_response";
                }
                // Mémorise l’URL pour d’éventuels appels suivants (whoami, etc.).
                _baseUrl = baseUrl;
                return "OK|registered";
            }
            catch (Exception ex)
            {
                return FormatCaughtError(ex);
            }
        }

        // Remontée erreurs / bugs Overwatch → Athena (URL en arg ou _baseUrl si déjà connecté).
        // args: baseUrl, severity, channel, message, detail, fingerprint, source,
        //       steamUid, playerUid, playerName, callsign, modVersion, armaBuild, extVersion, contextJson
        if (function == "ReportDiag" && args.Length >= 4)
        {
            using var diagCts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
            var diagToken = diagCts.Token;
            var baseUrl = NormalizeBaseUrl(args[0] ?? "");
            if (baseUrl.Length == 0) baseUrl = NormalizeBaseUrl(_baseUrl ?? "");
            if (baseUrl.Length == 0) return "ERR|invalid_url";
            if (!TryBuildRequestUri(baseUrl, "/api/atak/mod-report", out var diagUri, out var diagUriErr) || diagUri is null)
                return "ERR|" + diagUriErr;

            var severity = (args.Length > 1 ? args[1] : "error") ?? "error";
            var channel = (args.Length > 2 ? args[2] : "Core") ?? "Core";
            var message = (args.Length > 3 ? args[3] : "") ?? "";
            var detail = args.Length > 4 ? (args[4] ?? "") : "";
            var fingerprint = args.Length > 5 ? (args[5] ?? "") : "";
            var source = args.Length > 6 ? (args[6] ?? "auto") : "auto";
            var steamUid = args.Length > 7 ? (args[7] ?? "") : "";
            var playerUid = args.Length > 8 ? (args[8] ?? "") : "";
            var playerName = args.Length > 9 ? (args[9] ?? "") : "";
            var callsign = args.Length > 10 ? (args[10] ?? "") : "";
            var modVersion = args.Length > 11 ? (args[11] ?? "") : "";
            var armaBuild = args.Length > 12 ? (args[12] ?? "") : "";
            var extVersion = args.Length > 13 ? (args[13] ?? "") : "";

            if (string.IsNullOrWhiteSpace(message)) return "ERR|missing_message";

            if (!TryNormalizeSteamUid(steamUid, out var steamNorm) && steamUid.Length > 0)
                steamNorm = steamUid;

            // Journal : la DLL lit le fichier elle-même. Le JSON SQF (guillemets, retours
            // ligne) cassait souvent tout le POST → « impossible d’envoyer ».
            if (string.Equals(source.Trim(), "player", StringComparison.OrdinalIgnoreCase)
                && detail.Length < 800)
            {
                try
                {
                    var logPath = ResolveLogFilePath();
                    if (logPath != null && File.Exists(logPath))
                    {
                        var tail = SanitizeLogForReport(ReadLogFileTail(logPath, 3500));
                        if (!string.IsNullOrWhiteSpace(tail))
                            detail = (detail.Length > 0 ? detail + "\n" : "") + "--- journal ---\n" + tail;
                    }
                }
                catch
                {
                    // Le signalement part quand même sans journal.
                }
            }
            if (detail.Length > 8000)
                detail = detail[..8000] + "\n...[tronqué]";

            try
            {
                var payload =
                    $"{{\"severity\":\"{EscapeJson(severity.Trim())}\"," +
                    $"\"channel\":\"{EscapeJson(channel.Trim())}\"," +
                    $"\"message\":\"{EscapeJson(message.Trim())}\"," +
                    $"\"detail\":\"{EscapeJson(detail)}\"," +
                    $"\"fingerprint\":\"{EscapeJson(fingerprint)}\"," +
                    $"\"source\":\"{EscapeJson(source.Trim())}\"," +
                    $"\"steam_uid\":\"{EscapeJson(steamNorm)}\"," +
                    $"\"player_uid\":\"{EscapeJson(playerUid.Trim())}\"," +
                    $"\"player_name\":\"{EscapeJson(playerName.Trim())}\"," +
                    $"\"callsign\":\"{EscapeJson(callsign.Trim())}\"," +
                    $"\"mod_version\":\"{EscapeJson(modVersion.Trim())}\"," +
                    $"\"arma_build\":\"{EscapeJson(armaBuild.Trim())}\"," +
                    $"\"extension_version\":\"{EscapeJson(extVersion.Trim())}\"}}";

                using var content = new StringContent(payload, Encoding.UTF8);
                content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
                using var req = new HttpRequestMessage(HttpMethod.Post, diagUri) { Content = content };
                var resp = HttpClient.SendAsync(req, diagToken).GetAwaiter().GetResult();
                var respBody = ReadContentUtf8(resp, diagToken);
                if (!resp.IsSuccessStatusCode)
                {
                    var httpCode = (int)resp.StatusCode;
                    if (httpCode == 429) return "ERR|rate_limited";
                    if (httpCode == 400) return "ERR|invalid";
                    if (httpCode == 503) return "ERR|http_503";
                    return "ERR|http_" + httpCode;
                }
                var bodyNorm = respBody.Replace(" ", "", StringComparison.Ordinal);
                if (bodyNorm.IndexOf("\"ok\":true", StringComparison.OrdinalIgnoreCase) < 0
                    && bodyNorm.IndexOf("\"ok\":1", StringComparison.OrdinalIgnoreCase) < 0)
                {
                    return "ERR|invalid_response";
                }
                _baseUrl = baseUrl;
                return "OK|reported";
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
                return ServePollGet("GetMarkers", url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyMarkersJson(body));
                });
            }
            if (function == "GetUnits")
            {
                if (!TryBuildRequestUri(_baseUrl, "/api/units?mapId=1", out var unitsUri, out var unitsErr) || unitsUri is null)
                    return "ERR|" + unitsErr;
                var response = SendGet(unitsUri, token);
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
                    var response = SendGet(whoamiUri, token);
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
                var resp = SendJsonPost(_baseUrl + "/api/fire-support/calculate", payload, token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "GetFireSupportUnits")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = SendGet(_baseUrl + "/api/fire-support/units?missionId=" + Uri.EscapeDataString(missionId), token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "DangerZones.Sync")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = SendGet(_baseUrl + "/api/danger-zones?missionId=" + Uri.EscapeDataString(missionId), token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "IFF.Current")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = SendGet(_baseUrl + "/api/iff/current?missionId=" + Uri.EscapeDataString(missionId), token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "IFF.Status")
            {
                var missionId = args.Length > 0 ? (args[0] ?? "mission_1_map_1") : "mission_1_map_1";
                var resp = SendGet(_baseUrl + "/api/iff/assets?missionId=" + Uri.EscapeDataString(missionId), token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                return "OK|" + respBody.Replace("|", "_").Replace("\n", " ");
            }
            if (function == "Intel.Report" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return "{\"status\":\"error\",\"message\":\"payload vide\"}";
                var response = SendJsonPost(_baseUrl + "/api/intel/report", json, token);
                var body = ReadContentUtf8(response, token);
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
                return ServePollGet("GetCAS:" + mapId + ":" + (args[0] ?? ""), url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    var safe = body.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                    return PollOkClipped(safe);
                });
            }
            if (function == "GetMapShapes")
            {
                var mapId = args.Length > 0 ? (args[0] ?? "1") : "1";
                var since = args.Length > 1 ? Uri.EscapeDataString(args[1] ?? "") : "";
                var url = _baseUrl + "/api/map-shapes?mapId=" + mapId;
                if (!string.IsNullOrEmpty(since)) url += "&since=" + since;
                return ServePollGet("GetMapShapes:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    var safe = body.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                    return PollOkClipped(safe);
                });
            }
            // Modules pont ATAK Enhanced / cTab (activables admin).
            // Lignes : id\tenabled(0|1)\tlabel
            if (function == "GetModModules")
            {
                return ServePollGet("GetModModules", _baseUrl + "/api/atak/mod-modules", (body, code) =>
                {
                    if (code < 200 || code >= 300)
                    {
                        if (code == 401) return "ERR|unauthorized";
                        if (code == 403) return "ERR|forbidden";
                        return "ERR|http_" + code;
                    }
                    return PollOkClipped(SimplifyModModulesJson(body));
                });
            }
            // Expérience communauté (réalisme, troll, guide). Lignes : clef\tvaleur
            if (function == "GetExperience")
            {
                return ServePollGet("GetExperience", _baseUrl + "/api/atak/experience", (body, code) =>
                {
                    if (code < 200 || code >= 300)
                    {
                        if (code == 401) return "ERR|unauthorized";
                        if (code == 403) return "ERR|forbidden";
                        return "ERR|http_" + code;
                    }
                    return PollOkClipped(SimplifyExperienceJson(body));
                });
            }
            if (function == "GetRoleplayConfig")
            {
                return ServePollGet("GetRoleplayConfig", _baseUrl + "/api/atak/roleplay-stats", (body, code) =>
                {
                    if (code < 200 || code >= 300)
                    {
                        if (code == 401) return "ERR|unauthorized";
                        if (code == 403) return "ERR|forbidden";
                        return "ERR|http_" + code;
                    }
                    return PollOkClipped(SimplifyRoleplayConfigJson(body));
                });
            }
            if (function == "GetSessionRestore")
            {
                var steamUid = args.Length > 0 ? (args[0] ?? "").Trim() : "";
                if (steamUid.Length < 10) return "ERR|no_steam";
                var url = _baseUrl + "/api/atak/session-restore?steam_uid=" + Uri.EscapeDataString(steamUid);
                var resp = SendGet(url, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401) return "ERR|unauthorized";
                    return "ERR|http_" + code;
                }
                var respBody = ReadContentUtf8(resp, token);
                var simplified = SimplifySessionRestoreJson(respBody);
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            if (function == "GetLaserCodes")
            {
                var mapId = args.Length > 0 ? (args[0] ?? "1") : "1";
                var resp = SendGet(_baseUrl + "/api/atak/laser-codes?mapId=" + mapId, token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
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
                var resp = SendGet(url, token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                var simplified = SimplifyBriefingSlidesJson(respBody);
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Équipes de feu (mission ATAK). Format tabulaire SQF-friendly :
            // une ligne par équipe : id\tlabel\tcolor\tmapId\tkind\tmemberCount
            // puis lignes membres préfixées "M\t" : M\tteamId\tcallsign\trole\tdisplayName
            if (function == "GetFireTeams")
            {
                var tenantId = args.Length > 0 ? (args[0] ?? "") : "";
                var mapId = args.Length > 1 ? (args[1] ?? "") : "";
                var url = _baseUrl + "/api/atak/fire-teams?kind=ephemeral";
                if (!string.IsNullOrEmpty(tenantId)) url += "&tenant_id=" + Uri.EscapeDataString(tenantId);
                if (!string.IsNullOrEmpty(mapId)) url += "&mapId=" + Uri.EscapeDataString(mapId);
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
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
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyFireTeamsJson(respBody);
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // SEEK Query : registre + listes de surveillance Athena (pas de tirage simulé).
            // args : first, last, alias, q
            // retour : OK|found\tverdict\tscore\tname\talias\tref\tnote
            if (function == "QuerySseIdentity")
            {
                var first = args.Length > 0 ? (args[0] ?? "").Trim() : "";
                var last = args.Length > 1 ? (args[1] ?? "").Trim() : "";
                var alias = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                var q = args.Length > 3 ? (args[3] ?? "").Trim() : "";
                var url = _baseUrl + "/api/sse/identity-query"
                    + "?first=" + Uri.EscapeDataString(first)
                    + "&last=" + Uri.EscapeDataString(last)
                    + "&alias=" + Uri.EscapeDataString(alias);
                if (!string.IsNullOrEmpty(q))
                    url += "&q=" + Uri.EscapeDataString(q);
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401) return "ERR|unauthorized";
                    if (code == 403)
                    {
                        if (respBody.Contains("tenant_context_required", StringComparison.Ordinal))
                            return "ERR|no_tenant";
                        return "ERR|unauthorized";
                    }
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplifiedId = SimplifyIdentityQueryJson(respBody);
                return "OK|" + (simplifiedId.Length > MaxOutputBytes - 4 ? simplifiedId.Substring(0, MaxOutputBytes - 4) : simplifiedId);
            }
            if (function == "JoinFireTeam" && args.Length >= 1)
            {
                var teamId = (args[0] ?? "").Trim();
                var callsign = args.Length > 1 ? (args[1] ?? "").Trim() : "";
                var role = args.Length > 2 ? (args[2] ?? "member").Trim() : "member";
                if (string.IsNullOrWhiteSpace(teamId) || teamId == "0")
                    return FormatAtakExtArray("ERROR", "team empty");
                if (string.IsNullOrWhiteSpace(callsign))
                    return FormatAtakExtArray("ERROR", "callsign empty");
                if (!role.Equals("leader", StringComparison.OrdinalIgnoreCase))
                    role = "member";
                var json = "{\"callsign\":\"" + EscapeJson(callsign) + "\",\"role\":\"" + EscapeJson(role) + "\"}";
                return PostAtakJsonSync("/api/atak/fire-teams/" + Uri.EscapeDataString(teamId) + "/members", json, token);
            }
            // Messagerie radio Athena (journal /api/chat) → jeu (Groups / inbox Athena).
            // Lignes : id\tauthor\tbody\tcreated_at
            // args: [mapId, limit?, afterId?] — afterId = ne renvoyer que id > afterId
            if (function == "GetChatMessages")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var limit = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]!.Trim() : "25";
                var afterId = args.Length > 2 && !string.IsNullOrWhiteSpace(args[2]) ? args[2]!.Trim() : "";
                var url = _baseUrl + "/api/chat?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=" + Uri.EscapeDataString(limit);
                if (!string.IsNullOrEmpty(afterId) && afterId != "0")
                    url += "&after=" + Uri.EscapeDataString(afterId);
                return ServePollGet("GetChatMessages:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    var simplifiedChat = TruncateTabLinesKeepingNewest(SimplifyChatMessagesJson(body), MaxOutputBytes - 4);
                    return "OK|" + simplifiedChat;
                });
            }
            // Alertes tactiques Athena (Contact / FRAGO / BDA / …) → inbox cTab.
            // Lignes : id\tkind\tkind_label\tcall_sign\tgrid\tsummary\tcreated_at\tseverity
            if (function == "GetTacticalAlerts")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var limit = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]!.Trim() : "40";
                var url = _baseUrl + "/api/atak/tactical-alerts?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=" + Uri.EscapeDataString(limit);
                return ServePollGet("GetTacticalAlerts:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyTacticalAlertsJson(body));
                });
            }
            // Alertes médicales actives (≤ 30 min) + triage.
            // Lignes : id\tkind\tcall_sign\tlabel\tgrid\tcreated_at\ttriage_status\ttriage_label\tseverity
            if (function == "GetMedicalAlerts")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var limit = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]!.Trim() : "25";
                var url = _baseUrl + "/api/atak/medical-alerts?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=" + Uri.EscapeDataString(limit);
                return ServePollGet("GetMedicalAlerts:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyMedicalAlertsJson(body));
                });
            }
            // Ordres C2 web → jeu. Args : [mapId, limit, callsign?]
            // Lignes : id\ttype\ttarget\tpriority\tissuer\tstatus\tpayload\ttarget_type\ttarget_ref\taliases\ttype_label
            if (function == "GetOrders")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var limit = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]!.Trim() : "40";
                var callsign = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                var url = _baseUrl + "/api/atak/orders?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=" + Uri.EscapeDataString(limit)
                    + "&for_game=1";
                if (_steamUid.Length > 0)
                    url += "&steam_uid=" + Uri.EscapeDataString(_steamUid);
                if (callsign.Length > 0)
                    url += "&callsign=" + Uri.EscapeDataString(callsign);
                return ServePollGet("GetOrders:" + mapId + ":" + _steamUid + ":" + callsign, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyOrdersJson(body));
                });
            }
            // Ordre de mission (objectifs, LD, H). Lecture seule. Args : [mapId]
            // Lignes : P\tcode\ttitle\tstatus\th_hour\tsentence\tphase\tclock
            //          G\tid\tcode\tlabel\tkind\tx\ty\tstate
            //          T\tcode\tlabel\toccurred\tclock
            if (function == "GetMissionPlan")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var url = _baseUrl + "/api/atak/mission-plan?mapId=" + Uri.EscapeDataString(mapId);
                return ServePollGet("GetMissionPlan:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyMissionPlanJson(body));
                });
            }
            // Déclenchements TOC → jeu. Args : [mapId]
            // Lignes : charge_id\trequested_by\tid
            if (function == "GetExplosiveCommands")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var url = _baseUrl + "/api/atak/explosive-timers/commands?mapId=" + Uri.EscapeDataString(mapId);
                return ServePollGet("GetExplosiveCommands:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyExplosiveCommandsJson(body));
                });
            }
            // Déplacements IA alliée (carte ATAK → groupe en jeu). Args : [mapId]
            // Lignes : id\ttype\ttarget_ref\tstatus\tpos_x\tpos_y\tlabel
            if (function == "GetAiOrders")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var url = _baseUrl + "/api/atak/orders?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=20&for_game=1&for_ai=1";
                return ServePollGet("GetAiOrders:" + mapId, url, (body, code) =>
                {
                    if (code < 200 || code >= 300) return PollHttpErr(code);
                    return PollOkClipped(SimplifyAiOrdersJson(body));
                });
            }
            // Mise à jour statut ordre depuis le jeu. Args : [orderId, status, by, mapId?, note?]
            if (function == "UpdateOrderStatus" && args.Length >= 2)
            {
                var orderId = (args[0] ?? "").Trim();
                var status = (args[1] ?? "").Trim();
                var by = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                var mapId = args.Length > 3 && !string.IsNullOrWhiteSpace(args[3]) ? args[3]!.Trim() : "1";
                var note = args.Length > 4 ? (args[4] ?? "").Trim() : "";
                if (orderId.Length == 0 || status.Length == 0) return "ERR|invalid";
                if (!int.TryParse(mapId, out var mapIdNum)) mapIdNum = 1;
                var url = _baseUrl + "/api/atak/orders/" + Uri.EscapeDataString(orderId) + "/status";
                var steamJson = _steamUid.Length > 0
                    ? $",\"steam_uid\":\"{EscapeJson(_steamUid)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var noteJson = note.Length > 0
                    ? $",\"note\":\"{EscapeJson(note)}\""
                    : "";
                var payload = $"{{\"status\":\"{EscapeJson(status)}\",\"by\":\"{EscapeJson(by)}\",\"mapId\":{mapIdNum}{noteJson}{steamJson}{sessJson}}}";
                var resp = SendJsonPost(url, payload, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401 || code == 403) return "ERR|forbidden";
                    if (code == 503) return "ERR|not_migrated";
                    return "ERR|http_" + code;
                }
                return "OK|updated";
            }
            // Triage d'une alerte médicale : args = [chatId, status, by]
            if (function == "TriageMedicalAlert" && args.Length >= 2)
            {
                var alertId = (args[0] ?? "").Trim();
                var status = (args[1] ?? "").Trim();
                var by = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                if (alertId.Length == 0 || status.Length == 0) return "ERR|invalid";
                var url = _baseUrl + "/api/atak/medical-alerts/" + Uri.EscapeDataString(alertId) + "/triage";
                var steamJson = _steamUid.Length > 0
                    ? $",\"steam_uid\":\"{EscapeJson(_steamUid)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var payload = $"{{\"status\":\"{EscapeJson(status)}\",\"by\":\"{EscapeJson(by)}\",\"mapId\":1{steamJson}{sessJson}}}";
                var resp = SendJsonPost(url, payload, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401 || code == 403) return "ERR|forbidden";
                    if (code == 503) return "ERR|not_migrated";
                    if (respBody.Contains("invalid_status", StringComparison.Ordinal)) return "ERR|invalid_status";
                    return "ERR|http_" + code;
                }
                return "OK|triaged";
            }
            // Connexion téléphone (inspiré de cTab) : génère un token/QR + un code court côté serveur.
            // Format : token\tcode\tconnectUrl\tqrImageUrl\texpiresAt — le QR se télécharge ensuite
            // via DownloadBriefingSlideImage(qrImageUrl, "phoneqr") comme n'importe quelle diapositive.
            if (function == "GetPhoneConnectInfo")
            {
                // Préfère l’arg SQF ; sinon le tenant mémorisé au redeem / Connect (clé communauté).
                var tenantId = args.Length > 0 ? (args[0] ?? "") : "";
                if (string.IsNullOrEmpty(tenantId))
                    tenantId = _tenantId;
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/phone-pairing", out var phoneUri, out _) || phoneUri is null)
                    return "ERR|invalid_url";
                var url = phoneUri.AbsoluteUri;
                if (!string.IsNullOrEmpty(tenantId))
                {
                    var sep = url.Contains('?', StringComparison.Ordinal) ? "&" : "?";
                    url += sep + "tenant_id=" + Uri.EscapeDataString(tenantId);
                }
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
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
                    if (code == 503)
                    {
                        if (respBody.Contains("phone_pairing_schema_missing", StringComparison.Ordinal))
                            return "ERR|not_enabled";
                        return "ERR|unavailable";
                    }
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyPhonePairingJson(respBody);
                if (simplified.Length == 0) return "ERR|invalid_response";
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Terminal ATAK (réalisme) : enregistrement / mise à jour côté Athena.
            // args: terminalUid, terminalLabel, terminalType, operatorCallsign, pairingToken, status, platformLabel
            if (function == "RegisterTerminal")
            {
                if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
                var terminalUid = args.Length > 0 ? (args[0] ?? "").Trim() : "";
                if (terminalUid.Length == 0
                    || terminalUid.Equals("null", StringComparison.OrdinalIgnoreCase)
                    || terminalUid.Equals("<null>", StringComparison.OrdinalIgnoreCase)
                    || terminalUid.Equals("<nul>", StringComparison.OrdinalIgnoreCase)
                    || terminalUid.Equals("nil", StringComparison.OrdinalIgnoreCase)
                    || terminalUid.StartsWith("<null", StringComparison.OrdinalIgnoreCase))
                    return "ERR|missing_terminal_uid";
                var terminalLabel = args.Length > 1 ? (args[1] ?? "").Trim() : "";
                var terminalType = args.Length > 2 ? (args[2] ?? "tablet").Trim() : "tablet";
                var operatorCallsign = args.Length > 3 ? (args[3] ?? "").Trim() : "";
                var pairingToken = args.Length > 4 ? (args[4] ?? "").Trim() : "";
                var status = args.Length > 5 ? (args[5] ?? "active").Trim() : "active";
                var platformLabel = args.Length > 6 ? (args[6] ?? "").Trim() : "";
                if (operatorCallsign.Length == 0 && _callSign.Length > 0)
                    operatorCallsign = _callSign;
                if (platformLabel.Length == 0 && _modVersion.Length > 0)
                    platformLabel = "Arma 3 · COMSPEC " + _modVersion;

                if (!TryBuildRequestUri(_baseUrl, "/api/atak/terminals", out var termUri, out _) || termUri is null)
                    return "ERR|invalid_url";

                var payload =
                    $"{{\"terminal_uid\":\"{EscapeJson(terminalUid)}\"," +
                    $"\"terminal_label\":\"{EscapeJson(terminalLabel)}\"," +
                    $"\"terminal_type\":\"{EscapeJson(terminalType)}\"," +
                    $"\"operator_callsign\":\"{EscapeJson(operatorCallsign)}\"," +
                    $"\"platform_label\":\"{EscapeJson(platformLabel)}\"," +
                    $"\"pairing_token\":\"{EscapeJson(pairingToken)}\"," +
                    $"\"status\":\"{EscapeJson(status)}\"{ModVersionJsonFragment()}}}";
                var resp = SendJsonPost(termUri.AbsoluteUri, EnrichAtakPayload(payload), token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 422) return "ERR|pairing_invalid";
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyTerminalRegisterJson(respBody);
                if (simplified.Length == 0) return "ERR|invalid_response";
                return "OK|" + simplified;
            }
            // Certificat métier (pas de PKI réelle) pour un terminal enregistré.
            // args: terminalId, certificateRef, commonName, serialNumber, fingerprintSha256
            if (function == "RegisterCertificate")
            {
                if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
                var terminalId = args.Length > 0 ? (args[0] ?? "").Trim() : "";
                if (terminalId.Length == 0) return "ERR|missing_terminal_id";
                var certificateRef = args.Length > 1 ? (args[1] ?? "").Trim() : "";
                var commonName = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                var serialNumber = args.Length > 3 ? (args[3] ?? "").Trim() : "";
                var fingerprint = args.Length > 4 ? (args[4] ?? "").Trim() : "";
                if (commonName.Length == 0 && _callSign.Length > 0)
                    commonName = _callSign;

                if (!TryBuildRequestUri(_baseUrl, "/api/atak/certificates", out var certUri, out _) || certUri is null)
                    return "ERR|invalid_url";

                var payload =
                    $"{{\"terminal_id\":{EscapeJsonIntOrString(terminalId)}," +
                    $"\"certificate_ref\":\"{EscapeJson(certificateRef)}\"," +
                    $"\"certificate_type\":\"device\"," +
                    $"\"status\":\"active\"," +
                    $"\"common_name\":\"{EscapeJson(commonName)}\"," +
                    $"\"serial_number\":\"{EscapeJson(serialNumber)}\"," +
                    $"\"fingerprint_sha256\":\"{EscapeJson(fingerprint)}\"" +
                    $"{ModVersionJsonFragment()}}}";
                var resp = SendJsonPost(certUri.AbsoluteUri, EnrichAtakPayload(payload), token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401 || code == 403)
                    {
                        if (respBody.Contains("automatic_pairing_disabled", StringComparison.Ordinal))
                            return "ERR|pairing_disabled";
                        return "ERR|unauthorized";
                    }
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyCertificateRegisterJson(respBody);
                if (simplified.Length == 0) return "ERR|invalid_response";
                return "OK|" + simplified;
            }
            // Compromission / capture terminal (données illisibles côté viewer).
            // args: terminalUid, state(captured|compromised), reason?
            if (function == "CompromiseTerminal")
            {
                if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
                var terminalUid = args.Length > 0 ? (args[0] ?? "").Trim() : "";
                if (terminalUid.Length == 0) return "ERR|missing_terminal_uid";
                var state = args.Length > 1 ? (args[1] ?? "captured").Trim().ToLowerInvariant() : "captured";
                if (state != "captured" && state != "compromised") state = "captured";
                var reason = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/terminals/compromise", out var compUri, out _) || compUri is null)
                    return "ERR|invalid_url";
                var payload =
                    $"{{\"terminal_uid\":\"{EscapeJson(terminalUid)}\"," +
                    $"\"state\":\"{EscapeJson(state)}\"," +
                    $"\"reason\":\"{EscapeJson(reason)}\"" +
                    $"{ModVersionJsonFragment()}}}";
                var resp = SendJsonPost(compUri.AbsoluteUri, EnrichAtakPayload(payload), token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 404) return "ERR|not_found";
                    return "ERR|http_" + code;
                }
                return "OK|compromised";
            }
            if (function == "ClearCompromise")
            {
                if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
                var terminalUid = args.Length > 0 ? (args[0] ?? "").Trim() : "";
                if (terminalUid.Length == 0) return "ERR|missing_terminal_uid";
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/terminals/clear-compromise", out var clearUri, out _) || clearUri is null)
                    return "ERR|invalid_url";
                var payload =
                    $"{{\"terminal_uid\":\"{EscapeJson(terminalUid)}\"" +
                    $"{ModVersionJsonFragment()}}}";
                var resp = SendJsonPost(clearUri.AbsoluteUri, EnrichAtakPayload(payload), token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 404) return "ERR|not_found";
                    return "ERR|http_" + code;
                }
                return "OK|cleared";
            }
            // État terminal + certificat + réglages communauté (GET /api/atak/terminals?terminal_uid=…).
            if (function == "GetTerminalRealism" && args.Length >= 1)
            {
                if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
                var terminalUid = (args[0] ?? "").Trim();
                if (terminalUid.Length == 0) return "ERR|missing_terminal_uid";
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/terminals", out var statusUri, out _) || statusUri is null)
                    return "ERR|invalid_url";
                var url = statusUri.AbsoluteUri + "?terminal_uid=" + Uri.EscapeDataString(terminalUid);
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 404) return "ERR|not_found";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyTerminalRealismJson(respBody);
                if (simplified.Length == 0) return "ERR|invalid_response";
                return "OK|" + simplified;
            }
            // Profil site (nom, callsign, photo) d'un joueur identifié par son SteamUID, résolu via
            // le compte Athena lié (voir RedeemGameLink). Format : displayName\tcallsign\tavatarUrl —
            // l'avatar se télécharge ensuite via DownloadBriefingSlideImage(avatarUrl, "avatar_<uid>")
            // comme n'importe quelle image (même mécanisme que les diapositives / le QR téléphone).
            if (function == "GetPlayerAvatarInfo" && args.Length >= 1)
            {
                var steamUid = (args[0] ?? "").Trim();
                if (!TryNormalizeSteamUid(steamUid, out var steamNorm)) return "ERR|invalid";
                var tenantId = args.Length > 1 ? (args[1] ?? "") : "";
                var url = _baseUrl + "/api/atak/player-profile?steam_uid=" + Uri.EscapeDataString(steamNorm);
                if (!string.IsNullOrEmpty(tenantId)) url += "&tenant_id=" + Uri.EscapeDataString(tenantId);
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
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
                // Mémoriser military_id / callsign pour BFT ↔ indicatif
                try
                {
                    using var doc = JsonDocument.Parse(respBody);
                    var root = doc.RootElement;
                    if (root.TryGetProperty("military_id", out var midEl))
                    {
                        var mid = (midEl.GetString() ?? "").Trim();
                        if (mid.Length > 0) _militaryId = mid;
                    }
                    if (root.TryGetProperty("callsign", out var csEl))
                    {
                        var cs = (csEl.GetString() ?? "").Trim();
                        if (cs.Length > 0) _callSign = cs;
                    }
                }
                catch { /* ignore */ }
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

                // URL relatives : conserver le préfixe /public de _baseUrl (ne pas utiliser Uri(base, /abs)).
                if (!Uri.TryCreate(imageUrl, UriKind.Absolute, out _)
                    && !string.IsNullOrEmpty(_baseUrl))
                {
                    var rel = imageUrl.StartsWith('/') ? imageUrl : "/" + imageUrl.TrimStart('/');
                    imageUrl = _baseUrl.TrimEnd('/') + rel;
                }

                HttpResponseMessage imgResp;
                try
                {
                    imgResp = SendGet(imageUrl, token);
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
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }

                var bytes = imgResp.Content.ReadAsByteArrayAsync(token).GetAwaiter().GetResult();
                if (bytes.Length == 0) return "ERR|empty_image";

                // Rejeter les corps texte (ex. « QR unavailable ») / HTML d’erreur sauvés par erreur en .png.
                var isPng = bytes.Length >= 8
                    && bytes[0] == 0x89 && bytes[1] == (byte)'P' && bytes[2] == (byte)'N' && bytes[3] == (byte)'G';
                var isJpeg = bytes.Length >= 3
                    && bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF;
                if (!isPng && !isJpeg) return "ERR|invalid_image";
                if (isPng) ext = ".png";
                else if (isJpeg) ext = ".jpg";

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

                // Slash avant pour RscPicture / setObjectTexture sous Windows (Arma).
                var armaPath = destPath.Replace('\\', '/');
                return "OK|" + armaPath;
            }

            // --- Commandes ATAK Phase 1-2 (retour JSON ["OK"|"ERROR", message] pour SQF) ---
            if (function == "SubmitTacticalReport" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostAtakJsonSync("/api/atak/reports", json, token);
            }
            if (function == "SubmitSsePerson" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostSsePersonSync(json, token);
            }
            if (function == "SubmitSseFieldNote" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostSseFieldNoteSync(json, token);
            }
            // 2 args (Overwatch) : personId + json | 1 arg (COMSPEC_SSE) : json avec person_id / id
            if (function == "SubmitSseBiometricsSim" && args.Length >= 1)
            {
                string personId;
                string json;
                if (args.Length >= 2 && LooksLikeJsonObject(args[0]))
                {
                    json = args[0] ?? "{}";
                    personId = TryExtractSsePersonId(json);
                }
                else if (args.Length >= 2 && IsAthenaPersonId(args[0]))
                {
                    personId = (args[0] ?? "").Trim();
                    json = args[1] ?? "{}";
                }
                else
                {
                    json = args[0] ?? "{}";
                    personId = TryExtractSsePersonId(json);
                    if (!IsAthenaPersonId(personId) && args.Length >= 2)
                        personId = TryExtractSsePersonId(args[1] ?? "{}");
                }
                if (!IsAthenaPersonId(personId)) return FormatAtakExtArray("ERROR", "person_id empty");
                if (string.IsNullOrWhiteSpace(json)) json = "{}";
                return PostAtakJsonSync("/api/sse/persons/" + Uri.EscapeDataString(personId) + "/biometrics-sim", json, token);
            }
            if (function == "SubmitSseDigital" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostAtakJsonSync("/api/sse/digital-acquisitions", json, token);
            }
            // Fiche de renseignement simplifiée rédigée dans l'ATAK.
            if (function == "SubmitSseFieldNote" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostSseFieldNoteSync(json, token);
            }
            if (function == "UploadSseNoteAttachment" && args.Length >= 2)
            {
                return BeginUploadSseNoteAttachment(args);
            }
            // Canal générique SSE (numérique / lab / record) — évite le fallback sendIntel texte.
            if (function == "SendSSE" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostSseGenericSync(json, token);
            }
            if (function == "CreatePOI" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostAtakJsonSync("/api/atak/poi", json, token);
            }
            if (function == "RequestMEDEVAC" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostAtakJsonSync("/api/atak/medevac", json, token);
            }
            if (function == "SubmitExplosiveTimer" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/explosive-timers", out var expUri, out var expErr) || expUri is null)
                    return FormatAtakExtArray("ERROR", expErr);
                EnqueueOrSend(expUri.AbsoluteUri, EnrichAtakPayload(json));
                return FormatAtakExtArray("OK", "queued");
            }
            if (function == "RequestQRF" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostAtakJsonSync("/api/atak/qrf", json, token);
            }
            if (function == "UpdateVehicleTracking" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                if (!TryBuildRequestUri(_baseUrl, "/api/atak/vehicles", out var vehUri, out var vehErr) || vehUri is null)
                    return FormatAtakExtArray("ERROR", vehErr);
                EnqueueOrSend(vehUri.AbsoluteUri, EnrichAtakPayload(json));
                return FormatAtakExtArray("OK", "queued");
            }
            if (function == "RequestVehicleService" && args.Length >= 1)
            {
                var json = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(json)) return FormatAtakExtArray("ERROR", "payload empty");
                return PostVehicleServiceSync(json, token);
            }
            // Photos recon : signal rapide (queue + resolve/upload en arrière-plan).
            // NotifyNewPhoto / EnqueueReconImage = API sidecar ; UploadReconImage reste un alias.
            if ((function == "NotifyNewPhoto" || function == "EnqueueReconImage" || function == "UploadReconImage")
                && args.Length >= 1)
            {
                return EnqueueReconImage(args);
            }
            if (function == "StartPhotoWatcher")
            {
                EnsureScreenshotWatchers();
                return _screenshotWatchersStarted ? "OK|watching" : "ERR|watcher_failed";
            }
            if (function == "UploadLatestScreenshot" && args.Length >= 1)
            {
                return BeginUploadLatestScreenshot(args);
            }
            if (function == "UploadImage" && args.Length >= 1)
            {
                return BeginUploadIntelPhoto(args);
            }
            if (function == "UploadSsePhoto" && args.Length >= 2)
            {
                return BeginUploadSsePhoto(args);
            }
            if (function == "UploadSseNoteAttachment" && args.Length >= 2)
            {
                return BeginUploadSseNoteAttachment(args);
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
    /// Dossier des journaux COMSPEC (%LOCALAPPDATA%\Arma 3\COMSPEC\logs en priorité).
    /// </summary>
    private static string? ResolveLogDirectory()
    {
        var candidates = new List<string>();
        try
        {
            var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            if (!string.IsNullOrWhiteSpace(localAppData))
                candidates.Add(Path.Combine(localAppData, "Arma 3", "COMSPEC", "logs"));
        }
        catch
        {
            // ignore
        }
        candidates.Add(Path.Combine(AppContext.BaseDirectory, "COMSPEC", "logs"));
        candidates.Add(Path.Combine(Path.GetTempPath(), "COMSPEC", "logs"));

        foreach (var dir in candidates)
        {
            try
            {
                Directory.CreateDirectory(dir);
                var probe = Path.Combine(dir, ".write_test");
                File.WriteAllText(probe, "ok", Encoding.UTF8);
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

    /// <summary>
    /// Ouvre un nouveau fichier horodaté pour la session Arma en cours et purge les plus anciens.
    /// </summary>
    private static string? StartNewLogSession(int keepCount)
    {
        lock (LogFileLock)
        {
            if (_sessionLogInitialized && _resolvedLogPath != null)
                return _resolvedLogPath;

            var dir = ResolveLogDirectory();
            if (dir == null) return null;

            PurgeOldLogFiles(dir, keepCount);
            RemoveLegacySingleLogFiles();

            var stamp = DateTime.Now.ToString("yyyy-MM-dd_HHmmss_fff");
            var path = Path.Combine(dir, $"COMSPEC_{stamp}.log");
            try
            {
                var header = $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss.fff}] === Session COMSPEC Overwatch ==={Environment.NewLine}";
                File.WriteAllText(path, header, Encoding.UTF8);
                _resolvedLogPath = path;
                _sessionLogInitialized = true;
                return path;
            }
            catch
            {
                return null;
            }
        }
    }

    private static void PurgeOldLogFiles(string dir, int keepCount)
    {
        keepCount = Math.Max(3, Math.Min(keepCount, 50));
        try
        {
            var files = Directory.GetFiles(dir, "COMSPEC_*.log")
                .Select(f => new FileInfo(f))
                .OrderByDescending(f => f.LastWriteTimeUtc)
                .ToList();
            foreach (var info in files.Skip(keepCount))
            {
                try { info.Delete(); } catch { /* ignore */ }
            }
        }
        catch
        {
            // ignore
        }
    }

    /// <summary>Supprime l'ancien journal unique COMSPECExtension.log (schéma antérieur).</summary>
    private static void RemoveLegacySingleLogFiles()
    {
        var legacyPaths = new List<string>();
        try
        {
            var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            if (!string.IsNullOrWhiteSpace(localAppData))
            {
                legacyPaths.Add(Path.Combine(localAppData, "Arma 3", "COMSPECExtension.log"));
                legacyPaths.Add(Path.Combine(localAppData, "Arma 3", "COMSPECExtension.log.1"));
            }
        }
        catch
        {
            // ignore
        }
        legacyPaths.Add(Path.Combine(AppContext.BaseDirectory, "COMSPECExtension.log"));
        legacyPaths.Add(Path.Combine(AppContext.BaseDirectory, "COMSPECExtension.log.1"));

        foreach (var path in legacyPaths)
        {
            try
            {
                if (File.Exists(path)) File.Delete(path);
            }
            catch
            {
                // ignore
            }
        }
    }

    /// <summary>
    /// Chemin du journal de la session en cours (créé au premier accès si besoin).
    /// </summary>
    private static string? ResolveLogFilePath()
    {
        if (_resolvedLogPath != null) return _resolvedLogPath;
        return StartNewLogSession(DefaultRetainedLogFiles);
    }

    /// <summary>
    /// Append une ligne horodatée au journal fichier. Best-effort : tout échec est avalé silencieusement.
    /// </summary>
    private static bool AppendLogLine(string path, string line)
    {
        lock (LogFileLock)
        {
            try
            {
                var stamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss.fff");
                File.AppendAllText(path, $"[{stamp}] {line}{Environment.NewLine}", Encoding.UTF8);
                return true;
            }
            catch
            {
                return false;
            }
        }
    }

    private static readonly ConcurrentQueue<(string Path, string Line)> PendingLogLines = new();
    private static int _logDrainScheduled;

    /// <summary>
    /// LogWrite ne doit pas figer le thread jeu (lock fichier + Flush). File + drain ThreadPool.
    /// </summary>
    private static void EnqueueLogLine(string path, string line)
    {
        PendingLogLines.Enqueue((path, line));
        if (Interlocked.CompareExchange(ref _logDrainScheduled, 1, 0) == 0)
            ThreadPool.QueueUserWorkItem(static _ => DrainLogLines());
    }

    private static void DrainLogLines()
    {
        try
        {
            while (PendingLogLines.TryDequeue(out var item))
                AppendLogLine(item.Path, item.Line);
        }
        finally
        {
            Interlocked.Exchange(ref _logDrainScheduled, 0);
            if (!PendingLogLines.IsEmpty
                && Interlocked.CompareExchange(ref _logDrainScheduled, 1, 0) == 0)
            {
                ThreadPool.QueueUserWorkItem(static _ => DrainLogLines());
            }
        }
    }

    /// <summary>Lectures des N derniers octets d'un journal (partage lecture avec LogWrite).</summary>
    private static string ReadLogFileTail(string path, int maxBytes)
    {
        if (maxBytes <= 0 || !File.Exists(path)) return "";
        try
        {
            using var fs = new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite);
            var len = fs.Length;
            if (len <= 0) return "";
            var toRead = (int)Math.Min(maxBytes, len);
            fs.Seek(-toRead, SeekOrigin.End);
            var buf = new byte[toRead];
            var read = fs.Read(buf, 0, toRead);
            if (read <= 0) return "";
            var text = Encoding.UTF8.GetString(buf, 0, read);
            if (len > toRead)
            {
                var nl = text.IndexOf('\n');
                if (nl >= 0 && nl + 1 < text.Length)
                    text = text[(nl + 1)..];
            }
            return text;
        }
        catch
        {
            return "";
        }
    }

    /// <summary>Retire les lignes susceptibles de contenir des secrets avant remontée Athena.</summary>
    private static string SanitizeLogForReport(string text)
    {
        if (string.IsNullOrWhiteSpace(text)) return "";
        var sb = new StringBuilder();
        foreach (var rawLine in text.Split(new[] { '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries))
        {
            var line = rawLine.TrimEnd();
            if (line.Length == 0) continue;
            var lower = line.ToLowerInvariant();
            if (lower.Contains("api_key")
                || lower.Contains("apikey")
                || lower.Contains("bearer ")
                || lower.Contains("authorization:")
                || lower.Contains("x-api-key"))
            {
                continue;
            }
            sb.AppendLine(line);
        }
        return sb.ToString().TrimEnd();
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
                var color = "";
                var source = "";
                try
                {
                    using var data = JsonDocument.Parse(dataStr);
                    var root = data.RootElement;
                    if (root.TryGetProperty("pos", out var pos) && pos.GetArrayLength() >= 2)
                    {
                        x = pos[0].GetDouble();
                        y = pos[1].GetDouble();
                    }
                    else if (root.TryGetProperty("pos_x", out var px) && root.TryGetProperty("pos_y", out var py))
                    {
                        x = px.GetDouble();
                        y = py.GetDouble();
                    }
                    if (root.TryGetProperty("type", out var t)) type = t.GetString() ?? type;
                    if (root.TryGetProperty("text", out var tx)) text = tx.GetString() ?? "";
                    if (string.IsNullOrEmpty(text) && root.TryGetProperty("label", out var lb))
                        text = lb.GetString() ?? "";
                    if (root.TryGetProperty("color", out var col)) color = col.GetString() ?? "";
                    if (root.TryGetProperty("source", out var src)) source = src.GetString() ?? "";
                }
                catch { /* blob illisible : ligne quand même, coords 0 */ }
                text = text.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
                color = color.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
                source = source.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
                sb.Append("M\t").Append(id).Append("\t").Append(layerId).Append("\t")
                    .Append(x.ToString("R", System.Globalization.CultureInfo.InvariantCulture)).Append("\t")
                    .Append(y.ToString("R", System.Globalization.CultureInfo.InvariantCulture)).Append("\t")
                    .Append(type).Append("\t").Append(text).Append("\t")
                    .Append(color).Append("\t").Append(source).Append("\n");
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
            // Ligne méta : G\tgoogle_slides_url (lien communauté publié côté Athena).
            if (doc.RootElement.TryGetProperty("google_slides_url", out var gUrlEl))
            {
                var gUrl = (gUrlEl.GetString() ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
                if (gUrl.Length > 0)
                    sb.Append('G').Append('\t').Append(gUrl).Append('\n');
            }
            if (!doc.RootElement.TryGetProperty("slides", out var slides)) return sb.ToString();
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

    /// <summary>
    /// Simplifie GET /api/atak/fire-teams pour SQF (pas de JSON natif).
    /// Lignes équipe : id\tlabel\tcolor\tmapId\tkind\tmemberCount
    /// Lignes membre : M\tteamId\tcallsign\trole\tdisplayName
    /// </summary>
    /// <summary>
    /// Simplifie GET /api/atak/tactical-alerts pour SQF.
    /// Lignes : id\tkind\tkind_label\tcall_sign\tgrid\tsummary\tcreated_at\tseverity
    /// </summary>
    /// <summary>
    /// Simplifie GET /api/atak/mod-modules pour SQF.
    /// Lignes : id\tenabled(0|1)\tlabel
    /// </summary>
    private static string SimplifyExperienceJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\r", " ").Replace("|", "-");

            static void AppendLine(StringBuilder sb, string key, string val)
            {
                if (sb.Length > 0) sb.Append('\n');
                sb.Append(Clean(key)).Append('\t').Append(Clean(val));
            }

            if (doc.RootElement.TryGetProperty("realism", out var realism))
                AppendLine(sb, "realism", realism.ValueKind == JsonValueKind.True || (realism.ValueKind == JsonValueKind.Number && realism.GetInt32() != 0) ? "1" : "0");
            if (doc.RootElement.TryGetProperty("troll", out var troll))
                AppendLine(sb, "troll", troll.ValueKind == JsonValueKind.True || (troll.ValueKind == JsonValueKind.Number && troll.GetInt32() != 0) ? "1" : "0");
            foreach (var key in new[] { "screen_notifications", "vehicle_detail", "require_equipment", "show_opfor" })
            {
                if (doc.RootElement.TryGetProperty(key, out var el) && el.ValueKind == JsonValueKind.String)
                    AppendLine(sb, key, el.GetString() ?? "player");
            }
            if (doc.RootElement.TryGetProperty("guide", out var guide) && guide.ValueKind == JsonValueKind.String)
            {
                var g = guide.GetString() ?? "";
                g = g.Replace("\r\n", "\n").Replace('\r', '\n').Replace("\n", "§NL§");
                AppendLine(sb, "guide", g);
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Simplifie GET /api/atak/roleplay-stats pour SQF (clef\tvaleur par ligne).
    /// </summary>
    private static string SimplifyRoleplayConfigJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\r", " ").Replace("\n", " ").Replace("|", "-");

            static void AppendLine(StringBuilder sb, string key, string val)
            {
                if (sb.Length > 0) sb.Append('\n');
                sb.Append(Clean(key)).Append('\t').Append(Clean(val));
            }

            if (doc.RootElement.TryGetProperty("network", out var network) && network.ValueKind == JsonValueKind.Object)
            {
                var enabled = network.TryGetProperty("enabled", out var en)
                    && (en.ValueKind == JsonValueKind.True || (en.ValueKind == JsonValueKind.Number && en.GetInt32() != 0));
                AppendLine(sb, "network_enabled", enabled ? "1" : "0");
                if (network.TryGetProperty("mode", out var mode) && mode.ValueKind == JsonValueKind.String)
                    AppendLine(sb, "network_mode", mode.GetString() ?? "normal");
                if (network.TryGetProperty("packet_loss", out var pl) && pl.ValueKind == JsonValueKind.Number)
                    AppendLine(sb, "packet_loss_percent", pl.GetDouble().ToString(System.Globalization.CultureInfo.InvariantCulture));
            }

            if (doc.RootElement.TryGetProperty("zones_enabled", out var zonesEn))
            {
                var on = zonesEn.ValueKind == JsonValueKind.True
                    || (zonesEn.ValueKind == JsonValueKind.Number && zonesEn.GetInt32() != 0);
                AppendLine(sb, "zones_enabled", on ? "1" : "0");
            }

            if (doc.RootElement.TryGetProperty("zones_json", out var zonesJson))
            {
                JsonElement? zonesArray = null;
                if (zonesJson.ValueKind == JsonValueKind.Array)
                    zonesArray = zonesJson;
                else if (zonesJson.ValueKind == JsonValueKind.String)
                {
                    var raw = zonesJson.GetString() ?? "";
                    if (!string.IsNullOrWhiteSpace(raw))
                    {
                        try
                        {
                            using var zdoc = JsonDocument.Parse(raw);
                            if (zdoc.RootElement.ValueKind == JsonValueKind.Array)
                                zonesArray = zdoc.RootElement.Clone();
                        }
                        catch { /* ignore */ }
                    }
                }

                if (zonesArray.HasValue && zonesArray.Value.ValueKind == JsonValueKind.Array)
                {
                    var arr = zonesArray.Value;
                    foreach (var zone in arr.EnumerateArray())
                    {
                        if (sb.Length > 0) sb.Append('\n');
                        var name = zone.TryGetProperty("name", out var zn) && zn.ValueKind == JsonValueKind.String
                            ? Clean(zn.GetString() ?? "Zone") : "Zone";
                        var type = zone.TryGetProperty("type", out var zt) && zt.ValueKind == JsonValueKind.String
                            ? Clean(zt.GetString() ?? "degraded")
                            : (zone.TryGetProperty("effect", out var ze) && ze.ValueKind == JsonValueKind.String
                                ? Clean(ze.GetString() ?? "degraded") : "degraded");
                        double x = 0, y = 0, radius = 300, intensity = 50;
                        if (zone.TryGetProperty("center", out var center) && center.ValueKind == JsonValueKind.Array)
                        {
                            var cArr = center.EnumerateArray().ToArray();
                            if (cArr.Length > 0 && cArr[0].ValueKind == JsonValueKind.Number) x = cArr[0].GetDouble();
                            if (cArr.Length > 1 && cArr[1].ValueKind == JsonValueKind.Number) y = cArr[1].GetDouble();
                        }
                        if (zone.TryGetProperty("radius", out var zr) && zr.ValueKind == JsonValueKind.Number) radius = zr.GetDouble();
                        if (zone.TryGetProperty("intensity", out var zi) && zi.ValueKind == JsonValueKind.Number) intensity = zi.GetDouble();
                        sb.Append(name).Append('\t').Append(type).Append('\t')
                            .Append(x.ToString(System.Globalization.CultureInfo.InvariantCulture)).Append('\t')
                            .Append(y.ToString(System.Globalization.CultureInfo.InvariantCulture)).Append('\t')
                            .Append(radius.ToString(System.Globalization.CultureInfo.InvariantCulture)).Append('\t')
                            .Append(intensity.ToString(System.Globalization.CultureInfo.InvariantCulture));
                    }
                    AppendLine(sb, "zones_lines_count", arr.GetArrayLength().ToString());
                }
                else
                {
                    var z = zonesJson.ValueKind == JsonValueKind.String
                        ? zonesJson.GetString() ?? ""
                        : zonesJson.GetRawText();
                    z = z.Replace("\r\n", "\n").Replace('\r', '\n').Replace("\n", " ");
                    AppendLine(sb, "zones_json", z);
                }
            }

            if (doc.RootElement.TryGetProperty("intel_scramble_enabled", out var ise))
            {
                var on = ise.ValueKind == JsonValueKind.True
                    || (ise.ValueKind == JsonValueKind.Number && ise.GetInt32() != 0);
                AppendLine(sb, "intel_scramble_enabled", on ? "1" : "0");
            }

            if (doc.RootElement.TryGetProperty("session_ttl_sec", out var ttl) && ttl.ValueKind == JsonValueKind.Number)
                AppendLine(sb, "session_ttl_sec", ttl.GetInt32().ToString());

            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Simplifie GET /api/atak/session-restore pour SQF.
    /// Ligne : callsign\tlink_state
    /// </summary>
    private static string SimplifySessionRestoreJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\r", " ").Replace("\n", " ").Replace("|", "-");

            var cs = "";
            var link = "linked";
            if (doc.RootElement.TryGetProperty("callsign", out var c) && c.ValueKind == JsonValueKind.String)
                cs = Clean(c.GetString() ?? "");
            if (doc.RootElement.TryGetProperty("link_state", out var l) && l.ValueKind == JsonValueKind.String)
                link = Clean(l.GetString() ?? "linked");
            return Clean(cs) + "\t" + link;
        }
        catch { return ""; }
    }

    private static string SimplifyModModulesJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");

            if (doc.RootElement.TryGetProperty("catalog", out var catalog) && catalog.ValueKind == JsonValueKind.Array)
            {
                foreach (var el in catalog.EnumerateArray())
                {
                    var id = el.TryGetProperty("id", out var i) ? (i.GetString() ?? "") : "";
                    if (string.IsNullOrEmpty(id)) continue;
                    var enabled = true;
                    if (el.TryGetProperty("enabled", out var en))
                    {
                        enabled = en.ValueKind == JsonValueKind.True
                            || (en.ValueKind == JsonValueKind.Number && en.GetInt32() != 0)
                            || (en.ValueKind == JsonValueKind.String && (en.GetString() == "1" || string.Equals(en.GetString(), "true", StringComparison.OrdinalIgnoreCase)));
                    }
                    var label = el.TryGetProperty("label", out var lb) ? (lb.GetString() ?? id) : id;
                    if (sb.Length > 0) sb.Append('\n');
                    sb.Append(Clean(id)).Append('\t').Append(enabled ? "1" : "0").Append('\t').Append(Clean(label));
                }
                return sb.ToString();
            }

            if (doc.RootElement.TryGetProperty("modules", out var modules) && modules.ValueKind == JsonValueKind.Object)
            {
                foreach (var prop in modules.EnumerateObject())
                {
                    var enabled = prop.Value.ValueKind == JsonValueKind.True
                        || (prop.Value.ValueKind == JsonValueKind.Number && prop.Value.GetInt32() != 0);
                    if (sb.Length > 0) sb.Append('\n');
                    sb.Append(Clean(prop.Name)).Append('\t').Append(enabled ? "1" : "0").Append('\t').Append(Clean(prop.Name));
                }
            }
            return sb.ToString();
        }
        catch
        {
            return "";
        }
    }
    /// <summary>
    /// Simplifie GET /api/chat pour SQF.
    /// Lignes : id\tauthor\tbody\tcreated_at
    /// </summary>
    private static string SimplifyChatMessagesJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.ValueKind != JsonValueKind.Array)
                return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " — ").Replace("\r", "");
            foreach (var el in doc.RootElement.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i)
                    ? (i.ValueKind == JsonValueKind.Number ? i.GetInt32().ToString() : (i.GetString() ?? ""))
                    : "";
                if (string.IsNullOrEmpty(id) || id == "0") continue;
                var author = el.TryGetProperty("author", out var au) ? (au.GetString() ?? "") : "";
                var body = el.TryGetProperty("body", out var b) ? (b.GetString() ?? "") : "";
                if (body.Length > 280) body = body.Substring(0, 280);
                var created = "";
                if (el.TryGetProperty("created_at", out var ca))
                {
                    created = ca.ValueKind == JsonValueKind.Number
                        ? ca.GetDouble().ToString(System.Globalization.CultureInfo.InvariantCulture)
                        : (ca.GetString() ?? "");
                }
                sb.Append(Clean(id)).Append('\t')
                  .Append(Clean(author)).Append('\t')
                  .Append(Clean(body)).Append('\t')
                  .Append(Clean(created)).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Coupe un payload multi-lignes en gardant la fin (messages les plus récents).
    /// Un Substring(0, max) classique coupait les messages TOC tout juste postés.
    /// </summary>
    private static string TruncateTabLinesKeepingNewest(string payload, int maxLen)
    {
        if (string.IsNullOrEmpty(payload) || maxLen <= 0) return "";
        if (payload.Length <= maxLen) return payload;
        var lines = payload.Split('\n');
        var sb = new StringBuilder();
        for (var i = lines.Length - 1; i >= 0; i--)
        {
            var line = lines[i];
            if (string.IsNullOrEmpty(line)) continue;
            var candidate = line + "\n" + sb;
            if (candidate.Length > maxLen)
            {
                if (sb.Length == 0)
                {
                    // Une seule ligne trop longue : garder la fin utile.
                    var keep = Math.Min(line.Length, maxLen);
                    return line.Substring(line.Length - keep);
                }
                break;
            }
            sb.Insert(0, line + "\n");
        }
        return sb.ToString();
    }

    private static string SimplifyTacticalAlertsJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("alerts", out var alerts) || alerts.ValueKind != JsonValueKind.Array)
                return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            foreach (var el in alerts.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i)
                    ? (i.ValueKind == JsonValueKind.Number ? i.GetInt32().ToString() : (i.GetString() ?? ""))
                    : "";
                if (string.IsNullOrEmpty(id) || id == "0") continue;
                var kind = el.TryGetProperty("kind", out var k) ? (k.GetString() ?? "tic") : "tic";
                var kindLabel = el.TryGetProperty("kind_label", out var kl) ? (kl.GetString() ?? "") : "";
                var callSign = el.TryGetProperty("call_sign", out var cs) ? (cs.GetString() ?? "") : "";
                if (string.IsNullOrEmpty(callSign) && el.TryGetProperty("author", out var au))
                    callSign = au.GetString() ?? "";
                var grid = el.TryGetProperty("grid", out var g) ? (g.GetString() ?? "") : "";
                var summary = el.TryGetProperty("summary", out var sm) ? (sm.GetString() ?? "") : "";
                if (summary.Length > 160) summary = summary.Substring(0, 160);
                var created = "";
                if (el.TryGetProperty("created_at", out var ca))
                {
                    created = ca.ValueKind == JsonValueKind.Number
                        ? ca.GetDouble().ToString(System.Globalization.CultureInfo.InvariantCulture)
                        : (ca.GetString() ?? "");
                }
                var severity = el.TryGetProperty("severity", out var sev) ? (sev.GetString() ?? "") : "";
                sb.Append(Clean(id)).Append('\t')
                  .Append(Clean(kind)).Append('\t')
                  .Append(Clean(kindLabel)).Append('\t')
                  .Append(Clean(callSign)).Append('\t')
                  .Append(Clean(grid)).Append('\t')
                  .Append(Clean(summary)).Append('\t')
                  .Append(Clean(created)).Append('\t')
                  .Append(Clean(severity)).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Simplifie GET /api/atak/orders pour SQF.
    /// Lignes : id\ttype\ttarget\tpriority\tissuer\tstatus\tpayload\ttarget_type\ttarget_ref\taliases\ttype_label
    /// </summary>
    private static string SimplifyOrdersJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("orders", out var orders) || orders.ValueKind != JsonValueKind.Array)
                return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            // splitString SQF omet les champs vides : un tiret garde l’alignement des colonnes.
            static string Cell(string s)
            {
                var c = Clean(s);
                return c.Length == 0 ? "-" : c;
            }
            static string PropStr(JsonElement el, string snake, string camel = "")
            {
                if (el.TryGetProperty(snake, out var a) && a.ValueKind == JsonValueKind.String)
                    return a.GetString() ?? "";
                if (camel.Length > 0 && el.TryGetProperty(camel, out var b) && b.ValueKind == JsonValueKind.String)
                    return b.GetString() ?? "";
                return "";
            }
            foreach (var el in orders.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i) ? (i.GetString() ?? "") : "";
                if (string.IsNullOrEmpty(id)) continue;
                var type = el.TryGetProperty("type", out var t) ? (t.GetString() ?? "MOVE") : "MOVE";
                var target = el.TryGetProperty("target", out var tg) ? (tg.GetString() ?? "") : "";
                var priority = el.TryGetProperty("priority", out var pr) ? (pr.GetString() ?? "IMPORTANT") : "IMPORTANT";
                var issuer = el.TryGetProperty("issuer", out var iss) ? (iss.GetString() ?? "") : "";
                var status = el.TryGetProperty("status", out var st) ? (st.GetString() ?? "PENDING") : "PENDING";
                var payload = el.TryGetProperty("payload", out var pl) ? (pl.GetString() ?? "") : "";
                var targetType = el.TryGetProperty("target_type", out var tt) ? (tt.GetString() ?? "all") : "all";
                var targetRef = el.TryGetProperty("target_ref", out var tr) ? (tr.GetString() ?? "") : "";
                var typeLabel = PropStr(el, "type_label", "typeLabel");
                if (typeLabel.Length > 80) typeLabel = typeLabel.Substring(0, 80);
                var aliases = "";
                if (el.TryGetProperty("match_aliases", out var ma))
                {
                    if (ma.ValueKind == JsonValueKind.Array)
                    {
                        var parts = new List<string>();
                        foreach (var a in ma.EnumerateArray())
                        {
                            var s = Clean(a.GetString() ?? "");
                            if (s.Length > 0 && !parts.Contains(s, StringComparer.OrdinalIgnoreCase))
                                parts.Add(s);
                        }
                        aliases = string.Join(",", parts);
                    }
                    else if (ma.ValueKind == JsonValueKind.String)
                    {
                        aliases = Clean(ma.GetString() ?? "");
                    }
                }
                if (payload.Length > 120) payload = payload.Substring(0, 120);
                sb.Append(Cell(id)).Append('\t')
                  .Append(Cell(type)).Append('\t')
                  .Append(Cell(target)).Append('\t')
                  .Append(Cell(priority)).Append('\t')
                  .Append(Cell(issuer)).Append('\t')
                  .Append(Cell(status)).Append('\t')
                  .Append(Cell(payload)).Append('\t')
                  .Append(Cell(targetType)).Append('\t')
                  .Append(Cell(targetRef)).Append('\t')
                  .Append(Cell(aliases)).Append('\t')
                  .Append(Cell(typeLabel)).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Ordres de déplacement IA : id, type, cible, statut, coordonnées (pas le payload tronqué).
    /// Lignes : id\ttype\ttarget_ref\tstatus\tpos_x\tpos_y\tlabel
    /// </summary>
    private static string SimplifyAiOrdersJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("orders", out var orders) || orders.ValueKind != JsonValueKind.Array)
                return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            static string Cell(string s)
            {
                var c = Clean(s);
                return c.Length == 0 ? "-" : c;
            }
            foreach (var el in orders.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i) ? (i.GetString() ?? "") : "";
                if (string.IsNullOrEmpty(id)) continue;
                double posX = 0, posY = 0;
                var label = "";
                if (el.TryGetProperty("waypoint", out var wp) && wp.ValueKind == JsonValueKind.Object)
                {
                    if (wp.TryGetProperty("pos_x", out var px) && px.ValueKind == JsonValueKind.Number)
                        posX = px.GetDouble();
                    if (wp.TryGetProperty("pos_y", out var py) && py.ValueKind == JsonValueKind.Number)
                        posY = py.GetDouble();
                    if (wp.TryGetProperty("label", out var lb) && lb.ValueKind == JsonValueKind.String)
                        label = lb.GetString() ?? "";
                }
                if (Math.Abs(posX) < 0.5 && Math.Abs(posY) < 0.5) continue;
                var type = el.TryGetProperty("type", out var t) ? (t.GetString() ?? "MOVE") : "MOVE";
                var targetRef = el.TryGetProperty("target_ref", out var tr) ? (tr.GetString() ?? "") : "";
                if (string.IsNullOrEmpty(targetRef) && el.TryGetProperty("target", out var tg))
                    targetRef = tg.GetString() ?? "";
                var status = el.TryGetProperty("status", out var st) ? (st.GetString() ?? "PENDING") : "PENDING";
                if (label.Length == 0 && el.TryGetProperty("payload_display", out var pd) && pd.ValueKind == JsonValueKind.String)
                    label = pd.GetString() ?? "";
                if (label.Length > 40) label = label.Substring(0, 40);
                sb.Append(Cell(id)).Append('\t')
                  .Append(Cell(type)).Append('\t')
                  .Append(Cell(targetRef)).Append('\t')
                  .Append(Cell(status)).Append('\t')
                  .Append(posX.ToString("0.##", CultureInfo.InvariantCulture)).Append('\t')
                  .Append(posY.ToString("0.##", CultureInfo.InvariantCulture)).Append('\t')
                  .Append(Cell(label)).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Simplifie GET /api/atak/mission-plan pour SQF (ordre, repères, chronologie).
    /// Lignes : P\tcode\ttitle\tstatus\th_hour\tsentence\tphase\tclock
    ///          G\tid\tcode\tlabel\tkind\tx\ty\tstate
    ///          T\tcode\tlabel\toccurred\tclock
    /// </summary>
    private static string SimplifyMissionPlanJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            if (!root.TryGetProperty("plan", out var plan) || plan.ValueKind != JsonValueKind.Object)
                return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            static string Cell(string s)
            {
                var c = Clean(s);
                return c.Length == 0 ? "-" : c;
            }
            static string Prop(JsonElement el, string name)
            {
                if (!el.TryGetProperty(name, out var p) || p.ValueKind is JsonValueKind.Null or JsonValueKind.Undefined)
                    return "";
                if (p.ValueKind == JsonValueKind.Number)
                    return p.GetRawText();
                if (p.ValueKind == JsonValueKind.True) return "1";
                if (p.ValueKind == JsonValueKind.False) return "0";
                return p.GetString() ?? "";
            }
            static string Coord(JsonElement el, string name)
            {
                if (!el.TryGetProperty(name, out var p) || p.ValueKind is JsonValueKind.Null or JsonValueKind.Undefined)
                    return "0";
                if (p.ValueKind == JsonValueKind.Number)
                    return p.GetRawText();
                var s = (p.GetString() ?? "").Trim();
                return s.Length == 0 ? "0" : s;
            }
            var sb = new StringBuilder();
            var title = Prop(plan, "operation_name");
            if (title.Length == 0) title = Prop(plan, "title");
            var sentence = Prop(plan, "mission_sentence");
            if (sentence.Length > 220) sentence = sentence.Substring(0, 220);
            var status = Prop(plan, "status_label");
            if (status.Length == 0) status = Prop(plan, "status");
            var phase = Prop(plan, "phase_label");
            if (phase.Length == 0) phase = Prop(plan, "phase");
            sb.Append('P').Append('\t')
              .Append(Cell(Prop(plan, "mission_code"))).Append('\t')
              .Append(Cell(title)).Append('\t')
              .Append(Cell(status)).Append('\t')
              .Append(Cell(Prop(plan, "h_hour_at"))).Append('\t')
              .Append(Cell(sentence)).Append('\t')
              .Append(Cell(phase)).Append('\t')
              .Append(Cell(Prop(plan, "clock_seconds"))).Append('\n');

            if (root.TryGetProperty("overlay", out var overlay) && overlay.ValueKind == JsonValueKind.Object
                && overlay.TryGetProperty("graphics", out var graphics) && graphics.ValueKind == JsonValueKind.Array)
            {
                var n = 0;
                foreach (var g in graphics.EnumerateArray())
                {
                    if (n >= 40) break;
                    var code = Prop(g, "code");
                    var id = Prop(g, "id");
                    if (id.Length == 0 && code.Length == 0) continue;
                    var x = Coord(g, "x");
                    var y = Coord(g, "y");
                    if ((x == "0" || x == "0.0") && (y == "0" || y == "0.0")
                        && g.TryGetProperty("path", out var path) && path.ValueKind == JsonValueKind.Array)
                    {
                        foreach (var pt in path.EnumerateArray())
                        {
                            var px = Coord(pt, "x");
                            var py = Coord(pt, "y");
                            if (px != "0" || py != "0")
                            {
                                x = px;
                                y = py;
                                break;
                            }
                        }
                    }
                    sb.Append('G').Append('\t')
                      .Append(Cell(id)).Append('\t')
                      .Append(Cell(code)).Append('\t')
                      .Append(Cell(Prop(g, "label"))).Append('\t')
                      .Append(Cell(Prop(g, "kind"))).Append('\t')
                      .Append(Cell(x)).Append('\t')
                      .Append(Cell(y)).Append('\t')
                      .Append(Cell(Prop(g, "draw_state"))).Append('\n');
                    n++;
                }
            }

            if (root.TryGetProperty("timeline", out var timeline) && timeline.ValueKind == JsonValueKind.Array)
            {
                var n = 0;
                foreach (var ev in timeline.EnumerateArray())
                {
                    if (n >= 16) break;
                    var label = Prop(ev, "label");
                    if (label.Length == 0) continue;
                    sb.Append('T').Append('\t')
                      .Append(Cell(Prop(ev, "event_code"))).Append('\t')
                      .Append(Cell(label)).Append('\t')
                      .Append(Cell(Prop(ev, "occurred"))).Append('\t')
                      .Append(Cell(Prop(ev, "clock"))).Append('\n');
                    n++;
                }
            }

            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Simplifie GET /api/atak/explosive-timers/commands pour SQF.
    /// Lignes : charge_id\trequested_by\tid
    /// </summary>
    private static string SimplifyExplosiveCommandsJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("commands", out var commands) || commands.ValueKind != JsonValueKind.Array)
                return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            static string Cell(string s)
            {
                var c = Clean(s);
                return c.Length == 0 ? "-" : c;
            }
            foreach (var el in commands.EnumerateArray())
            {
                var chargeId = "";
                if (el.TryGetProperty("charge_id", out var c) && c.ValueKind == JsonValueKind.String)
                    chargeId = c.GetString() ?? "";
                if (string.IsNullOrEmpty(chargeId)) continue;
                var by = "";
                if (el.TryGetProperty("requested_by", out var rb) && rb.ValueKind == JsonValueKind.String)
                    by = rb.GetString() ?? "";
                var id = "";
                if (el.TryGetProperty("id", out var i))
                {
                    id = i.ValueKind == JsonValueKind.Number
                        ? i.GetInt32().ToString(System.Globalization.CultureInfo.InvariantCulture)
                        : (i.GetString() ?? "");
                }
                sb.Append(Cell(chargeId)).Append('\t')
                  .Append(Cell(by)).Append('\t')
                  .Append(Cell(id)).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    /// <summary>
    /// Simplifie GET /api/atak/medical-alerts pour SQF.
    /// Lignes : id\tkind\tcall_sign\tlabel\tgrid\tcreated_at\ttriage_status\ttriage_label\tseverity
    /// </summary>
    private static string SimplifyMedicalAlertsJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("alerts", out var alerts) || alerts.ValueKind != JsonValueKind.Array)
                return "";
            foreach (var el in alerts.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i)
                    ? (i.ValueKind == JsonValueKind.Number ? i.GetInt32().ToString() : (i.GetString() ?? ""))
                    : "";
                if (string.IsNullOrEmpty(id) || id == "0") continue;
                var kind = el.TryGetProperty("kind", out var k) ? (k.GetString() ?? "") : "";
                var callSign = el.TryGetProperty("call_sign", out var cs) ? (cs.GetString() ?? "") : "";
                var label = el.TryGetProperty("label", out var lb) ? (lb.GetString() ?? "") : "";
                if (string.IsNullOrEmpty(label) && el.TryGetProperty("summary", out var sm))
                    label = sm.GetString() ?? "";
                var grid = el.TryGetProperty("grid", out var g) ? (g.GetString() ?? "") : "";
                var created = "";
                if (el.TryGetProperty("created_at", out var ca))
                {
                    // ISO string ou epoch numérique selon l’API — toujours une chaîne pour SQF
                    created = ca.ValueKind == JsonValueKind.Number
                        ? ca.GetDouble().ToString(System.Globalization.CultureInfo.InvariantCulture)
                        : (ca.GetString() ?? "");
                }
                var severity = el.TryGetProperty("severity", out var sev) ? (sev.GetString() ?? "") : "";
                var triageStatus = "a_secourir";
                var triageLabel = "A secourir";
                if (el.TryGetProperty("triage", out var tri) && tri.ValueKind == JsonValueKind.Object)
                {
                    if (tri.TryGetProperty("status", out var ts))
                        triageStatus = ts.GetString() ?? triageStatus;
                    if (tri.TryGetProperty("status_label", out var tl))
                        triageLabel = tl.GetString() ?? triageLabel;
                }
                static string Clean(string s) =>
                    (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
                if (created.Length > 19) created = created.Replace('T', ' ').Substring(0, 19);
                sb.Append(Clean(id)).Append('\t')
                  .Append(Clean(kind)).Append('\t')
                  .Append(Clean(callSign)).Append('\t')
                  .Append(Clean(label)).Append('\t')
                  .Append(Clean(grid)).Append('\t')
                  .Append(Clean(created)).Append('\t')
                  .Append(Clean(triageStatus)).Append('\t')
                  .Append(Clean(triageLabel)).Append('\t')
                  .Append(Clean(severity)).Append('\n');
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    private static string SimplifyFireTeamsJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty("fire_teams", out var teams)) return "";
            foreach (var el in teams.EnumerateArray())
            {
                var id = el.TryGetProperty("id", out var i) ? i.GetInt32() : 0;
                if (id <= 0) continue;
                var label = el.TryGetProperty("label", out var lb) ? (lb.GetString() ?? "") : "";
                var color = el.TryGetProperty("color", out var c) ? (c.GetString() ?? "") : "";
                var mapId = el.TryGetProperty("map_id", out var mi) && mi.ValueKind != JsonValueKind.Null ? mi.ToString() : "";
                var kind = el.TryGetProperty("kind", out var k) ? (k.GetString() ?? "") : "";
                var memberCount = el.TryGetProperty("member_count", out var mc) ? mc.GetInt32() : 0;
                label = label.Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
                color = color.Replace("\t", "").Replace("\n", "").Replace("|", "");
                sb.Append(id).Append('\t').Append(label).Append('\t').Append(color).Append('\t')
                  .Append(mapId).Append('\t').Append(kind).Append('\t').Append(memberCount).Append('\n');
                if (el.TryGetProperty("members", out var members) && members.ValueKind == JsonValueKind.Array)
                {
                    foreach (var mem in members.EnumerateArray())
                    {
                        var callsign = mem.TryGetProperty("callsign", out var cs) ? (cs.GetString() ?? "") : "";
                        var role = mem.TryGetProperty("role", out var ro) ? (ro.GetString() ?? "member") : "member";
                        var displayName = mem.TryGetProperty("display_name", out var dn) ? (dn.GetString() ?? "") : "";
                        callsign = callsign.Replace("\t", " ").Replace("\n", " ").Replace("|", "-");
                        displayName = displayName.Replace("\t", " ").Replace("\n", " ").Replace("|", "-");
                        sb.Append('M').Append('\t').Append(id).Append('\t').Append(callsign).Append('\t')
                          .Append(role).Append('\t').Append(displayName).Append('\n');
                    }
                }
            }
            return sb.ToString();
        }
        catch { return ""; }
    }

    private static string SanitizeIdentityField(string s)
    {
        return (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
    }

    private static string SimplifyIdentityQueryJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            var found = root.TryGetProperty("found", out var f) && f.ValueKind is JsonValueKind.True;
            var verdict = root.TryGetProperty("verdict", out var v) ? (v.GetString() ?? "") : "";
            var score = 0;
            if (root.TryGetProperty("score", out var sc))
            {
                if (sc.ValueKind == JsonValueKind.Number) score = sc.GetInt32();
                else int.TryParse(sc.GetString(), out score);
            }
            var name = root.TryGetProperty("name", out var n) ? (n.GetString() ?? "") : "";
            var alias = root.TryGetProperty("alias", out var a) ? (a.GetString() ?? "") : "";
            var reference = root.TryGetProperty("ref", out var r) ? (r.GetString() ?? "") : "";
            var note = root.TryGetProperty("note", out var nt) ? (nt.GetString() ?? "") : "";
            return string.Join("\t", new[]
            {
                found ? "1" : "0",
                SanitizeIdentityField(verdict),
                score.ToString(CultureInfo.InvariantCulture),
                SanitizeIdentityField(name),
                SanitizeIdentityField(alias),
                SanitizeIdentityField(reference),
                SanitizeIdentityField(note)
            });
        }
        catch { return "0\tRéponse illisible\t0\t\t\t\t"; }
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
            // Séparateur tab : côté SQF il faut splitString (toString [9]), PAS "\t" (\ + t).
            // Nettoyer | / tab / LF pour ne pas casser le parse pipe+colonnes.
            token = SanitizePhoneField(token);
            code = SanitizePhoneField(code).ToUpperInvariant();
            connectUrl = SanitizePhoneField(connectUrl);
            qrImageUrl = SanitizePhoneField(qrImageUrl);
            expiresAt = SanitizePhoneField(expiresAt);
            // Code court obligatoire (alphanumérique) ; URL de connexion obligatoire ; QR optionnel.
            if (token.Length == 0 || code.Length < 4 || code.Length > 12) return "";
            if (connectUrl.Length == 0) return "";
            if (code.Contains("://", StringComparison.Ordinal) || code.Contains('/') || code.Contains('.'))
                return "";
            for (var i = 0; i < code.Length; i++)
            {
                if (!char.IsAsciiLetterOrDigit(code[i])) return "";
            }
            return token + "\t" + code + "\t" + connectUrl + "\t" + qrImageUrl + "\t" + expiresAt;
        }
        catch { return ""; }
    }

    private static string SanitizePhoneField(string? value)
    {
        if (string.IsNullOrEmpty(value)) return "";
        return value
            .Replace("\t", " ")
            .Replace("\n", " ")
            .Replace("\r", "")
            .Replace("|", "-")
            .Trim();
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
            var militaryId = root.TryGetProperty("military_id", out var mid) ? (mid.GetString() ?? "") : "";
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
            militaryId = militaryId.Replace("\t", " ").Replace("\n", " ").Replace("\r", "");
            if (displayName.Length == 0 && callsign.Length == 0) return "";
            return displayName + "\t" + callsign + "\t" + avatarUrl + "\t" + unitName + "\t" + atakId
                + "\t" + playtimeHours + "\t" + lastSeenAt + "\t" + militaryId;
        }
        catch { return ""; }
    }

    /// <summary>
    /// POST /api/atak/terminals — terminal_id, terminal_uid, status (tab).
    /// </summary>
    private static string SimplifyTerminalRegisterJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            if (!root.TryGetProperty("terminal", out var term) || term.ValueKind != JsonValueKind.Object)
                return "";
            var id = term.TryGetProperty("id", out var idEl) ? idEl.ToString() : "";
            var uid = term.TryGetProperty("terminal_uid", out var uidEl) ? (uidEl.GetString() ?? "") : "";
            var status = term.TryGetProperty("status", out var stEl) ? (stEl.GetString() ?? "") : "";
            if (id.Length == 0 && uid.Length == 0) return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            return Clean(id) + "\t" + Clean(uid) + "\t" + Clean(status);
        }
        catch { return ""; }
    }

    /// <summary>
    /// POST /api/atak/certificates — certificate_ref, status, expires_at (tab).
    /// </summary>
    private static string SimplifyCertificateRegisterJson(string json)
    {
        try
        {
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            if (!root.TryGetProperty("certificate", out var cert) || cert.ValueKind != JsonValueKind.Object)
                return "";
            var reference = cert.TryGetProperty("certificate_ref", out var refEl) ? (refEl.GetString() ?? "") : "";
            var status = cert.TryGetProperty("status", out var stEl) ? (stEl.GetString() ?? "") : "";
            var expires = cert.TryGetProperty("expires_at", out var exEl) ? (exEl.GetString() ?? "") : "";
            if (reference.Length == 0) return "";
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            if (expires.Length > 19) expires = expires.Replace('T', ' ').Substring(0, 19);
            return Clean(reference) + "\t" + Clean(status) + "\t" + Clean(expires);
        }
        catch { return ""; }
    }

    /// <summary>
    /// GET /api/atak/terminals?terminal_uid=… — lignes clé\tvaleur pour SQF.
    /// </summary>
    private static string SimplifyTerminalRealismJson(string json)
    {
        try
        {
            var sb = new StringBuilder();
            using var doc = JsonDocument.Parse(json);
            var root = doc.RootElement;
            static string Clean(string s) =>
                (s ?? "").Replace("\t", " ").Replace("\n", " ").Replace("\r", "").Replace("|", "-");
            static void AppendLine(StringBuilder sb, string key, string val)
            {
                if (sb.Length > 0) sb.Append('\n');
                sb.Append(Clean(key)).Append('\t').Append(Clean(val));
            }

            if (root.TryGetProperty("terminal", out var term) && term.ValueKind == JsonValueKind.Object)
            {
                if (term.TryGetProperty("id", out var idEl))
                    AppendLine(sb, "terminal_id", idEl.ToString());
                if (term.TryGetProperty("terminal_uid", out var uidEl))
                    AppendLine(sb, "terminal_uid", uidEl.GetString() ?? "");
                if (term.TryGetProperty("status", out var stEl))
                    AppendLine(sb, "terminal_status", stEl.GetString() ?? "");
            }

            var certStatus = "missing";
            var certExpires = "";
            var certRef = "";
            if (root.TryGetProperty("certificate", out var cert) && cert.ValueKind == JsonValueKind.Object)
            {
                certRef = cert.TryGetProperty("certificate_ref", out var refEl) ? (refEl.GetString() ?? "") : "";
                var rawStatus = cert.TryGetProperty("status", out var cst) ? (cst.GetString() ?? "") : "";
                certExpires = cert.TryGetProperty("expires_at", out var exEl) ? (exEl.GetString() ?? "") : "";
                if (certExpires.Length > 19) certExpires = certExpires.Replace('T', ' ').Substring(0, 19);
                // Sentinelles Arma « <null> » → considérer comme absent pour forcer une réémission
                if (certRef.Length == 0
                    || certRef.Contains("<null", StringComparison.OrdinalIgnoreCase)
                    || certRef.Contains("<nul>", StringComparison.OrdinalIgnoreCase)
                    || certRef.Equals("null", StringComparison.OrdinalIgnoreCase)
                    || certRef.Contains("-null", StringComparison.OrdinalIgnoreCase))
                {
                    certRef = "";
                    certStatus = "missing";
                }
                else
                {
                    certStatus = rawStatus.Length > 0 ? rawStatus : "active";
                    if (certStatus is "active" or "issued" && certExpires.Length > 0)
                    {
                        if (DateTime.TryParse(certExpires, System.Globalization.CultureInfo.InvariantCulture,
                                System.Globalization.DateTimeStyles.AssumeUniversal, out var exp)
                            && exp.ToUniversalTime() < DateTime.UtcNow)
                        {
                            certStatus = "expired";
                        }
                    }
                    if (certRef.Length > 0) AppendLine(sb, "certificate_ref", certRef);
                }
            }
            AppendLine(sb, "cert_status", certStatus);
            if (certExpires.Length > 0) AppendLine(sb, "cert_expires", certExpires);

            if (root.TryGetProperty("atak_defaults", out var defs) && defs.ValueKind == JsonValueKind.Object)
            {
                if (defs.TryGetProperty("automatic_pairing", out var ap))
                    AppendLine(sb, "auto_pairing", ap.ValueKind == JsonValueKind.True || (ap.ValueKind == JsonValueKind.Number && ap.GetInt32() != 0) ? "1" : "0");
                if (defs.TryGetProperty("minimum_client_version", out var mv))
                    AppendLine(sb, "min_client_version", mv.GetString() ?? "");
                if (defs.TryGetProperty("certificate_duration_days", out var cd))
                    AppendLine(sb, "cert_duration_days", cd.ToString());
            }

            return sb.ToString();
        }
        catch { return ""; }
    }

    private static void RvExtensionArgsImpl(string? function, string?[] args)
    {
        try
        {
            if (function == "Connect")
            {
                // Chemin async de secours (TryGetSyncResponse gère normalement Connect).
                if (args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
                {
                    var normalized = NormalizeBaseUrl(args[0]);
                    if (normalized.Length == 0) return;
                    _baseUrl = normalized;
                    var key = args.Length > 1 ? (args[1] ?? "") : "";
                    ApplyApiKeyHeaders(key);
                    if (args.Length > 2)
                        ApplyTenantId(args[2]);
                    if (_apiKey.Length > 0)
                        StartClientInitAsync();
                }
                return;
            }

            if (function == "UpdatePosition" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 4)
            {
                // InvariantCulture + virgule→point : SQF str/format sous locale FR envoie « 1850,12 ».
                // Sans normalisation : parse raté → (0,0) drop, ou « 1850,12 » lu comme milliers → hors limites.
                static double ParseArmaFloat(string? raw, double fallback = 0)
                {
                    if (string.IsNullOrWhiteSpace(raw)) return fallback;
                    var s = raw.Trim().Replace(',', '.');
                    return double.TryParse(s, System.Globalization.NumberStyles.Float,
                        System.Globalization.CultureInfo.InvariantCulture, out var v) ? v : fallback;
                }
                static double? ParseArmaFloatNullable(string? raw)
                {
                    if (string.IsNullOrWhiteSpace(raw)) return null;
                    var s = raw.Trim().Replace(',', '.');
                    return double.TryParse(s, System.Globalization.NumberStyles.Float,
                        System.Globalization.CultureInfo.InvariantCulture, out var v) ? v : null;
                }
                var posX = ParseArmaFloat(args[0]);
                var posY = ParseArmaFloat(args[1]);
                var heading = ParseArmaFloatNullable(args[2]);
                var callSign = (args[3] ?? "").Trim();
                if (callSign.Equals("Unknown", StringComparison.OrdinalIgnoreCase)
                    || callSign.Equals("Inconnu", StringComparison.OrdinalIgnoreCase))
                    callSign = "";
                var role = args.Length > 4 ? (args[4] ?? "") : "";
                var health = args.Length > 5 ? (args[5] ?? "ok") : "ok";
                var fuel = args.Length > 6 ? (args[6] ?? "") : "";
                var ammo = args.Length > 7 ? (args[7] ?? "n/a") : "n/a";
                var radioFreq = args.Length > 8 ? (args[8] ?? "") : "";
                // args[9] = JSON véhicule optionnel (speed, in_vehicle, vector_dir, vector_up, velocity…)
                var vehicleJson = args.Length > 9 ? (args[9] ?? "") : "";
                // args[10] = Steam UID — repli sur UID mémorisé à la liaison (ne pas faire confiance au seul SQF).
                var steamUid = args.Length > 10 ? (args[10] ?? "").Trim() : "";
                if (TryNormalizeSteamUid(steamUid, out var steamNorm))
                    _steamUid = steamNorm;
                else if (_steamUid.Length > 0)
                    steamNorm = _steamUid;
                else
                    steamNorm = "";
                // args[11] = nom de groupe Arma (groupId)
                var groupName = args.Length > 11 ? (args[11] ?? "").Trim() : "";
                // args[12] = altitude ASL (getPosASL Z) — format BI absolu pour Z
                double? aslZ = args.Length > 12 ? ParseArmaFloatNullable(args[12]) : null;
                // args[13] = version mod Overwatch (optionnel)
                if (args.Length > 13)
                    ApplyModVersion(args[13]);
                // Position2D carte : X/Y hors origine (0,0) = menu / parse raté — ne pas poster
                if (Math.Abs(posX) < 1.0 && Math.Abs(posY) < 1.0)
                    return;
                // Mémo pose pour uploads photo déclenchés par FileSystemWatcher (sans SQF).
                if (callSign.Length > 0)
                {
                    _lastPhotoAuthor = callSign;
                    if (_callSign.Length == 0) _callSign = callSign;
                }
                _lastPhotoPosX = posX.ToString("R", System.Globalization.CultureInfo.InvariantCulture);
                _lastPhotoPosY = posY.ToString("R", System.Globalization.CultureInfo.InvariantCulture);
                if (aslZ.HasValue)
                    _lastPhotoPosZ = aslZ.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture);
                if (heading.HasValue)
                    _lastPhotoHeading = heading.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture);
                var headingStr = heading.HasValue ? heading.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture) : "null";
                var extra = new System.Text.StringBuilder();
                extra.Append("\"role\":\"").Append(EscapeJson(role)).Append("\"");
                extra.Append(",\"health\":\"").Append(EscapeJson(health)).Append("\"");
                if (!string.IsNullOrEmpty(fuel)) extra.Append(",\"fuel\":\"").Append(EscapeJson(fuel)).Append("\"");
                extra.Append(",\"ammo\":\"").Append(EscapeJson(ammo)).Append("\"");
                if (!string.IsNullOrEmpty(radioFreq)) extra.Append(",\"radio_freq\":\"").Append(EscapeJson(radioFreq)).Append("\"");
                if (!string.IsNullOrEmpty(groupName))
                {
                    extra.Append(",\"group_name\":\"").Append(EscapeJson(groupName)).Append("\"");
                    extra.Append(",\"group\":\"").Append(EscapeJson(groupName)).Append("\"");
                }
                // ID BFT lié à l’indicatif (mémorisé à client-init / profil)
                // Contacts relais (téléphone / IA alliée) : ne pas coller l’identité du joueur pont.
                var isProxyContact = !string.IsNullOrWhiteSpace(vehicleJson)
                    && (vehicleJson.Contains("\"phone_geoloc\"", StringComparison.Ordinal)
                        || vehicleJson.Contains("\"ally_ai\"", StringComparison.Ordinal)
                        || vehicleJson.Contains("\"gps_beacon\"", StringComparison.Ordinal)
                        || vehicleJson.Contains("\"source\":\"phone\"", StringComparison.Ordinal)
                        || vehicleJson.Contains("\"source\":\"ally\"", StringComparison.Ordinal)
                        || vehicleJson.Contains("\"source\":\"gps\"", StringComparison.Ordinal));
                if (!isProxyContact && _militaryId.Length > 0
                    && (string.IsNullOrWhiteSpace(vehicleJson)
                        || (!vehicleJson.Contains("\"bft_id\"", StringComparison.Ordinal)
                            && !vehicleJson.Contains("\"military_id\"", StringComparison.Ordinal))))
                {
                    extra.Append(",\"bft_id\":\"").Append(EscapeJson(_militaryId)).Append("\"");
                    extra.Append(",\"military_id\":\"").Append(EscapeJson(_militaryId)).Append("\"");
                }
                if (aslZ.HasValue)
                {
                    var aslStr = aslZ.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture);
                    // Évite doublon si le JSON véhicule fournit déjà asl_z / pos_z
                    if (string.IsNullOrWhiteSpace(vehicleJson)
                        || (!vehicleJson.Contains("\"asl_z\"", StringComparison.Ordinal)
                            && !vehicleJson.Contains("\"pos_z\"", StringComparison.Ordinal)))
                    {
                        extra.Append(",\"asl_z\":").Append(aslStr);
                        extra.Append(",\"pos_z\":").Append(aslStr);
                    }
                }
                if (!string.IsNullOrWhiteSpace(vehicleJson) && vehicleJson.StartsWith('{') && vehicleJson.EndsWith('}'))
                {
                    // Locale FR : format SQF peut produire « "speed":1,5 » → JSON invalide →
                    // tout le POST /position est rejeté (body vide) alors que SALUTE (chat) passe.
                    var sanitizedVeh = System.Text.RegularExpressions.Regex.Replace(
                        vehicleJson,
                        @"(?<=[:\[\s])(-?\d+),(\d+)(?=[,\}\]\s])",
                        "$1.$2");
                    try
                    {
                        using var _ = JsonDocument.Parse(sanitizedVeh);
                        var inner = sanitizedVeh.Substring(1, sanitizedVeh.Length - 2).Trim();
                        if (inner.Length > 0) extra.Append(',').Append(inner);
                    }
                    catch
                    {
                        // Métadonnées véhicule irrécupérables : poster quand même la Position2D.
                    }
                }
                var steamJson = (!isProxyContact && steamNorm.Length > 0)
                    ? $",\"steam_uid\":\"{EscapeJson(steamNorm)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var modJson = ModVersionJsonFragment();
                var aslJson = aslZ.HasValue
                    ? $",\"pos_z\":{aslZ.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"asl_z\":{aslZ.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture)}"
                    : "";
                if (_modVersion.Length > 0
                    && (string.IsNullOrWhiteSpace(vehicleJson)
                        || !vehicleJson.Contains("\"mod_version\"", StringComparison.Ordinal)))
                {
                    extra.Append(",\"mod_version\":\"").Append(EscapeJson(_modVersion)).Append('"');
                }
                var payload = $"{{\"mapId\":1,\"call_sign\":\"{EscapeJson(callSign)}\",\"pos_x\":{posX.ToString("R", System.Globalization.CultureInfo.InvariantCulture)},\"pos_y\":{posY.ToString("R", System.Globalization.CultureInfo.InvariantCulture)}{aslJson},\"heading\":{headingStr},\"role\":\"{EscapeJson(role)}\"{steamJson}{sessJson}{modJson},\"extra\":{{{extra}}}}}";
                EnqueueOrSend(_baseUrl + "/api/atak/position", payload);
                return;
            }

            if (function == "Terrain.Chunk")
            {
                // Acquittement synchrone dans TryGetSyncResponse (HandleTerrainChunk).
                return;
            }

            if (function == "ReportPlaytime" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var uid = args[0] ?? "";
                if (!TryNormalizeSteamUid(uid, out var uidNorm))
                {
                    if (_steamUid.Length == 0) return;
                    uidNorm = _steamUid;
                }
                else
                {
                    _steamUid = uidNorm;
                }
                var secStr = args[1] ?? "0";
                var secs = long.TryParse(secStr, out var s) ? s : 0L;
                if (secs < 1) return;
                if (secs > 7200) secs = 7200;
                var call = args.Length > 2 ? (args[2] ?? "") : "";
                var tenantId = args.Length > 3 ? (args[3] ?? "") : "";
                if (string.IsNullOrEmpty(tenantId)) tenantId = _tenantId;
                var tenantJson = string.IsNullOrEmpty(tenantId) ? "" : $",\"tenant_id\":\"{EscapeJson(tenantId)}\"";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var payload = $"{{\"player_uid\":\"{EscapeJson(uidNorm)}\",\"session_seconds\":{secs.ToString(System.Globalization.CultureInfo.InvariantCulture)},\"call_sign\":\"{EscapeJson(call)}\"{tenantJson}{sessJson}}}";
                EnqueueOrSend(_baseUrl + "/api/atak/playtime", payload);
                return;
            }

            if (function == "SendChat" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                var author = args[0] ?? "Unknown";
                var body = args[1] ?? "";
                var steamJson = _steamUid.Length > 0
                    ? $",\"steam_uid\":\"{EscapeJson(_steamUid)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var payload = $"{{\"mapId\":1,\"author\":\"{EscapeJson(author)}\",\"body\":\"{EscapeJson(body)}\"{steamJson}{sessJson}}}";
                EnqueueOrSend(_baseUrl + "/api/chat", payload);
                return;
            }

            if (function == "SendPing" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 4)
            {
                var author = args[0] ?? "Unknown";
                var x = args[1] ?? "0";
                var y = args[2] ?? "0";
                var msg = args[3] ?? "Ping";
                var steamJson = _steamUid.Length > 0
                    ? $",\"steam_uid\":\"{EscapeJson(_steamUid)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var payload = $"{{\"mapId\":1,\"author\":\"{EscapeJson(author)}\",\"pos_x\":{x},\"pos_y\":{y},\"message\":\"{EscapeJson(msg)}\"{steamJson}{sessJson}}}";
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
                var markerDataRaw = SanitizeLooseJsonObject(args[1] ?? "{}");
                var layerId = args.Length > 2 ? (args[2] ?? "1") : "1";
                var deleted = args.Length > 3 && (args[3] ?? "") == "1";
                var steamJson = _steamUid.Length > 0
                    ? $",\"steam_uid\":\"{EscapeJson(_steamUid)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var modJson = ModVersionJsonFragment();
                var payload = deleted
                    ? "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\"" + steamJson + sessJson + modJson + ",\"deleted\":true}"
                    : "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\"" + steamJson + sessJson + modJson + ",\"markerData\":" + markerDataRaw + "}";
                EnqueueOrSend(_baseUrl + "/api/atak/marker", payload);
                return;
            }

            if (function == "SendWeather" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var weatherJson = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(weatherJson)) return;
                EnqueueOrSend(_baseUrl + "/api/atak/weather", weatherJson);
                return;
            }

            if (function == "SendVideoFeeds" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var feedsJson = args[0] ?? "{}";
                if (string.IsNullOrWhiteSpace(feedsJson)) return;
                // Même filet que les autres POST : guillemets doublés Arma → identity/payload cassé / 500.
                EnqueueOrSend(_baseUrl + "/api/atak/video-feeds", EnrichAtakPayload(feedsJson));
                return;
            }

            if (function == "UploadLatestScreenshot" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                // Géré en synchrone par TryGetSyncResponse (BeginUploadLatestScreenshot).
                return;
            }

            if (function == "UploadImage" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                // Géré en synchrone par TryGetSyncResponse (BeginUploadIntelPhoto).
                return;
            }

            if (function == "SendFlightManifest" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                var json = NormalizeArmaJson(args[0]);
                EnqueueOrSend(_baseUrl + "/api/atak/flight-manifest", EnrichAtakPayload(json));
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

            if ((function == "UploadReconImage" || function == "NotifyNewPhoto" || function == "EnqueueReconImage")
                && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 1)
            {
                // Géré en sync court par TryGetSyncResponse (EnqueueReconImage → worker).
                return;
            }

            if (function == "UploadSsePhoto" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                // Géré en sync court par TryGetSyncResponse (file + worker, comme NotifyNewPhoto).
                return;
            }

            if (function == "UploadSseNoteAttachment" && !string.IsNullOrEmpty(_baseUrl) && args.Length >= 2)
            {
                // Géré en synchrone par TryGetSyncResponse (BeginUploadSseNoteAttachment).
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
                    var content = new StringContent(payload, Encoding.UTF8);
                    content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
                    var request = new HttpRequestMessage(HttpMethod.Patch, url) { Content = content };
                    AttachApiKeyHeader(request);
                    _ = HttpClient.SendAsync(request);
                }
                catch
                {
                    var url = _baseUrl + "/api/atak/air-assets/" + Uri.EscapeDataString(callsign) + "/pilot-status";
                    EnqueueForRetry(url, payload);
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

    /// <summary>
    /// Sidecar photo : accepte le signal SQF (chemin / nom) et file resolve+upload en arrière-plan.
    /// Retour immédiat : OK|queued | OK|duplicate | ERR|…
    /// Alias callExtension : NotifyNewPhoto, EnqueueReconImage, UploadReconImage.
    /// </summary>
    private static string EnqueueReconImage(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl) || _apiKey.Length == 0)
            return "ERR|not_connected";

        EnsureScreenshotQuota();

        var rawPath = args.Length > 0 ? (args[0] ?? "") : "";
        var author = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1])
            ? args[1]!.Trim()
            : (_lastPhotoAuthor.Length > 0 ? _lastPhotoAuthor : (_callSign.Length > 0 ? _callSign : "Unknown"));

        var trimmedPath = rawPath.Trim().Trim('"').Trim('\'');
        // Nom seul (avec ou sans extension) : autoriser le repli « newest since enqueue »
        // — Photo Library / BCE annoncent souvent « foo.jpg » sans jamais écrire le fichier
        // au chemin annoncé ; le screenshot Arma ou le watcher peut arriver juste après.
        var normalized = trimmedPath.Replace('/', '\\');
        var isNameOnly = normalized.Length > 0
            && !normalized.Contains('\\');
        var parentMissing = false;
        try
        {
            if (normalized.Length > 0
                && Path.IsPathRooted(normalized)
                && !(normalized.StartsWith('\\') && !normalized.StartsWith("\\\\", StringComparison.Ordinal)))
            {
                var parent = Path.GetDirectoryName(normalized);
                parentMissing = !string.IsNullOrWhiteSpace(parent) && !Directory.Exists(parent);
            }
        }
        catch { /* ignore */ }

        var newestFallback = string.IsNullOrWhiteSpace(trimmedPath)
            || isNameOnly
            || parentMissing;

        var dedupKey = NormalizePhotoDedupKey(trimmedPath.Length > 0 ? trimmedPath : ("newest|" + author));
        if (!TryClaimPhotoDedup(dedupKey))
            return "OK|duplicate";

        // File plafonnée : évite accumulation si Athena est down.
        if (PhotoJobs.Count >= PhotoQueueMax)
            return "ERR|queue_full";

        var meta = new string?[Math.Max(args.Length, 17)];
        for (var i = 0; i < args.Length; i++)
            meta[i] = args[i];
        meta[0] = trimmedPath;
        meta[1] = author;
        // Compléter pose absente avec dernière UpdatePosition (signal SQF minimal / watcher).
        if (string.IsNullOrWhiteSpace(meta[2]) && _lastPhotoPosX.Length > 0) meta[2] = _lastPhotoPosX;
        if (string.IsNullOrWhiteSpace(meta[3]) && _lastPhotoPosY.Length > 0) meta[3] = _lastPhotoPosY;
        if (string.IsNullOrWhiteSpace(meta[4]) && _lastPhotoPosZ.Length > 0) meta[4] = _lastPhotoPosZ;
        if (string.IsNullOrWhiteSpace(meta[5]) && _lastPhotoGrid.Length > 0) meta[5] = _lastPhotoGrid;
        if (string.IsNullOrWhiteSpace(meta[6]) && _lastPhotoHeading.Length > 0) meta[6] = _lastPhotoHeading;

        PhotoJobs.Enqueue(new ReconPhotoJob
        {
            RawPath = trimmedPath,
            Author = author,
            Meta = meta,
            DedupKey = dedupKey,
            NewestFallback = newestFallback,
            EnqueuedUtc = DateTime.UtcNow
        });
        EnsureScreenshotWatchers();
        EnsurePhotoWorker();
        return "OK|queued";
    }

    private static string NormalizePhotoDedupKey(string raw)
    {
        var p = (raw ?? "").Trim().Trim('"').Trim('\'').Replace('/', '\\');
        if (p.Length == 0) return "empty";
        try { return Path.GetFullPath(p); }
        catch { return p.ToLowerInvariant(); }
    }

    private static bool TryClaimPhotoDedup(string key)
    {
        if (string.IsNullOrWhiteSpace(key)) return false;
        PrunePhotoDedup();
        var now = DateTime.UtcNow.Ticks;
        if (PhotoDedupTicks.TryGetValue(key, out var prev))
        {
            if (now - prev < TimeSpan.FromSeconds(PhotoDedupTtlSeconds).Ticks)
                return false;
        }
        PhotoDedupTicks[key] = now;
        return true;
    }

    /// <summary>
    /// Libère le dédup après échec (fichier introuvable / HTTP) pour permettre un vrai retry.
    /// </summary>
    private static void ReleasePhotoDedup(string? key)
    {
        if (string.IsNullOrWhiteSpace(key)) return;
        PhotoDedupTicks.TryRemove(key, out _);
    }

    private static void PrunePhotoDedup()
    {
        if (PhotoDedupTicks.Count < 80) return;
        var cutoff = DateTime.UtcNow.AddSeconds(-PhotoDedupTtlSeconds).Ticks;
        foreach (var kv in PhotoDedupTicks)
        {
            if (kv.Value < cutoff)
                PhotoDedupTicks.TryRemove(kv.Key, out _);
        }
    }

    private static void EnsurePhotoWorker()
    {
        if (Interlocked.CompareExchange(ref _photoWorkerRunning, 1, 0) != 0)
            return;
        _ = Task.Run(async () =>
        {
            try
            {
                while (PhotoJobs.TryDequeue(out var job))
                {
                    try { await ProcessReconPhotoJobAsync(job).ConfigureAwait(false); }
                    catch { /* ne jamais tuer le worker */ }
                }
            }
            finally
            {
                Interlocked.Exchange(ref _photoWorkerRunning, 0);
                // Course : job enfilé pendant le finally → relancer.
                if (!PhotoJobs.IsEmpty)
                    EnsurePhotoWorker();
            }
        });
    }

    /// <summary>
    /// Resolve + upload hors thread callExtension (autorise attentes fichier BCE).
    /// </summary>
    private static async Task ProcessReconPhotoJobAsync(ReconPhotoJob job)
    {
        var cbName = string.Equals(job.UploadKind, "sse_face", StringComparison.OrdinalIgnoreCase)
            ? "SsePhotoUpload"
            : "PhotoUpload";

        if (string.IsNullOrEmpty(_baseUrl) || _apiKey.Length == 0)
        {
            ReleasePhotoDedup(job.DedupKey);
            InvokeCallback(cbName, "ERR|not_connected|" + Path.GetFileName(job.RawPath));
            return;
        }

        TimeSpan? newestFallback = job.NewestFallback ? TimeSpan.FromSeconds(180) : null;
        string? resolved = null;
        string? identityKey = null;

        EnsureScreenshotQuota();

        // Attente non bloquante du flush disque (BCE / Arma_ScreenShot) — max ~12 s.
        // Chemins Photo Library morts (srcdir_missing) : chercher par nom + captures
        // écrites depuis l’enqueue (screenshot Arma / watcher), sans abandon immédiat.
        var isSseFace = string.Equals(job.UploadKind, "sse_face", StringComparison.OrdinalIgnoreCase);
        for (var attempt = 0; attempt < 25; attempt++)
        {
            var fb = newestFallback;
            if (job.NewestFallback && attempt >= 0)
                fb = TimeSpan.FromSeconds(Math.Max(180, (DateTime.UtcNow - job.EnqueuedUtc).TotalSeconds + 30));
            resolved = ResolveLocalImagePath(job.RawPath, attempt >= 2 ? fb : newestFallback);
            if (resolved == null && attempt >= 1)
            {
                var leaf = Path.GetFileName((job.RawPath ?? "").Replace('/', '\\'));
                if (!string.IsNullOrWhiteSpace(leaf))
                    resolved = FindScreenshotByFileName(leaf);
            }
            if (resolved == null && isSseFace && attempt >= 2)
                resolved = FindNewestMatchingPrefix("COMSPEC_SSE_Face", TimeSpan.FromSeconds(
                    Math.Max(180, (DateTime.UtcNow - job.EnqueuedUtc).TotalSeconds + 30)));
            if (resolved == null && attempt >= 2)
                resolved = FindNewestScreenshotSince(job.EnqueuedUtc.AddSeconds(-5));
            if (resolved != null) break;
            try
            {
                var p = (job.RawPath ?? "").Replace('/', '\\');
                var parent = Path.GetDirectoryName(p);
                var parentMissing = !string.IsNullOrWhiteSpace(parent) && !Directory.Exists(parent);
                var orphan = Path.GetFileName(p);
                if (parentMissing && !string.IsNullOrWhiteSpace(orphan))
                {
                    resolved = FindScreenshotByFileName(orphan);
                    if (resolved == null && attempt >= 2)
                        resolved = FindNewestScreenshotSince(job.EnqueuedUtc.AddSeconds(-5));
                    if (resolved != null || attempt >= 8)
                        break;
                }
            }
            catch { /* ignore */ }
            await Task.Delay(400).ConfigureAwait(false);
        }

        if (resolved == null)
        {
            // Ne pas libérer le dédup : un fichier définitivement introuvable
            // (Photo Library morte / srcdir_missing) ne doit pas être re-scanné
            // en boucle (coût disque + risque STATUS_STACK_OVERFLOW).
            var hint = string.IsNullOrWhiteSpace(job.RawPath)
                ? "empty_path"
                : Path.GetFileName(job.RawPath.Replace('/', '\\'));
            InvokeCallback(cbName,
                $"ERR|file_not_found|{hint}|{DescribeImageLookupFailure(job.RawPath)}");
            return;
        }

        // Dédup secondaire après résolution (watcher + SQF → même fichier).
        try
        {
            var fi = new FileInfo(resolved);
            if (!fi.Exists)
            {
                ReleasePhotoDedup(job.DedupKey);
                InvokeCallback(cbName, "ERR|file_not_found|" + Path.GetFileName(resolved));
                return;
            }
            if (fi.Length < 32)
            {
                ReleasePhotoDedup(job.DedupKey);
                InvokeCallback(cbName, "ERR|file_empty|" + Path.GetFileName(resolved));
                return;
            }
            identityKey = $"{fi.FullName.ToLowerInvariant()}|{fi.Length}|{fi.LastWriteTimeUtc.Ticks}";
            if (!string.Equals(identityKey, job.DedupKey, StringComparison.OrdinalIgnoreCase)
                && !TryClaimPhotoDedup(identityKey))
            {
                // Autre job déjà en cours / récemment traité pour le même fichier.
                InvokeCallback(cbName, "OK|duplicate|" + Path.GetFileName(resolved));
                return;
            }
            MirrorCapture(resolved);
        }
        catch
        {
            ReleasePhotoDedup(job.DedupKey);
            ReleasePhotoDedup(identityKey);
            InvokeCallback(cbName, "ERR|read_failed|" + Path.GetFileName(resolved));
            return;
        }

        if (isSseFace)
        {
            await ProcessSseFacePhotoUploadAsync(job, resolved, identityKey).ConfigureAwait(false);
            return;
        }

        MultipartFormDataContent? multipart = null;
        HttpRequestMessage? req = null;
        try
        {
            var args = job.Meta ?? Array.Empty<string?>();
            var author = !string.IsNullOrWhiteSpace(job.Author) ? job.Author : "Unknown";
            multipart = new MultipartFormDataContent();
            multipart.Add(new StringContent("1"), "mapId");
            multipart.Add(new StringContent(author), "author");
            AddOptionalForm(multipart, "pos_x", args, 2);
            AddOptionalForm(multipart, "pos_y", args, 3);
            AddOptionalForm(multipart, "pos_z", args, 4);
            AddOptionalForm(multipart, "grid_ref", args, 5);
            AddOptionalForm(multipart, "heading", args, 6);
            AddOptionalForm(multipart, "altitude", args, 7);
            AddOptionalForm(multipart, "caption", args, 8);
            AddOptionalForm(multipart, "unit_name", args, 9);
            multipart.Add(new StringContent(args.Length > 10 && !string.IsNullOrEmpty(args[10]) ? args[10]! : "WEST"), "side");
            AddOptionalForm(multipart, "mission_id", args, 11);
            multipart.Add(new StringContent(args.Length > 12 && !string.IsNullOrEmpty(args[12]) ? args[12]! : "CTAB"), "device_type");
            AddOptionalForm(multipart, "capturedAt", args, 13);
            AddOptionalForm(multipart, "feed_id", args, 14);
            AddOptionalForm(multipart, "fx_profile", args, 15);
            AddOptionalForm(multipart, "fx_intensity", args, 16);
            if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
            if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
            var fileName = Path.GetFileName(resolved) ?? "recon.png";
            var fileStream = new FileStream(resolved, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 128 * 1024, FileOptions.Asynchronous | FileOptions.SequentialScan);
            var fileContent = new StreamContent(fileStream);
            fileContent.Headers.ContentType = new MediaTypeHeaderValue(GuessImageMediaType(resolved));
            multipart.Add(fileContent, "image", fileName);

            // Attendre le POST ici (worker déjà hors thread jeu) → ACK réel vers SQF.
            if (!TryBuildRequestUri(_baseUrl, "/api/recon/images", out var uri, out var err) || uri is null)
            {
                ReleasePhotoDedup(job.DedupKey);
                ReleasePhotoDedup(identityKey);
                try { multipart.Dispose(); } catch { /* ignore */ }
                InvokeCallback("PhotoUpload", "ERR|" + err + "|" + fileName);
                return;
            }

            req = new HttpRequestMessage(HttpMethod.Post, uri) { Content = multipart };
            multipart = null; // ownership transferred to request
            AttachApiKeyHeader(req);
            using var resp = await UploadHttpClient.SendAsync(req).ConfigureAwait(false);
            var code = (int)resp.StatusCode;
            if (resp.IsSuccessStatusCode)
            {
                NoteRateLimitCleared();
                InvokeCallback("PhotoUpload", "OK|uploaded|" + fileName);
                return;
            }

            ReleasePhotoDedup(job.DedupKey);
            ReleasePhotoDedup(identityKey);
            NotePostError(code, uri.AbsoluteUri);
            if (code == 401 && _sessionToken.Length > 0)
                _sessionToken = "";
            InvokeCallback("PhotoUpload", $"ERR|http_{code}|{fileName}");
        }
        catch
        {
            ReleasePhotoDedup(job.DedupKey);
            ReleasePhotoDedup(identityKey);
            InvokeCallback("PhotoUpload", "ERR|network|" + Path.GetFileName(resolved));
        }
        finally
        {
            try { req?.Dispose(); } catch { /* ignore */ }
            try { multipart?.Dispose(); } catch { /* ignore */ }
        }
    }

    /// <summary>
    /// Surveille Screenshots/Screenshot (profil + Workshop) et file les nouveaux fichiers.
    /// </summary>
    private static void EnsureScreenshotWatchers()
    {
        if (_screenshotWatchersStarted) return;
        lock (ScreenshotWatcherLock)
        {
            if (_screenshotWatchersStarted) return;
            var nowTicks = DateTime.UtcNow.Ticks;
            // Throttle si aucun dossier encore visible (cwd Arma / profils pas prêts).
            if (_lastWatcherAttemptTicks > 0
                && nowTicks - _lastWatcherAttemptTicks < TimeSpan.FromSeconds(20).Ticks
                && ScreenshotWatchers.Count == 0)
                return;
            _lastWatcherAttemptTicks = nowTicks;
            _screenshotWatchersStartedTicks = nowTicks;
            var started = 0;
            foreach (var dir in EnumerateScreenshotDirs())
            {
                try
                {
                    if (!Directory.Exists(dir)) continue;
                    // Jamais surveiller la racine Arma / un dossier « fourre-tout » :
                    // IncludeSubdirectories dessus = tempête d’événements + scans fatals.
                    if (!IsScreenshotCaptureDir(dir)) continue;
                    var w = new FileSystemWatcher(dir)
                    {
                        Filter = "*.*",
                        IncludeSubdirectories = false,
                        NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite | NotifyFilters.CreationTime,
                        InternalBufferSize = 64 * 1024,
                        EnableRaisingEvents = true
                    };
                    w.Created += OnScreenshotFsEvent;
                    w.Changed += OnScreenshotFsEvent;
                    w.Renamed += OnScreenshotFsRenamed;
                    ScreenshotWatchers.Add(w);
                    started++;
                }
                catch { /* dossier inaccessible */ }
            }
            if (started > 0)
            {
                _screenshotWatchersStarted = true;
                InvokeCallback("PhotoWatcher", "OK|watching|" + started.ToString(System.Globalization.CultureInfo.InvariantCulture));
            }
        }
    }

    private static void OnScreenshotFsRenamed(object sender, RenamedEventArgs e)
    {
        try { OnScreenshotPathCandidate(e.FullPath); } catch { /* ignore */ }
    }

    private static void OnScreenshotFsEvent(object sender, FileSystemEventArgs e)
    {
        try { OnScreenshotPathCandidate(e.FullPath); } catch { /* ignore */ }
    }

    private static void OnScreenshotPathCandidate(string? fullPath)
    {
        if (string.IsNullOrWhiteSpace(fullPath)) return;
        if (!IsImageExtension(Path.GetExtension(fullPath))) return;
        if (string.IsNullOrEmpty(_baseUrl) || _apiKey.Length == 0) return;

        // Debounce Created+Changed pendant l'écriture.
        if (!WatcherDebounce.TryAdd(fullPath, 0)) return;

        _ = Task.Run(async () =>
        {
            try
            {
                await Task.Delay(500).ConfigureAwait(false);
                if (!await WaitFileStableAsync(fullPath, TimeSpan.FromSeconds(4)).ConfigureAwait(false))
                    return;

                FileInfo fi;
                try { fi = new FileInfo(fullPath); }
                catch { return; }
                if (!fi.Exists || fi.Length < 32) return;

                var age = DateTime.UtcNow - fi.LastWriteTimeUtc;
                if (age > TimeSpan.FromSeconds(WatcherMaxAgeSeconds)) return;
                // Fichiers déjà présents avant le démarrage du watcher : ignorer sauf très récents.
                var startedUtc = new DateTime(_screenshotWatchersStartedTicks, DateTimeKind.Utc);
                if (fi.CreationTimeUtc < startedUtc.AddSeconds(-WatcherMinAgeSeconds)
                    && fi.LastWriteTimeUtc < startedUtc.AddSeconds(-WatcherMinAgeSeconds))
                    return;

                var author = _lastPhotoAuthor.Length > 0
                    ? _lastPhotoAuthor
                    : (_callSign.Length > 0 ? _callSign : "Unknown");
                var caption = "Photo ATAK (sidecar) — " + (Path.GetFileName(fullPath) ?? "capture");
                var args = new string?[]
                {
                    fullPath,
                    author,
                    _lastPhotoPosX,
                    _lastPhotoPosY,
                    _lastPhotoPosZ,
                    _lastPhotoGrid,
                    _lastPhotoHeading,
                    _lastPhotoPosZ,
                    caption,
                    "",
                    "WEST",
                    "",
                    "CTAB",
                    DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString(System.Globalization.CultureInfo.InvariantCulture),
                    "",
                    "",
                    ""
                };
                EnqueueReconImage(args);
            }
            finally
            {
                WatcherDebounce.TryRemove(fullPath, out _);
            }
        });
    }

    private static async Task<bool> WaitFileStableAsync(string path, TimeSpan timeout)
    {
        var deadline = DateTime.UtcNow + timeout;
        long lastSize = -1;
        var stableHits = 0;
        while (DateTime.UtcNow < deadline)
        {
            try
            {
                var fi = new FileInfo(path);
                if (!fi.Exists) { await Task.Delay(150).ConfigureAwait(false); continue; }
                if (fi.Length < 32) { await Task.Delay(150).ConfigureAwait(false); continue; }
                if (fi.Length == lastSize) { stableHits++; if (stableHits >= 2) return true; }
                else { lastSize = fi.Length; stableHits = 0; }
            }
            catch { /* locked */ }
            await Task.Delay(200).ConfigureAwait(false);
        }
        try { return File.Exists(path) && new FileInfo(path).Length >= 32; }
        catch { return false; }
    }

    /// <summary>
    /// Photo visage SSE : file async (même worker que NotifyNewPhoto).
    /// args[0]=personId, args[1]=path, args[2]=author, args[3]=angle, args[4..6]=pos.
    /// </summary>
    private static string BeginUploadSsePhoto(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl) || _apiKey.Length == 0)
            return "ERR|not_connected";
        var personId = args.Length > 0 ? (args[0] ?? "").Trim() : "";
        if (string.IsNullOrEmpty(personId)) return "ERR|person_id_empty";

        EnsureScreenshotQuota();

        var rawPath = args.Length > 1 ? (args[1] ?? "") : "";
        var author = args.Length > 2 && !string.IsNullOrWhiteSpace(args[2])
            ? args[2]!.Trim()
            : (_callSign.Length > 0 ? _callSign : "Unknown");
        var angle = args.Length > 3 && !string.IsNullOrEmpty(args[3]) ? args[3]! : "face";
        var trimmedPath = rawPath.Trim().Trim('"').Trim('\'');

        var dedupKey = NormalizePhotoDedupKey("sse|" + personId + "|" + (trimmedPath.Length > 0 ? trimmedPath : "newest"));
        if (!TryClaimPhotoDedup(dedupKey))
            return "OK|duplicate";
        if (PhotoJobs.Count >= PhotoQueueMax)
            return "ERR|queue_full";

        var meta = new string?[Math.Max(args.Length, 9)];
        for (var i = 0; i < args.Length; i++)
            meta[i] = args[i];
        meta[0] = personId;
        meta[1] = trimmedPath;
        meta[2] = author;
        meta[3] = angle;

        PhotoJobs.Enqueue(new ReconPhotoJob
        {
            RawPath = trimmedPath,
            Author = author,
            Meta = meta,
            DedupKey = dedupKey,
            NewestFallback = true,
            EnqueuedUtc = DateTime.UtcNow,
            UploadKind = "sse_face",
            SsePersonId = personId,
            SseAngle = angle
        });
        EnsureScreenshotWatchers();
        EnsurePhotoWorker();
        return "OK|queued";
    }

    private static async Task ProcessSseFacePhotoUploadAsync(ReconPhotoJob job, string resolved, string? identityKey)
    {
        var personId = !string.IsNullOrWhiteSpace(job.SsePersonId)
            ? job.SsePersonId.Trim()
            : (job.Meta.Length > 0 ? (job.Meta[0] ?? "").Trim() : "");
        if (string.IsNullOrEmpty(personId))
        {
            ReleasePhotoDedup(job.DedupKey);
            ReleasePhotoDedup(identityKey);
            InvokeCallback("SsePhotoUpload", "ERR|person_id_empty|" + Path.GetFileName(resolved));
            return;
        }

        MultipartFormDataContent? multipart = null;
        HttpRequestMessage? req = null;
        try
        {
            var args = job.Meta ?? Array.Empty<string?>();
            var author = !string.IsNullOrWhiteSpace(job.Author) ? job.Author : "Unknown";
            var angle = !string.IsNullOrWhiteSpace(job.SseAngle) ? job.SseAngle : "face";
            multipart = new MultipartFormDataContent();
            multipart.Add(new StringContent(author), "author");
            multipart.Add(new StringContent(angle), "angle");
            AddOptionalForm(multipart, "pos_x", args, 4);
            AddOptionalForm(multipart, "pos_y", args, 5);
            AddOptionalForm(multipart, "pos_z", args, 6);
            AddOptionalForm(multipart, "caption", args, 7);
            AddOptionalForm(multipart, "grid_reference", args, 8);
            if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
            if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
            var fileName = Path.GetFileName(resolved) ?? "sse_face.png";
            var fileStream = new FileStream(resolved, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 128 * 1024, FileOptions.Asynchronous | FileOptions.SequentialScan);
            var fileContent = new StreamContent(fileStream);
            fileContent.Headers.ContentType = new MediaTypeHeaderValue(GuessImageMediaType(resolved));
            multipart.Add(fileContent, "image", fileName);

            var apiPath = "/api/sse/persons/" + Uri.EscapeDataString(personId) + "/photos";
            if (!TryBuildRequestUri(_baseUrl, apiPath, out var uri, out var err) || uri is null)
            {
                ReleasePhotoDedup(job.DedupKey);
                ReleasePhotoDedup(identityKey);
                try { multipart.Dispose(); } catch { /* ignore */ }
                InvokeCallback("SsePhotoUpload", "ERR|" + err + "|" + fileName);
                return;
            }

            req = new HttpRequestMessage(HttpMethod.Post, uri) { Content = multipart };
            multipart = null;
            AttachApiKeyHeader(req);
            using var resp = await UploadHttpClient.SendAsync(req).ConfigureAwait(false);
            var code = (int)resp.StatusCode;
            if (resp.IsSuccessStatusCode)
            {
                NoteRateLimitCleared();
                InvokeCallback("SsePhotoUpload", "OK|uploaded|" + fileName);
                return;
            }

            ReleasePhotoDedup(job.DedupKey);
            ReleasePhotoDedup(identityKey);
            NotePostError(code, uri.AbsoluteUri);
            if (code == 401 && _sessionToken.Length > 0)
                _sessionToken = "";
            InvokeCallback("SsePhotoUpload", $"ERR|http_{code}|{fileName}");
        }
        catch
        {
            ReleasePhotoDedup(job.DedupKey);
            ReleasePhotoDedup(identityKey);
            InvokeCallback("SsePhotoUpload", "ERR|network|" + Path.GetFileName(resolved));
        }
        finally
        {
            try { req?.Dispose(); } catch { /* ignore */ }
            try { multipart?.Dispose(); } catch { /* ignore */ }
        }
    }

    /// <summary>
    /// Joint une capture ou un fichier local à une fiche de renseignement.
    /// Args : [noteId, cheminOuMotif, auteur, nature, posX, posY, posZ, légende, repère]
    /// </summary>
    private static string BeginUploadSseNoteAttachment(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
        var noteId = args.Length > 0 ? (args[0] ?? "").Trim() : "";
        if (string.IsNullOrEmpty(noteId)) return "ERR|note_id_empty";
        var rawPath = args.Length > 1 ? (args[1] ?? "") : "";
        var author = args.Length > 2 && !string.IsNullOrEmpty(args[2]) ? args[2]! : "Terrain";
        var kind = args.Length > 3 && !string.IsNullOrEmpty(args[3]) ? args[3]! : "capture";

        string? resolved = null;
        if (!string.IsNullOrWhiteSpace(rawPath))
            resolved = ResolveLocalImagePath(rawPath, TimeSpan.FromSeconds(120));
        if (resolved == null)
            resolved = FindNewestScreenshot(TimeSpan.FromSeconds(90));
        if (resolved == null) return "ERR|file_not_found";

        try
        {
            var fi = new FileInfo(resolved);
            if (!fi.Exists) return "ERR|file_not_found";
            if (fi.Length < 32) return "ERR|file_empty";
            var multipart = new MultipartFormDataContent();
            multipart.Add(new StringContent(author), "author");
            multipart.Add(new StringContent(kind), "kind");
            AddOptionalForm(multipart, "pos_x", args, 4);
            AddOptionalForm(multipart, "pos_y", args, 5);
            AddOptionalForm(multipart, "pos_z", args, 6);
            AddOptionalForm(multipart, "caption", args, 7);
            AddOptionalForm(multipart, "grid_reference", args, 8);
            if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
            if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
            var fileName = Path.GetFileName(resolved) ?? "fiche_piece.png";
            var fileStream = new FileStream(resolved, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 128 * 1024, FileOptions.Asynchronous | FileOptions.SequentialScan);
            var fileContent = new StreamContent(fileStream);
            fileContent.Headers.ContentType = new MediaTypeHeaderValue(GuessImageMediaType(resolved));
            multipart.Add(fileContent, "piece", fileName);
            var path = "/api/sse/notes/" + Uri.EscapeDataString(noteId) + "/pieces";
            return QueueMultipartUpload(path, multipart);
        }
        catch
        {
            return "ERR|read_failed";
        }
    }

    private static string BeginUploadLatestScreenshot(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
        var author = args.Length > 0 ? (args[0] ?? "Unknown") : "Unknown";
        var device = args.Length > 1 ? (args[1] ?? "HELMET") : "HELMET";
        var caption = args.Length > 2 ? (args[2] ?? "") : "";
        var feedId = args.Length > 3 ? (args[3] ?? "") : "";
        var posX = args.Length > 4 ? (args[4] ?? "") : "";
        var posY = args.Length > 5 ? (args[5] ?? "") : "";
        var posZ = args.Length > 6 ? (args[6] ?? "") : "";
        var grid = args.Length > 7 ? (args[7] ?? "") : "";
        var heading = args.Length > 8 ? (args[8] ?? "") : "";
        var unitName = args.Length > 9 ? (args[9] ?? "") : "";
        var side = args.Length > 10 ? (args[10] ?? "WEST") : "WEST";
        var missionId = args.Length > 11 ? (args[11] ?? "") : "";
        var maxAgeSec = 45;
        if (args.Length > 12 && int.TryParse(args[12], out var parsedAge) && parsedAge > 0)
            maxAgeSec = Math.Min(parsedAge, 180);
        var fxProfile = args.Length > 13 ? (args[13] ?? "") : "";
        var fxIntensity = args.Length > 14 ? (args[14] ?? "") : "";
        var shot = FindNewestScreenshot(TimeSpan.FromSeconds(maxAgeSec));
        if (shot == null) return "ERR|file_not_found";
        try
        {
            var fi = new FileInfo(shot);
            if (!fi.Exists) return "ERR|file_not_found";
            if (fi.Length < 32) return "ERR|file_empty";
            var multipart = new MultipartFormDataContent();
            multipart.Add(new StringContent("1"), "mapId");
            multipart.Add(new StringContent(author), "author");
            multipart.Add(new StringContent(device), "device_type");
            if (!string.IsNullOrEmpty(caption)) multipart.Add(new StringContent(caption), "caption");
            if (!string.IsNullOrEmpty(feedId)) multipart.Add(new StringContent(feedId), "feed_id");
            if (!string.IsNullOrEmpty(posX)) multipart.Add(new StringContent(posX), "pos_x");
            if (!string.IsNullOrEmpty(posY)) multipart.Add(new StringContent(posY), "pos_y");
            if (!string.IsNullOrEmpty(posZ)) multipart.Add(new StringContent(posZ), "pos_z");
            if (!string.IsNullOrEmpty(grid)) multipart.Add(new StringContent(grid), "grid_ref");
            if (!string.IsNullOrEmpty(heading)) multipart.Add(new StringContent(heading), "heading");
            var unit = !string.IsNullOrEmpty(unitName) ? unitName : feedId;
            if (!string.IsNullOrEmpty(unit)) multipart.Add(new StringContent(unit), "unit_name");
            multipart.Add(new StringContent(side), "side");
            if (!string.IsNullOrEmpty(missionId)) multipart.Add(new StringContent(missionId), "mission_id");
            multipart.Add(new StringContent(DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString()), "capturedAt");
            if (!string.IsNullOrEmpty(fxProfile)) multipart.Add(new StringContent(fxProfile), "fx_profile");
            if (!string.IsNullOrEmpty(fxIntensity)) multipart.Add(new StringContent(fxIntensity), "fx_intensity");
            if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
            if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
            var fileStream = new FileStream(shot, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 128 * 1024, FileOptions.Asynchronous | FileOptions.SequentialScan);
            var fileContent = new StreamContent(fileStream);
            fileContent.Headers.ContentType = new MediaTypeHeaderValue(GuessImageMediaType(shot));
            multipart.Add(fileContent, "image", Path.GetFileName(shot) ?? "feed.png");
            return QueueMultipartUpload("/api/recon/images", multipart);
        }
        catch
        {
            return "ERR|read_failed";
        }
    }

    private static string BeginUploadIntelPhoto(string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl)) return "ERR|not_connected";
        var rawPath = args.Length > 0 ? (args[0] ?? "") : "";
        var author = args.Length > 1 ? (args[1] ?? "Unknown") : "Unknown";
        var resolved = ResolveLocalImagePath(rawPath, TimeSpan.FromSeconds(120));
        if (resolved == null) return "ERR|file_not_found";
        try
        {
            var fi = new FileInfo(resolved);
            if (!fi.Exists) return "ERR|file_not_found";
            if (fi.Length < 32) return "ERR|file_empty";
            var multipart = new MultipartFormDataContent();
            multipart.Add(new StringContent("1"), "mapId");
            multipart.Add(new StringContent(author), "author");
            if (_steamUid.Length > 0) multipart.Add(new StringContent(_steamUid), "steam_uid");
            if (_sessionToken.Length > 0) multipart.Add(new StringContent(_sessionToken), "session_token");
            var fileStream = new FileStream(resolved, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete, 128 * 1024, FileOptions.Asynchronous | FileOptions.SequentialScan);
            var fileContent = new StreamContent(fileStream);
            fileContent.Headers.ContentType = new MediaTypeHeaderValue(GuessImageMediaType(resolved));
            multipart.Add(fileContent, "photo", Path.GetFileName(resolved) ?? "photo.png");
            return QueueMultipartUpload("/api/intel/photos", multipart);
        }
        catch
        {
            return "ERR|read_failed";
        }
    }

    private static void AddOptionalForm(MultipartFormDataContent multipart, string name, string?[] args, int index)
    {
        if (args.Length <= index) return;
        var v = args[index] ?? "";
        if (string.IsNullOrEmpty(v)) return;
        multipart.Add(new StringContent(v), name);
    }

    private static string GuessImageMediaType(string path, byte[] bytes)
    {
        if (bytes.Length >= 8
            && bytes[0] == 0x89 && bytes[1] == (byte)'P' && bytes[2] == (byte)'N' && bytes[3] == (byte)'G')
            return "image/png";
        if (bytes.Length >= 3 && bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF)
            return "image/jpeg";
        var ext = Path.GetExtension(path);
        if (ext.Equals(".png", StringComparison.OrdinalIgnoreCase)) return "image/png";
        return "image/jpeg";
    }

    private static string GuessImageMediaType(string path)
    {
        var ext = Path.GetExtension(path ?? "");
        if (ext.Equals(".png", StringComparison.OrdinalIgnoreCase)) return "image/png";
        return "image/jpeg";
    }

    private static string SanitizeLooseJsonObject(string raw)
    {
        var trimmed = (raw ?? "").Trim();
        if (string.IsNullOrWhiteSpace(trimmed))
            return "{}";
        var sanitized = System.Text.RegularExpressions.Regex.Replace(
            trimmed,
            // Virgule décimale FR (1–3 chiffres) seulement — pas les paires [x,y] entières
            // du type [19345,17682] qui devenaient 19345.17682 et vidaient le marqueur.
            @"(?<=[:\[\s])(-?\d+),(\d{1,3})(?=[,\}\]\s])",
            "$1.$2");
        try
        {
            using var _ = JsonDocument.Parse(sanitized);
            return sanitized;
        }
        catch
        {
            return "{}";
        }
    }

    /// <summary>
    /// Construit l’URL (préfixe /public conservé) et envoie en async avec suivi d’erreur.
    /// </summary>
    private static string QueueMultipartUpload(string relativeApiPath, MultipartFormDataContent multipart)
    {
        if (!TryBuildRequestUri(_baseUrl, relativeApiPath, out var uri, out var err) || uri is null)
        {
            try { multipart.Dispose(); } catch { /* ignore */ }
            return "ERR|" + err;
        }
        var url = uri.AbsoluteUri;
        try
        {
            var req = new HttpRequestMessage(HttpMethod.Post, url) { Content = multipart };
            AttachApiKeyHeader(req);
            _ = UploadHttpClient.SendAsync(req).ContinueWith(t =>
            {
                try
                {
                    HttpResponseMessage? resp = null;
                    if (t.Status == TaskStatus.RanToCompletion)
                        resp = t.Result;
                    if (resp == null)
                    {
                        NotePostError(0, url);
                        return;
                    }
                    var code = (int)resp.StatusCode;
                    if (resp.IsSuccessStatusCode)
                    {
                        NoteRateLimitCleared();
                        return;
                    }
                    // Session périmée attachée en en-tête : oublier et retenter une fois sans jeton.
                    if (code == 401 && _sessionToken.Length > 0)
                    {
                        _sessionToken = "";
                        try
                        {
                            // Le contenu a déjà été consommé — pas de retry multipart ici.
                            // Le soft-fail serveur (invalid_session_ignored) couvre le cas courant.
                        }
                        catch { /* ignore */ }
                    }
                    NotePostError(code, url);
                }
                catch
                {
                    NotePostError(-1, url);
                }
                finally
                {
                    try { req.Dispose(); } catch { /* ignore */ }
                }
            });
            return "OK|queued";
        }
        catch
        {
            try { multipart.Dispose(); } catch { /* ignore */ }
            NotePostError(-1, url);
            return "ERR|network";
        }
    }

    private static readonly string[] ImageExtensions = [".png", ".jpg", ".jpeg"];

    private static bool IsImageExtension(string? ext) =>
        !string.IsNullOrEmpty(ext)
        && ImageExtensions.Any(e => e.Equals(ext, StringComparison.OrdinalIgnoreCase));

    /// <summary>
    /// Résout un chemin BCE / Photo Library (absolu, relatif, ou nom seul) vers un fichier disque.
    /// Gère : guillemets, chemins Arma « \Documents\… », jpg↔png, sous-dossiers Screenshots.
    /// Court délai d’attente uniquement si le fichier n’existe pas encore (écriture BCE).
    /// </summary>
    private static string? ResolveLocalImagePath(string? raw, TimeSpan? newestFallback = null)
    {
        var path = (raw ?? "").Trim().Trim('"').Trim('\'');
        if (path.Length == 0)
            return newestFallback.HasValue ? FindNewestScreenshot(newestFallback.Value) : null;

        path = path.Replace('/', '\\');

        // Chemin absolu Windows dont le dossier parent n'existe pas → échec immédiat
        // (Photo Library obsolète). Évite 8× Sleep + scan Screenshots qui gèle le jeu.
        try
        {
            if (Path.IsPathRooted(path)
                && !(path.StartsWith('\\') && !path.StartsWith("\\\\", StringComparison.Ordinal)))
            {
                var parent = Path.GetDirectoryName(path);
                if (!string.IsNullOrWhiteSpace(parent) && !Directory.Exists(parent))
                {
                    // Dernier espoir : même nom de fichier ailleurs (mod Workshop renommé).
                    var orphanName = Path.GetFileName(path);
                    if (!string.IsNullOrWhiteSpace(orphanName))
                        return FindScreenshotByFileName(orphanName);
                    return null;
                }
            }
        }
        catch { /* ignore */ }

        foreach (var candidate in ExpandLocalImageCandidates(path))
        {
            var found = TryReadableImageFile(candidate);
            if (found != null)
                return found;
        }

        var fileName = Path.GetFileName(path);
        string? byNameOnce = null;
        if (!string.IsNullOrWhiteSpace(fileName))
        {
            foreach (var variant in ScreenshotFileNameVariants(fileName))
            {
                byNameOnce = FindScreenshotByFileName(variant);
                if (byNameOnce != null)
                    return byNameOnce;
            }
        }

        if (newestFallback.HasValue)
        {
            var immediate = FindNewestScreenshot(newestFallback.Value);
            if (immediate != null)
                return immediate;
        }

        // Attente courte uniquement si le dossier source existe (écriture BCE en cours).
        // Pas de re-scan global : FindScreenshotByFileName a déjà été tenté une fois.
        var parentExists = false;
        try
        {
            var parent = Path.GetDirectoryName(path);
            parentExists = !string.IsNullOrWhiteSpace(parent) && Directory.Exists(parent);
        }
        catch { /* ignore */ }

        if (!parentExists)
            return null;

        const int maxAttempts = 6;
        for (var attempt = 0; attempt < maxAttempts; attempt++)
        {
            foreach (var candidate in ExpandLocalImageCandidates(path))
            {
                var found = TryReadableImageFile(candidate);
                if (found != null)
                    return found;
            }

            if (newestFallback.HasValue && attempt >= 3)
            {
                var newest = FindNewestScreenshot(newestFallback.Value);
                if (newest != null)
                    return newest;
            }

            if (attempt < maxAttempts - 1)
                System.Threading.Thread.Sleep(50);
        }

        return null;
    }

    /// <summary>
    /// Diagnostic compact d’un échec de résolution : dossier d’origine connu ou non,
    /// nombre de dossiers de captures balayés. Évite les « file_not_found » muets.
    /// </summary>
    private static string DescribeImageLookupFailure(string? rawPath)
    {
        try
        {
            // Lister les dossiers réellement balayés : « dirs=3 » ne permet pas de savoir
            // lesquels, donc pas de savoir où Arma a réellement écrit la capture.
            var dirs = new List<string>();
            foreach (var d in EnumerateScreenshotDirs())
            {
                try
                {
                    var name = d.Length > 48 ? "…" + d.Substring(d.Length - 47) : d;
                    dirs.Add(name.Replace('|', '/'));
                }
                catch { /* ignore */ }
            }
            var dirCount = dirs.Count;

            var srcDir = "none";
            var path = (rawPath ?? "").Trim().Trim('"').Trim('\'').Replace('/', '\\');
            if (path.Length > 0)
            {
                var parent = Path.GetDirectoryName(path);
                if (!string.IsNullOrWhiteSpace(parent))
                    srcDir = Directory.Exists(parent) ? "srcdir_ok" : "srcdir_missing";
                else
                    srcDir = "name_only";
            }

            // Indique si le dossier profil a encore des captures récentes (sinon screenshot Arma HS).
            var newestHint = "no_recent";
            try
            {
                FileInfo? newest = null;
                foreach (var d in EnumerateScreenshotDirs())
                {
                    if (!IsScreenshotCaptureDir(d)) continue;
                    foreach (var f in EnumerateRecentImagesInDir(d))
                    {
                        try
                        {
                            var fi = new FileInfo(f);
                            if (!fi.Exists || fi.Length < 32) continue;
                            if (newest == null || fi.LastWriteTimeUtc > newest.LastWriteTimeUtc)
                                newest = fi;
                        }
                        catch { /* ignore */ }
                    }
                }
                if (newest != null)
                {
                    var ageH = (int)Math.Max(0, (DateTime.UtcNow - newest.LastWriteTimeUtc).TotalHours);
                    newestHint = ageH < 1
                        ? "newest_<1h"
                        : (ageH < 48 ? $"newest_{ageH}h" : $"newest_{ageH / 24}d");
                }
            }
            catch { /* ignore */ }

            return $"{srcDir}|dirs={dirCount}|{newestHint}|{string.Join(" ; ", dirs)}";
        }
        catch
        {
            return "diag_failed";
        }
    }

    private static string? TryReadableImageFile(string candidate)
    {
        try
        {
            if (!File.Exists(candidate))
                return null;
            var fi = new FileInfo(candidate);
            if (fi.Length < 32)
                return null;
            return Path.GetFullPath(candidate);
        }
        catch
        {
            return null;
        }
    }

    /// <summary>
    /// Variantes du chemin reçu (absolu Windows, préfixe Arma « \Documents\… », extension alternative).
    /// </summary>
    private static IEnumerable<string> ExpandLocalImageCandidates(string path)
    {
        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        var list = new List<string>();
        void Add(string? p)
        {
            if (string.IsNullOrWhiteSpace(p)) return;
            if (!seen.Add(p)) return;
            list.Add(p);
            foreach (var alt in WithAlternateImageExtensions(p))
            {
                if (seen.Add(alt))
                    list.Add(alt);
            }
        }

        Add(path);

        // Arma : chemin style "\Documents\Arma 3\Screenshots\foo.png" (racine lecteur ≠ UserProfile).
        if (path.StartsWith('\\') && !path.StartsWith("\\\\", StringComparison.Ordinal))
        {
            var trimmed = path.TrimStart('\\');
            var userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            if (!string.IsNullOrWhiteSpace(userProfile))
                Add(Path.Combine(userProfile, trimmed));

            var docs = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
            if (!string.IsNullOrWhiteSpace(docs)
                && trimmed.StartsWith("Documents\\", StringComparison.OrdinalIgnoreCase))
            {
                Add(Path.Combine(docs, trimmed.Substring("Documents\\".Length)));
            }

            try
            {
                var root = Path.GetPathRoot(userProfile);
                if (!string.IsNullOrWhiteSpace(root))
                    Add(Path.Combine(root, trimmed));
            }
            catch { /* ignore */ }
        }

        // Relatif "Screenshots\foo.png" ou "ATAK\foo.png"
        if (!Path.IsPathRooted(path)
            || (path.StartsWith('\\') && !path.StartsWith("\\\\", StringComparison.Ordinal)))
        {
            var rel = path.TrimStart('\\');
            foreach (var dir in EnumerateScreenshotDirs())
            {
                Add(Path.Combine(dir, Path.GetFileName(path)));
                Add(Path.Combine(dir, rel));
                try
                {
                    var profileRoot = Directory.GetParent(dir)?.FullName;
                    if (!string.IsNullOrWhiteSpace(profileRoot))
                        Add(Path.Combine(profileRoot, rel));
                }
                catch { /* ignore */ }
            }
        }

        return list;
    }

    private static IEnumerable<string> WithAlternateImageExtensions(string filePath)
    {
        string? dir;
        string name;
        try
        {
            dir = Path.GetDirectoryName(filePath);
            name = Path.GetFileName(filePath);
        }
        catch
        {
            yield break;
        }
        if (string.IsNullOrWhiteSpace(name)) yield break;
        var stem = Path.GetFileNameWithoutExtension(name);
        if (string.IsNullOrWhiteSpace(stem)) yield break;
        foreach (var ext in ImageExtensions)
        {
            var altName = stem + ext;
            if (altName.Equals(name, StringComparison.OrdinalIgnoreCase)) continue;
            if (string.IsNullOrWhiteSpace(dir))
                yield return altName;
            else
                yield return Path.Combine(dir, altName);
        }
    }

    /// <summary>
    /// True si le dossier est un vrai dossier de captures (Screenshots / Screenshot / Captures COMSPEC).
    /// Évite de traiter la racine Arma 3 comme source de photos.
    /// </summary>
    private static bool IsScreenshotCaptureDir(string dir)
    {
        try
        {
            var name = Path.GetFileName(dir.TrimEnd(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar));
            if (string.IsNullOrWhiteSpace(name)) return false;
            if (name.Equals("Screenshots", StringComparison.OrdinalIgnoreCase)) return true;
            if (name.Equals("Screenshot", StringComparison.OrdinalIgnoreCase)) return true;
            if (name.Equals("Captures", StringComparison.OrdinalIgnoreCase))
            {
                var parent = Directory.GetParent(dir)?.Name;
                return parent != null
                    && parent.Equals("Arma 3 - COMSPEC", StringComparison.OrdinalIgnoreCase);
            }
            return false;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>
    /// Cherche un fichier image par nom (et stem .jpg↔.png) dans les dossiers Screenshots.
    /// Scan peu profond uniquement (racine + 1 niveau) — jamais AllDirectories sur l’install Arma
    /// (jonctions Workshop → STATUS_STACK_OVERFLOW / 0xC00000FD).
    /// </summary>
    private static string? FindScreenshotByFileName(string fileName)
    {
        var names = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var variant in ScreenshotFileNameVariants(fileName))
            names.Add(variant);

        var stem = Path.GetFileNameWithoutExtension(fileName);
        foreach (var dir in EnumerateScreenshotDirs())
        {
            if (!IsScreenshotCaptureDir(dir)) continue;

            foreach (var name in names)
            {
                try
                {
                    var direct = Path.Combine(dir, name);
                    if (File.Exists(direct))
                        return Path.GetFullPath(direct);
                }
                catch { /* ignore */ }
            }

            if (string.IsNullOrWhiteSpace(stem)) continue;
            try
            {
                foreach (var f in EnumerateFilesShallow(dir, stem + ".*", maxDepth: 1))
                {
                    try
                    {
                        if (!IsImageExtension(Path.GetExtension(f))) continue;
                        if (!File.Exists(f)) continue;
                        return Path.GetFullPath(f);
                    }
                    catch { /* ignore */ }
                }
            }
            catch { /* ignore */ }
        }
        return null;
    }

    /// <summary>
    /// Énumère des fichiers jusqu’à maxDepth sous-dossiers (0 = racine seule).
    /// Remplace SearchOption.AllDirectories pour éviter les boucles de jonctions.
    /// </summary>
    private static IEnumerable<string> EnumerateFilesShallow(string root, string pattern, int maxDepth)
    {
        if (string.IsNullOrWhiteSpace(root) || maxDepth < 0) yield break;
        IEnumerable<string> top;
        try { top = Directory.EnumerateFiles(root, pattern, SearchOption.TopDirectoryOnly); }
        catch { yield break; }
        foreach (var f in top)
            yield return f;

        if (maxDepth < 1) yield break;
        IEnumerable<string> subs;
        try { subs = Directory.EnumerateDirectories(root); }
        catch { yield break; }
        foreach (var sub in subs)
        {
            IEnumerable<string> nested;
            try { nested = Directory.EnumerateFiles(sub, pattern, SearchOption.TopDirectoryOnly); }
            catch { continue; }
            foreach (var f in nested)
                yield return f;
        }
    }

    /// <summary>
    /// Dossier de captures COMSPEC — emplacement stable et prévisible, indépendant de
    /// l’endroit où Arma ou BCE écrivent réellement. Toute capture résolue y est recopiée,
    /// ce qui donne un point de reprise fiable quand un mod annonce un chemin inexistant.
    /// </summary>
    private static string? ComspecCaptureDir()
    {
        if (_comspecCaptureDir != null)
            return _comspecCaptureDir.Length == 0 ? null : _comspecCaptureDir;
        try
        {
            var docs = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
            if (string.IsNullOrWhiteSpace(docs))
            {
                _comspecCaptureDir = "";
                return null;
            }
            var dir = Path.Combine(docs, "Arma 3 - COMSPEC", "Captures");
            Directory.CreateDirectory(dir);
            _comspecCaptureDir = dir;
            return dir;
        }
        catch
        {
            _comspecCaptureDir = "";
            return null;
        }
    }

    private static string? _comspecCaptureDir;

    /// <summary>
    /// Recopie best-effort d’une capture résolue dans le dossier COMSPEC (jamais bloquant).
    /// Conserve les 200 fichiers les plus récents.
    /// </summary>
    private static void MirrorCapture(string resolved)
    {
        try
        {
            var dir = ComspecCaptureDir();
            if (dir == null) return;
            var name = Path.GetFileName(resolved);
            if (string.IsNullOrWhiteSpace(name)) return;

            var srcDir = Path.GetDirectoryName(resolved);
            if (!string.IsNullOrWhiteSpace(srcDir)
                && string.Equals(Path.GetFullPath(srcDir).TrimEnd('\\'), dir.TrimEnd('\\'),
                    StringComparison.OrdinalIgnoreCase))
                return;

            var dest = Path.Combine(dir, name);
            if (!File.Exists(dest))
                File.Copy(resolved, dest, false);

            var files = new DirectoryInfo(dir).GetFiles();
            if (files.Length <= 200) return;
            Array.Sort(files, (a, b) => b.LastWriteTimeUtc.CompareTo(a.LastWriteTimeUtc));
            for (var i = 200; i < files.Length; i++)
            {
                try { files[i].Delete(); } catch { /* ignore */ }
            }
        }
        catch
        {
            // Le miroir ne doit jamais faire échouer un envoi.
        }
    }

    /// <summary>
    /// SQF <c>format ["%1", 1114100]</c> produit <c>1.1141e+06</c> — le PNG disque
    /// n’a jamais ce nom. On reconstitue l’entier pour la recherche.
    /// </summary>
    private static readonly Regex ArmaScientificNumber = new(
        @"\d+\.\d+[eE][+\-]?\d+",
        RegexOptions.CultureInvariant | RegexOptions.Compiled);

    private static IEnumerable<string> ScreenshotFileNameVariants(string fileName)
    {
        var names = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        void Add(string? n)
        {
            if (string.IsNullOrWhiteSpace(n)) return;
            var leaf = Path.GetFileName(n);
            if (string.IsNullOrWhiteSpace(leaf) || !names.Add(leaf)) return;
            foreach (var alt in WithAlternateImageExtensions(leaf))
                names.Add(Path.GetFileName(alt));
        }
        Add(fileName);
        Add(NormalizeArmaScientificFileName(fileName));
        return names;
    }

    private static string NormalizeArmaScientificFileName(string fileName)
    {
        try
        {
            var name = Path.GetFileName(fileName);
            if (string.IsNullOrWhiteSpace(name)) return fileName;
            var ext = Path.GetExtension(name);
            var stem = Path.GetFileNameWithoutExtension(name);
            if (string.IsNullOrWhiteSpace(stem) || !ArmaScientificNumber.IsMatch(stem))
                return fileName;
            var newStem = ArmaScientificNumber.Replace(stem, m =>
            {
                if (double.TryParse(m.Value, NumberStyles.Float, CultureInfo.InvariantCulture, out var d)
                    && !double.IsNaN(d) && !double.IsInfinity(d) && d >= 0 && d < 1e15)
                    return Math.Round(d, MidpointRounding.AwayFromZero)
                        .ToString("0", CultureInfo.InvariantCulture);
                return m.Value;
            });
            if (string.Equals(newStem, stem, StringComparison.Ordinal)) return fileName;
            var dir = Path.GetDirectoryName(fileName);
            var rebuilt = newStem + ext;
            return string.IsNullOrEmpty(dir) ? rebuilt : Path.Combine(dir, rebuilt);
        }
        catch
        {
            return fileName;
        }
    }

    /// <summary>
    /// Dossier Screenshots saturé (plafond Arma 250 Mo) → <c>screenshot</c> échoue
    /// sans écrire. On libère nos PNG COMSPEC et on relève le plafond du profil.
    /// </summary>
    private static void EnsureScreenshotQuota()
    {
        if (_screenshotQuotaEnsured) return;
        _screenshotQuotaEnsured = true;
        try
        {
            const long softLimit = 180L * 1024 * 1024;
            foreach (var dir in EnumerateScreenshotDirs())
            {
                if (!IsScreenshotCaptureDir(dir)) continue;
                FileInfo[] files;
                try { files = new DirectoryInfo(dir).GetFiles("*.*", SearchOption.TopDirectoryOnly); }
                catch { continue; }
                long total = 0;
                foreach (var f in files)
                {
                    try { total += f.Length; } catch { /* ignore */ }
                }
                if (total < softLimit) continue;
                var ours = files
                    .Where(f =>
                        f.Name.StartsWith("COMSPEC_", StringComparison.OrdinalIgnoreCase)
                        && IsImageExtension(f.Extension))
                    .OrderBy(f => f.LastWriteTimeUtc)
                    .ToArray();
                foreach (var f in ours)
                {
                    if (total < softLimit) break;
                    try
                    {
                        var len = f.Length;
                        f.Delete();
                        total -= len;
                    }
                    catch { /* ignore */ }
                }
            }
            PatchArma3ProfilesScreenshotLimit();
        }
        catch
        {
            // Best-effort : ne jamais faire échouer un envoi pour un ménage.
        }
    }

    private static void PatchArma3ProfilesScreenshotLimit()
    {
        var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var dir in EnumerateScreenshotDirs())
        {
            DirectoryInfo? parent;
            try { parent = Directory.GetParent(dir); }
            catch { continue; }
            if (parent == null || !parent.Exists) continue;
            IEnumerable<string> profiles;
            try { profiles = parent.EnumerateFiles("*.Arma3Profile", SearchOption.TopDirectoryOnly).Select(f => f.FullName); }
            catch { continue; }
            foreach (var prof in profiles)
            {
                if (!seen.Add(prof)) continue;
                try
                {
                    var text = File.ReadAllText(prof);
                    if (Regex.IsMatch(text, @"maxScreenShotFolderSizeMB\s*=\s*\d+", RegexOptions.IgnoreCase))
                    {
                        var patched = Regex.Replace(
                            text,
                            @"maxScreenShotFolderSizeMB\s*=\s*(\d+)",
                            m =>
                            {
                                if (int.TryParse(m.Groups[1].Value, out var cur) && cur >= 2000)
                                    return m.Value;
                                return "maxScreenShotFolderSizeMB=4000";
                            },
                            RegexOptions.IgnoreCase);
                        if (!string.Equals(patched, text, StringComparison.Ordinal))
                            File.WriteAllText(prof, patched);
                    }
                    else
                    {
                        File.AppendAllText(prof, "\r\nmaxScreenShotFolderSizeMB=4000;\r\n");
                    }
                }
                catch { /* profil verrouillé par Arma */ }
            }
        }
    }

    private static IEnumerable<string> EnumerateArmaLaunchScreenshotDirs()
    {
        string? profiles = null;
        string? name = null;
        try
        {
            var cmd = Environment.GetCommandLineArgs();
            for (var i = 0; i < cmd.Length; i++)
            {
                var a = cmd[i] ?? "";
                if (a.StartsWith("-profiles=", StringComparison.OrdinalIgnoreCase))
                    profiles = a["-profiles=".Length..].Trim().Trim('"');
                else if (a.Equals("-profiles", StringComparison.OrdinalIgnoreCase) && i + 1 < cmd.Length)
                    profiles = (cmd[++i] ?? "").Trim().Trim('"');
                else if (a.StartsWith("-name=", StringComparison.OrdinalIgnoreCase))
                    name = a["-name=".Length..].Trim().Trim('"');
                else if (a.Equals("-name", StringComparison.OrdinalIgnoreCase) && i + 1 < cmd.Length)
                    name = (cmd[++i] ?? "").Trim().Trim('"');
            }
        }
        catch
        {
            yield break;
        }
        if (string.IsNullOrWhiteSpace(profiles)) yield break;
        if (!string.IsNullOrWhiteSpace(name))
            yield return Path.Combine(profiles, name, "Screenshots");
        yield return Path.Combine(profiles, "Screenshots");
    }

    private static string? FindNewestMatchingPrefix(string prefix, TimeSpan maxAge)
    {
        try
        {
            FileInfo? best = null;
            var cutoff = DateTime.UtcNow - maxAge;
            foreach (var dir in EnumerateScreenshotDirs())
            {
                if (!IsScreenshotCaptureDir(dir)) continue;
                IEnumerable<string> files;
                try { files = EnumerateRecentImagesInDir(dir); }
                catch { continue; }
                foreach (var f in files)
                {
                    try
                    {
                        var leaf = Path.GetFileName(f);
                        if (string.IsNullOrWhiteSpace(leaf)
                            || !leaf.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
                            continue;
                        var fi = new FileInfo(f);
                        if (!fi.Exists || fi.Length < 32) continue;
                        if (fi.LastWriteTimeUtc < cutoff) continue;
                        if (best == null || fi.LastWriteTimeUtc > best.LastWriteTimeUtc)
                            best = fi;
                    }
                    catch { /* ignore */ }
                }
            }
            return best?.FullName;
        }
        catch
        {
            return null;
        }
    }

    private static IEnumerable<string> EnumerateScreenshotDirs()
    {
        var dirs = new List<string>();
        void AddIfExists(string p)
        {
            if (!string.IsNullOrWhiteSpace(p) && Directory.Exists(p)
                && !dirs.Contains(p, StringComparer.OrdinalIgnoreCase))
                dirs.Add(p);
        }
        void AddScreenshotsUnder(string root)
        {
            if (string.IsNullOrWhiteSpace(root) || !Directory.Exists(root)) return;
            AddIfExists(Path.Combine(root, "Screenshots"));
            AddIfExists(Path.Combine(root, "Screenshot"));
            try
            {
                foreach (var sub in Directory.EnumerateDirectories(root))
                {
                    AddIfExists(Path.Combine(sub, "Screenshots"));
                    AddIfExists(Path.Combine(sub, "Screenshot"));
                }
            }
            catch { /* ignore */ }
        }
        try
        {
            var docs = Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments);
            if (!string.IsNullOrWhiteSpace(docs))
            {
                AddIfExists(Path.Combine(docs, "Arma 3", "Screenshots"));
                AddIfExists(Path.Combine(docs, "Arma 3", "Screenshot"));
                AddScreenshotsUnder(Path.Combine(docs, "Arma 3 - Other Profiles"));
                // Profils déplacés / -profiles= parfois sous Documents\Arma 3\<name>
                AddScreenshotsUnder(Path.Combine(docs, "Arma 3"));
            }
        }
        catch { /* ignore */ }
        try
        {
            foreach (var launchDir in EnumerateArmaLaunchScreenshotDirs())
                AddIfExists(launchDir);
        }
        catch { /* ignore */ }
        try
        {
            var userProfile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
            if (!string.IsNullOrWhiteSpace(userProfile))
            {
                AddIfExists(Path.Combine(userProfile, "OneDrive", "Documents", "Arma 3", "Screenshots"));
                AddIfExists(Path.Combine(userProfile, "OneDrive", "Documents", "Arma 3", "Screenshot"));
                AddScreenshotsUnder(Path.Combine(userProfile, "OneDrive", "Documents", "Arma 3 - Other Profiles"));
                AddScreenshotsUnder(Path.Combine(userProfile, "OneDrive", "Documents", "Arma 3"));
            }
        }
        catch { /* ignore */ }
        try
        {
            var local = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            if (!string.IsNullOrWhiteSpace(local))
                AddScreenshotsUnder(Path.Combine(local, "Arma 3"));
        }
        catch { /* ignore */ }
        // Captures miroir COMSPEC (stable, hors Workshop).
        try
        {
            var cap = ComspecCaptureDir();
            if (!string.IsNullOrWhiteSpace(cap))
                AddIfExists(cap);
        }
        catch { /* ignore */ }

        // Dossier courant / install Arma : uniquement les feuilles Screenshots/Screenshot.
        // Ne JAMAIS ajouter la racine Arma elle-même (scan AllDirectories / watcher
        // dessus → jonctions Workshop → STATUS_STACK_OVERFLOW 0xC00000FD).
        try
        {
            var cwd = Directory.GetCurrentDirectory();
            if (!string.IsNullOrWhiteSpace(cwd))
            {
                AddIfExists(Path.Combine(cwd, "Screenshots"));
                AddIfExists(Path.Combine(cwd, "Screenshot"));
                // Profils / dossiers déportés sous la racine Arma (un niveau).
                AddScreenshotsUnder(cwd);

                // Mods Workshop : BCE / ATAK Enhanced écrivent dans le dossier du mod,
                // deux niveaux sous la racine Arma.
                //   <Arma 3>\!Workshop\@<mod>\Screenshot\<capture>.jpg
                foreach (var workshopRoot in new[] { "!Workshop", "!workshop" })
                {
                    var wr = Path.Combine(cwd, workshopRoot);
                    if (!Directory.Exists(wr)) continue;
                    AddScreenshotsUnder(wr);
                }
            }
        }
        catch { /* ignore */ }
        return dirs;
    }

    /// <summary>
    /// Cherche la capture d’écran Arma la plus récente (dossier Screenshots du profil).
    /// </summary>
    private static string? FindNewestScreenshot(TimeSpan maxAge)
    {
        try
        {
            FileInfo? best = null;
            var cutoff = DateTime.UtcNow - maxAge;
            foreach (var dir in EnumerateScreenshotDirs())
            {
                if (!IsScreenshotCaptureDir(dir)) continue;
                IEnumerable<string> files;
                try
                {
                    files = EnumerateRecentImagesInDir(dir);
                }
                catch { continue; }
                foreach (var f in files)
                {
                    try
                    {
                        var fi = new FileInfo(f);
                        if (!fi.Exists) continue;
                        var writeUtc = fi.LastWriteTimeUtc;
                        if (writeUtc < cutoff) continue;
                        if (best == null || writeUtc > best.LastWriteTimeUtc)
                            best = fi;
                    }
                    catch { }
                }
            }
            return best?.FullName;
        }
        catch
        {
            return null;
        }
    }

    /// <summary>
    /// Variante pour captures async : fichier écrit après (ou juste avant) l’enqueue.
    /// </summary>
    private static string? FindNewestScreenshotSince(DateTime utcMin)
    {
        try
        {
            FileInfo? best = null;
            foreach (var dir in EnumerateScreenshotDirs())
            {
                if (!IsScreenshotCaptureDir(dir)) continue;
                IEnumerable<string> files;
                try { files = EnumerateRecentImagesInDir(dir); }
                catch { continue; }
                foreach (var f in files)
                {
                    try
                    {
                        var fi = new FileInfo(f);
                        if (!fi.Exists || fi.Length < 32) continue;
                        var writeUtc = fi.LastWriteTimeUtc;
                        if (writeUtc < utcMin) continue;
                        if (best == null || writeUtc > best.LastWriteTimeUtc)
                            best = fi;
                    }
                    catch { }
                }
            }
            return best?.FullName;
        }
        catch
        {
            return null;
        }
    }

    /// <summary>Énumère jpg/png du dossier racine Screenshots (évite AllDirectories à chaque photo).</summary>
    private static IEnumerable<string> EnumerateRecentImagesInDir(string dir)
    {
        foreach (var pattern in new[] { "*.jpg", "*.jpeg", "*.png", "*.webp" })
        {
            IEnumerable<string> chunk;
            try
            {
                chunk = Directory.EnumerateFiles(dir, pattern, SearchOption.TopDirectoryOnly);
            }
            catch
            {
                continue;
            }
            foreach (var f in chunk)
                yield return f;
        }
    }

    private static string ListLocalScreenshotsTab(int limit)
    {
        try
        {
            var files = new List<FileInfo>();
            foreach (var dir in EnumerateScreenshotDirs())
            {
                if (!IsScreenshotCaptureDir(dir)) continue;
                IEnumerable<string> names;
                try { names = EnumerateRecentImagesInDir(dir); }
                catch { continue; }
                foreach (var f in names)
                {
                    try
                    {
                        var fi = new FileInfo(f);
                        if (fi.Exists && fi.Length >= 32)
                            files.Add(fi);
                    }
                    catch { }
                }
            }
            files.Sort((a, b) => b.LastWriteTimeUtc.CompareTo(a.LastWriteTimeUtc));
            var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            var sb = new StringBuilder();
            var n = 0;
            foreach (var fi in files)
            {
                if (!seen.Add(fi.Name)) continue;
                sb.Append(SanitizeIdentityField(fi.Name)).Append('\t')
                  .Append(SanitizeIdentityField(fi.FullName)).Append('\n');
                n++;
                if (n >= limit) break;
            }
            return sb.ToString();
        }
        catch
        {
            return "";
        }
    }

    /// <summary>
    /// Retour callExtension attendu par SQF Phase 1-2 : ["OK","msg"] ou ["ERROR","msg"].
    /// Échappement compatible parseSimpleArray (pas JSON : pas de \" ni \\).
    /// </summary>
    private static string FormatAtakExtArray(string status, string message)
    {
        var msg = (message ?? "").Replace('\n', ' ').Replace('\r', ' ').Replace('|', ' ');
        if (msg.Length > 480) msg = msg.Substring(0, 480);
        return $"[\"{EscapeForSimpleArray(status)}\",\"{EscapeForSimpleArray(msg)}\"]";
    }

    /// <summary>Échappement chaîne pour parseSimpleArray Arma (guillemet = double guillemet).</summary>
    private static string EscapeForSimpleArray(string s)
    {
        if (string.IsNullOrEmpty(s)) return s;
        return s.Replace("\"", "\"\"");
    }

    /// <summary>
    /// Complète le JSON SQF avec mapId, steam_uid et session_token si absents.
    /// Normalise d’abord les guillemets doublés Arma — sinon Parse échoue, le corps
    /// part cassé, PHP json_decode renvoie [] et SSE répond identity_required.
    /// </summary>
    private static string EnrichAtakPayload(string? jsonBody)
    {
        if (string.IsNullOrWhiteSpace(jsonBody)) return "{\"mapId\":1}";
        var trimmed = NormalizeArmaJson(jsonBody).Trim();
        if (string.IsNullOrWhiteSpace(trimmed) || !trimmed.StartsWith('{')) return "{\"mapId\":1}";
        try
        {
            using var doc = JsonDocument.Parse(trimmed);
            using var stream = new MemoryStream();
            using (var writer = new Utf8JsonWriter(stream))
            {
                writer.WriteStartObject();
                var hasMapId = false;
                foreach (var prop in doc.RootElement.EnumerateObject())
                {
                    if (prop.NameEquals("mapId") || prop.NameEquals("map_id")) hasMapId = true;
                    prop.WriteTo(writer);
                }
                if (!hasMapId) writer.WriteNumber("mapId", 1);
                if (_steamUid.Length > 0 && !doc.RootElement.TryGetProperty("steam_uid", out _))
                    writer.WriteString("steam_uid", _steamUid);
                if (_sessionToken.Length > 0 && !doc.RootElement.TryGetProperty("session_token", out _))
                    writer.WriteString("session_token", _sessionToken);
                writer.WriteEndObject();
            }
            return Encoding.UTF8.GetString(stream.ToArray());
        }
        catch
        {
            return trimmed;
        }
    }

    private static string PostAtakJsonSync(string relativePath, string jsonBody, CancellationToken token)
    {
        if (!TryBuildRequestUri(_baseUrl, relativePath, out var uri, out var err) || uri is null)
            return FormatAtakExtArray("ERROR", err);
        try
        {
            var payload = EnrichAtakPayload(jsonBody);
            var resp = SendJsonPost(uri.AbsoluteUri, payload, token);
            var body = ReadContentUtf8(resp, token);
            if (resp.IsSuccessStatusCode)
                return FormatAtakExtArray("OK", "Success");
            var code = (int)resp.StatusCode;
            var modBlock = MapModAccessBlockError(body);
            if (modBlock != null)
                return FormatAtakExtArray("ERROR", modBlock.Replace("ERR|", "", StringComparison.Ordinal));
            if (code == 401 || code == 403) return FormatAtakExtArray("ERROR", "unauthorized");
            if (code == 503 && body.Contains("migration", StringComparison.OrdinalIgnoreCase))
                return FormatAtakExtArray("ERROR", "migration_required");
            var errMsg = body.Length > 240 ? $"HTTP {code}" : body;
            return FormatAtakExtArray("ERROR", $"HTTP {code}: {errMsg}");
        }
        catch (OperationCanceledException)
        {
            return FormatAtakExtArray("TIMEOUT", "Request timeout");
        }
        catch (HttpRequestException ex)
        {
            return FormatAtakExtArray("NETWORK_ERROR", ex.Message);
        }
        catch (Exception ex)
        {
            return FormatAtakExtArray("ERROR", ex.Message);
        }
    }

    /// <summary>
    /// Crée une fiche SSE et renvoie l’id personne dans le détail OK (pour photo / biométrie).
    /// </summary>
    private static string PostSsePersonSync(string jsonBody, CancellationToken token)
    {
        if (!TryBuildRequestUri(_baseUrl, "/api/sse/persons", out var uri, out var err) || uri is null)
            return FormatAtakExtArray("ERROR", err);
        try
        {
            var payload = EnrichAtakPayload(jsonBody);
            var resp = SendJsonPost(uri.AbsoluteUri, payload, token);
            var body = ReadContentUtf8(resp, token);
            if (resp.IsSuccessStatusCode)
            {
                var id = "";
                try
                {
                    using var doc = JsonDocument.Parse(body);
                    if (doc.RootElement.TryGetProperty("id", out var idEl))
                        id = idEl.ValueKind == JsonValueKind.Number
                            ? idEl.GetInt32().ToString()
                            : (idEl.GetString() ?? "");
                }
                catch
                {
                    // ignore parse
                }
                return FormatAtakExtArray("OK", string.IsNullOrEmpty(id) ? "Success" : id);
            }
            var code = (int)resp.StatusCode;
            var modBlock = MapModAccessBlockError(body);
            if (modBlock != null)
                return FormatAtakExtArray("ERROR", modBlock.Replace("ERR|", "", StringComparison.Ordinal));
            if (code == 401 || code == 403) return FormatAtakExtArray("ERROR", "unauthorized");
            var errMsg = body.Length > 240 ? $"HTTP {code}" : body;
            return FormatAtakExtArray("ERROR", $"HTTP {code}: {errMsg}");
        }
        catch (OperationCanceledException)
        {
            return FormatAtakExtArray("TIMEOUT", "Request timeout");
        }
        catch (HttpRequestException ex)
        {
            return FormatAtakExtArray("NETWORK_ERROR", ex.Message);
        }
        catch (Exception ex)
        {
            return FormatAtakExtArray("ERROR", ex.Message);
        }
    }

    /// <summary>
    /// Transmet une fiche de renseignement simplifiée et renvoie son identifiant
    /// Athena, dont le rédacteur ATAK a besoin pour envoyer les pièces jointes.
    /// </summary>
    private static string PostSseFieldNoteSync(string jsonBody, CancellationToken token)
    {
        if (!TryBuildRequestUri(_baseUrl, "/api/sse/notes", out var uri, out var err) || uri is null)
            return FormatAtakExtArray("ERROR", err);
        try
        {
            var payload = EnrichAtakPayload(jsonBody);
            var resp = SendJsonPost(uri.AbsoluteUri, payload, token);
            var body = ReadContentUtf8(resp, token);
            if (resp.IsSuccessStatusCode)
            {
                var id = "";
                var reference = "";
                try
                {
                    using var doc = JsonDocument.Parse(body);
                    if (doc.RootElement.TryGetProperty("id", out var idEl))
                        id = idEl.ValueKind == JsonValueKind.Number
                            ? idEl.GetInt32().ToString()
                            : (idEl.GetString() ?? "");
                    if (doc.RootElement.TryGetProperty("reference_code", out var refEl))
                        reference = refEl.GetString() ?? "";
                    else if (doc.RootElement.TryGetProperty("note", out var noteEl)
                             && noteEl.ValueKind == JsonValueKind.Object
                             && noteEl.TryGetProperty("reference_code", out var nestedRef))
                        reference = nestedRef.GetString() ?? "";
                }
                catch
                {
                    // ignore parse
                }
                // « id|référence » : le SQF garde l'id pour les pièces jointes et
                // affiche la référence à l'opérateur.
                var detail = string.IsNullOrEmpty(id)
                    ? "Success"
                    : (string.IsNullOrEmpty(reference) ? id : id + "|" + reference);
                return FormatAtakExtArray("OK", detail);
            }
            var code = (int)resp.StatusCode;
            var modBlock = MapModAccessBlockError(body);
            if (modBlock != null)
                return FormatAtakExtArray("ERROR", modBlock.Replace("ERR|", "", StringComparison.Ordinal));
            if (code == 401 || code == 403) return FormatAtakExtArray("ERROR", "unauthorized");
            if (code == 503 && body.Contains("migration", StringComparison.OrdinalIgnoreCase))
                return FormatAtakExtArray("ERROR", "migration_required");
            if (body.Contains("body_required", StringComparison.OrdinalIgnoreCase))
                return FormatAtakExtArray("ERROR", "body_required");
            if (body.Contains("theme_required", StringComparison.OrdinalIgnoreCase))
                return FormatAtakExtArray("ERROR", "theme_required");
            if (body.Contains("maintenance", StringComparison.OrdinalIgnoreCase))
                return FormatAtakExtArray("ERROR", "maintenance");
            var errMsg = body.Length > 240 ? $"HTTP {code}" : body;
            return FormatAtakExtArray("ERROR", $"HTTP {code}: {errMsg}");
        }
        catch (OperationCanceledException)
        {
            return FormatAtakExtArray("TIMEOUT", "Request timeout");
        }
        catch (HttpRequestException ex)
        {
            return FormatAtakExtArray("NETWORK_ERROR", ex.Message);
        }
        catch (Exception ex)
        {
            return FormatAtakExtArray("ERROR", ex.Message);
        }
    }

    /// <summary>
    /// Extrait un id Athena numérique depuis un JSON biométrie / envelope.
    /// Ignore les UID terrain (SSE-35-000001) et les objets JSON passés par erreur.
    /// </summary>
    private static string TryExtractSsePersonId(string jsonBody)
    {
        try
        {
            using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(jsonBody) ? "{}" : jsonBody);
            var root = doc.RootElement;
            foreach (var key in new[] { "person_id", "athena_person_id", "id", "personId" })
            {
                if (!root.TryGetProperty(key, out var el)) continue;
                var v = el.ValueKind == JsonValueKind.Number
                    ? el.GetInt32().ToString()
                    : (el.GetString() ?? "").Trim();
                if (IsAthenaPersonId(v))
                    return v;
            }
        }
        catch { /* ignore */ }
        return "";
    }

    private static bool LooksLikeJsonObject(string? raw)
    {
        if (string.IsNullOrWhiteSpace(raw)) return false;
        var t = raw.TrimStart();
        return t.StartsWith('{') || t.StartsWith('[');
    }

    private static bool IsAthenaPersonId(string? raw)
    {
        if (string.IsNullOrWhiteSpace(raw)) return false;
        var v = raw.Trim();
        if (v.Length == 0 || v == "0") return false;
        if (v.Equals("Success", StringComparison.OrdinalIgnoreCase)) return false;
        if (LooksLikeJsonObject(v)) return false;
        foreach (var c in v)
        {
            if (c < '0' || c > '9') return false;
        }
        return true;
    }

    /// <summary>
    /// SendSSE : rapport tactique, sinon acquisition numérique SSE.
    /// Le reste ne doit pas créer un rapport vide « OTHER ».
    /// </summary>
    private static string PostSseGenericSync(string jsonBody, CancellationToken token)
    {
        try
        {
            var enriched = EnrichAtakPayload(jsonBody);
            try
            {
                using var doc = JsonDocument.Parse(enriched);
                var root = doc.RootElement;
                if (root.TryGetProperty("report_type", out var rt)
                    && rt.ValueKind == JsonValueKind.String
                    && !string.IsNullOrWhiteSpace(rt.GetString()))
                {
                    return PostAtakJsonSync("/api/atak/reports", enriched, token);
                }
                if (IsSseDigitalPayload(root))
                {
                    return PostAtakJsonSync("/api/sse/digital-acquisitions", enriched, token);
                }
            }
            catch { /* ignore */ }
            return FormatAtakExtArray("ERROR", "not_a_tactical_report");
        }
        catch (Exception ex)
        {
            return FormatAtakExtArray("ERROR", ex.Message);
        }
    }

    private static bool IsSseDigitalPayload(JsonElement root)
    {
        if (root.TryGetProperty("category", out var cat)
            && cat.ValueKind == JsonValueKind.String
            && string.Equals(cat.GetString(), "digital", StringComparison.OrdinalIgnoreCase))
            return true;
        if (root.TryGetProperty("schema", out var sch)
            && sch.ValueKind == JsonValueKind.String
            && (sch.GetString() ?? "").Contains("digital", StringComparison.OrdinalIgnoreCase))
            return true;
        if (root.TryGetProperty("phone_summary", out _) || root.TryGetProperty("computer_summary", out _))
            return true;
        return false;
    }

    /// <summary>
    /// Upsert véhicule par callsign puis POST /api/atak/vehicles/{id}/service.
    /// </summary>
    private static string PostVehicleServiceSync(string jsonBody, CancellationToken token)
    {
        try
        {
            var payload = EnrichAtakPayload(jsonBody);
            using var doc = JsonDocument.Parse(payload);
            var root = doc.RootElement;
            var callsign = root.TryGetProperty("vehicle_callsign", out var cs) ? (cs.GetString() ?? "") : "";
            if (callsign.Length == 0)
                return FormatAtakExtArray("ERROR", "vehicle_callsign required");

            var upsertSb = new StringBuilder("{\"mapId\":1,\"vehicle_callsign\":\"")
                .Append(EscapeJson(callsign)).Append('"');
            if (root.TryGetProperty("service_pos_x", out var px) && px.ValueKind == JsonValueKind.Number)
                upsertSb.Append(",\"pos_x\":").Append(px.GetDouble().ToString("R", System.Globalization.CultureInfo.InvariantCulture));
            if (root.TryGetProperty("service_pos_y", out var py) && py.ValueKind == JsonValueKind.Number)
                upsertSb.Append(",\"pos_y\":").Append(py.GetDouble().ToString("R", System.Globalization.CultureInfo.InvariantCulture));
            upsertSb.Append('}');
            var upsertJson = upsertSb.ToString();
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/vehicles", out var upsertUri, out var upsertErr) || upsertUri is null)
                return FormatAtakExtArray("ERROR", upsertErr);

            var upsertResp = SendJsonPost(upsertUri.AbsoluteUri, EnrichAtakPayload(upsertJson), token);
            var upsertBody = ReadContentUtf8(upsertResp, token);
            if (!upsertResp.IsSuccessStatusCode)
                return FormatAtakExtArray("ERROR", $"Vehicle lookup failed: HTTP {(int)upsertResp.StatusCode}");

            using var upsertDoc = JsonDocument.Parse(upsertBody);
            if (!upsertDoc.RootElement.TryGetProperty("id", out var idProp) || idProp.ValueKind != JsonValueKind.Number)
                return FormatAtakExtArray("ERROR", "vehicle_id not returned");
            var vehicleId = idProp.GetInt32();
            if (vehicleId <= 0) return FormatAtakExtArray("ERROR", "vehicle_id invalid");

            var serviceSb = new StringBuilder("{");
            var first = true;
            void AppendStr(string key, string? val)
            {
                if (string.IsNullOrEmpty(val)) return;
                if (!first) serviceSb.Append(',');
                serviceSb.Append('"').Append(key).Append("\":\"").Append(EscapeJson(val)).Append('"');
                first = false;
            }
            void AppendNum(string key, double val)
            {
                if (!first) serviceSb.Append(',');
                serviceSb.Append('"').Append(key).Append("\":")
                    .Append(val.ToString("R", System.Globalization.CultureInfo.InvariantCulture));
                first = false;
            }
            if (root.TryGetProperty("request_type", out var rt) && rt.ValueKind == JsonValueKind.String)
                AppendStr("request_type", rt.GetString());
            if (root.TryGetProperty("priority", out var pr) && pr.ValueKind == JsonValueKind.String)
                AppendStr("priority", pr.GetString());
            if (root.TryGetProperty("request_details", out var rd) && rd.ValueKind == JsonValueKind.String)
                AppendStr("request_details", rd.GetString());
            if (root.TryGetProperty("requested_by_callsign", out var rb) && rb.ValueKind == JsonValueKind.String)
                AppendStr("requested_by_callsign", rb.GetString());
            if (root.TryGetProperty("service_pos_x", out var sx) && sx.ValueKind == JsonValueKind.Number)
                AppendNum("service_pos_x", sx.GetDouble());
            if (root.TryGetProperty("service_pos_y", out var sy) && sy.ValueKind == JsonValueKind.Number)
                AppendNum("service_pos_y", sy.GetDouble());
            serviceSb.Append('}');
            var serviceJson = serviceSb.ToString();
            var servicePath = $"/api/atak/vehicles/{vehicleId}/service";
            if (!TryBuildRequestUri(_baseUrl, servicePath, out var serviceUri, out var serviceErr) || serviceUri is null)
                return FormatAtakExtArray("ERROR", serviceErr);

            var serviceResp = SendJsonPost(serviceUri.AbsoluteUri, serviceJson, token);
            var serviceBody = ReadContentUtf8(serviceResp, token);
            if (serviceResp.IsSuccessStatusCode)
                return FormatAtakExtArray("OK", "Service requested");
            return FormatAtakExtArray("ERROR", $"HTTP {(int)serviceResp.StatusCode}: {(serviceBody.Length > 200 ? "" : serviceBody)}");
        }
        catch (OperationCanceledException)
        {
            return FormatAtakExtArray("TIMEOUT", "Request timeout");
        }
        catch (HttpRequestException ex)
        {
            return FormatAtakExtArray("NETWORK_ERROR", ex.Message);
        }
        catch (Exception ex)
        {
            return FormatAtakExtArray("ERROR", ex.Message);
        }
    }

    private static string EscapeJsonIntOrString(string raw)
    {
        if (string.IsNullOrWhiteSpace(raw)) return "0";
        raw = raw.Trim();
        if (long.TryParse(raw, System.Globalization.NumberStyles.Integer,
                System.Globalization.CultureInfo.InvariantCulture, out var n))
            return n.ToString(System.Globalization.CultureInfo.InvariantCulture);
        return "\"" + EscapeJson(raw) + "\"";
    }

    private static string EscapeJson(string s)
    {
        if (string.IsNullOrEmpty(s)) return s;
        return s.Replace("\\", "\\\\").Replace("\"", "\\\"").Replace("\n", "\\n").Replace("\r", "\\r");
    }

    /// <summary>
    /// Normalise SteamID64 / SteamID2 / SteamID3 (et chiffres avec séparateurs) vers SteamID64.
    /// Rejette vide, placeholders solo Arma, et chaînes absurdes.
    /// </summary>
    private static bool TryNormalizeSteamUid(string? raw, out string steam64)
    {
        steam64 = "";
        if (string.IsNullOrWhiteSpace(raw)) return false;
        raw = raw.Trim();
        if (raw.Length >= 2
            && ((raw[0] == '"' && raw[^1] == '"') || (raw[0] == '\'' && raw[^1] == '\'')))
        {
            raw = raw.Substring(1, raw.Length - 2).Trim();
        }
        if (raw.Length == 0) return false;

        if (raw.StartsWith("_SP_", StringComparison.OrdinalIgnoreCase)
            || raw.Equals("LOCAL", StringComparison.OrdinalIgnoreCase)
            || raw.Equals("AI", StringComparison.OrdinalIgnoreCase))
        {
            return false;
        }

        // STEAM_X:Y:Z
        if (raw.StartsWith("STEAM_", StringComparison.OrdinalIgnoreCase))
        {
            var parts = raw.Split(':');
            if (parts.Length == 3
                && parts[0].Length == 7
                && (parts[1] == "0" || parts[1] == "1")
                && ulong.TryParse(parts[2], out var z)
                && z > 0)
            {
                var account = z * 2UL + ulong.Parse(parts[1]);
                steam64 = (account + 76561197960265728UL).ToString();
                return steam64.Length is >= 15 and <= 20;
            }
            return false;
        }

        // [U:1:N] ou U:1:N
        var steam3 = raw;
        if (steam3.StartsWith('[') && steam3.EndsWith(']'))
            steam3 = steam3.Substring(1, steam3.Length - 2);
        if (steam3.StartsWith("U:1:", StringComparison.OrdinalIgnoreCase))
        {
            var idPart = steam3.Substring(4);
            if (ulong.TryParse(idPart, out var account) && account > 0)
            {
                steam64 = (account + 76561197960265728UL).ToString();
                return steam64.Length is >= 15 and <= 20;
            }
            return false;
        }

        // …/profiles/7656…
        const string profilesMarker = "/profiles/";
        var pIdx = raw.IndexOf(profilesMarker, StringComparison.OrdinalIgnoreCase);
        if (pIdx >= 0)
        {
            var start = pIdx + profilesMarker.Length;
            var end = start;
            while (end < raw.Length && char.IsDigit(raw[end])) end++;
            if (end > start)
            {
                var digs = raw.Substring(start, end - start);
                if (digs.Length is >= 15 and <= 20 && digs.TrimStart('0').Length > 0)
                {
                    steam64 = digs;
                    return true;
                }
            }
        }

        // Chiffres seuls (espaces / tirets tolérés) — pas d’URL vanity.
        if (raw.Contains('/') || raw.Contains("steamcommunity", StringComparison.OrdinalIgnoreCase))
            return false;

        var digits = new string(raw.Where(char.IsDigit).ToArray());
        if (digits.Length is >= 15 and <= 20 && digits.TrimStart('0').Length > 0)
        {
            steam64 = digits;
            return true;
        }

        return false;
    }
}
