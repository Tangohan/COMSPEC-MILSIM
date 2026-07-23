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
    /// <summary>Tenant issu du redeem / Connect (requis par client-init côté portail).</summary>
    private static string _tenantId = "";
    /// <summary>SteamID64 mémorisé (liaison / Connect / UpdatePosition) — identité côté DLL, pas seulement SQF.</summary>
    private static string _steamUid = "";
    /// <summary>Version du mod Overwatch (CfgPatches) — remontée dans les journal Activité.</summary>
    private static string _modVersion = "";
    /// <summary>Jeton de session court renvoyé par client-init (anti-spoof serveur).</summary>
    private static string _sessionToken = "";
    private static readonly HttpClient HttpClient = new() { Timeout = TimeSpan.FromSeconds(SyncTimeoutSeconds) };
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
    /// <summary>Backoff après HTTP 429 (Ticks UTC). Pendant ce délai : pas d’envoi position / drain réduit.</summary>
    private static long _rateLimitUntilTicks;
    private static int _rateLimitBackoffSec = 2;
    private static long _lastRateLimitCbTicks;
    /// <summary>
    /// Dernier échec d'envoi fire-and-forget (position, tchat, marqueurs...) : ces requêtes ne
    /// remontent jamais d'erreur à SQF (retry silencieux via PendingPosts), donc sans ce
    /// compteur un échec serveur persistant (403/422/500) est invisible même en debug.
    /// 0 = code réseau (pas de réponse HTTP, ex. DNS/TLS/timeout).
    /// </summary>
    private static int _lastPostErrorCode;
    private static string _lastPostErrorPath = "";
    private static long _lastPostErrorAtTicks;

    private static void NotePostError(int code, string url)
    {
        _lastPostErrorCode = code;
        string path;
        try { path = new Uri(url).AbsolutePath; } catch { path = url; }
        _lastPostErrorPath = path.Replace("|", "/");
        System.Threading.Interlocked.Exchange(ref _lastPostErrorAtTicks, DateTime.UtcNow.Ticks);
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
        if (_drainTimer != null) return;
        _drainTimer = new System.Threading.Timer(_ => DrainQueue(), null, 2000, 2000);
    }

    private static bool IsPositionEndpoint(string url) =>
        url.Contains("/api/atak/position", StringComparison.OrdinalIgnoreCase);

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

    private static void NoteRateLimited()
    {
        var next = Math.Min(_rateLimitBackoffSec * 2, 60);
        if (_rateLimitBackoffSec < 2) _rateLimitBackoffSec = 2;
        var delaySec = _rateLimitBackoffSec;
        _rateLimitBackoffSec = next;
        var until = DateTime.UtcNow.AddSeconds(delaySec).Ticks;
        System.Threading.Interlocked.Exchange(ref _rateLimitUntilTicks, until);
        var now = DateTime.UtcNow.Ticks;
        // Évite de spammer le callback SQF (max ~1 / 3 s).
        if (now - System.Threading.Interlocked.Read(ref _lastRateLimitCbTicks) > TimeSpan.FromSeconds(3).Ticks)
        {
            System.Threading.Interlocked.Exchange(ref _lastRateLimitCbTicks, now);
            InvokeCallback("RateLimited", "Athena est saturé — synchronisation ralentie quelques instants.");
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
            NoteRateLimited();
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
        NotePostError((int)response.StatusCode, url);
        EnqueueForRetry(url, jsonBody);
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
                NoteRateLimited();
                EnqueueForRetry(item.Url, item.Body);
                return false;
            }
            if (!response.IsSuccessStatusCode)
            {
                NotePostError((int)response.StatusCode, item.Url);
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

    private static void EnqueueOrSend(string url, string jsonBody)
    {
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

    private static HttpResponseMessage SendJsonPost(string url, string jsonBody, CancellationToken token)
    {
        using var req = new HttpRequestMessage(HttpMethod.Post, url)
        {
            Content = JsonContent(jsonBody)
        };
        AttachApiKeyHeader(req);
        return HttpClient.SendAsync(req, token).GetAwaiter().GetResult();
    }

    // --- MessageBox Win32 (alerte liaison Athena) ---
    private const uint MbYesNo = 0x00000004;
    private const uint MbIconInformation = 0x00000040;
    private const uint MbSetForeground = 0x00010000;
    private const uint MbTopmost = 0x00040000;
    private const int IdNo = 7;

    [DllImport("user32.dll", CharSet = CharSet.Unicode, ExactSpelling = true)]
    private static extern int MessageBoxW(nint hWnd, string lpText, string lpCaption, uint uType);

    private static readonly object AthenaHelpLock = new();
    private static bool _athenaHelpShowing;

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

    [UnmanagedCallersOnly(EntryPoint = "RVExtensionVersion")]
    public static void RvExtensionVersion(nint output, int outputSize)
    {
            Output(output, outputSize, "COMSPECExtension 1.16");
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
            return "OK|COMSPECExtension 1.16";
        }

        // Alerte Windows : marche à suivre pour lier le compte Athena (bloquant, thread OK).
        if (function is "ShowAthenaLinkHelp")
        {
            return ShowAthenaLinkHelpMessageBox();
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
                return verify;

            // Dernier filet : si la clé SQF vient de faire échouer client-init, restaurer Redeem.
            if (prevKey.Length > 0 && !string.Equals(prevKey, _apiKey, StringComparison.Ordinal))
            {
                ApplyApiKeyHeaders(prevKey);
                if (prevTenant.Length > 0)
                    ApplyTenantId(prevTenant);
                var restored = VerifyClientInitSync();
                if (restored.StartsWith("OK|", StringComparison.Ordinal))
                    return restored;
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
                var response = SendGet(url, token);
                response.EnsureSuccessStatusCode();
                var body = ReadContentUtf8(response, token);
                return "OK|" + SimplifyMarkersJson(body);
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
                var resp = SendGet(url, token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                var safe = respBody.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                return "OK|" + (safe.Length > MaxOutputBytes - 4 ? safe.Substring(0, MaxOutputBytes - 4) : safe);
            }
            if (function == "GetMapShapes")
            {
                var mapId = args.Length > 0 ? (args[0] ?? "1") : "1";
                var since = args.Length > 1 ? Uri.EscapeDataString(args[1] ?? "") : "";
                var url = _baseUrl + "/api/map-shapes?mapId=" + mapId;
                if (!string.IsNullOrEmpty(since)) url += "&since=" + since;
                var resp = SendGet(url, token);
                resp.EnsureSuccessStatusCode();
                var respBody = ReadContentUtf8(resp, token);
                var safe = respBody.Replace("|", "_").Replace("\n", " ").Replace("\r", "");
                return "OK|" + (safe.Length > MaxOutputBytes - 4 ? safe.Substring(0, MaxOutputBytes - 4) : safe);
            }
            // Modules pont ATAK Enhanced / cTab (activables admin).
            // Lignes : id\tenabled(0|1)\tlabel
            if (function == "GetModModules")
            {
                var resp = SendGet(_baseUrl + "/api/atak/mod-modules", token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 401) return "ERR|unauthorized";
                    if (code == 403) return "ERR|forbidden";
                    return "ERR|http_" + code;
                }
                var respBody = ReadContentUtf8(resp, token);
                var simplified = SimplifyModModulesJson(respBody);
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
            // Alertes tactiques Athena (Contact / FRAGO / BDA / …) → inbox cTab.
            // Lignes : id\tkind\tkind_label\tcall_sign\tgrid\tsummary\tcreated_at\tseverity
            if (function == "GetTacticalAlerts")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var limit = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]!.Trim() : "40";
                var url = _baseUrl + "/api/atak/tactical-alerts?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=" + Uri.EscapeDataString(limit);
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplifiedTac = SimplifyTacticalAlertsJson(respBody);
                return "OK|" + (simplifiedTac.Length > MaxOutputBytes - 4 ? simplifiedTac.Substring(0, MaxOutputBytes - 4) : simplifiedTac);
            }
            // Alertes médicales actives (≤ 30 min) + triage.
            // Lignes : id\tkind\tcall_sign\tlabel\tgrid\tcreated_at\ttriage_status\ttriage_label\tseverity
            if (function == "GetMedicalAlerts")
            {
                var mapId = args.Length > 0 && !string.IsNullOrWhiteSpace(args[0]) ? args[0]!.Trim() : "1";
                var limit = args.Length > 1 && !string.IsNullOrWhiteSpace(args[1]) ? args[1]!.Trim() : "25";
                var url = _baseUrl + "/api/atak/medical-alerts?mapId=" + Uri.EscapeDataString(mapId)
                    + "&limit=" + Uri.EscapeDataString(limit);
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401) return "ERR|unauthorized";
                    if (code == 403) return "ERR|unauthorized";
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyMedicalAlertsJson(respBody);
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Ordres C2 web → jeu. Args : [mapId, limit, callsign?]
            // Lignes : id\ttype\ttarget\tpriority\tissuer\tstatus\tpayload\ttarget_type\ttarget_ref\taliases
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
                var resp = SendGet(url, token);
                var respBody = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode)
                {
                    var code = (int)resp.StatusCode;
                    if (code == 404) return "ERR|not_found";
                    if (code == 401 || code == 403) return "ERR|unauthorized";
                    if (code == 503) return "ERR|unavailable";
                    return "ERR|http_" + code;
                }
                var simplified = SimplifyOrdersJson(respBody);
                return "OK|" + (simplified.Length > MaxOutputBytes - 4 ? simplified.Substring(0, MaxOutputBytes - 4) : simplified);
            }
            // Mise à jour statut ordre depuis le jeu. Args : [orderId, status, by, mapId?]
            if (function == "UpdateOrderStatus" && args.Length >= 2)
            {
                var orderId = (args[0] ?? "").Trim();
                var status = (args[1] ?? "").Trim();
                var by = args.Length > 2 ? (args[2] ?? "").Trim() : "";
                var mapId = args.Length > 3 && !string.IsNullOrWhiteSpace(args[3]) ? args[3]!.Trim() : "1";
                if (orderId.Length == 0 || status.Length == 0) return "ERR|invalid";
                if (!int.TryParse(mapId, out var mapIdNum)) mapIdNum = 1;
                var url = _baseUrl + "/api/atak/orders/" + Uri.EscapeDataString(orderId) + "/status";
                var steamJson = _steamUid.Length > 0
                    ? $",\"steam_uid\":\"{EscapeJson(_steamUid)}\""
                    : "";
                var sessJson = _sessionToken.Length > 0
                    ? $",\"session_token\":\"{EscapeJson(_sessionToken)}\""
                    : "";
                var payload = $"{{\"status\":\"{EscapeJson(status)}\",\"by\":\"{EscapeJson(by)}\",\"mapId\":{mapIdNum}{steamJson}{sessJson}}}";
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
                var url = _baseUrl + "/api/atak/phone-pairing";
                if (!string.IsNullOrEmpty(tenantId)) url += "?tenant_id=" + Uri.EscapeDataString(tenantId);
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
    /// Lignes : id\ttype\ttarget\tpriority\tissuer\tstatus\tpayload\ttarget_type\ttarget_ref\taliases
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
                sb.Append(Clean(id)).Append('\t')
                  .Append(Clean(type)).Append('\t')
                  .Append(Clean(target)).Append('\t')
                  .Append(Clean(priority)).Append('\t')
                  .Append(Clean(issuer)).Append('\t')
                  .Append(Clean(status)).Append('\t')
                  .Append(Clean(payload)).Append('\t')
                  .Append(Clean(targetType)).Append('\t')
                  .Append(Clean(targetRef)).Append('\t')
                  .Append(Clean(aliases)).Append('\n');
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
            // Code court obligatoire (alphanumérique) ; QR optionnel (téléchargement séparé).
            if (token.Length == 0 || code.Length < 4 || code.Length > 12) return "";
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
                var steamJson = steamNorm.Length > 0
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
                var markerDataRaw = args[1] ?? "{}";
                var layerId = args.Length > 2 ? (args[2] ?? "1") : "1";
                var deleted = args.Length > 3 && (args[3] ?? "") == "1";
                var payload = deleted
                    ? "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\",\"deleted\":true}"
                    : "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\",\"markerData\":" + markerDataRaw + "}";
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
                    var photoReq = new HttpRequestMessage(HttpMethod.Post, _baseUrl + "/api/intel/photos") { Content = multipart };
                    AttachApiKeyHeader(photoReq);
                    _ = HttpClient.SendAsync(photoReq);
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
                    var reconReq = new HttpRequestMessage(HttpMethod.Post, _baseUrl + "/api/recon/images") { Content = multipart };
                    AttachApiKeyHeader(reconReq);
                    _ = HttpClient.SendAsync(reconReq);
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
