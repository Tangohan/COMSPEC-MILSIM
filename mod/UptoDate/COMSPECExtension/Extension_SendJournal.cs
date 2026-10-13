using System.Collections.Concurrent;
using System.Diagnostics;
using System.Net.Http.Headers;
using System.Text;

namespace COMSPECExtension;

/// <summary>
/// Journal des envois vers Athena : chaque envoi suivi (rapports, 9-line, SSE, logistique, Discord...) avec ses étapes
/// (en file, envoi, nouvel essai, envoyé, échec), plus un signal de charge d'Athena (latence, 429, 5xx, délais dépassés).
/// Positions, marqueurs, télémétrie et relevés de terrain ne sont pas suivis un par un (trop nombreux) : ils comptent
/// seulement dans les statistiques. Lu par le SQF (SendQueue.Status) et envoyé au site toutes les 30 s s'il a changé
/// (POST /api/atak/send-queue/report, page d'administration « Files d'envoi Athena »).
/// </summary>
public static partial class Extension
{
    private sealed class JournalItem
    {
        public string Id = "";
        public string Kind = "";
        public string Label = "";
        public string State = "queued";
        public int Attempts;
        public int Bytes;
        public long CreatedAt;
        public long UpdatedAt;
        public int LastHttp;
        public string LastError = "";
        public readonly List<(string State, long At, string Detail)> Steps = new();
    }

    private const int JournalMaxItems = 300;
    private const int JournalWindowSeconds = 900;
    private static readonly ConcurrentDictionary<string, JournalItem> JournalItems = new();
    private static readonly object JournalLock = new();
    // (moment, latence ms) des requêtes récentes ; (moment, type) des incidents : "429", "5xx", "timeout".
    private static readonly Queue<(long At, int Ms)> JournalLatency = new();
    private static readonly Queue<(long At, string Kind)> JournalIncidents = new();
    // Toutes les requêtes vers Athena depuis le lancement (positions comprises) : app Réseau du téléphone
    // (paquets envoyés, dernière réponse, perte), qui ne voyait aucune réponse aux envois de position.
    private static long _httpReqTotal;
    private static long _httpOkTotal;
    private static long _httpLastOkTicks;
    private static int _httpLastMs = -1;
    private static long _journalVersion;
    private static long _journalReportedVersion = -1;
    private static long _journalReportAtTicks;
    private static int _journalReportBusy;

    private static long NowUnix() => DateTimeOffset.UtcNow.ToUnixTimeSeconds();

    private static bool IsJournalTracked(string url)
    {
        if (string.IsNullOrEmpty(url)) return false;
        if (url.Contains("/send-queue/report", StringComparison.OrdinalIgnoreCase)) return false;
        if (url.Contains("/telemetry", StringComparison.OrdinalIgnoreCase)) return false;
        // Liaison, jetons et appairage : pas des envois de joueur.
        foreach (var k in new[] { "/client-init", "/disconnect", "/pair", "/recovery", "/auth/", "/token" })
            if (url.Contains(k, StringComparison.OrdinalIgnoreCase)) return false;
        return !(IsPositionEndpoint(url) || IsMarkerEndpoint(url) || IsWeatherEndpoint(url)
                 || IsBestEffortEndpoint(url) || IsHeavyIngestEndpoint(url) || IsVideoFeedsEndpoint(url));
    }

    private static string JournalPath(string url)
    {
        if (url.StartsWith("queued:", StringComparison.OrdinalIgnoreCase))
        {
            var q = url.Substring("queued:".Length);
            return q.StartsWith('/') ? q : "/" + q;
        }
        try
        {
            if (Uri.TryCreate(url, UriKind.Absolute, out var u)) return u.AbsolutePath;
        }
        catch { /* ignore */ }
        return url;
    }

    private static string JournalLabel(string path)
    {
        var p = path.ToLowerInvariant();
        if (p.Contains("/api/cas")) return "9-line CAS";
        if (p.Contains("/discord")) return "Message Discord";
        if (p.Contains("/logistics")) return "Demande logistique";
        if (p.Contains("/api/sse/")) return "Fiche SSE";
        if (p.Contains("/medical")) return "Alerte médicale";
        if (p.Contains("/orders") || p.Contains("/order-status")) return "Statut d'ordre";
        if (p.Contains("/reports")) return "Rapport tactique";
        if (p.Contains("/recon")) return "Note de reconnaissance";
        if (p.Contains("/intel")) return "Renseignement";
        if (p.Contains("/briefing")) return "Briefing";
        if (p.Contains("/upload") || p.Contains("/photo") || p.Contains("/image")) return "Photo";
        if (p.Contains("/chat")) return "Message";
        if (p.Contains("/operator")) return "Profil opérateur";
        return path;
    }

    private static string JournalId(string url, string body)
    {
        // FNV-1a 64 bits : le même envoi remis en file garde le même identifiant.
        ulong h = 1469598103934665603UL;
        // Chemin seul : « queued:/api/... » et l'URL résolue désignent le même envoi.
        foreach (var ch in JournalPath(url)) { h ^= ch; h *= 1099511628211UL; }
        h ^= 10; h *= 1099511628211UL;
        foreach (var ch in body ?? "") { h ^= ch; h *= 1099511628211UL; }
        return h.ToString("x16")[..10];
    }

    /// <summary>Étape d'un envoi suivi. "sending" compte un essai de plus.</summary>
    private static void JournalTrack(string url, string body, string state, string detail = "", int http = 0, string label = "")
    {
        try
        {
            if (!IsJournalTracked(url)) return;
            var id = JournalId(url, body);
            var now = NowUnix();
            lock (JournalLock)
            {
                if (!JournalItems.TryGetValue(id, out var it))
                {
                    var path = JournalPath(url);
                    it = new JournalItem
                    {
                        Id = id,
                        Kind = "POST " + path,
                        Label = label.Length > 0 ? label : JournalLabel(path),
                        Bytes = Encoding.UTF8.GetByteCount(body ?? ""),
                        CreatedAt = now
                    };
                    JournalItems[id] = it;
                }
                // Un envoi remis en file après un échec : « nouvel essai », pas « en file ».
                if (state == "queued" && it.State is "sending" or "retry") state = "retry";
                if (state == "sending") it.Attempts++;
                it.State = state;
                it.UpdatedAt = now;
                if (http != 0) it.LastHttp = http;
                if (state is "retry" or "failed" && detail.Length > 0) it.LastError = detail;
                if (state == "done") it.LastError = "";
                it.Steps.Add((state, now, detail));
                if (it.Steps.Count > 12) it.Steps.RemoveAt(1);
                _journalVersion++;
                JournalPruneLocked(now);
            }
        }
        catch { /* le journal ne doit jamais gêner un envoi */ }
    }

    /// <summary>Issue d'un envoi : réussi, sinon nouvel essai (s'il a été remis en file entre-temps) ou échec.</summary>
    private static void JournalOutcome(string url, string body, int code, long startedTicks)
    {
        try
        {
            if (!IsJournalTracked(url)) return;
            var id = JournalId(url, body);
            if (code is >= 200 and < 300) { JournalTrack(url, body, "done", code.ToString(), code); return; }
            var reason = code switch
            {
                0 or -1 => "délai dépassé ou pas de réseau",
                429 => "Athena surchargé (429)",
                503 => "Athena indisponible (503)",
                _ => "http_" + code
            };
            lock (JournalLock)
            {
                if (JournalItems.TryGetValue(id, out var it) && it.State == "retry" && it.Steps.Count > 0)
                {
                    // Remis en file pendant le traitement de la réponse : on précise la raison.
                    var last = it.Steps[^1];
                    it.Steps[^1] = (last.State, last.At, reason);
                    it.LastError = reason;
                    if (code != 0) it.LastHttp = code;
                    _journalVersion++;
                    return;
                }
            }
            JournalTrack(url, body, "failed", reason, code);
        }
        catch { /* ignore */ }
    }

    /// <summary>Latence et incidents de toutes les requêtes vers Athena (suivies ou non).</summary>
    private static void JournalHttp(long startedTicks, int code)
    {
        try
        {
            var now = DateTime.UtcNow.Ticks;
            var ms = (int)Math.Min(int.MaxValue, (now - startedTicks) / TimeSpan.TicksPerMillisecond);
            System.Threading.Interlocked.Increment(ref _httpReqTotal);
            if (code is >= 200 and < 300)
            {
                System.Threading.Interlocked.Increment(ref _httpOkTotal);
                System.Threading.Interlocked.Exchange(ref _httpLastOkTicks, now);
                _httpLastMs = ms;
            }
            lock (JournalLock)
            {
                JournalLatency.Enqueue((now, ms));
                while (JournalLatency.Count > 400) JournalLatency.Dequeue();
                var kind = code == 429 ? "429" : code >= 500 ? "5xx" : code <= 0 ? "timeout" : "";
                if (kind.Length > 0)
                {
                    JournalIncidents.Enqueue((now, kind));
                    while (JournalIncidents.Count > 400) JournalIncidents.Dequeue();
                }
            }
        }
        catch { /* ignore */ }
    }

    private static void JournalPruneLocked(long now)
    {
        // Envois terminés gardés 15 min ; au-delà de 300 suivis, les plus anciens terminés partent d'abord.
        foreach (var kv in JournalItems)
        {
            if (kv.Value.State is "done" or "failed" && now - kv.Value.UpdatedAt > JournalWindowSeconds)
                JournalItems.TryRemove(kv.Key, out _);
        }
        if (JournalItems.Count <= JournalMaxItems) return;
        foreach (var it in JournalItems.Values.OrderBy(i => i.State is "done" or "failed" ? 0 : 1).ThenBy(i => i.UpdatedAt).Take(JournalItems.Count - JournalMaxItems).ToList())
            JournalItems.TryRemove(it.Id, out _);
    }

    private sealed class JournalSummary
    {
        public int Queued, Sending, Retry, Failed, Done, OldestQueuedS, AvgMs, P95Ms, H429, H5xx, Timeouts, RateLimitedForS, BackoffForS;
        public string Overload = "ok";
    }

    private static JournalSummary JournalSummarize()
    {
        var s = new JournalSummary();
        var nowUnix = NowUnix();
        var nowTicks = DateTime.UtcNow.Ticks;
        var since = nowTicks - TimeSpan.FromSeconds(JournalWindowSeconds).Ticks;
        lock (JournalLock)
        {
            foreach (var it in JournalItems.Values)
            {
                switch (it.State)
                {
                    case "queued": s.Queued++; break;
                    case "sending": s.Sending++; break;
                    case "retry": s.Retry++; break;
                    case "failed": s.Failed++; break;
                    case "done": s.Done++; break;
                }
                if (it.State is "queued" or "retry")
                    s.OldestQueuedS = (int)Math.Max(s.OldestQueuedS, nowUnix - it.CreatedAt);
            }
            var lat = JournalLatency.Where(l => l.At >= since).Select(l => l.Ms).OrderBy(x => x).ToList();
            if (lat.Count > 0)
            {
                s.AvgMs = (int)lat.Average();
                s.P95Ms = lat[(int)Math.Min(lat.Count - 1, Math.Floor(lat.Count * 0.95))];
            }
            foreach (var inc in JournalIncidents)
            {
                if (inc.At < since) continue;
                if (inc.Kind == "429") s.H429++;
                else if (inc.Kind == "5xx") s.H5xx++;
                else s.Timeouts++;
            }
        }
        // Envois en file non suivis un par un (relevés, flux vidéo) : comptés aussi, sans doublon.
        foreach (var p in PendingPosts)
            if (!IsJournalTracked(p.Url)) s.Queued++;
        s.RateLimitedForS = (int)Math.Max(0, (System.Threading.Interlocked.Read(ref _rateLimitUntilTicks) - nowTicks) / TimeSpan.TicksPerSecond);
        s.BackoffForS = (int)Math.Max(0, (System.Threading.Interlocked.Read(ref _networkBackoffUntilTicks) - nowTicks) / TimeSpan.TicksPerSecond);
        // Hors ligne : pause réseau après des délais dépassés ; surchargé : Athena a demandé de ralentir (429/503) ;
        // ralenti : réponses lentes ou erreurs serveur récentes.
        if (s.BackoffForS > 0 && s.Timeouts > 0) s.Overload = "offline";
        else if (s.RateLimitedForS > 0) s.Overload = "rate_limited";
        else if (s.P95Ms > 3000 || s.H5xx >= 3 || s.H429 > 0) s.Overload = "slow";
        return s;
    }

    /// <summary>
    /// SendQueue.Status : "OK|en file\tenvoi\tnouvel essai\téchec\tenvoyés\tétat\tlatence moy.\tp95\t429\t5xx\tdélais"
    /// \trequêtes depuis le lancement\tréponses 2xx\tâge de la dernière réponse 2xx en s (-1 : jamais)\tlatence de cette réponse ms
    /// \trequêtes sur la fenêtre\tincidents sur la fenêtre (429, 5xx, délais)
    /// puis une ligne par envoi suivi (le plus récent d'abord) : id\tlibellé\tétat\tessais\tâge s\tdernière erreur.
    /// </summary>
    private static string JournalStatusForSqf()
    {
        var s = JournalSummarize();
        var sb = new StringBuilder("OK|");
        sb.Append(string.Join('\t', s.Queued, s.Sending, s.Retry, s.Failed, s.Done, s.Overload, s.AvgMs, s.P95Ms, s.H429, s.H5xx, s.Timeouts));
        var lastOk = System.Threading.Interlocked.Read(ref _httpLastOkTicks);
        var lastOkAge = lastOk <= 0 ? -1 : (long)((DateTime.UtcNow.Ticks - lastOk) / TimeSpan.TicksPerSecond);
        int winReq;
        var since = DateTime.UtcNow.Ticks - TimeSpan.FromSeconds(JournalWindowSeconds).Ticks;
        lock (JournalLock) winReq = JournalLatency.Count(l => l.At >= since);
        sb.Append('\t').Append(string.Join('\t', System.Threading.Interlocked.Read(ref _httpReqTotal), System.Threading.Interlocked.Read(ref _httpOkTotal),
            lastOkAge, lastOk <= 0 ? -1 : _httpLastMs, winReq, s.H429 + s.H5xx + s.Timeouts));
        var now = NowUnix();
        List<JournalItem> items;
        lock (JournalLock) items = JournalItems.Values.OrderByDescending(i => i.UpdatedAt).Take(30).ToList();
        foreach (var it in items)
        {
            sb.Append('\n').Append(it.Id).Append('\t').Append(it.Label.Replace('\t', ' ').Replace('|', '/'))
              .Append('\t').Append(it.State).Append('\t').Append(it.Attempts)
              .Append('\t').Append(now - it.CreatedAt).Append('\t').Append(it.LastError.Replace('\t', ' ').Replace('|', '/'));
            if (sb.Length > MaxOutputBytes - 200) break;
        }
        return sb.ToString();
    }

    private static string JournalReportJson()
    {
        var s = JournalSummarize();
        var sb = new StringBuilder("{\"mapId\":");
        sb.Append(CurrentMapId().ToString(System.Globalization.CultureInfo.InvariantCulture));
        if (_tenantId.Length > 0) sb.Append(",\"tenant_id\":").Append(EscapeJsonIntOrString(_tenantId));
        sb.Append(",\"steam_uid\":\"").Append(EscapeJson(_steamUid)).Append('"');
        sb.Append(",\"call_sign\":\"").Append(EscapeJson(_callSign)).Append('"');
        sb.Append(",\"extension_version\":\"").Append(EscapeJson(CurrentExtensionVersion())).Append('"');
        sb.Append(",\"mod_version\":\"").Append(EscapeJson(_modVersion)).Append('"');
        sb.Append(",\"client_kind\":\"").Append(_steamUid.Length > 0 ? "client" : "server").Append('"');
        sb.Append(",\"summary\":{")
          .Append("\"queued\":").Append(s.Queued).Append(",\"sending\":").Append(s.Sending)
          .Append(",\"retry\":").Append(s.Retry).Append(",\"failed\":").Append(s.Failed).Append(",\"done\":").Append(s.Done)
          .Append(",\"oldest_queued_s\":").Append(s.OldestQueuedS)
          .Append(",\"overload\":\"").Append(s.Overload).Append('"')
          .Append(",\"rate_limited_for_s\":").Append(s.RateLimitedForS).Append(",\"backoff_for_s\":").Append(s.BackoffForS)
          .Append(",\"avg_latency_ms\":").Append(s.AvgMs).Append(",\"p95_latency_ms\":").Append(s.P95Ms)
          .Append(",\"http_429\":").Append(s.H429).Append(",\"http_5xx\":").Append(s.H5xx).Append(",\"timeouts\":").Append(s.Timeouts)
          .Append(",\"window_s\":").Append(JournalWindowSeconds).Append('}');
        sb.Append(",\"items\":[");
        List<JournalItem> items;
        lock (JournalLock) items = JournalItems.Values.OrderByDescending(i => i.UpdatedAt).Take(100).ToList();
        var first = true;
        foreach (var it in items)
        {
            if (!first) sb.Append(',');
            first = false;
            sb.Append("{\"id\":\"").Append(it.Id).Append("\",\"kind\":\"").Append(EscapeJson(it.Kind))
              .Append("\",\"label\":\"").Append(EscapeJson(it.Label)).Append("\",\"state\":\"").Append(it.State)
              .Append("\",\"attempts\":").Append(it.Attempts).Append(",\"bytes\":").Append(it.Bytes)
              .Append(",\"created_at\":").Append(it.CreatedAt).Append(",\"updated_at\":").Append(it.UpdatedAt)
              .Append(",\"last_http\":").Append(it.LastHttp).Append(",\"last_error\":\"").Append(EscapeJson(it.LastError)).Append("\",\"steps\":[");
            List<(string State, long At, string Detail)> steps;
            lock (JournalLock) steps = it.Steps.ToList();
            for (var i = 0; i < steps.Count; i++)
            {
                if (i > 0) sb.Append(',');
                sb.Append("[\"").Append(steps[i].State).Append("\",").Append(steps[i].At).Append(",\"").Append(EscapeJson(steps[i].Detail)).Append("\"]");
            }
            sb.Append("]}");
        }
        sb.Append("]}");
        return sb.ToString();
    }

    /// <summary>
    /// Rapport au site (minuteur de vidage, hors du thread du jeu) : toutes les 30 s s'il a changé, au moins toutes
    /// les 5 min pour montrer que le client est en ligne ; pendant une surcharge, une fois par minute au plus.
    /// </summary>
    private static void JournalMaybeReport()
    {
        try
        {
            if (string.IsNullOrEmpty(_baseUrl) || !HasPortalAuth()) return;
            var nowTicks = DateTime.UtcNow.Ticks;
            var since = nowTicks - System.Threading.Interlocked.Read(ref _journalReportAtTicks);
            var changed = System.Threading.Interlocked.Read(ref _journalVersion) != System.Threading.Interlocked.Read(ref _journalReportedVersion);
            var minGap = IsPostPausedNow() ? TimeSpan.FromSeconds(60) : TimeSpan.FromSeconds(30);
            if (since < minGap.Ticks) return;
            if (!changed && since < TimeSpan.FromMinutes(5).Ticks) return;
            if (System.Threading.Interlocked.CompareExchange(ref _journalReportBusy, 1, 0) != 0) return;
            System.Threading.Interlocked.Exchange(ref _journalReportAtTicks, nowTicks);
            var version = System.Threading.Interlocked.Read(ref _journalVersion);
            var json = JournalReportJson();
            var req = new HttpRequestMessage(HttpMethod.Post, _baseUrl.TrimEnd('/') + "/api/atak/send-queue/report")
            {
                Content = JsonContent(json)
            };
            AttachApiKeyHeader(req);
            _ = HttpClient.SendAsync(req).ContinueWith(t =>
            {
                try
                {
                    if (t.Status == TaskStatus.RanToCompletion && t.Result.IsSuccessStatusCode)
                        System.Threading.Interlocked.Exchange(ref _journalReportedVersion, version);
                    if (t.Status == TaskStatus.RanToCompletion) t.Result.Dispose();
                }
                catch { /* ignore */ }
                finally
                {
                    try { req.Dispose(); } catch { /* ignore */ }
                    System.Threading.Interlocked.Exchange(ref _journalReportBusy, 0);
                }
            });
        }
        catch
        {
            System.Threading.Interlocked.Exchange(ref _journalReportBusy, 0);
        }
    }
}
