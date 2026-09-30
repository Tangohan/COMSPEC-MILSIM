using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// Phase A — bus télémétrie : priorités P0–P3, delta cache, coalescence, batch HTTP, métriques.
/// Les endpoints unitaires restent le secours si le lot est refusé / indisponible.
/// </summary>
public static partial class Extension
{
    private const int TelemetryMaxBatchEvents = 24;
    private const int TelemetryP3Max = 64;
    private const int TelemetryP0Max = 128;

    private static volatile bool _telemetryBatchEnabled = true;
    private static long _telemetrySeq;
    private static long _telemetryDropped;
    private static long _telemetryOldestEnqueueMs;
    private static long _telemetryLastFlushMs;
    private static long _telemetryLastLatencyMs;
    private static long _telemetryBatchOk;
    private static long _telemetryBatchFail;
    private static long _telemetryDeltaSkipped;
    private static int _telemetryFallbackStreak;

    private static readonly ConcurrentQueue<TelemetryItem> TelemetryP0 = new();
    private static readonly ConcurrentDictionary<string, TelemetryItem> TelemetryP1 =
        new(StringComparer.OrdinalIgnoreCase);
    private static readonly ConcurrentDictionary<string, TelemetryItem> TelemetryP2 =
        new(StringComparer.OrdinalIgnoreCase);
    private static readonly ConcurrentQueue<TelemetryItem> TelemetryP3 = new();
    private static readonly ConcurrentDictionary<string, string> TelemetryDeltaFingerprints =
        new(StringComparer.OrdinalIgnoreCase);
    private static readonly object TelemetryDrainLock = new();

    private sealed class TelemetryItem
    {
        public byte Priority;
        public string Type = "";
        public string CoalesceKey = "";
        public string EventJson = "";
        public string FallbackUrl = "";
        public string FallbackBody = "";
        public long EnqueuedAtMs;
    }

    private static string ApplyTelemetryMode(string? raw)
    {
        var mode = (raw ?? "").Trim().ToLowerInvariant();
        if (mode is "batch" or "on" or "1" or "true")
        {
            _telemetryBatchEnabled = true;
            return "OK|batch";
        }
        if (mode is "legacy" or "unit" or "unitary" or "off" or "0" or "false")
        {
            _telemetryBatchEnabled = false;
            return "OK|legacy";
        }
        return "OK|" + (_telemetryBatchEnabled ? "batch" : "legacy");
    }

    /// <summary>
    /// Entrée SQF Phase B : EmitTelemetry [priority, type, coalesceKey, eventJson]
    /// </summary>
    private static string ApplyEmitTelemetry(string?[] args)
    {
        if (args.Length < 4)
            return "ERR|args";
        if (!_telemetryBatchEnabled)
            return "ERR|legacy_mode";
        if (string.IsNullOrEmpty(_baseUrl))
            return "ERR|no_base";

        byte priority = 1;
        if (byte.TryParse((args[0] ?? "1").Trim(), out var p))
            priority = Math.Clamp(p, (byte)0, (byte)3);
        var type = (args[1] ?? "").Trim().ToLowerInvariant();
        if (type.Length == 0)
            return "ERR|type";
        var coalesceKey = (args[2] ?? "").Trim();
        if (coalesceKey.Length == 0)
            coalesceKey = type + ":" + Guid.NewGuid().ToString("N")[..8];
        var eventJson = (args[3] ?? "").Trim();
        if (eventJson.Length < 2 || eventJson[0] != '{')
            return "ERR|json";

        // Garantir "t" dans le JSON.
        if (!eventJson.Contains("\"t\"", StringComparison.Ordinal))
        {
            eventJson = "{\"t\":\"" + EscapeJson(type) + "\"," + eventJson.Substring(1);
        }

        if (priority >= 2 && !ShouldTransmitDelta(coalesceKey, eventJson))
        {
            Interlocked.Increment(ref _telemetryDeltaSkipped);
            return "OK|delta_skip";
        }

        OfferTelemetry(priority, type, coalesceKey, eventJson, "", "");
        return "OK|queued|" + priority.ToString(System.Globalization.CultureInfo.InvariantCulture);
    }

    private static string FormatTelemetryMetrics()
    {
        var depth = TelemetryQueueDepth();
        var oldest = 0;
        var oldestMs = Interlocked.Read(ref _telemetryOldestEnqueueMs);
        if (oldestMs > 0)
            oldest = Math.Max(0, (int)(Environment.TickCount64 - oldestMs));
        return string.Join("|",
            "OK",
            "mode=" + (_telemetryBatchEnabled ? "batch" : "legacy"),
            "depth=" + depth.ToString(System.Globalization.CultureInfo.InvariantCulture),
            "dropped=" + Interlocked.Read(ref _telemetryDropped).ToString(System.Globalization.CultureInfo.InvariantCulture),
            "oldest_ms=" + oldest.ToString(System.Globalization.CultureInfo.InvariantCulture),
            "latency_ms=" + Interlocked.Read(ref _telemetryLastLatencyMs).ToString(System.Globalization.CultureInfo.InvariantCulture),
            "ok=" + Interlocked.Read(ref _telemetryBatchOk).ToString(System.Globalization.CultureInfo.InvariantCulture),
            "fail=" + Interlocked.Read(ref _telemetryBatchFail).ToString(System.Globalization.CultureInfo.InvariantCulture),
            "delta_skip=" + Interlocked.Read(ref _telemetryDeltaSkipped).ToString(System.Globalization.CultureInfo.InvariantCulture),
            "seq=" + Interlocked.Read(ref _telemetrySeq).ToString(System.Globalization.CultureInfo.InvariantCulture));
    }

    private static int TelemetryQueueDepth()
    {
        return TelemetryP0.Count + TelemetryP1.Count + TelemetryP2.Count + TelemetryP3.Count
            + (_coalescedPosition is null ? 0 : 0); // position live is offered into P1
    }

    private static bool IsTelemetryBatchEndpoint(string url) =>
        url.Contains("/api/atak/telemetry/batch", StringComparison.OrdinalIgnoreCase);

    private static bool IsBatchableTelemetryUrl(string url)
    {
        if (string.IsNullOrWhiteSpace(url)) return false;
        if (IsPositionEndpoint(url)) return true;
        if (IsWeatherEndpoint(url)) return true;
        if (url.Contains("/api/atak/sigint", StringComparison.OrdinalIgnoreCase)
            && !url.Contains("/api/atak/sigint/", StringComparison.OrdinalIgnoreCase))
            return true;
        if (url.Contains("/api/atak/vehicles", StringComparison.OrdinalIgnoreCase)
            && !url.Contains("/service", StringComparison.OrdinalIgnoreCase))
            return true;
        if (url.Contains("/api/atak/flight-manifest", StringComparison.OrdinalIgnoreCase))
            return true;
        if (url.Contains("/api/logistics/update", StringComparison.OrdinalIgnoreCase))
            return true;
        return false;
    }

    private static byte PriorityForUrl(string url)
    {
        if (IsPositionEndpoint(url)) return 1;
        if (url.Contains("/api/atak/sigint", StringComparison.OrdinalIgnoreCase)) return 1;
        if (url.Contains("/api/atak/vehicles", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/atak/flight-manifest", StringComparison.OrdinalIgnoreCase)
            || url.Contains("/api/logistics/update", StringComparison.OrdinalIgnoreCase))
            return 2;
        if (IsWeatherEndpoint(url) || IsVideoFeedsEndpoint(url)) return 3;
        return 2;
    }

    private static string TypeForUrl(string url)
    {
        if (IsPositionEndpoint(url)) return "pos";
        if (IsWeatherEndpoint(url)) return "weather";
        if (url.Contains("/api/atak/sigint", StringComparison.OrdinalIgnoreCase)) return "sigint";
        if (url.Contains("/api/atak/flight-manifest", StringComparison.OrdinalIgnoreCase)) return "flight";
        if (url.Contains("/api/logistics/update", StringComparison.OrdinalIgnoreCase)) return "logstat";
        if (url.Contains("/api/atak/vehicles", StringComparison.OrdinalIgnoreCase)) return "veh";
        return "evt";
    }

    /// <summary>Entrée principale depuis EnqueueOrSend : convertit un POST unitaire en événement priorisé.</summary>
    private static bool TryOfferBatchablePost(string url, string jsonBody)
    {
        if (!_telemetryBatchEnabled || !IsBatchableTelemetryUrl(url))
            return false;

        var type = TypeForUrl(url);
        var priority = PriorityForUrl(url);
        if (type == "pos" && LooksCriticalPosition(jsonBody))
            priority = 0;

        var eventJson = BuildEventJsonFromUnitary(type, jsonBody);
        if (eventJson.Length == 0)
            return false;

        var coalesceKey = type switch
        {
            "pos" => "pos:" + ExtractCallSign(jsonBody),
            "weather" => "weather",
            "veh" => "veh:" + ExtractVehicleKey(jsonBody),
            "sigint" => "sigint:" + Guid.NewGuid().ToString("N")[..8],
            _ => type + ":" + Guid.NewGuid().ToString("N")[..8]
        };

        // Delta : ne pas renvoyer un état inchangé (heartbeat position ~25 s).
        if (!ShouldTransmitDelta(coalesceKey, eventJson))
        {
            if (priority == 0)
            {
                // Critique : toujours transmettre.
            }
            else if (type == "pos")
            {
                var now = Environment.TickCount64;
                var last = TelemetryDeltaFingerprints.TryGetValue(coalesceKey + ":ts", out var tsRaw)
                    && long.TryParse(tsRaw, out var ts)
                    ? ts
                    : 0L;
                if (last > 0 && (now - last) < 25_000)
                {
                    Interlocked.Increment(ref _telemetryDeltaSkipped);
                    return true;
                }
                TelemetryDeltaFingerprints[coalesceKey + ":ts"] =
                    now.ToString(System.Globalization.CultureInfo.InvariantCulture);
            }
            else if (priority >= 2)
            {
                Interlocked.Increment(ref _telemetryDeltaSkipped);
                return true;
            }
        }

        OfferTelemetry(priority, type, coalesceKey, eventJson, url, jsonBody);
        return true;
    }

    private static bool LooksCriticalPosition(string jsonBody)
    {
        try
        {
            using var doc = JsonDocument.Parse(jsonBody);
            if (!doc.RootElement.TryGetProperty("extra", out var extra) || extra.ValueKind != JsonValueKind.Object)
                return false;
            var health = "";
            if (extra.TryGetProperty("health", out var h) && h.ValueKind == JsonValueKind.String)
                health = (h.GetString() ?? "").Trim().ToLowerInvariant();
            if (health is "unconscious" or "incapacitated" or "critical" or "dead" or "kia" or "killed")
                return true;
            if (extra.TryGetProperty("link_state", out var ls) && ls.ValueKind == JsonValueKind.String)
            {
                var link = (ls.GetString() ?? "").Trim().ToLowerInvariant();
                if (link is "lost" or "down" or "disconnected")
                    return true;
            }
        }
        catch { /* ignore */ }
        return false;
    }

    private static bool ShouldTransmitDelta(string key, string eventJson)
    {
        var fp = Fingerprint(eventJson);
        if (TelemetryDeltaFingerprints.TryGetValue(key, out var prev) && prev == fp)
            return false;
        TelemetryDeltaFingerprints[key] = fp;
        TelemetryDeltaFingerprints[key + ":ts"] =
            Environment.TickCount64.ToString(System.Globalization.CultureInfo.InvariantCulture);
        return true;
    }

    private static string Fingerprint(string s)
    {
        unchecked
        {
            uint hash = 2166136261;
            foreach (var c in s)
            {
                hash ^= c;
                hash *= 16777619;
            }
            return hash.ToString("X8", System.Globalization.CultureInfo.InvariantCulture);
        }
    }

    private static void OfferTelemetry(
        byte priority,
        string type,
        string coalesceKey,
        string eventJson,
        string fallbackUrl,
        string fallbackBody)
    {
        var item = new TelemetryItem
        {
            Priority = priority,
            Type = type,
            CoalesceKey = coalesceKey,
            EventJson = eventJson,
            FallbackUrl = fallbackUrl,
            FallbackBody = fallbackBody,
            EnqueuedAtMs = Environment.TickCount64
        };
        NoteOldestEnqueue(item.EnqueuedAtMs);

        switch (priority)
        {
            case 0:
                if (TelemetryP0.Count >= TelemetryP0Max)
                {
                    TelemetryP0.TryDequeue(out _);
                    Interlocked.Increment(ref _telemetryDropped);
                }
                TelemetryP0.Enqueue(item);
                break;
            case 1:
                TelemetryP1[coalesceKey] = item;
                break;
            case 2:
                TelemetryP2[coalesceKey] = item;
                break;
            default:
                if (TelemetryP3.Count >= TelemetryP3Max)
                {
                    TelemetryP3.TryDequeue(out _);
                    Interlocked.Increment(ref _telemetryDropped);
                }
                TelemetryP3.Enqueue(item);
                break;
        }
        EnsureDrainTimer();
    }

    private static void NoteOldestEnqueue(long ms)
    {
        var cur = Interlocked.Read(ref _telemetryOldestEnqueueMs);
        if (cur == 0 || ms < cur)
            Interlocked.Exchange(ref _telemetryOldestEnqueueMs, ms);
    }

    private static string BuildEventJsonFromUnitary(string type, string jsonBody)
    {
        try
        {
            using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(jsonBody) ? "{}" : jsonBody);
            var root = doc.RootElement;
            using var stream = new System.IO.MemoryStream();
            using (var w = new Utf8JsonWriter(stream))
            {
                w.WriteStartObject();
                w.WriteString("t", type);
                if (type == "pos")
                {
                    WriteNum(w, "x", root, "pos_x", "x");
                    WriteNum(w, "y", root, "pos_y", "y");
                    WriteNum(w, "h", root, "heading", "h");
                    WriteNum(w, "z", root, "asl_z", "pos_z");
                    if (root.TryGetProperty("call_sign", out var cs) && cs.ValueKind == JsonValueKind.String)
                        w.WriteString("call_sign", cs.GetString() ?? "");
                    if (root.TryGetProperty("role", out var role) && role.ValueKind == JsonValueKind.String)
                        w.WriteString("role", role.GetString() ?? "");
                    if (root.TryGetProperty("steam_uid", out var steam) && steam.ValueKind == JsonValueKind.String)
                        w.WriteString("steam_uid", steam.GetString() ?? "");
                    if (root.TryGetProperty("session_token", out var sess) && sess.ValueKind == JsonValueKind.String)
                        w.WriteString("session_token", sess.GetString() ?? "");
                    if (root.TryGetProperty("mod_version", out var mv) && mv.ValueKind == JsonValueKind.String)
                        w.WriteString("mod_version", mv.GetString() ?? "");
                    if (root.TryGetProperty("mapId", out var mid))
                    {
                        w.WritePropertyName("mapId");
                        mid.WriteTo(w);
                    }
                    if (root.TryGetProperty("extra", out var extra))
                    {
                        w.WritePropertyName("extra");
                        extra.WriteTo(w);
                    }
                }
                else
                {
                    // Véhicule / météo / SIGINT : enveloppe data = corps unitaire.
                    w.WritePropertyName("data");
                    root.WriteTo(w);
                    if (root.TryGetProperty("call_sign", out var cs2) && cs2.ValueKind == JsonValueKind.String)
                        w.WriteString("call_sign", cs2.GetString() ?? "");
                    if (root.TryGetProperty("bearing", out var br))
                    {
                        w.WritePropertyName("bearing");
                        br.WriteTo(w);
                    }
                    WriteNum(w, "x", root, "pos_x", "x");
                    WriteNum(w, "y", root, "pos_y", "y");
                }
                w.WriteEndObject();
            }
            return Encoding.UTF8.GetString(stream.ToArray());
        }
        catch
        {
            return "";
        }
    }

    private static void WriteNum(Utf8JsonWriter w, string outName, JsonElement root, params string[] keys)
    {
        foreach (var k in keys)
        {
            if (!root.TryGetProperty(k, out var p)) continue;
            if (p.ValueKind == JsonValueKind.Number)
            {
                w.WritePropertyName(outName);
                p.WriteTo(w);
                return;
            }
            if (p.ValueKind == JsonValueKind.String
                && double.TryParse(p.GetString(), System.Globalization.NumberStyles.Float,
                    System.Globalization.CultureInfo.InvariantCulture, out var d))
            {
                w.WriteNumber(outName, d);
                return;
            }
        }
    }

    private static string ExtractCallSign(string jsonBody)
    {
        try
        {
            using var doc = JsonDocument.Parse(jsonBody);
            if (doc.RootElement.TryGetProperty("call_sign", out var p) && p.ValueKind == JsonValueKind.String)
                return (p.GetString() ?? "").Trim();
        }
        catch { /* ignore */ }
        return "unknown";
    }

    private static string ExtractVehicleKey(string jsonBody)
    {
        try
        {
            using var doc = JsonDocument.Parse(jsonBody);
            var root = doc.RootElement;
            foreach (var k in new[] { "vehicle_id", "id", "call_sign", "tail", "name" })
            {
                if (root.TryGetProperty(k, out var p) && p.ValueKind == JsonValueKind.String)
                {
                    var s = (p.GetString() ?? "").Trim();
                    if (s.Length > 0) return s;
                }
                if (root.TryGetProperty(k, out var n) && n.ValueKind == JsonValueKind.Number)
                    return n.ToString();
            }
        }
        catch { /* ignore */ }
        return "veh";
    }

    /// <summary>
    /// Drain prioritaire : construit un lot et POST /api/atak/telemetry/batch.
    /// Échec → secours unitaire via EnqueueForRetry.
    /// </summary>
    private static bool TryDrainTelemetryBatch()
    {
        if (!_telemetryBatchEnabled || string.IsNullOrEmpty(_baseUrl))
            return false;
        if (IsPostPausedNow())
            return false;

        List<TelemetryItem> items;
        lock (TelemetryDrainLock)
        {
            items = CollectTelemetryBatchItems();
            if (items.Count == 0)
                return false;
        }

        var seq = Interlocked.Increment(ref _telemetrySeq);
        var ts = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
        var sb = new StringBuilder(512 + items.Count * 180);
        sb.Append("{\"seq\":").Append(seq.ToString(System.Globalization.CultureInfo.InvariantCulture));
        sb.Append(",\"ts\":").Append(ts.ToString(System.Globalization.CultureInfo.InvariantCulture));
        sb.Append(",\"mapId\":").Append(CurrentMapId().ToString(System.Globalization.CultureInfo.InvariantCulture));
        sb.Append(",\"events\":[");
        for (var i = 0; i < items.Count; i++)
        {
            if (i > 0) sb.Append(',');
            sb.Append(items[i].EventJson);
        }
        sb.Append("]}");
        var payload = sb.ToString();
        var url = _baseUrl.TrimEnd('/') + "/api/atak/telemetry/batch";
        var started = Environment.TickCount64;

        try
        {
            using var req = new HttpRequestMessage(HttpMethod.Post, url)
            {
                Content = JsonContent(payload)
            };
            AttachApiKeyHeader(req);
            // Compression légère : Content-Encoding gzip si le corps est gros.
            if (payload.Length > 1200)
            {
                try
                {
                    var raw = Encoding.UTF8.GetBytes(payload);
                    using var ms = new System.IO.MemoryStream();
                    using (var gz = new System.IO.Compression.GZipStream(ms, System.IO.Compression.CompressionLevel.Fastest, true))
                        gz.Write(raw, 0, raw.Length);
                    var compressed = ms.ToArray();
                    if (compressed.Length + 32 < raw.Length)
                    {
                        req.Content = new ByteArrayContent(compressed);
                        req.Content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
                        req.Content.Headers.ContentEncoding.Add("gzip");
                    }
                }
                catch { /* garder JSON brut */ }
            }

            var response = HttpClient.SendAsync(req).GetAwaiter().GetResult();
            var code = (int)response.StatusCode;
            Interlocked.Exchange(ref _telemetryLastLatencyMs, Math.Max(0, Environment.TickCount64 - started));
            Interlocked.Exchange(ref _telemetryLastFlushMs, Environment.TickCount64);

            if (code == 429 || code == 503)
            {
                NoteRateLimited(response);
                RequeueTelemetryItems(items);
                Interlocked.Increment(ref _telemetryBatchFail);
                return true;
            }
            if (code == 403)
            {
                NoteAccessDenied(response, url);
                RequeueTelemetryItems(items);
                Interlocked.Increment(ref _telemetryBatchFail);
                return true;
            }
            if (code == 404 || code == 501 || code == 405)
            {
                // Athena sans batch : bascule legacy pour cette session + secours unitaire.
                _telemetryBatchEnabled = false;
                Interlocked.Increment(ref _telemetryFallbackStreak);
                FallbackTelemetryUnitary(items);
                Interlocked.Increment(ref _telemetryBatchFail);
                return true;
            }
            if (!response.IsSuccessStatusCode)
            {
                NotePostError(code, url);
                if (ShouldRetryStatusCode(code))
                    RequeueTelemetryItems(items);
                else
                    FallbackTelemetryUnitary(items);
                Interlocked.Increment(ref _telemetryBatchFail);
                return true;
            }

            NoteRateLimitCleared();
            Interlocked.Increment(ref _telemetryBatchOk);
            Interlocked.Exchange(ref _telemetryOldestEnqueueMs, 0);
            Interlocked.Exchange(ref _telemetryFallbackStreak, 0);
            return true;
        }
        catch
        {
            Interlocked.Exchange(ref _telemetryLastLatencyMs, Math.Max(0, Environment.TickCount64 - started));
            NoteTransientPostFailure(-1, url, payload, response: null);
            RequeueTelemetryItems(items);
            Interlocked.Increment(ref _telemetryBatchFail);
            return true;
        }
    }

    private static List<TelemetryItem> CollectTelemetryBatchItems()
    {
        var items = new List<TelemetryItem>(TelemetryMaxBatchEvents);
        while (items.Count < TelemetryMaxBatchEvents && TelemetryP0.TryDequeue(out var p0))
            items.Add(p0);

        if (items.Count < TelemetryMaxBatchEvents)
        {
            foreach (var key in TelemetryP1.Keys)
            {
                if (items.Count >= TelemetryMaxBatchEvents) break;
                if (TelemetryP1.TryRemove(key, out var p1))
                    items.Add(p1);
            }
        }
        if (items.Count < TelemetryMaxBatchEvents)
        {
            foreach (var key in TelemetryP2.Keys)
            {
                if (items.Count >= TelemetryMaxBatchEvents) break;
                if (TelemetryP2.TryRemove(key, out var p2))
                    items.Add(p2);
            }
        }
        while (items.Count < TelemetryMaxBatchEvents && TelemetryP3.TryDequeue(out var p3))
            items.Add(p3);

        // Abandon des événements trop vieux (> 120 s) hors P0.
        var now = Environment.TickCount64;
        items.RemoveAll(it =>
        {
            if (it.Priority == 0) return false;
            if (now - it.EnqueuedAtMs <= 120_000) return false;
            Interlocked.Increment(ref _telemetryDropped);
            return true;
        });
        return items;
    }

    private static void RequeueTelemetryItems(List<TelemetryItem> items)
    {
        foreach (var it in items)
            OfferTelemetry(it.Priority, it.Type, it.CoalesceKey, it.EventJson, it.FallbackUrl, it.FallbackBody);
    }

    private static void FallbackTelemetryUnitary(List<TelemetryItem> items)
    {
        foreach (var it in items)
        {
            if (string.IsNullOrEmpty(it.FallbackUrl) || string.IsNullOrEmpty(it.FallbackBody))
                continue;
            EnqueueForRetry(it.FallbackUrl, it.FallbackBody);
        }
    }

    /// <summary>Jitter ±10 % sur la période de drain (évite les rafales synchrones multi-clients).</summary>
    private static int JitteredDrainPeriodMs()
    {
        var baseMs = Math.Clamp(_drainPeriodMs, 250, 2000);
        var delta = (int)Math.Round(baseMs * 0.10);
        if (delta < 1) return baseMs;
        var offset = Random.Shared.Next(-delta, delta + 1);
        return Math.Clamp(baseMs + offset, 250, 2200);
    }
}
