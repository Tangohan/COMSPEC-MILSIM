using System;
using System.Collections.Generic;
using System.Globalization;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// ORBAT du site pour l'onglet ORBAT du BFT (fn_bftOrbat).
/// <list type="bullet">
/// <item>GetCommunityOrbat [] → GET /api/atak/community-orbat → « OK|nbMorceaux|révision|generated_at ». Toujours par Async
/// (« ERR|use_async » sinon), une requête à la fois (« ERR|busy » si la précédente n'est pas finie). Erreurs : not_linked,
/// no_tenant, unauthorized, forbidden, unavailable, too_large, invalid_response, http_….</item>
/// <item>GetCommunityOrbatChunk [i] → « OK|morceau i » (au plus 6000 octets), lu en mémoire ; morceaux concaténés → parseSimpleArray
/// (objet = paires ["clé", valeur], comme le pack d'identité). Le dernier ORBAT reçu reste en mémoire si une lecture échoue.</item>
/// </list>
/// Les champs ajoutés plus tard par le site passent tels quels : le téléphone ignore ce qu'il ne connaît pas.
/// </summary>
public static partial class Extension
{
    private const int CommunityOrbatChunkBytes = 6000;
    private const int CommunityOrbatMaxBytes = 512 * 1024;

    private static readonly object CommunityOrbatLock = new();
    private static string _communityOrbatText = "";
    private static List<int> _communityOrbatStarts = new();
    private static int _communityOrbatBusy;

    private static string CommunityOrbatChunk(string?[] args)
    {
        if (!int.TryParse((args.Length > 0 ? args[0] : "") ?? "", NumberStyles.Integer, CultureInfo.InvariantCulture, out var i) || i < 0)
            return "ERR|invalid_chunk";
        lock (CommunityOrbatLock)
        {
            if (_communityOrbatText.Length == 0) return "ERR|no_orbat";
            if (i >= _communityOrbatStarts.Count) return "ERR|chunk_oob";
            var start = _communityOrbatStarts[i];
            var end = i + 1 < _communityOrbatStarts.Count ? _communityOrbatStarts[i + 1] : _communityOrbatText.Length;
            return "OK|" + _communityOrbatText.Substring(start, end - start);
        }
    }

    private static string CommunityOrbatCommand(CancellationToken token)
    {
        if (!InAsyncWorker.Value) return "ERR|use_async";
        if (Interlocked.CompareExchange(ref _communityOrbatBusy, 1, 0) != 0) return "ERR|busy";
        try
        {
            using var resp = SendGet(_baseUrl + "/api/atak/community-orbat", token);
            var body = ReadContentUtf8(resp, token);
            if (!resp.IsSuccessStatusCode)
            {
                if (body.Contains("session_required", StringComparison.Ordinal)) return "ERR|not_linked";
                return IdentityHttpError((int)resp.StatusCode, body);
            }
            if (Encoding.UTF8.GetByteCount(body) > CommunityOrbatMaxBytes) return "ERR|too_large";
            using var doc = JsonDocument.Parse(body);
            var root = doc.RootElement;
            if (root.ValueKind != JsonValueKind.Object || !root.TryGetProperty("units", out var units) || units.ValueKind != JsonValueKind.Array)
                return "ERR|invalid_response";
            var sb = new StringBuilder(body.Length);
            AppendSqfValue(sb, root, 0);
            var text = sb.ToString();
            var starts = Utf8ChunkStarts(text, CommunityOrbatChunkBytes);
            lock (CommunityOrbatLock)
            {
                _communityOrbatText = text;
                _communityOrbatStarts = starts;
            }
            return "OK|" + starts.Count.ToString(CultureInfo.InvariantCulture)
                + "|" + SqfField(CommunityOrbatString(root, "revision"))
                + "|" + SqfField(CommunityOrbatString(root, "generated_at"));
        }
        catch (Exception ex) { return FormatCaughtError(ex); }
        finally { Interlocked.Exchange(ref _communityOrbatBusy, 0); }
    }

    private static string CommunityOrbatString(JsonElement root, string key) =>
        root.TryGetProperty(key, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";
}
