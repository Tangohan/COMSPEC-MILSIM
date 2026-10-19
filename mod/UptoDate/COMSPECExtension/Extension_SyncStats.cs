using System.Collections.Concurrent;
using System.Globalization;
using System.IO.Compression;
using System.Net;
using System.Net.Http.Headers;
using System.Text;

namespace COMSPECExtension;

/// <summary>
/// Volume échangé avec Athena, canal par canal (chemin d'API), et réponses inchangées non retéléchargées.
///
/// - Chaque requête passée par SendTimed annonce « gzip » et, pour un GET déjà reçu, l'empreinte (ETag) de la
///   dernière réponse. Athena répond 304 sans corps si rien n'a changé : on ressert la copie gardée ici, l'appelant
///   voit un 200 identique au précédent. Les listes interrogées toutes les 2 à 10 s (messages, marqueurs, formes,
///   ordres…) ne repartent plus en entier quand elles ne bougent pas.
/// - Compteurs : octets envoyés (ligne, en-têtes, corps) et reçus (corps tel qu'il passe sur le réseau, compressé
///   ou non, plus en-têtes estimés), nombre d'appels, réponses inchangées, dernier code et âge du dernier appel.
///   Lus par l'app Réseau du téléphone (SyncStats) et par le bandeau de dépannage d'Overwatch.
/// </summary>
public static partial class Extension
{
    private sealed class TrafficChannel
    {
        public long Calls;
        public long OutBytes;
        public long InBytes;
        public long NotModified;
        public long SavedBytes;
        public long LastAtMs;
        public int LastCode;
    }

    private sealed class EtagEntry
    {
        public string Etag = "";
        public byte[] Body = Array.Empty<byte>();
        public string ContentType = "";
        public long At;
    }

    private const int EtagMaxEntries = 96;
    private const int EtagMaxBodyBytes = 512 * 1024;
    private const int TrafficWindowSeconds = 300;
    private static readonly ConcurrentDictionary<string, TrafficChannel> TrafficChannels = new();
    private static readonly ConcurrentDictionary<string, EtagEntry> EtagCache = new();
    private static readonly object TrafficLock = new();
    private static readonly Queue<(long At, long Out, long In)> TrafficWindow = new();
    private static readonly long TrafficStartedMs = Environment.TickCount64;

    /// <summary>Canal = chemin d'API sans requête, identifiants numériques remplacés (« /api/cas/{id} »).</summary>
    private static string TrafficChannelKey(Uri? uri)
    {
        if (uri is null) return "?";
        var parts = uri.AbsolutePath.Split('/', StringSplitOptions.RemoveEmptyEntries);
        for (var i = 0; i < parts.Length; i++)
        {
            if (parts[i].Length > 0 && parts[i].All(char.IsDigit))
                parts[i] = "{id}";
        }
        return "/" + string.Join('/', parts);
    }

    private static long EstimateRequestBytes(HttpRequestMessage req)
    {
        long n = req.Method.Method.Length + (req.RequestUri?.PathAndQuery.Length ?? 0) + 60;
        foreach (var h in req.Headers)
            foreach (var v in h.Value) n += h.Key.Length + v.Length + 4;
        if (req.Content != null)
        {
            foreach (var h in req.Content.Headers)
                foreach (var v in h.Value) n += h.Key.Length + v.Length + 4;
            n += req.Content.Headers.ContentLength ?? 0;
        }
        return n;
    }

    /// <summary>Avant l'envoi : accepte gzip, et pour un GET connu envoie l'empreinte de la dernière réponse.</summary>
    private static void PrepareTrackedRequest(HttpRequestMessage req)
    {
        try
        {
            if (!req.Headers.Contains("Accept-Encoding"))
                req.Headers.TryAddWithoutValidation("Accept-Encoding", "gzip");
            if (req.Method == HttpMethod.Get && req.RequestUri != null
                && EtagCache.TryGetValue(req.RequestUri.AbsoluteUri, out var e) && e.Etag.Length > 0)
                req.Headers.TryAddWithoutValidation("If-None-Match", e.Etag);
        }
        catch { /* en-têtes best-effort */ }
    }

    /// <summary>
    /// Après la réponse : lit le corps (compté tel que reçu), le décompresse, ressert la copie gardée sur un 304 et
    /// garde la nouvelle réponse si elle porte une empreinte. Le corps rendu à l'appelant est toujours en clair.
    /// </summary>
    private static void FinishTrackedResponse(HttpRequestMessage req, HttpResponseMessage resp, long outBytes, CancellationToken token)
    {
        var code = (int)resp.StatusCode;
        long inBytes = 120;
        foreach (var h in resp.Headers)
            foreach (var v in h.Value) inBytes += h.Key.Length + v.Length + 4;
        var notModified = false;
        long saved = 0;
        try
        {
            var wire = resp.Content.ReadAsByteArrayAsync(token).GetAwaiter().GetResult();
            inBytes += wire.Length;
            var gz = resp.Content.Headers.ContentEncoding.Any(static x => x.Equals("gzip", StringComparison.OrdinalIgnoreCase));
            var body = wire;
            if (gz && wire.Length > 0)
            {
                using var src = new MemoryStream(wire);
                using var unzip = new GZipStream(src, CompressionMode.Decompress);
                using var dst = new MemoryStream();
                unzip.CopyTo(dst);
                body = dst.ToArray();
            }
            var contentType = resp.Content.Headers.ContentType?.ToString() ?? "";
            var key = req.RequestUri?.AbsoluteUri ?? "";
            if (code == 304 && req.Method == HttpMethod.Get && EtagCache.TryGetValue(key, out var cached))
            {
                resp.StatusCode = HttpStatusCode.OK;
                body = cached.Body;
                contentType = cached.ContentType;
                cached.At = Environment.TickCount64;
                notModified = true;
                saved = cached.Body.Length;
            }
            else if (code == 200 && req.Method == HttpMethod.Get && key.Length > 0)
            {
                var etag = resp.Headers.TryGetValues("ETag", out var tags) ? tags.FirstOrDefault() ?? "" : "";
                if (etag.Length > 0 && body.Length <= EtagMaxBodyBytes)
                {
                    EtagCache[key] = new EtagEntry { Etag = etag, Body = body, ContentType = contentType, At = Environment.TickCount64 };
                    TrimEtagCache();
                }
                else
                {
                    EtagCache.TryRemove(key, out _);
                }
            }
            if (gz || notModified)
            {
                var old = resp.Content;
                var fresh = new ByteArrayContent(body);
                foreach (var h in old.Headers)
                {
                    if (h.Key.Equals("Content-Encoding", StringComparison.OrdinalIgnoreCase)
                        || h.Key.Equals("Content-Length", StringComparison.OrdinalIgnoreCase)
                        || h.Key.Equals("Content-Type", StringComparison.OrdinalIgnoreCase))
                        continue;
                    fresh.Headers.TryAddWithoutValidation(h.Key, h.Value);
                }
                if (contentType.Length > 0)
                    fresh.Headers.TryAddWithoutValidation("Content-Type", contentType);
                resp.Content = fresh;
                old.Dispose();
            }
        }
        catch (OperationCanceledException)
        {
            throw;
        }
        catch
        {
            // Corps illisible ou gzip invalide : l'appelant lira ce qui reste, comme avant.
        }
        NoteTraffic(TrafficChannelKey(req.RequestUri), outBytes, inBytes, code, notModified, saved);
    }

    private static void TrimEtagCache()
    {
        if (EtagCache.Count <= EtagMaxEntries) return;
        foreach (var k in EtagCache.OrderBy(static kv => kv.Value.At).Take(EtagCache.Count - EtagMaxEntries).Select(static kv => kv.Key).ToList())
            EtagCache.TryRemove(k, out _);
    }

    private static void NoteTraffic(string channel, long outBytes, long inBytes, int code, bool notModified = false, long saved = 0)
    {
        try
        {
            var c = TrafficChannels.GetOrAdd(channel, static _ => new TrafficChannel());
            var now = Environment.TickCount64;
            Interlocked.Increment(ref c.Calls);
            Interlocked.Add(ref c.OutBytes, outBytes);
            Interlocked.Add(ref c.InBytes, inBytes);
            if (notModified)
            {
                Interlocked.Increment(ref c.NotModified);
                Interlocked.Add(ref c.SavedBytes, saved);
            }
            c.LastCode = code;
            Interlocked.Exchange(ref c.LastAtMs, now);
            lock (TrafficLock)
            {
                TrafficWindow.Enqueue((now, outBytes, inBytes));
                while (TrafficWindow.Count > 0 && now - TrafficWindow.Peek().At > TrafficWindowSeconds * 1000L)
                    TrafficWindow.Dequeue();
                while (TrafficWindow.Count > 4000) TrafficWindow.Dequeue();
            }
        }
        catch { /* compteurs best-effort */ }
    }

    /// <summary>Requête envoyée hors SendTimed (file d'envoi, télémétrie) : comptée sans toucher à la réponse.</summary>
    private static void NoteTrafficUntracked(HttpRequestMessage req, HttpResponseMessage? resp)
    {
        try
        {
            long inBytes = 120 + (resp?.Content.Headers.ContentLength ?? 0);
            NoteTraffic(TrafficChannelKey(req.RequestUri), EstimateRequestBytes(req), inBytes, resp is null ? -1 : (int)resp.StatusCode);
        }
        catch { /* best-effort */ }
    }

    /// <summary>
    /// SyncStats : « OK| » puis une ligne d'en-tête (tabulations) :
    /// secondes depuis le lancement, octets envoyés, octets reçus, appels, réponses inchangées, octets évités,
    /// octets envoyés et reçus sur les 5 dernières minutes, durée réelle de cette fenêtre (s) ;
    /// puis une ligne par canal, les plus lourds d'abord (20 au plus) :
    /// chemin, appels, envoyés, reçus, inchangées, évités, âge du dernier appel (s), dernier code HTTP.
    /// </summary>
    private static string SyncStatsForSqf()
    {
        var now = Environment.TickCount64;
        long outT = 0, inT = 0, calls = 0, nm = 0, saved = 0;
        var rows = TrafficChannels.ToArray();
        foreach (var (_, c) in rows)
        {
            outT += Interlocked.Read(ref c.OutBytes);
            inT += Interlocked.Read(ref c.InBytes);
            calls += Interlocked.Read(ref c.Calls);
            nm += Interlocked.Read(ref c.NotModified);
            saved += Interlocked.Read(ref c.SavedBytes);
        }
        long wOut = 0, wIn = 0, wFirst = now;
        lock (TrafficLock)
        {
            foreach (var (at, o, i) in TrafficWindow)
            {
                if (now - at > TrafficWindowSeconds * 1000L) continue;
                wOut += o;
                wIn += i;
                if (at < wFirst) wFirst = at;
            }
        }
        var ic = CultureInfo.InvariantCulture;
        var span = Math.Max(1, Math.Min(TrafficWindowSeconds, (now - Math.Min(wFirst, now)) / 1000));
        var up = Math.Max(1, (now - TrafficStartedMs) / 1000);
        var sb = new StringBuilder("OK|");
        sb.Append(up.ToString(ic)).Append('\t').Append(outT.ToString(ic)).Append('\t').Append(inT.ToString(ic))
          .Append('\t').Append(calls.ToString(ic)).Append('\t').Append(nm.ToString(ic)).Append('\t').Append(saved.ToString(ic))
          .Append('\t').Append(wOut.ToString(ic)).Append('\t').Append(wIn.ToString(ic)).Append('\t').Append(span.ToString(ic));
        foreach (var (path, c) in rows.OrderByDescending(static kv => kv.Value.OutBytes + kv.Value.InBytes).Take(20))
        {
            var line = new StringBuilder("\n");
            line.Append(path.Replace('\t', ' ').Replace('\n', ' ')).Append('\t')
                .Append(Interlocked.Read(ref c.Calls).ToString(ic)).Append('\t')
                .Append(Interlocked.Read(ref c.OutBytes).ToString(ic)).Append('\t')
                .Append(Interlocked.Read(ref c.InBytes).ToString(ic)).Append('\t')
                .Append(Interlocked.Read(ref c.NotModified).ToString(ic)).Append('\t')
                .Append(Interlocked.Read(ref c.SavedBytes).ToString(ic)).Append('\t')
                .Append(Math.Max(0, (now - Interlocked.Read(ref c.LastAtMs)) / 1000).ToString(ic)).Append('\t')
                .Append(c.LastCode.ToString(ic));
            if (sb.Length + line.Length > MaxOutputBytes - 16) break;
            sb.Append(line);
        }
        return sb.ToString();
    }

    /// <summary>Corps JSON compressé (fiche opérateur : Athena décompresse les corps gzip, HttpJsonBody).</summary>
    private static HttpContent GzipJsonContent(string jsonBody)
    {
        var raw = Encoding.UTF8.GetBytes(jsonBody ?? "");
        if (raw.Length < 1024)
            return JsonContent(jsonBody ?? "");
        using var ms = new MemoryStream();
        using (var gz = new GZipStream(ms, CompressionLevel.Fastest, leaveOpen: true))
            gz.Write(raw, 0, raw.Length);
        var content = new ByteArrayContent(ms.ToArray());
        content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
        content.Headers.ContentEncoding.Add("gzip");
        return content;
    }
}
