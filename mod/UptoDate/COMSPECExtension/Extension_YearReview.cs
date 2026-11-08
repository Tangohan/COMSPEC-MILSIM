using System;
using System.Collections.Generic;
using System.Globalization;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

/// <summary>
/// Bilan de l'année pour l'app « Bilan de l'année » du téléphone (fn_pageYearReview).
/// <list type="bullet">
/// <item>GetYearReview [] → GET /api/atak/year-review → « OK|nbMorceaux|année ». Toujours par Async (« ERR|use_async »
/// sinon), une requête à la fois (« ERR|busy »). Erreurs comme GetCommunityOrbat : not_linked, no_tenant, unauthorized,
/// forbidden, unavailable, too_large, invalid_response, http_….</item>
/// <item>GetYearReviewChunk [i] → « OK|morceau i » (au plus 6000 octets), lu en mémoire ; morceaux concaténés →
/// parseSimpleArray (objet = paires ["clé", valeur]).</item>
/// </list>
/// Lecture seule. Ne part qu'après autorisation (session Athena établie) : même circuit que GetCommunityOrbat, jamais
/// de requête avant que la licence soit acceptée.
/// </summary>
public static partial class Extension
{
    private const int YearReviewChunkBytes = 6000;
    private const int YearReviewMaxBytes = 128 * 1024;

    private static readonly object YearReviewLock = new();
    private static string _yearReviewText = "";
    private static List<int> _yearReviewStarts = new();
    private static int _yearReviewBusy;

    private static string YearReviewChunk(string?[] args)
    {
        if (!int.TryParse((args.Length > 0 ? args[0] : "") ?? "", NumberStyles.Integer, CultureInfo.InvariantCulture, out var i) || i < 0)
            return "ERR|invalid_chunk";
        lock (YearReviewLock)
        {
            if (_yearReviewText.Length == 0) return "ERR|no_review";
            if (i >= _yearReviewStarts.Count) return "ERR|chunk_oob";
            var start = _yearReviewStarts[i];
            var end = i + 1 < _yearReviewStarts.Count ? _yearReviewStarts[i + 1] : _yearReviewText.Length;
            return "OK|" + _yearReviewText.Substring(start, end - start);
        }
    }

    private static string YearReviewCommand(CancellationToken token)
    {
        if (!InAsyncWorker.Value) return "ERR|use_async";
        if (Interlocked.CompareExchange(ref _yearReviewBusy, 1, 0) != 0) return "ERR|busy";
        try
        {
            using var resp = SendGet(_baseUrl + "/api/atak/year-review", token);
            var body = ReadContentUtf8(resp, token);
            if (!resp.IsSuccessStatusCode)
            {
                if (body.Contains("session_required", StringComparison.Ordinal)) return "ERR|not_linked";
                return IdentityHttpError((int)resp.StatusCode, body);
            }
            if (Encoding.UTF8.GetByteCount(body) > YearReviewMaxBytes) return "ERR|too_large";
            using var doc = JsonDocument.Parse(body);
            var root = doc.RootElement;
            if (root.ValueKind != JsonValueKind.Object || !root.TryGetProperty("slides", out var slides) || slides.ValueKind != JsonValueKind.Array)
                return "ERR|invalid_response";
            var sb = new StringBuilder(body.Length);
            AppendSqfValue(sb, root, 0);
            var text = sb.ToString();
            var starts = Utf8ChunkStarts(text, YearReviewChunkBytes);
            lock (YearReviewLock)
            {
                _yearReviewText = text;
                _yearReviewStarts = starts;
            }
            var year = root.TryGetProperty("year", out var y) && y.ValueKind == JsonValueKind.Number ? y.GetInt32() : 0;
            return "OK|" + starts.Count.ToString(CultureInfo.InvariantCulture) + "|" + year.ToString(CultureInfo.InvariantCulture);
        }
        catch (Exception ex) { return FormatCaughtError(ex); }
        finally { Interlocked.Exchange(ref _yearReviewBusy, 0); }
    }
}
