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
    private const int SyncTimeoutSeconds = 3;
    private static string _baseUrl = "";
    private static readonly HttpClient HttpClient = new() { Timeout = TimeSpan.FromSeconds(SyncTimeoutSeconds) };
    private static readonly ConcurrentQueue<(string Url, string Body)> PendingPosts = new();
    private static readonly int MaxQueueSize = 500;
    private static readonly object QueueDrainLock = new();
    private static System.Threading.Timer? _drainTimer;

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
        Output(output, outputSize, "COMSPECExtension 1.0");
    }

    private static void Output(nint output, int outputSize, string data)
    {
        var bytes = Encoding.UTF8.GetBytes(data);
        Marshal.Copy(bytes, 0, output, Math.Min(bytes.Length, outputSize));
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

        var syncResult = TryGetSyncResponse(functionString, argsString);
        if (syncResult != null)
        {
            var maxLen = Math.Min(syncResult.Length, Math.Min(outputSize - 1, MaxOutputBytes));
            if (maxLen < syncResult.Length)
                syncResult = syncResult.Substring(0, maxLen);
            Output(output, outputSize, syncResult);
            return 0;
        }

        RvExtensionArgsImpl(functionString, argsString);
        Output(output, outputSize, "");
        return 0;
    }

    private static string? TryGetSyncResponse(string? function, string?[] args)
    {
        if (string.IsNullOrEmpty(_baseUrl)) return "ERR|invalid";
        using var cts = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
        var token = cts.Token;
        try
        {
            if (function == "GetMarkers")
            {
                var since = args.Length > 0 ? (args[0] ?? "") : "";
                var url = _baseUrl + "/api/atak/markers?mapId=1";
                if (!string.IsNullOrEmpty(since)) url += "&since=" + Uri.EscapeDataString(since);
                var response = HttpClient.GetAsync(url, token).GetAwaiter().GetResult();
                response.EnsureSuccessStatusCode();
                var body = response.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + SimplifyMarkersJson(body);
            }
            if (function == "GetUnits")
            {
                var response = HttpClient.GetAsync(_baseUrl + "/api/units?mapId=1", token).GetAwaiter().GetResult();
                response.EnsureSuccessStatusCode();
                var body = response.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                return "OK|" + SimplifyUnitsJson(body);
            }
            if (function == "Connect" && args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
            {
                _baseUrl = args[0]!.TrimEnd('/');
                return "OK|connected";
            }
            if (function == "GetClientIp")
            {
                var response = HttpClient.GetAsync(_baseUrl + "/api/atak/whoami", token).GetAwaiter().GetResult();
                response.EnsureSuccessStatusCode();
                var body = response.Content.ReadAsStringAsync(token).GetAwaiter().GetResult();
                using var doc = JsonDocument.Parse(body);
                var ip = doc.RootElement.TryGetProperty("ip", out var p) ? (p.GetString() ?? "—") : "—";
                return "OK|" + ip;
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

    private static void RvExtensionArgsImpl(string? function, string?[] args)
    {
        try
        {
            if (function == "Connect")
            {
                if (args.Length >= 1 && !string.IsNullOrWhiteSpace(args[0]))
                {
                    _baseUrl = args[0]!.TrimEnd('/');
                    SendChatMessage("COMSPEC Overwatch: mod actif.");
                    SendChatMessage("Liaison établie avec le nœud: " + _baseUrl);
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
                var headingStr = heading.HasValue ? heading.Value.ToString("R", System.Globalization.CultureInfo.InvariantCulture) : "null";
                var extra = new System.Text.StringBuilder();
                extra.Append("\"role\":\"").Append(EscapeJson(role)).Append("\"");
                extra.Append(",\"health\":\"").Append(EscapeJson(health)).Append("\"");
                if (!string.IsNullOrEmpty(fuel)) extra.Append(",\"fuel\":\"").Append(EscapeJson(fuel)).Append("\"");
                extra.Append(",\"ammo\":\"").Append(EscapeJson(ammo)).Append("\"");
                if (!string.IsNullOrEmpty(radioFreq)) extra.Append(",\"radio_freq\":\"").Append(EscapeJson(radioFreq)).Append("\"");
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
                var payload = "{\"mapId\":1,\"layerId\":" + layerId + ",\"arma_name\":\"" + EscapeJson(armaName) + "\",\"markerData\":" + markerDataRaw + "}";
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
                var checked = (args[2] ?? "true").ToLowerInvariant() == "true";
                var checkedBy = args[3] ?? "Pilot";
                var payload = "{\"line\":\"" + EscapeJson(line) + "\",\"checked\":" + (checked ? "true" : "false") + ",\"checkedBy\":\"" + EscapeJson(checkedBy) + "\"}";
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
