using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// COMSPEC IDENTITY (Lot 1, 2.0.118) : registre d'identité Athena pour le contrôle d'identité du mod SSE.
/// <list type="bullet">
/// <item>GetIdentityPack [mapId, opKey, sinceIso] → GET /api/sse/identity/pack (toutes les pages) → « OK|nbChunks|generated_at » ;
/// le pack converti en texte lisible par parseSimpleArray reste en mémoire.</item>
/// <item>GetIdentityPackChunk [i] → « OK|morceau i » (au plus 6000 octets) ; morceaux concaténés → parseSimpleArray.</item>
/// <item>QueryIdDocument [country, type, number, last, first, dob] → GET /api/sse/identity/documents/lookup →
/// « OK|[found,verdict,ref,level,[flags],updated_at,[holder]] ».</item>
/// <item>SubmitIdCheck [json…] (JSON découpé en morceaux de 4000 caractères, recollés ici) → POST /api/sse/identity/checks →
/// « OK|id|ref ».</item>
/// </list>
/// Conversion JSON → texte SQF : objet = tableau de paires ["clé", valeur], tableau = tableau, chaîne entre guillemets
/// (guillemets doublés, retours à la ligne remplacés par une espace), nombre, true / false ; null devient "".
/// </summary>
public static partial class Extension
{
    private const int IdentityPackChunkBytes = 6000;
    private const int IdentityPackPageLimit = 200;
    private const int IdentityPackMaxPages = 5;

    private static readonly object IdentityPackLock = new();
    private static string _identityPackText = "";
    private static List<int> _identityPackStarts = new();

    private static string IdentityPackChunk(string?[] args)
    {
        if (!int.TryParse((args.Length > 0 ? args[0] : "") ?? "", NumberStyles.Integer, CultureInfo.InvariantCulture, out var i) || i < 0)
            return "ERR|invalid_chunk";
        lock (IdentityPackLock)
        {
            if (_identityPackText.Length == 0) return "ERR|no_pack";
            if (i >= _identityPackStarts.Count) return "ERR|chunk_oob";
            var start = _identityPackStarts[i];
            var end = i + 1 < _identityPackStarts.Count ? _identityPackStarts[i + 1] : _identityPackText.Length;
            return "OK|" + _identityPackText.Substring(start, end - start);
        }
    }

    private static string IdentityCommand(string function, string?[] args, CancellationToken token)
    {
        string Arg(int i) => args.Length > i ? (args[i] ?? "").Trim() : "";
        try
        {
            if (function == "GetIdentityPack")
            {
                var map = Arg(0);
                if (!int.TryParse(map, NumberStyles.Integer, CultureInfo.InvariantCulture, out var mapId) || mapId < 1)
                    mapId = CurrentMapId();
                var op = Arg(1);
                var since = Arg(2);
                var sections = new Dictionary<string, List<JsonElement>>(StringComparer.Ordinal)
                {
                    ["countries"] = new(), ["identities"] = new(), ["documents"] = new(), ["watch"] = new(), ["revoked"] = new(),
                };
                var head = new List<KeyValuePair<string, JsonElement>>();
                var generatedAt = "";
                var offset = 0;
                for (var page = 0; page < IdentityPackMaxPages; page++)
                {
                    var url = _baseUrl + "/api/sse/identity/pack?map=" + mapId.ToString(CultureInfo.InvariantCulture)
                        + "&offset=" + offset.ToString(CultureInfo.InvariantCulture)
                        + "&limit=" + IdentityPackPageLimit.ToString(CultureInfo.InvariantCulture);
                    if (op.Length > 0) url += "&op=" + Uri.EscapeDataString(op);
                    if (since.Length > 0) url += "&since=" + Uri.EscapeDataString(since);
                    using var resp = SendGet(url, token);
                    var body = ReadContentUtf8(resp, token);
                    if (!resp.IsSuccessStatusCode) return IdentityHttpError((int)resp.StatusCode, body);
                    using var doc = JsonDocument.Parse(body);
                    var root = doc.RootElement;
                    if (root.ValueKind != JsonValueKind.Object) return "ERR|invalid_response";
                    if (page == 0)
                    {
                        foreach (var p in root.EnumerateObject())
                        {
                            // « settings » (réglage de la communauté, fn_idAthenaPack) : objet converti en paires par AppendSqfValue.
                            if (p.Name is "version" or "generated_at" or "map" or "op" or "since" or "settings")
                                head.Add(new KeyValuePair<string, JsonElement>(p.Name, p.Value.Clone()));
                        }
                        if (root.TryGetProperty("generated_at", out var g) && g.ValueKind == JsonValueKind.String)
                            generatedAt = g.GetString() ?? "";
                    }
                    foreach (var (name, list) in sections)
                    {
                        if (root.TryGetProperty(name, out var arr) && arr.ValueKind == JsonValueKind.Array)
                        {
                            // Pays et listes : envoyés avec la première page seulement.
                            if (page > 0 && name is "countries" or "watch" or "revoked") continue;
                            foreach (var el in arr.EnumerateArray()) list.Add(el.Clone());
                        }
                    }
                    if (!root.TryGetProperty("paging", out var paging) || paging.ValueKind != JsonValueKind.Object
                        || !paging.TryGetProperty("next_offset", out var next) || next.ValueKind != JsonValueKind.Number
                        || !next.TryGetInt32(out var nextOffset) || nextOffset <= offset)
                        break;
                    offset = nextOffset;
                }

                var sb = new StringBuilder(4096);
                sb.Append('[');
                var first = true;
                foreach (var kv in head)
                {
                    if (!first) sb.Append(',');
                    first = false;
                    sb.Append("[\"").Append(kv.Key).Append("\",");
                    AppendSqfValue(sb, kv.Value, 0);
                    sb.Append(']');
                }
                foreach (var (name, list) in sections)
                {
                    if (!first) sb.Append(',');
                    first = false;
                    sb.Append("[\"").Append(name).Append("\",[");
                    for (var i = 0; i < list.Count; i++)
                    {
                        if (i > 0) sb.Append(',');
                        AppendSqfValue(sb, list[i], 0);
                    }
                    sb.Append("]]");
                }
                sb.Append(']');
                var text = sb.ToString();
                var starts = Utf8ChunkStarts(text, IdentityPackChunkBytes);
                lock (IdentityPackLock)
                {
                    _identityPackText = text;
                    _identityPackStarts = starts;
                }
                return "OK|" + starts.Count.ToString(CultureInfo.InvariantCulture) + "|" + SqfField(generatedAt);
            }

            if (function == "QueryIdDocument")
            {
                var url = _baseUrl + "/api/sse/identity/documents/lookup"
                    + "?country=" + Uri.EscapeDataString(Arg(0))
                    + "&type=" + Uri.EscapeDataString(Arg(1))
                    + "&number=" + Uri.EscapeDataString(Arg(2))
                    + "&last=" + Uri.EscapeDataString(Arg(3))
                    + "&first=" + Uri.EscapeDataString(Arg(4))
                    + "&dob=" + Uri.EscapeDataString(Arg(5));
                using var resp = SendGet(url, token);
                var body = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode) return IdentityHttpError((int)resp.StatusCode, body);
                using var doc = JsonDocument.Parse(body);
                var r = doc.RootElement;
                if (r.ValueKind != JsonValueKind.Object) return "ERR|invalid_response";
                var sb = new StringBuilder("OK|[");
                sb.Append(r.TryGetProperty("found", out var f) && f.ValueKind == JsonValueKind.True ? "true" : "false");
                foreach (var k in new[] { "verdict", "ref", "level" })
                {
                    sb.Append(',');
                    AppendSqfString(sb, r.TryGetProperty(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "");
                }
                sb.Append(",[");
                if (r.TryGetProperty("flags", out var flags) && flags.ValueKind == JsonValueKind.Array)
                {
                    var n = 0;
                    foreach (var fl in flags.EnumerateArray().Take(16))
                    {
                        if (fl.ValueKind != JsonValueKind.String) continue;
                        if (n++ > 0) sb.Append(',');
                        AppendSqfString(sb, fl.GetString() ?? "");
                    }
                }
                sb.Append("],");
                AppendSqfString(sb, r.TryGetProperty("updated_at", out var u) && u.ValueKind == JsonValueKind.String ? u.GetString() ?? "" : "");
                sb.Append(",[");
                if (r.TryGetProperty("holder", out var h) && h.ValueKind == JsonValueKind.Object)
                {
                    var hk = new[] { "last_name", "first_name", "level" };
                    for (var i = 0; i < hk.Length; i++)
                    {
                        if (i > 0) sb.Append(',');
                        AppendSqfString(sb, h.TryGetProperty(hk[i], out var hv) && hv.ValueKind == JsonValueKind.String ? hv.GetString() ?? "" : "");
                    }
                }
                sb.Append("]]");
                return Utf8ByteCount(sb) > MaxOutputBytes ? "ERR|too_large" : sb.ToString();
            }

            if (function == "SubmitIdCheck")
            {
                // Le SQF découpe le JSON en morceaux de 4000 caractères passés à la suite : on les recolle.
                var raw = string.Concat(args.Select(a => a ?? ""));
                if (string.IsNullOrWhiteSpace(raw)) return "ERR|payload_empty";
                var json = NormalizeArmaJson(raw);
                if (json == "{}") return "ERR|payload_empty";
                if (!TryBuildRequestUri(_baseUrl, "/api/sse/identity/checks", out var uri, out var uriErr) || uri is null)
                    return "ERR|" + uriErr;
                using var resp = SendJsonPost(uri.AbsoluteUri, json, token);
                var body = ReadContentUtf8(resp, token);
                if (!resp.IsSuccessStatusCode) return IdentityHttpError((int)resp.StatusCode, body);
                using var doc = JsonDocument.Parse(body);
                var r = doc.RootElement;
                if (r.ValueKind != JsonValueKind.Object || !r.TryGetProperty("id", out var idEl) || !idEl.TryGetInt64(out var id))
                    return "ERR|invalid_response";
                var reference = r.TryGetProperty("ref", out var refEl) && refEl.ValueKind == JsonValueKind.String ? refEl.GetString() ?? "" : "";
                return "OK|" + id.ToString(CultureInfo.InvariantCulture) + "|" + SqfField(reference);
            }
        }
        catch (Exception ex) { return FormatCaughtError(ex); }
        return "ERR|unknown_function";
    }

    /// <summary>Codes d'erreur alignés sur QuerySseIdentity (no_tenant, unauthorized, forbidden, unavailable, http_…).</summary>
    private static string IdentityHttpError(int code, string body)
    {
        if (code == 401) return "ERR|unauthorized";
        if (code == 403)
        {
            if (body.Contains("tenant_context_required", StringComparison.Ordinal)) return "ERR|no_tenant";
            if (body.Contains("clearance_required", StringComparison.Ordinal)) return "ERR|forbidden";
            return "ERR|unauthorized";
        }
        if (code == 404) return "ERR|not_found";
        if (code == 422)
        {
            try
            {
                using var doc = JsonDocument.Parse(body);
                if (doc.RootElement.ValueKind == JsonValueKind.Object && doc.RootElement.TryGetProperty("error", out var e)
                    && e.ValueKind == JsonValueKind.String && (e.GetString() ?? "").Length is > 0 and <= 40)
                    return "ERR|" + SqfField(e.GetString() ?? "");
            }
            catch (JsonException) { }
            return "ERR|invalid";
        }
        if (code == 429) return "ERR|rate_limited";
        if (code == 503) return "ERR|unavailable";
        return "ERR|http_" + code.ToString(CultureInfo.InvariantCulture);
    }

    private static int Utf8ByteCount(StringBuilder sb) => Encoding.UTF8.GetByteCount(sb.ToString());

    /// <summary>Champ d'une réponse « OK|a|b » : sans « | » ni retour à la ligne.</summary>
    private static string SqfField(string s) =>
        s.Replace("|", "-").Replace("\r", " ").Replace("\n", " ").Replace("\t", " ");

    private static void AppendSqfString(StringBuilder sb, string s)
    {
        sb.Append('"');
        foreach (var c in s)
        {
            if (c == '"') sb.Append("\"\"");
            else if (char.IsControl(c)) sb.Append(' ');
            else sb.Append(c);
        }
        sb.Append('"');
    }

    /// <summary>JSON → texte SQF sûr pour parseSimpleArray (profondeur bornée).</summary>
    internal static void AppendSqfValue(StringBuilder sb, JsonElement el, int depth)
    {
        if (depth > 12)
        {
            sb.Append("\"\"");
            return;
        }
        switch (el.ValueKind)
        {
            case JsonValueKind.Object:
                sb.Append('[');
                var firstProp = true;
                foreach (var p in el.EnumerateObject())
                {
                    if (!firstProp) sb.Append(',');
                    firstProp = false;
                    sb.Append('[');
                    AppendSqfString(sb, p.Name);
                    sb.Append(',');
                    AppendSqfValue(sb, p.Value, depth + 1);
                    sb.Append(']');
                }
                sb.Append(']');
                break;
            case JsonValueKind.Array:
                sb.Append('[');
                var firstItem = true;
                foreach (var item in el.EnumerateArray())
                {
                    if (!firstItem) sb.Append(',');
                    firstItem = false;
                    AppendSqfValue(sb, item, depth + 1);
                }
                sb.Append(']');
                break;
            case JsonValueKind.String:
                AppendSqfString(sb, el.GetString() ?? "");
                break;
            case JsonValueKind.Number:
                if (el.TryGetInt64(out var l)) sb.Append(l.ToString(CultureInfo.InvariantCulture));
                else if (el.TryGetDouble(out var d) && double.IsFinite(d)) sb.Append(d.ToString("0.0#########", CultureInfo.InvariantCulture));
                else sb.Append('0');
                break;
            case JsonValueKind.True:
                sb.Append("true");
                break;
            case JsonValueKind.False:
                sb.Append("false");
                break;
            default:
                sb.Append("\"\"");
                break;
        }
    }
}
